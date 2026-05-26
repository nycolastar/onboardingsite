<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';

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
        $pin = strtoupper(trim($_POST['pin'] ?? ''));

        if ($pin === '') {
            respond(422, false, 'Informe o PIN.');
        }

        if ($pin === ADMIN_PIN) {
            $_SESSION['role'] = 'admin';
            unset($_SESSION['user']);

            echo json_encode([
                'success' => true,
                'role' => 'admin',
                'message' => 'Login de admin realizado.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id, nome, pin FROM usuarios_acesso WHERE pin = :pin LIMIT 1');
        $stmt->execute([':pin' => $pin]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            respond(401, false, 'PIN invalido.');
        }

        $_SESSION['role'] = 'user';
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'nome' => $user['nome'],
            'pin' => $user['pin']
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

function respond(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}
