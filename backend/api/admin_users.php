<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';

$sections = [
    'dados_loja' => 'Dados de Loja',
    'categorias' => 'Categoria',
    'ativos_fisicos' => 'Ativos Fisicos',
    'usuarios_internos' => 'Usuarios Internos',
    'gerentes_loja' => 'Gerente de loja',
    'industrias' => 'Industrias',
    'dados_bancarios' => 'Dados Bancarios',
    'plantas_loja' => 'Planta de loja',
    'ativos_digitais' => 'Ativos Digitais',
    'alcadas' => 'Alcada',
    'header_clientes' => 'Header Clientes',
];

try {
    requireAdmin();

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'create') {
        $name = trim($_POST['nome'] ?? '');

        if ($name === '') {
            respond(422, false, 'Informe o nome do usuario.');
        }

        $pin = generateUniquePin($pdo);
        $stmt = $pdo->prepare('INSERT INTO usuarios_acesso (nome, pin) VALUES (:nome, :pin)');
        $stmt->execute([
            ':nome' => $name,
            ':pin' => $pin
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Usuario criado com sucesso.',
            'user' => [
                'id' => (int) $pdo->lastInsertId(),
                'nome' => $name,
                'pin' => $pin
            ]
        ]);
        exit;
    }

    if ($action === 'list') {
        echo json_encode([
            'success' => true,
            'sections' => $sections,
            'users' => listUsers($pdo, $sections)
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
        ->query('SELECT id, nome, pin, created_at FROM usuarios_acesso ORDER BY created_at DESC, id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $user['id'] = (int) $user['id'];
        $user['progress'] = [];
        $sent = 0;

        foreach ($sections as $table => $label) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE usuario_id = :usuario_id");
            $stmt->execute([':usuario_id' => $user['id']]);
            $count = (int) $stmt->fetchColumn();

            if ($count > 0) {
                $sent++;
            }

            $user['progress'][] = [
                'section' => $table,
                'label' => $label,
                'count' => $count,
                'sent' => $count > 0
            ];
        }

        $user['completed'] = $sent;
        $user['total'] = count($sections);
    }

    return $users;
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
