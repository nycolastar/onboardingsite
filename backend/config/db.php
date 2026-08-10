<?php
require_once __DIR__ . '/config.local.php';

$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$password = DB_PASS;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erro na conexao com o banco de dados. Confira host, nome do banco, usuario e senha.'
    ]);
    exit;
}