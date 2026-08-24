<?php

$onboardingSections = [
    'dados_loja' => [
        'label' => 'Dados de Loja',
        'columns' => ['nome_fantasia', 'cnpj', 'razao_social', 'sigla_loja', 'formato', 'bandeira', 'cep', 'endereco', 'complemento', 'regiao', 'faturamento'],
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
        'columns' => ['favorecido', 'banco', 'agencia', 'conta_corrente', 'cnpj', 'chave_pix', 'tipo_pagamento', 'observacoes'],
    ],
    'plantas_loja' => [
        'label' => 'Planta de loja',
        'columns' => ['pasta_upload', 'link_pasta', 'observacoes'],
    ],
    'diagnostico_loja' => [
        'label' => 'Diagnostico de loja',
        'columns' => ['pasta_fotos', 'link_fotos', 'dados_loja_id', 'preenchido_por', 'loja_nome_numero', 'endereco_loja', 'banner_estacionamento', 'banner_estacionamento_quantidade', 'banners_gradil_estacionamento', 'banners_gradil_estacionamento_qtd', 'antena_alarme_entrada', 'antena_alarme_entrada_qtd', 'placas_cancela_estacionamento', 'placas_cancela_estacionamento_qtd', 'quantidade_checkouts', 'reguas_check_stand', 'reguas_check_stand_qtd', 'quantidade_pontas_gondola', 'quantidade_portas_pontas_refrigeradas', 'quantidade_orelhas_ponta_gondola', 'ilhas_loja', 'ilhas_loja_qtd', 'localizacao_principais_ilhas', 'quantidade_display_chao', 'backlights', 'backlights_qtd', 'exclusividade_ponta_backlight', 'banners_interior', 'banners_interior_detalhes', 'retail_media', 'retail_media_ativos', 'televisores_internos', 'televisores_internos_qtd', 'elevadores', 'elevadores_qtd', 'radio_interna', 'escadas_esteiras_rolantes', 'escadas_esteiras_rolantes_qtd', 'quantidade_freezers', 'quantidade_pontas_ilha_congelados', 'displays_laterais_lfc', 'displays_laterais_lfc_qtd', 'walk_in_cooler', 'walk_in_cooler_portas', 'quantidade_portas_bebidas', 'quantidade_portas_laticinios', 'quantidade_portas_congelados_refrigerados', 'quantidade_carrinhos', 'quantidade_cestas', 'quantidade_check_stands', 'pontas_gondola_refrigeradas_detalhes', 'observacoes'],
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
