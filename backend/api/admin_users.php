<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$sections = $onboardingSections;

try {
    requireAdmin();

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'create') {
        $name = trim($_POST['nome'] ?? '');
        $driveLink = trim($_POST['drive_link'] ?? '');

        if ($name === '') {
            respond(422, false, 'Informe o nome do usuario.');
        }

        $pin = generateUniquePin($pdo);
        $stmt = $pdo->prepare('INSERT INTO usuarios_acesso (nome, pin, drive_link) VALUES (:nome, :pin, :drive_link)');
        $stmt->execute([
            ':nome' => $name,
            ':pin' => $pin,
            ':drive_link' => $driveLink === '' ? null : $driveLink
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Usuario criado com sucesso.',
            'user' => [
                'id' => (int) $pdo->lastInsertId(),
                'nome' => $name,
                'pin' => $pin,
                'drive_link' => $driveLink
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

        if ($userId <= 0) {
            respond(422, false, 'Usuario invalido.');
        }

        $user = findUser($pdo, $userId);

        if (!$user) {
            respond(404, false, 'Usuario nao encontrado.');
        }

        $stmt = $pdo->prepare('UPDATE usuarios_acesso SET drive_link = :drive_link WHERE id = :id');
        $stmt->execute([
            ':drive_link' => $driveLink === '' ? null : $driveLink,
            ':id' => $userId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Link da pasta atualizado.',
            'drive_link' => $driveLink
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
            'sections' => listRecords($pdo, $sections, $userId)
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
        ->query('SELECT id, nome, pin, drive_link, created_at FROM usuarios_acesso ORDER BY created_at DESC, id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $user['id'] = (int) $user['id'];
        $user['progress'] = [];
        $sent = 0;

        foreach ($sections as $table => $section) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE usuario_id = :usuario_id");
            $stmt->execute([':usuario_id' => $user['id']]);
            $count = (int) $stmt->fetchColumn();

            if ($count > 0) {
                $sent++;
            }

            $user['progress'][] = [
                'section' => $table,
                'label' => $section['label'],
                'count' => $count,
                'sent' => $count > 0
            ];
        }

        $user['completed'] = $sent;
        $user['total'] = count($sections);
    }

    return $users;
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
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    return $records;
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
