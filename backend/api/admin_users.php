<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$sections = $onboardingSections;

try {
    requireAdmin();
    ensureSectionStatusTable($pdo);
    ensureUserConfigColumns($pdo);
    ensureExtraSectionTables($pdo);

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'create') {
        $name = trim($_POST['nome'] ?? '');
        $driveLink = trim($_POST['drive_link'] ?? '');
        $diagnosticPhotosLink = trim($_POST['diagnostic_photos_link'] ?? '');
        $level = 'cliente';
        $clientProfile = normalizeClientProfile($_POST['perfil_cliente'] ?? 'SMB');
        $allowedSections = normalizeAllowedSections($_POST['allowed_sections'] ?? [], $sections);

        if ($name === '') {
            respond(422, false, 'Informe o nome do usuario.');
        }

        $pin = generateUniquePin($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios_acesso (nome, pin, drive_link, diagnostic_photos_link, nivel, perfil_cliente, allowed_sections)
             VALUES (:nome, :pin, :drive_link, :diagnostic_photos_link, :nivel, :perfil_cliente, :allowed_sections)'
        );
        $stmt->execute([
            ':nome' => $name,
            ':pin' => $pin,
            ':drive_link' => $driveLink === '' ? null : $driveLink,
            ':diagnostic_photos_link' => $diagnosticPhotosLink === '' ? null : $diagnosticPhotosLink,
            ':nivel' => $level,
            ':perfil_cliente' => $clientProfile,
            ':allowed_sections' => implode(',', $allowedSections)
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Usuario criado com sucesso.',
            'user' => [
                'id' => (int) $pdo->lastInsertId(),
                'nome' => $name,
                'pin' => $pin,
                'drive_link' => $driveLink,
                'diagnostic_photos_link' => $diagnosticPhotosLink,
                'nivel' => $level,
                'perfil_cliente' => $clientProfile,
                'allowed_sections' => $allowedSections
            ]
        ]);
        exit;
    }

    if ($action === 'list') {
        echo json_encode([
            'success' => true,
            'sections' => array_map(fn($section) => $section['label'], $sections),
            'users' => listUsers($pdo, $sections)
        ]);
        exit;
    }

    if ($action === 'update_drive_link') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $driveLink = trim($_POST['drive_link'] ?? '');
        $diagnosticPhotosLink = trim($_POST['diagnostic_photos_link'] ?? '');

        if ($userId <= 0) {
            respond(422, false, 'Usuario invalido.');
        }

        $user = findUser($pdo, $userId);

        if (!$user) {
            respond(404, false, 'Usuario nao encontrado.');
        }

        $stmt = $pdo->prepare('UPDATE usuarios_acesso SET drive_link = :drive_link, diagnostic_photos_link = :diagnostic_photos_link WHERE id = :id');
        $stmt->execute([
            ':drive_link' => $driveLink === '' ? null : $driveLink,
            ':diagnostic_photos_link' => $diagnosticPhotosLink === '' ? null : $diagnosticPhotosLink,
            ':id' => $userId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Links das pastas atualizados.',
            'drive_link' => $driveLink,
            'diagnostic_photos_link' => $diagnosticPhotosLink
        ]);
        exit;
    }

    if ($action === 'update_settings') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['nome'] ?? '');
        $level = normalizeLevel($_POST['nivel'] ?? 'cliente');
        $clientProfile = normalizeClientProfile($_POST['perfil_cliente'] ?? 'SMB');
        $allowedSections = normalizeAllowedSections($_POST['allowed_sections'] ?? [], $sections);

        if ($userId <= 0) {
            respond(422, false, 'Usuario invalido.');
        }

        if ($name === '') {
            respond(422, false, 'Informe o nome do cliente.');
        }

        if (!findUser($pdo, $userId)) {
            respond(404, false, 'Usuario nao encontrado.');
        }

        $stmt = $pdo->prepare(
            'UPDATE usuarios_acesso SET nome = :nome, nivel = :nivel, perfil_cliente = :perfil_cliente, allowed_sections = :allowed_sections WHERE id = :id'
        );
        $stmt->execute([
            ':nome' => $name,
            ':nivel' => $level,
            ':perfil_cliente' => $clientProfile,
            ':allowed_sections' => implode(',', $allowedSections),
            ':id' => $userId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Cliente atualizado.',
            'nome' => $name,
            'nivel' => $level,
            'perfil_cliente' => $clientProfile,
            'allowed_sections' => $allowedSections
        ]);
        exit;
    }

    if ($action === 'records') {
        $userId = (int) ($_GET['user_id'] ?? 0);

        if ($userId <= 0) {
            respond(422, false, 'Usuario invalido.');
        }

        $user = findUser($pdo, $userId);

        if (!$user) {
            respond(404, false, 'Usuario nao encontrado.');
        }

        echo json_encode([
            'success' => true,
            'user' => $user,
            'sections' => listRecords($pdo, getAllowedSections($sections, $user), $userId),
            'supplier_batches' => listSupplierBatches($pdo, $userId)
        ]);
        exit;
    }

    if ($action === 'download_supplier_batch') {
        $batchId = (int) ($_GET['batch_id'] ?? 0);

        if ($batchId <= 0) {
            respond(422, false, 'Importacao invalida.');
        }

        downloadSupplierBatch($pdo, $batchId);
        exit;
    }

    if ($action === 'review_supplier_batch') {
        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($batchId <= 0) {
            respond(422, false, 'Importacao invalida.');
        }

        if (!in_array($status, ['aprovado', 'reprovado'], true)) {
            respond(422, false, 'Status invalido.');
        }

        if ($status === 'reprovado' && $message === '') {
            respond(422, false, 'Informe o motivo da reprovacao.');
        }

        $stmt = $pdo->prepare(
            'UPDATE fornecedores_import_lotes
             SET status = :status, mensagem = :mensagem
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':mensagem' => $message === '' ? ($status === 'aprovado' ? 'Importacao aprovada pelo admin.' : null) : $message,
            ':id' => $batchId
        ]);

        echo json_encode([
            'success' => true,
            'message' => $status === 'aprovado' ? 'Importacao aprovada.' : 'Importacao reprovada.'
        ]);
        exit;
    }

    respond(400, false, 'Acao invalida.');
} catch (Throwable $error) {
    respond(500, false, 'Erro: ' . $error->getMessage());
}

