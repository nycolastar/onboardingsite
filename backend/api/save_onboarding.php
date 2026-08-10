<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$schemas = array_map(fn($section) => $section['columns'], $onboardingSections);

$required = [
    'dados_loja' => ['nome_fantasia'],
    'categorias' => [],
    'usuarios_internos' => ['nome'],
    'gerentes_loja' => ['nome'],
    'industrias' => ['cnpj_industria'],
    'dados_bancarios' => ['tipo_pagamento'],
    'plantas_loja' => ['pasta_upload'],
    'diagnostico_loja' => ['pasta_fotos'],
    'fornecedores_importacao' => ['codigo_fornecedor'],
    'ativos_fisicos' => ['nome_ativo'],
    'ativos_digitais' => ['nome_ativo'],
    'alcadas' => ['nome'],
];

try {
    ensureSectionStatusTable($pdo);
    ensureUserConfigColumns($pdo);
    ensureExtraSectionTables($pdo);

    if (($_SESSION['role'] ?? '') !== 'user' || empty($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Faca login com seu PIN para salvar as informacoes.'
        ]);
        exit;
    }

    $section = $_POST['section'] ?? '';

    if (!isset($schemas[$section])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aba de cadastro invalida.'
        ]);
        exit;
    }

    if (!userCanAccessSection($pdo, (int) $_SESSION['user']['id'], $section, $onboardingSections)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Esta aba nao esta liberada para este cliente.'
        ]);
        exit;
    }

    if (isset($_POST['rows_json'])) {
        $rows = json_decode($_POST['rows_json'], true);

        if (!is_array($rows)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Dados da grade invalidos.'
            ]);
            exit;
        }

        $saved = saveRows($pdo, $section, $schemas[$section], $required[$section], $rows, (int) $_SESSION['user']['id']);
        markSectionInProgress($pdo, (int) $_SESSION['user']['id'], $section);

        echo json_encode([
            'success' => true,
            'message' => $saved . ' registro(s) salvo(s) com sucesso.',
            'count' => $saved
        ]);
        exit;
    }

    $userId = (int) $_SESSION['user']['id'];
    $recordId = (int) ($_POST['record_id'] ?? 0);

    if ($recordId > 0) {
        updateSingleRow($pdo, $section, $schemas[$section], $required[$section], $_POST, $userId, $recordId);
        markSectionInProgress($pdo, $userId, $section);

        echo json_encode([
            'success' => true,
            'message' => 'Registro atualizado com sucesso.',
            'id' => $recordId
        ]);
        exit;
    }

    $savedId = saveSingleRow($pdo, $section, $schemas[$section], $required[$section], $_POST, $userId);
    markSectionInProgress($pdo, $userId, $section);

    echo json_encode([
        'success' => true,
        'message' => 'Registro salvo com sucesso.',
        'id' => $savedId
    ]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $error->getMessage()
    ]);
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

function markSectionInProgress(PDO $pdo, int $userId, string $section): void
{
    $stmt = $pdo->prepare('DELETE FROM onboarding_section_status WHERE usuario_id = :usuario_id AND section_key = :section_key');
    $stmt->execute([
        ':usuario_id' => $userId,
        ':section_key' => $section
    ]);
}

function ensureUserConfigColumns(PDO $pdo): void
{
    ensureColumn($pdo, 'usuarios_acesso', 'diagnostic_photos_link', 'VARCHAR(500) NULL');
    ensureColumn($pdo, 'usuarios_acesso', 'nivel', "VARCHAR(40) NOT NULL DEFAULT 'cliente'");
    ensureColumn($pdo, 'usuarios_acesso', 'allowed_sections', 'TEXT NULL');
}

function ensureExtraSectionTables(PDO $pdo): void
{
    ensureColumn($pdo, 'dados_loja', 'faturamento', 'DECIMAL(14,2) NULL');
    ensureNullableColumn($pdo, 'categorias', 'categoria', 'VARCHAR(180) NULL');
    ensureColumn($pdo, 'dados_bancarios', 'favorecido', 'VARCHAR(255) NULL');
    ensureColumn($pdo, 'dados_bancarios', 'banco', 'VARCHAR(120) NULL');
    ensureColumn($pdo, 'dados_bancarios', 'agencia', 'VARCHAR(40) NULL');
    ensureColumn($pdo, 'dados_bancarios', 'conta_corrente', 'VARCHAR(40) NULL');
    ensureColumn($pdo, 'dados_bancarios', 'cnpj', 'VARCHAR(20) NULL');
    ensureColumn($pdo, 'dados_bancarios', 'chave_pix', 'VARCHAR(255) NULL');

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
            total_linhas INT NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'em_analise',
            mensagem TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_fornecedores_import_lotes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE CASCADE
        )"
    );

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

