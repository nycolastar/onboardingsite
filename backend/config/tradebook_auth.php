<?php

// Helper compartilhado pelas APIs do tradebook (backend/api/tb_*.php).
// Reaproveita a mesma sessao de PIN do onboarding (usuarios_acesso / auth.php),
// so libera quem tem role=admin ou nivel=tradebook.

function tbRespond(int $status, bool $success, string $message, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

function tbRequireAccess(): void
{
    $role = $_SESSION['role'] ?? '';

    if ($role === 'admin') {
        return;
    }

    $nivel = $_SESSION['user']['nivel'] ?? '';

    if ($role === 'user' && $nivel === 'tradebook') {
        return;
    }

    tbRespond(401, false, 'Faca login com um PIN de acesso ao tradebook.');
}