function requireAdmin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        respond(401, false, 'Acesso restrito ao admin.');
    }
}

function listUsers(PDO $pdo, array $sections): array
{
    $users = $pdo
        ->query("SELECT id, nome, pin, drive_link, diagnostic_photos_link, nivel, perfil_cliente, allowed_sections, created_at FROM usuarios_acesso WHERE nivel <> 'tradebook' ORDER BY created_at DESC, id DESC")
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $user['id'] = (int) $user['id'];
        $user['allowed_sections'] = parseAllowedSections($user['allowed_sections'] ?? '', $sections);
        $user['progress'] = [];
        $completed = 0;
        $allowedSections = getAllowedSections($sections, $user);

        foreach ($allowedSections as $table => $section) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE usuario_id = :usuario_id");
            $stmt->execute([':usuario_id' => $user['id']]);
            $count = (int) $stmt->fetchColumn();

            $finalizedAt = findFinalizedAt($pdo, $user['id'], $table);
            $status = $finalizedAt !== null ? 'completed' : ($count > 0 ? 'sent' : 'missing');

            if ($status === 'completed') {
                $completed++;
            }

            $user['progress'][] = [
                'section' => $table,
                'label' => $section['label'],
                'count' => $count,
                'sent' => $count > 0,
                'status' => $status,
                'finalized_at' => $finalizedAt
            ];
        }

        $user['completed'] = $completed;
        $user['total'] = count($allowedSections);
    }

    return $users;
}

function findUser(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT id, nome, pin, drive_link, diagnostic_photos_link, nivel, perfil_cliente, allowed_sections, created_at FROM usuarios_acesso WHERE id = :id AND nivel <> 'tradebook'");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return null;
    }

    $user['id'] = (int) $user['id'];
    return $user;
}

