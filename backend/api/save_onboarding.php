<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';

$schemas = [
    'dados_loja' => ['nome_fantasia', 'cnpj', 'razao_social', 'sigla_loja', 'formato', 'bandeira', 'cep', 'endereco', 'complemento', 'regiao'],
    'categorias' => ['categoria', 'setor', 'departamento'],
    'ativos_fisicos' => ['nome_ativo', 'valor_custo', 'valor_venda', 'loja', 'quantidade', 'observacoes'],
    'usuarios_internos' => ['nome', 'email', 'whatsapp', 'area'],
    'gerentes_loja' => ['nome', 'email', 'whatsapp', 'loja_responsavel'],
    'industrias' => ['cnpj_industria', 'razao_social', 'nome_fantasia', 'codigo_interno', 'nome_representante', 'telefone_representante', 'email_representante', 'whatsapp_representante', 'segmento', 'faturamento'],
    'dados_bancarios' => ['tipo_pagamento', 'observacoes'],
    'plantas_loja' => ['pasta_upload', 'link_pasta', 'observacoes'],
    'ativos_digitais' => ['nome_ativo', 'valor_custo', 'valor_venda', 'loja_digital'],
    'alcadas' => ['nome', 'alcada_percentual'],
    'header_clientes' => ['campo', 'descricao', 'tipo', 'manutencao', 'fonte', 'comentario'],
];

$required = [
    'dados_loja' => ['nome_fantasia'],
    'categorias' => ['categoria'],
    'ativos_fisicos' => ['nome_ativo'],
    'usuarios_internos' => ['nome'],
    'gerentes_loja' => ['nome'],
    'industrias' => ['cnpj_industria'],
    'dados_bancarios' => ['tipo_pagamento'],
    'plantas_loja' => ['pasta_upload'],
    'ativos_digitais' => ['nome_ativo'],
    'alcadas' => ['nome'],
    'header_clientes' => ['campo'],
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
        'id' => $pdo->lastInsertId(),
        'sql' => previewSql($section, $columns, $params)
    ]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $error->getMessage()
    ]);
}

function previewSql(string $table, array $columns, array $params): string
{
    $values = array_map(function ($column) use ($params) {
        $value = $params[':' . $column];
        if ($value === null) {
            return 'NULL';
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }, $columns);

    return sprintf(
        "INSERT INTO %s (%s)\nVALUES (%s);",
        $table,
        implode(', ', $columns),
        implode(', ', $values)
    );
}