function saveRows(PDO $pdo, string $section, array $schema, array $required, array $rows, int $userId): int
{
    $validRows = [];

    foreach ($rows as $row) {
        if (!is_array($row) || rowIsEmpty($row, $schema)) {
            continue;
        }

        foreach ($required as $field) {
            if (trim($row[$field] ?? '') === '') {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Preencha os campos obrigatorios nas linhas preenchidas.'
                ]);
                exit;
            }
        }

        normalizeAndValidateNumericFields($row);

        $validRows[] = $row;
    }

    if (count($validRows) === 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Preencha pelo menos uma linha da grade.'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    try {
        foreach ($validRows as $row) {
            insertRow($pdo, $section, $schema, $row, $userId);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return count($validRows);
}

function saveSingleRow(PDO $pdo, string $section, array $schema, array $required, array $row, int $userId): string
{
    validateRequired($required, $row);

    if (rowIsEmpty($row, $schema)) {
        rejectEmptyRow();
    }

    normalizeAndValidateNumericFields($row);

    insertRow($pdo, $section, $schema, $row, $userId);

    return $pdo->lastInsertId();
}

function updateSingleRow(PDO $pdo, string $section, array $schema, array $required, array $row, int $userId, int $recordId): void
{
    validateRequired($required, $row);

    if (rowIsEmpty($row, $schema)) {
        rejectEmptyRow();
    }

    normalizeAndValidateNumericFields($row);

    $exists = $pdo->prepare("SELECT COUNT(*) FROM {$section} WHERE id = :id AND usuario_id = :usuario_id");
    $exists->execute([
        ':id' => $recordId,
        ':usuario_id' => $userId
    ]);

    if ((int) $exists->fetchColumn() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Registro nao encontrado para este usuario.'
        ]);
        exit;
    }

    $assignments = array_map(fn($column) => $column . ' = :' . $column, $schema);
    $sql = sprintf(
        'UPDATE %s SET %s WHERE id = :id AND usuario_id = :usuario_id',
        $section,
        implode(', ', $assignments)
    );

    $params = [
        ':id' => $recordId,
        ':usuario_id' => $userId
    ];

    foreach ($schema as $column) {
        $value = trim($row[$column] ?? '');
        $params[':' . $column] = $value === '' ? null : $value;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function insertRow(PDO $pdo, string $section, array $schema, array $row, int $userId): void
{
    $columns = array_merge(['usuario_id'], $schema);
    $placeholders = array_map(fn($column) => ':' . $column, $columns);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $section,
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    $params = [];
    foreach ($columns as $column) {
        if ($column === 'usuario_id') {
            $params[':' . $column] = $userId;
            continue;
        }

        $value = trim($row[$column] ?? '');
        $params[':' . $column] = $value === '' ? null : $value;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function rowIsEmpty(array $row, array $schema): bool
{
    foreach ($schema as $field) {
        if (trim($row[$field] ?? '') !== '') {
            return false;
        }
    }

    return true;
}

function rejectEmptyRow(): void
{
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Preencha pelo menos um campo.'
    ]);
    exit;
}

function validateRequired(array $required, array $row): void
{
    foreach ($required as $field) {
        if (trim($row[$field] ?? '') === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Preencha os campos obrigatorios.'
            ]);
            exit;
        }
    }
}

function normalizeAndValidateNumericFields(array &$row): void
{
    $rules = [
        'cnpj' => ['length' => 14, 'label' => 'CNPJ'],
        'cnpj_industria' => ['length' => 14, 'label' => 'CNPJ Industria'],
        'cep' => ['length' => 8, 'label' => 'CEP'],
    ];

    foreach ($rules as $field => $rule) {
        if (!array_key_exists($field, $row)) {
            continue;
        }

        $value = trim($row[$field] ?? '');

        if ($value === '') {
            continue;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) !== $rule['length']) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $rule['label'] . ' deve ter ' . $rule['length'] . ' digitos.'
            ]);
            exit;
        }

        $row[$field] = $digits;
    }
}
