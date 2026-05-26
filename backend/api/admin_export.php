<?php

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

try {
    requireAdmin();

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
    $stmt = $pdo->prepare('SELECT id, nome, pin, drive_link, created_at FROM usuarios_acesso WHERE id = :id');
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

    echo insertSql('usuarios_acesso', ['id', 'nome', 'pin', 'drive_link', 'created_at'], $user) . "\n\n";

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

function respondPlain(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}
