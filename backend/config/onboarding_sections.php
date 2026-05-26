<?php

$onboardingSections = [
    'dados_loja' => [
        'label' => 'Dados de Loja',
        'columns' => ['nome_fantasia', 'cnpj', 'razao_social', 'sigla_loja', 'formato', 'bandeira', 'cep', 'endereco', 'complemento', 'regiao'],
    ],
    'categorias' => [
        'label' => 'Categoria',
        'columns' => ['categoria', 'setor', 'departamento'],
    ],
    'usuarios_internos' => [
        'label' => 'Usuarios Internos',
        'columns' => ['nome', 'email', 'whatsapp', 'area'],
    ],
    'gerentes_loja' => [
        'label' => 'Gerente de loja',
        'columns' => ['nome', 'email', 'whatsapp', 'loja_responsavel'],
    ],
    'industrias' => [
        'label' => 'Industrias',
        'columns' => ['cnpj_industria', 'razao_social', 'nome_fantasia', 'codigo_interno', 'nome_representante', 'telefone_representante', 'email_representante', 'whatsapp_representante', 'segmento', 'faturamento'],
    ],
    'dados_bancarios' => [
        'label' => 'Dados Bancarios',
        'columns' => ['tipo_pagamento', 'observacoes'],
    ],
    'plantas_loja' => [
        'label' => 'Planta de loja',
        'columns' => ['pasta_upload', 'link_pasta', 'observacoes'],
    ],
    'ativos_fisicos' => [
        'label' => 'Ativos Fisicos',
        'columns' => ['nome_ativo', 'valor_custo', 'valor_venda', 'loja', 'quantidade', 'observacoes'],
    ],
    'ativos_digitais' => [
        'label' => 'Ativos Digitais',
        'columns' => ['nome_ativo', 'valor_custo', 'valor_venda', 'loja_digital'],
    ],
    'alcadas' => [
        'label' => 'Alcada',
        'columns' => ['nome', 'alcada_percentual'],
    ],
];
