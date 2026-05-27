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

    $userId = (int) $_SESSION['user']['id'];
    $recordId = (int) ($_POST['record_id'] ?? 0);

    if ($recordId > 0) {
        updateSingleRow($pdo, $section, $schemas[$section], $required[$section], $_POST, $userId, $recordId);

        echo json_encode([
            'success' => true,
            'message' => 'Registro atualizado com sucesso.',
            'id' => $recordId
        ]);
        exit;
    }

    $savedId = saveSingleRow($pdo, $section, $schemas[$section], $required[$section], $_POST, $userId);

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

        normalizeAndValidateNumericFields($row);

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
    validateRequired($required, $row);
    normalizeAndValidateNumericFields($row);

    insertRow($pdo, $section, $schema, $row, $userId);

    return $pdo->lastInsertId();
}

function updateSingleRow(PDO $pdo, string $section, array $schema, array $required, array $row, int $userId, int $recordId): void
{
    validateRequired($required, $row);
    normalizeAndValidateNumericFields($row);

    $exists = $pdo->prepare("SELECT COUNT(*) FROM {$section} WHERE id = :id AND usuario_id = :usuario_id");
    $exists->execute([
        ':id' => $recordId,
        ':usuario_id' => $userId
    ]);

    if ((int) $exists->fetchColumn() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Registro nao encontrado para este usuario.'
        ]);
        exit;
    }

    $assignments = array_map(fn($column) => $column . ' = :' . $column, $schema);
    $sql = sprintf(
        'UPDATE %s SET %s WHERE id = :id AND usuario_id = :usuario_id',
        $section,
        implode(', ', $assignments)
    );

    $params = [
        ':id' => $recordId,
        ':usuario_id' => $userId
    ];

    foreach ($schema as $column) {
        $value = trim($row[$column] ?? '');
        $params[':' . $column] = $value === '' ? null : $value;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

function validateRequired(array $required, array $row): void
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
}

function normalizeAndValidateNumericFields(array &$row): void
{
    $rules = [
        'cnpj' => ['length' => 14, 'label' => 'CNPJ'],
        'cnpj_industria' => ['length' => 14, 'label' => 'CNPJ Industria'],
        'cep' => ['length' => 8, 'label' => 'CEP'],
    ];

    foreach ($rules as $field => $rule) {
        if (!array_key_exists($field, $row)) {
            continue;
        }

        $value = trim($row[$field] ?? '');

        if ($value === '') {
            continue;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) !== $rule['length']) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $rule['label'] . ' deve ter ' . $rule['length'] . ' digitos.'
            ]);
            exit;
        }

        $row[$field] = $digits;
    }
}