function listRecords(PDO $pdo, array $sections, int $userId): array
{
    $records = [];

    foreach ($sections as $table => $section) {
        $columns = array_merge(['id'], $section['columns'], ['created_at']);
        $sql = sprintf(
            'SELECT %s FROM %s WHERE usuario_id = :usuario_id ORDER BY id ASC',
            implode(', ', $columns),
            $table
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario_id' => $userId]);

        $records[] = [
            'section' => $table,
            'label' => $section['label'],
            'columns' => $columns,
            'finalized_at' => findFinalizedAt($pdo, $userId, $table),
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    return $records;
}

function listSupplierBatches(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nome_arquivo, arquivo_path, total_linhas, status, mensagem, created_at
         FROM fornecedores_import_lotes
         WHERE usuario_id = :usuario_id
         ORDER BY id DESC'
    );
    $stmt->execute([':usuario_id' => $userId]);

    return array_map(function ($batch) {
        $batch['id'] = (int) $batch['id'];
        $batch['total_linhas'] = (int) $batch['total_linhas'];
        $batch['has_file'] = trim((string) ($batch['arquivo_path'] ?? '')) !== '';
        unset($batch['arquivo_path']);
        return $batch;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function downloadSupplierBatch(PDO $pdo, int $batchId): void
{
    $stmt = $pdo->prepare(
        'SELECT nome_arquivo, arquivo_path, mime_type
         FROM fornecedores_import_lotes
         WHERE id = :id'
    );
    $stmt->execute([':id' => $batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch || trim((string) ($batch['arquivo_path'] ?? '')) === '') {
        respond(404, false, 'Arquivo da importacao nao encontrado.');
    }

    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $batch['arquivo_path']);
    $allowedPrefix = 'backend' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'supplier_imports' . DIRECTORY_SEPARATOR;

    if (substr($relativePath, 0, strlen($allowedPrefix)) !== $allowedPrefix) {
        respond(403, false, 'Caminho de arquivo invalido.');
    }

    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;

    if (!is_file($absolutePath)) {
        respond(404, false, 'Arquivo da importacao nao encontrado no servidor.');
    }

    header('Content-Type: ' . ($batch['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . safeDownloadName($batch['nome_arquivo'] ?: 'importacao.csv') . '"');
    header('Content-Length: ' . filesize($absolutePath));
    readfile($absolutePath);
}

function ensureSectionStatusTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS onboarding_section_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            section_key VARCHAR(80) NOT NULL,
            finalized_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_onboarding_section_status (usuario_id, section_key),
            CONSTRAINT fk_onboarding_section_status_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE CASCADE
        )'
    );
}

function ensureUserConfigColumns(PDO $pdo): void
{
    ensureColumn($pdo, 'usuarios_acesso', 'diagnostic_photos_link', 'VARCHAR(500) NULL');
    ensureColumn($pdo, 'usuarios_acesso', 'nivel', "VARCHAR(40) NOT NULL DEFAULT 'cliente'");
    ensureColumn($pdo, 'usuarios_acesso', 'perfil_cliente', "VARCHAR(40) NOT NULL DEFAULT 'SMB'");
    ensureColumn($pdo, 'usuarios_acesso', 'allowed_sections', 'TEXT NULL');
}

function ensureExtraSectionTables(PDO $pdo): void
{
    ensureColumn($pdo, 'dados_loja', 'faturamento', 'DECIMAL(14,2) NULL');
    ensureNullableColumn($pdo, 'categorias', 'categoria', 'VARCHAR(180) NULL');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS diagnostico_loja (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            pasta_fotos VARCHAR(255) NOT NULL,
            link_fotos VARCHAR(500),
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_diagnostico_loja_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
        )'
    );

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

function safeDownloadName(string $name): string
{
    $name = basename($name);
    $name = str_replace(["\r", "\n", '"'], '', $name);

    return $name === '' ? 'importacao.csv' : $name;
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

function ensureNullableColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column
    ]);

    if ($stmt->fetchColumn() === 'NO') {
        $pdo->exec("ALTER TABLE {$table} MODIFY COLUMN {$column} {$definition}");
    }
}

function parseAllowedSections($value, array $sections): array
{
    $keys = array_keys($sections);

    if (is_array($value)) {
        $selected = array_values(array_intersect(array_map('trim', $value), $keys));
        return count($selected) > 0 ? $selected : $keys;
    }

    if (trim((string) $value) === '') {
        return $keys;
    }

    $selected = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    $selected = array_values(array_intersect($selected, $keys));

    return count($selected) > 0 ? $selected : $keys;
}

function getAllowedSections(array $sections, array $user): array
{
    $allowed = parseAllowedSections($user['allowed_sections'] ?? '', $sections);
    return array_intersect_key($sections, array_flip($allowed));
}

function normalizeAllowedSections($value, array $sections): array
{
    $keys = array_keys($sections);
    $selected = is_array($value) ? $value : explode(',', (string) $value);
    $selected = array_values(array_intersect(array_map('trim', $selected), $keys));

    return count($selected) > 0 ? $selected : $keys;
}

function normalizeLevel(string $level): string
{
    $level = strtolower(trim($level));
    return in_array($level, ['cliente', 'admin_cliente', 'visualizador'], true) ? $level : 'cliente';
}

function normalizeClientProfile(string $profile): string
{
    $profile = strtoupper(trim($profile));
    return in_array($profile, ['SMB', 'ENTERPRISE', 'SMART'], true) ? $profile : 'SMB';
}

function findFinalizedAt(PDO $pdo, int $userId, string $section): ?string
{
    $stmt = $pdo->prepare('SELECT finalized_at FROM onboarding_section_status WHERE usuario_id = :usuario_id AND section_key = :section_key');
    $stmt->execute([
        ':usuario_id' => $userId,
        ':section_key' => $section
    ]);

    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}

function generateUniquePin(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    do {
        $pin = '';
        for ($i = 0; $i < 5; $i++) {
            $pin .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios_acesso WHERE pin = :pin');
        $stmt->execute([':pin' => $pin]);
    } while ((int) $stmt->fetchColumn() > 0 || $pin === '7X90K');

    return $pin;
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
