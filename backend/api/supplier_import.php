<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$requiredHeaders = [
    'cnpj_industria'
];

$templateHeaders = [
    'CNPJ Industria',
    'Razao Social',
    'Nome Fantasia',
    'Codigo Interno',
    'Nome do representante',
    'Telefone do representante',
    'E-mail do representante',
    'WhatsApp do representante',
    'Segmento',
    'Faturamento'
];

try {
    ensureUserConfigColumns($pdo);
    ensureExtraSectionTables($pdo);

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'template') {
        downloadTemplate($templateHeaders);
        exit;
    }

    if (($_SESSION['role'] ?? '') !== 'user' || empty($_SESSION['user']['id'])) {
        respond(401, false, 'Faca login com seu PIN para importar fornecedores.');
    }

    $userId = (int) $_SESSION['user']['id'];

    if (!userCanAccessSection($pdo, $userId, 'industrias', $onboardingSections)) {
        respond(403, false, 'A aba Industrias nao esta liberada para este cliente.');
    }

    if ($action === 'batches') {
        echo json_encode([
            'success' => true,
            'batches' => listBatches($pdo, $userId)
        ]);
        exit;
    }

    if ($action !== 'upload') {
        respond(400, false, 'Acao invalida.');
    }

    if (empty($_FILES['supplier_file']) || $_FILES['supplier_file']['error'] !== UPLOAD_ERR_OK) {
        respond(422, false, 'Envie uma planilha valida.');
    }

    $file = $_FILES['supplier_file'];
    $rows = readSpreadsheetRows($file['tmp_name'], $file['name'], $requiredHeaders);

    if (count($rows) === 0) {
        respond(422, false, 'A planilha nao possui linhas preenchidas.');
    }

    $pdo->beginTransaction();

    try {
        $batchStmt = $pdo->prepare(
            'INSERT INTO fornecedores_import_lotes (usuario_id, nome_arquivo, total_linhas, status, mensagem, mime_type, tamanho_bytes)
             VALUES (:usuario_id, :nome_arquivo, :total_linhas, :status, :mensagem, :mime_type, :tamanho_bytes)'
        );
        $batchStmt->execute([
            ':usuario_id' => $userId,
            ':nome_arquivo' => basename($file['name']),
            ':total_linhas' => count($rows),
            ':status' => 'em_analise',
            ':mensagem' => 'Planilha recebida. Aguardando verificacao do time.',
            ':mime_type' => $file['type'] ?: 'application/octet-stream',
            ':tamanho_bytes' => (int) ($file['size'] ?? 0)
        ]);

        $batchId = (int) $pdo->lastInsertId();
        $storedPath = storeUploadedSpreadsheet($file, $userId, $batchId);
        $fileStmt = $pdo->prepare('UPDATE fornecedores_import_lotes SET arquivo_path = :arquivo_path WHERE id = :id');
        $fileStmt->execute([
            ':arquivo_path' => $storedPath,
            ':id' => $batchId
        ]);

        $insertStmt = $pdo->prepare(
            'INSERT INTO industrias
             (usuario_id, cnpj_industria, razao_social, nome_fantasia, codigo_interno, nome_representante, telefone_representante, email_representante, whatsapp_representante, segmento, faturamento)
             VALUES
             (:usuario_id, :cnpj_industria, :razao_social, :nome_fantasia, :codigo_interno, :nome_representante, :telefone_representante, :email_representante, :whatsapp_representante, :segmento, :faturamento)'
        );

        foreach ($rows as $row) {
            $insertStmt->execute([
                ':usuario_id' => $userId,
                ':cnpj_industria' => nullIfEmpty(preg_replace('/\D/', '', $row['cnpj_industria'] ?? '')),
                ':razao_social' => nullIfEmpty($row['razao_social'] ?? ''),
                ':nome_fantasia' => nullIfEmpty($row['nome_fantasia'] ?? ''),
                ':codigo_interno' => nullIfEmpty($row['codigo_interno'] ?? ''),
                ':nome_representante' => nullIfEmpty($row['nome_representante'] ?? ''),
                ':telefone_representante' => nullIfEmpty($row['telefone_representante'] ?? ''),
                ':email_representante' => nullIfEmpty($row['email_representante'] ?? ''),
                ':whatsapp_representante' => nullIfEmpty($row['whatsapp_representante'] ?? ''),
                ':segmento' => nullIfEmpty($row['segmento'] ?? ''),
                ':faturamento' => decimalOrNull($row['faturamento'] ?? '')
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Planilha recebida e enviada para analise.',
        'batch' => [
            'id' => $batchId,
            'status' => 'em_analise',
            'total_linhas' => count($rows)
        ]
    ]);
} catch (Throwable $error) {
    respond(500, false, 'Erro ao importar: ' . $error->getMessage());
}

function downloadTemplate(array $headers): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_industrias.csv"');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    fputcsv($out, ['12345678000190', 'Industria Exemplo Ltda', 'Industria Exemplo', 'IND-001', 'Maria Silva', '(11) 99999-9999', 'maria@industria.com.br', '(11) 98888-8888', 'Alimentos', '150000.00'], ';');
    fclose($out);
}

