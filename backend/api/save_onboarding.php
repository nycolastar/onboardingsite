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

    if (isset($_POST['rows_json'])) {
        $rows = json_decode($_POST['rows_json'], true);

        if (!is_array($rows)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Dados da grade invalidos.'
            ]);
            exit;
        }

        $saved = saveRows($pdo, $section, $schemas[$section], $required[$section], $rows, (int) $_SESSION['user']['id']);

        echo json_encode([
            'success' => true,
            'message' => $saved . ' registro(s) salvo(s) com sucesso.',
            'count' => $saved
        ]);
        exit;
    }

    $savedId = saveSingleRow($pdo, $section, $schemas[$section], $required[$section], $_POST, (int) $_SESSION['user']['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Registro salvo com sucesso.',
        'id' => $savedId
    ]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $error->getMessage()
    ]);
}

function saveRows(PDO $pdo, string $section, array $schema, array $required, array $rows, int $userId): int
{
    $validRows = [];

    foreach ($rows as $row) {
        if (!is_array($row) || rowIsEmpty($row, $schema)) {
            continue;
        }

        foreach ($required as $field) {
            if (trim($row[$field] ?? '') === '') {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Preencha os campos obrigatorios nas linhas preenchidas.'
                ]);
                exit;
            }
        }

        $validRows[] = $row;
    }

    if (count($validRows) === 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Preencha pelo menos uma linha da grade.'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    try {
        foreach ($validRows as $row) {
            insertRow($pdo, $section, $schema, $row, $userId);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return count($validRows);
}

function saveSingleRow(PDO $pdo, string $section, array $schema, array $required, array $row, int $userId): string
{
    foreach ($required as $field) {
        if (trim($row[$field] ?? '') === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Preencha os campos obrigatorios.'
            ]);
            exit;
        }
    }

    insertRow($pdo, $section, $schema, $row, $userId);

    return $pdo->lastInsertId();
}

function insertRow(PDO $pdo, string $section, array $schema, array $row, int $userId): void
{
    $columns = array_merge(['usuario_id'], $schema);
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
            $params[':' . $column] = $userId;
            continue;
        }

        $value = trim($row[$column] ?? '');
        $params[':' . $column] = $value === '' ? null : $value;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function rowIsEmpty(array $row, array $schema): bool
{
    foreach ($schema as $field) {
        if (trim($row[$field] ?? '') !== '') {
            return false;
        }
    }

    return true;
}
