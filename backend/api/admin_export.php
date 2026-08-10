<?php

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

try {
    requireAdmin();
    ensureUserConfigColumns($pdo);
    ensureExtraSectionTables($pdo);

    $type = $_GET['type'] ?? '';
    $userId = (int) ($_GET['user_id'] ?? 0);

    if ($userId <= 0) {
        respondPlain(422, 'Usuario invalido.');
    }

    $user = findUser($pdo, $userId);

    if (!$user) {
        respondPlain(404, 'Usuario nao encontrado.');
    }

    $records = listRecords($pdo, $onboardingSections, $userId);

    if ($type === 'sql') {
        exportSql($user, $records);
        exit;
    }

    if ($type === 'excel') {
        exportExcel($user, $records);
        exit;
    }

    respondPlain(400, 'Tipo de exportacao invalido.');
} catch (Throwable $error) {
    respondPlain(500, 'Erro: ' . $error->getMessage());
}

function requireAdmin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        respondPlain(401, 'Acesso restrito ao admin.');
    }
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
        $columns = array_merge(['id', 'usuario_id'], $section['columns'], ['created_at']);
        $stmt = $pdo->prepare(sprintf(
            'SELECT %s FROM %s WHERE usuario_id = :usuario_id ORDER BY id ASC',
            implode(', ', $columns),
            $table
        ));
        $stmt->execute([':usuario_id' => $userId]);

        $records[] = [
            'table' => $table,
            'label' => $section['label'],
            'columns' => $columns,
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    return $records;
}

function exportSql(array $user, array $records): void
{
    $filename = safeFilename($user['nome']) . '_onboarding.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- Exportacao de onboarding\n";
    echo "-- Cliente: " . $user['nome'] . "\n";
    echo "-- PIN: " . $user['pin'] . "\n";
    echo "-- Gerado em: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\n\n";

    echo insertSql('usuarios_acesso', ['id', 'nome', 'pin', 'drive_link', 'diagnostic_photos_link', 'nivel', 'perfil_cliente', 'allowed_sections', 'created_at'], $user) . "\n\n";

    foreach ($records as $section) {
        echo "-- " . $section['label'] . "\n";

        if (count($section['rows']) === 0) {
            echo "-- Sem registros.\n\n";
            continue;
        }

        foreach ($section['rows'] as $row) {
            echo insertSql($section['table'], $section['columns'], $row) . "\n";
        }

        echo "\n";
    }
}

function exportExcel(array $user, array $records): void
{
    $filename = safeFilename($user['nome']) . '_onboarding.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "\xEF\xBB\xBF";
    ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; color: #1f2933; }
    h1 { font-size: 20px; margin: 0 0 8px; }
    h2 { font-size: 16px; margin: 24px 0 8px; color: #330066; }
    table { border-collapse: collapse; margin-bottom: 18px; width: 100%; }
    th { background: #330066; color: #ffffff; font-weight: bold; }
    th, td { border: 1px solid #d7dde5; padding: 6px; mso-number-format: "\@"; }
    .meta th { width: 160px; text-align: left; }
    .empty { color: #667085; font-style: italic; }
  </style>
</head>
<body>
  <h1>Onboarding - <?= html($user['nome']) ?></h1>
  <table class="meta">
    <tr><th>Cliente</th><td><?= html($user['nome']) ?></td></tr>
    <tr><th>PIN</th><td><?= html($user['pin']) ?></td></tr>
    <tr><th>Exportado em</th><td><?= html(date('d/m/Y H:i:s')) ?></td></tr>
  </table>

  <?php foreach ($records as $section): ?>
    <h2><?= html($section['label']) ?></h2>
    <?php if (count($section['rows']) === 0): ?>
      <p class="empty">Sem registros enviados.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <?php foreach ($section['columns'] as $column): ?>
              <th><?= html($column) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($section['rows'] as $row): ?>
            <tr>
              <?php foreach ($section['columns'] as $column): ?>
                <td><?= html($row[$column] ?? '') ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endforeach; ?>
</body>
</html>
    <?php
}

function insertSql(string $table, array $columns, array $row): string
{
    $values = array_map(fn($column) => sqlValue($row[$column] ?? null), $columns);

    return sprintf(
        'INSERT INTO %s (%s) VALUES (%s);',
        $table,
        implode(', ', $columns),
        implode(', ', $values)
    );
}

function sqlValue($value): string
{
    if ($value === null || $value === '') {
        return 'NULL';
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
}

function safeFilename(string $name): string
{
    $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
    $normalized = trim($normalized ?? '', '_');

    return $normalized === '' ? 'cliente' : strtolower($normalized);
}

function html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

function respondPlain(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}