function readSpreadsheetRows(string $path, string $name, array $requiredHeaders): array
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($extension === 'csv') {
        return readCsvRows($path, $requiredHeaders);
    }

    if ($extension === 'xlsx') {
        return readXlsxRows($path, $requiredHeaders);
    }

    throw new RuntimeException('Use uma planilha CSV ou XLSX.');
}

function readCsvRows(string $path, array $requiredHeaders): array
{
    $handle = fopen($path, 'r');

    if (!$handle) {
        throw new RuntimeException('Nao foi possivel ler o arquivo CSV.');
    }

    $firstLine = fgets($handle);
    $delimiter = substr_count((string) $firstLine, ';') >= substr_count((string) $firstLine, ',') ? ';' : ',';
    rewind($handle);

    $header = fgetcsv($handle, 0, $delimiter);
    $header = normalizeHeaderRow($header ?: []);
    validateHeaders($header, $requiredHeaders);

    $rows = [];

    while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row = mapRow($header, $line);

        if (!rowIsEmpty($row)) {
            $rows[] = $row;
        }
    }

    fclose($handle);
    return $rows;
}

function readXlsxRows(string $path, array $requiredHeaders): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('O PHP precisa da extensao ZipArchive para ler XLSX. Use CSV como alternativa.');
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Nao foi possivel abrir o XLSX.');
    }

    $sharedStrings = readSharedStrings($zip);
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('A primeira aba da planilha nao foi encontrada.');
    }

    $xml = simplexml_load_string($sheetXml);

    if (!$xml) {
        throw new RuntimeException('Nao foi possivel ler a primeira aba da planilha.');
    }

    $table = [];

    foreach ($xml->sheetData->row as $rowNode) {
        $row = [];

        foreach ($rowNode->c as $cell) {
            $cellRef = (string) $cell['r'];
            $columnIndex = columnIndexFromRef($cellRef);
            $value = (string) $cell->v;

            if ((string) $cell['t'] === 's') {
                $value = $sharedStrings[(int) $value] ?? '';
            }

            $row[$columnIndex] = trim($value);
        }

        if (count($row) > 0) {
            ksort($row);
            $table[] = $row;
        }
    }

    if (count($table) === 0) {
        return [];
    }

    $header = normalizeHeaderRow(array_values($table[0]));
    validateHeaders($header, $requiredHeaders);
    $rows = [];

    foreach (array_slice($table, 1) as $line) {
        $row = mapRow($header, array_values($line));

        if (!rowIsEmpty($row)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function readSharedStrings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');

    if ($xml === false) {
        return [];
    }

    $strings = [];
    $data = simplexml_load_string($xml);

    foreach ($data->si ?? [] as $item) {
        $strings[] = trim((string) $item->t);
    }

    return $strings;
}

function normalizeHeaderRow(array $header): array
{
    return array_map(function ($value) {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return canonicalHeaderName(trim($value, '_'));
    }, $header);
}

function canonicalHeaderName(string $header): string
{
    $aliases = [
        'nome_do_representante' => 'nome_representante',
        'telefone_do_representante' => 'telefone_representante',
        'e_mail_do_representante' => 'email_representante',
        'email_do_representante' => 'email_representante',
        'whatsapp_do_representante' => 'whatsapp_representante',
    ];

    return $aliases[$header] ?? $header;
}

function validateHeaders(array $header, array $requiredHeaders): void
{
    $missing = array_diff($requiredHeaders, $header);

    if (count($missing) > 0) {
        throw new RuntimeException('Colunas obrigatorias ausentes: ' . implode(', ', $missing) . '. Baixe o template ou mantenha pelo menos a coluna CNPJ Industria.');
    }
}

function mapRow(array $header, array $line): array
{
    $row = [];

    foreach ($header as $index => $name) {
        $row[$name] = trim((string) ($line[$index] ?? ''));
    }

    return $row;
}

function rowIsEmpty(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }

    return true;
}

