<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$schemas = array_map(fn($section) => $section['columns'], $onboardingSections);

try {
    if (($_SESSION['role'] ?? '') !== 'user' || empty($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Faca login com seu PIN para ver as informacoes.'
        ]);
        exit;
    }

    $section = $_GET['section'] ?? '';

    if (!isset($schemas[$section])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aba de cadastro invalida.'
        ]);
        exit;
    }

    $columns = array_merge(['id'], $schemas[$section], ['created_at']);
    $sql = sprintf(
        'SELECT %s FROM %s WHERE usuario_id = :usuario_id ORDER BY id DESC',
        implode(', ', $columns),
        $section
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario_id' => (int) $_SESSION['user']['id']]);

    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar registros: ' . $error->getMessage()
    ]);
}
