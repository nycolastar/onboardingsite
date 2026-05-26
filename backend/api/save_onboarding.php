<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$schemas = array_map(fn($section) => $section['columns'], $onboardingSections);

$required = [
    'dados_loja' => ['nome_fantasia'],
    'categorias' => ['categoria'],
    'usuarios_internos' => ['nome'],
    'gerentes_loja' => ['nome'],
    'industrias' => ['cnpj_industria'],
    'dados_bancarios' => ['tipo_pagamento'],
    'plantas_loja' => ['pasta_upload'],
    'ativos_fisicos' => ['nome_ativo'],
    'ativos_digitais' => ['nome_ativo'],
    'alcadas' => ['nome'],
];

try {
    if (($_SESSION['role'] ?? '') !== 'user' || empty($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Faca login com seu PIN para salvar as informacoes.'
        ]);
        exit;
    }

    $section = $_POST['section'] ?? '';

    if (!isset($schemas[$section])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aba de cadastro invalida.'
        ]);
        exit;
    }

    foreach ($required[$section] as $field) {
        if (trim($_POST[$field] ?? '') === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Preencha os campos obrigatorios.'
            ]);
            exit;
        }
    }

    $columns = array_merge(['usuario_id'], $schemas[$section]);
    $placeholders = array_map(fn($column) => ':' . $column, $columns);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $section,
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    $params = [];
    foreach ($columns as $column) {
        if ($column === 'usuario_id') {
            $params[':' . $column] = (int) $_SESSION['user']['id'];
            continue;
        }

        $value = trim($_POST[$column] ?? '');
        $params[':' . $column] = $value === '' ? null : $value;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Registro salvo com sucesso.',
        'id' => $pdo->lastInsertId()
    ]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $error->getMessage()
    ]);
}