function columnIndexFromRef(string $ref): int
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
    $index = 0;

    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return max(0, $index - 1);
}

function listBatches(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nome_arquivo, total_linhas, status, mensagem, created_at
         FROM fornecedores_import_lotes
         WHERE usuario_id = :usuario_id
         ORDER BY id DESC'
    );
    $stmt->execute([':usuario_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function nullIfEmpty(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function decimalOrNull(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $value = str_replace(['R$', ' '], '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? $value : null;
}

function ensureUserConfigColumns(PDO $pdo): void
{
    ensureColumn($pdo, 'usuarios_acesso', 'diagnostic_photos_link', 'VARCHAR(500) NULL');
    ensureColumn($pdo, 'usuarios_acesso', 'nivel', "VARCHAR(40) NOT NULL DEFAULT 'cliente'");
    ensureColumn($pdo, 'usuarios_acesso', 'allowed_sections', 'TEXT NULL');
}

function ensureExtraSectionTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS fornecedores_import_lotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            nome_arquivo VARCHAR(255) NOT NULL,
            arquivo_path VARCHAR(500),
            mime_type VARCHAR(120),
            tamanho_bytes INT,
            total_linhas INT NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'em_analise',
            mensagem TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_fornecedores_import_lotes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE CASCADE
        )"
    );

    ensureColumn($pdo, 'fornecedores_import_lotes', 'arquivo_path', 'VARCHAR(500) NULL');
    ensureColumn($pdo, 'fornecedores_import_lotes', 'mime_type', 'VARCHAR(120) NULL');
    ensureColumn($pdo, 'fornecedores_import_lotes', 'tamanho_bytes', 'INT NULL');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS fornecedores_importacao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            lote_id INT,
            codigo_fornecedor VARCHAR(120),
            razao_social VARCHAR(255),
            nome_fantasia VARCHAR(255),
            cnpj VARCHAR(20),
            email VARCHAR(255),
            telefone VARCHAR(40),
            categoria VARCHAR(180),
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_fornecedores_importacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL,
            CONSTRAINT fk_fornecedores_importacao_lote FOREIGN KEY (lote_id) REFERENCES fornecedores_import_lotes(id) ON DELETE CASCADE
        )'
    );
}

function storeUploadedSpreadsheet(array $file, int $userId, int $batchId): string
{
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extension = in_array($extension, ['csv', 'xlsx'], true) ? $extension : 'dat';
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'supplier_imports';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de uploads.');
    }

    $filename = sprintf('usuario_%d_lote_%d.%s', $userId, $batchId, $extension);
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Nao foi possivel salvar a planilha enviada.');
    }

    return 'backend/uploads/supplier_imports/' . $filename;
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column
    ]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function userCanAccessSection(PDO $pdo, int $userId, string $section, array $sections): bool
{
    $stmt = $pdo->prepare('SELECT allowed_sections FROM usuarios_acesso WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $allowedSections = parseAllowedSections($stmt->fetchColumn() ?: '', $sections);

    return in_array($section, $allowedSections, true);
}

function parseAllowedSections(?string $value, array $sections): array
{
    $keys = array_keys($sections);

    if (trim((string) $value) === '') {
        return $keys;
    }

    $selected = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    $selected = array_values(array_intersect($selected, $keys));

    return count($selected) > 0 ? $selected : $keys;
}

function respond(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}
