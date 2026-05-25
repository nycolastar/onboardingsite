<?php

header('Content-Type: application/json');

require_once '../config/db.php';

$nome_loja = $_POST['nome_loja'] ?? '';
$cidade = $_POST['cidade'] ?? '';
$uf = $_POST['uf'] ?? '';
$email = $_POST['email'] ?? '';

$stmt = $pdo->prepare("
    INSERT INTO lojas (
        nome_loja,
        cidade,
        uf,
        email
    ) VALUES (
        :nome_loja,
        :cidade,
        :uf,
        :email
    )
");

$stmt->execute([
    ':nome_loja' => $nome_loja,
    ':cidade' => $cidade,
    ':uf' => $uf,
    ':email' => $email
]);

$sql = "
INSERT INTO lojas (
    nome_loja,
    cidade,
    uf,
    email
) VALUES (
    '$nome_loja',
    '$cidade',
    '$uf',
    '$email'
);
";

echo json_encode([
    'success' => true,
    'message' => 'Onboarding salvo com sucesso!',
    'sql' => $sql
]);
