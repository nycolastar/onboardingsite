<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/onboarding_sections.php';

const ADMIN_PIN = '7X90K';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'status') {
        echo json_encode([
            'success' => true,
            'authenticated' => isset($_SESSION['role']),
            'role' => $_SESSION['role'] ?? null,
            'user' => $_SESSION['user'] ?? null
        ]);
        exit;
    }

    if ($action === 'login') {
        enforceLoginRateLimit();

        $pin = strtoupper(trim($_POST['pin'] ?? ''));

        if ($pin === '') {
            registerFailedLogin();
            respond(422, false, 'Informe o PIN.');
        }

        if ($pin === ADMIN_PIN) {
            clearLoginRateLimit();
            $_SESSION['role'] = 'admin';
            unset($_SESSION['user']);

            echo json_encode([
                'success' => true,
                'role' => 'admin',
                'message' => 'Login de admin realizado.'
            ]);
            exit;
        }

        require_once '../config/db.php';
        ensureUserConfigColumns($pdo);

        $stmt = $pdo->prepare('SELECT id, nome, pin, drive_link, diagnostic_photos_link, nivel, allowed_sections FROM usuarios_acesso WHERE pin = :pin LIMIT 1');
        $stmt->execute([':pin' => $pin]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            registerFailedLogin();
            respond(401, false, 'PIN invalido.');
        }

        clearLoginRateLimit();
        $_SESSION['role'] = 'user';
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'nome' => $user['nome'],
            'pin' => $user['pin'],
            'drive_link' => $user['drive_link'],
            'diagnostic_photos_link' => $user['diagnostic_photos_link'] ?? null,
            'nivel' => $user['nivel'] ?? 'cliente',
            'allowed_sections' => parseAllowedSections($user['allowed_sections'] ?? '', $onboardingSections)
        ];

        echo json_encode([
            'success' => true,
            'role' => 'user',
            'user' => $_SESSION['user'],
            'message' => 'Login realizado.'
        ]);
        exit;
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();

        echo json_encode([
            'success' => true,
            'message' => 'Sessao encerrada.'
        ]);
        exit;
    }

    respond(400, false, 'Acao invalida.');
} catch (Throwable $error) {
    respond(500, false, 'Erro: ' . $error->getMessage());
}

function ensureUserConfigColumns(PDO $pdo): void
{
    ensureColumn($pdo, 'usuarios_acesso', 'diagnostic_photos_link', 'VARCHAR(500) NULL');
    ensureColumn($pdo, 'usuarios_acesso', 'nivel', "VARCHAR(40) NOT NULL DEFAULT 'cliente'");
    ensureColumn($pdo, 'usuarios_acesso', 'allowed_sections', 'TEXT NULL');
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

function enforceLoginRateLimit(): void
{
    $limit = $_SESSION['login_rate_limit'] ?? ['attempts' => 0, 'locked_until' => 0];
    $lockedUntil = (int) ($limit['locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $seconds = $lockedUntil - time();
        respond(429, false, "Muitas tentativas. Tente novamente em {$seconds} segundos.");
    }
}

function registerFailedLogin(): void
{
    $limit = $_SESSION['login_rate_limit'] ?? ['attempts' => 0, 'locked_until' => 0];
    $attempts = (int) ($limit['attempts'] ?? 0) + 1;

    $_SESSION['login_rate_limit'] = [
        'attempts' => $attempts,
        'locked_until' => $attempts >= 5 ? time() + 60 : 0
    ];
}

function clearLoginRateLimit(): void
{
    unset($_SESSION['login_rate_limit']);
}
