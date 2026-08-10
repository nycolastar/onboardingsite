<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$schemas = array_map(fn($section) => $section['columns'], $onboardingSections);

try {
    ensureSectionStatusTable($pdo);
    ensureUserConfigColumns($pdo);
    ensureExtraSectionTables($pdo);

    if (($_SESSION['role'] ?? '') !== 'user' || empty($_SESSION['user']['id'])) {
        respond(401, false, 'Faca login com seu PIN para confirmar a aba.');
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $section = $_POST['section'] ?? '';

    if ($action !== 'finalize') {
        respond(400, false, 'Acao invalida.');
    }

    if (!isset($schemas[$section])) {
        respond(400, false, 'Aba de cadastro invalida.');
    }

    $userId = (int) $_SESSION['user']['id'];

    if (!userCanAccessSection($pdo, $userId, $section, $onboardingSections)) {
        respond(403, false, 'Esta aba nao esta liberada para este cliente.');
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$section} WHERE usuario_id = :usuario_id");
    $countStmt->execute([':usuario_id' => $userId]);

    if ((int) $countStmt->fetchColumn() === 0) {
        respond(422, false, 'Envie pelo menos um registro antes de confirmar a aba.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO onboarding_section_status (usuario_id, section_key, finalized_at)
         VALUES (:usuario_id, :section_key, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE finalized_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':usuario_id' => $userId,
        ':section_key' => $section
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Aba finalizada com sucesso.',
        'status' => [
            'section' => $section,
            'finalized_at' => findFinalizedAt($pdo, $userId, $section)
        ]
    ]);
} catch (Throwable $error) {
    respond(500, false, 'Erro ao confirmar: ' . $error->getMessage());
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

function ensureUserConfigColumns(PDO $pdo): void
{
    ensureColumn($pdo, 'usuarios_acesso', 'diagnostic_photos_link', 'VARCHAR(500) NULL');
    ensureColumn($pdo, 'usuarios_acesso', 'nivel', "VARCHAR(40) NOT NULL DEFAULT 'cliente'");
    ensureColumn($pdo, 'usuarios_acesso', 'allowed_sections', 'TEXT NULL');
}

function ensureExtraSectionTables(PDO $pdo): void
{
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
