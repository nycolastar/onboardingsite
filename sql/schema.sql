CREATE DATABASE IF NOT EXISTS onboarding_system
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE onboarding_system;

DROP TABLE IF EXISTS cronograma_implantacao;

CREATE TABLE IF NOT EXISTS usuarios_acesso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    pin VARCHAR(20) NOT NULL UNIQUE,
    drive_link VARCHAR(500),
    diagnostic_photos_link VARCHAR(500),
    nivel VARCHAR(40) NOT NULL DEFAULT 'cliente',
    perfil_cliente VARCHAR(40) NOT NULL DEFAULT 'SMB',
    allowed_sections TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS onboarding_section_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    section_key VARCHAR(80) NOT NULL,
    finalized_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_onboarding_section_status (usuario_id, section_key),
    CONSTRAINT fk_onboarding_section_status_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dados_loja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nome_fantasia VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20),
    razao_social VARCHAR(255),
    sigla_loja VARCHAR(80),
    formato VARCHAR(120),
    bandeira VARCHAR(120),
    cep VARCHAR(20),
    endereco VARCHAR(255),
    complemento VARCHAR(255),
    regiao VARCHAR(180),
    faturamento DECIMAL(14,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dados_loja_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    categoria VARCHAR(180),
    setor VARCHAR(180),
    departamento VARCHAR(180),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_categorias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ativos_fisicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nome_ativo VARCHAR(255) NOT NULL,
    valor_custo DECIMAL(12,2),
    valor_venda DECIMAL(12,2),
    loja VARCHAR(120),
    quantidade VARCHAR(80),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ativos_fisicos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS usuarios_internos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    whatsapp VARCHAR(30),
    area VARCHAR(180),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_internos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS gerentes_loja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    whatsapp VARCHAR(30),
    loja_responsavel VARCHAR(120),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gerentes_loja_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS industrias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    cnpj_industria VARCHAR(20) NOT NULL,
    razao_social VARCHAR(255),
    nome_fantasia VARCHAR(255),
    codigo_interno VARCHAR(80),
    nome_representante VARCHAR(255),
    telefone_representante VARCHAR(40),
    email_representante VARCHAR(255),
    whatsapp_representante VARCHAR(40),
    segmento VARCHAR(180),
    faturamento DECIMAL(14,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_industrias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS dados_bancarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    favorecido VARCHAR(255),
    banco VARCHAR(120),
    agencia VARCHAR(40),
    conta_corrente VARCHAR(40),
    cnpj VARCHAR(20),
    chave_pix VARCHAR(255),
    tipo_pagamento VARCHAR(120) NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dados_bancarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS plantas_loja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    pasta_upload VARCHAR(255) NOT NULL,
    link_pasta VARCHAR(500),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_plantas_loja_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS diagnostico_loja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    pasta_fotos VARCHAR(255) NOT NULL,
    link_fotos VARCHAR(500),
    dados_loja_id INT,
    preenchido_por VARCHAR(255),
    loja_nome_numero VARCHAR(255),
    endereco_loja VARCHAR(500),
    banner_estacionamento VARCHAR(20),
    banner_estacionamento_quantidade VARCHAR(80),
    banners_gradil_estacionamento VARCHAR(20),
    banners_gradil_estacionamento_qtd VARCHAR(80),
    antena_alarme_entrada VARCHAR(20),
    antena_alarme_entrada_qtd VARCHAR(80),
    placas_cancela_estacionamento VARCHAR(20),
    placas_cancela_estacionamento_qtd VARCHAR(80),
    quantidade_checkouts VARCHAR(80),
    reguas_check_stand VARCHAR(20),
    reguas_check_stand_qtd VARCHAR(80),
    quantidade_pontas_gondola VARCHAR(80),
    quantidade_portas_pontas_refrigeradas VARCHAR(80),
    quantidade_orelhas_ponta_gondola VARCHAR(80),
    ilhas_loja VARCHAR(20),
    ilhas_loja_qtd VARCHAR(80),
    localizacao_principais_ilhas TEXT,
    quantidade_display_chao VARCHAR(80),
    backlights VARCHAR(20),
    backlights_qtd VARCHAR(80),
    exclusividade_ponta_backlight VARCHAR(20),
    banners_interior VARCHAR(20),
    banners_interior_detalhes TEXT,
    retail_media VARCHAR(20),
    retail_media_ativos TEXT,
    televisores_internos VARCHAR(20),
    televisores_internos_qtd VARCHAR(80),
    elevadores VARCHAR(20),
    elevadores_qtd VARCHAR(80),
    radio_interna VARCHAR(20),
    escadas_esteiras_rolantes VARCHAR(20),
    escadas_esteiras_rolantes_qtd TEXT,
    quantidade_freezers VARCHAR(80),
    quantidade_pontas_ilha_congelados VARCHAR(80),
    displays_laterais_lfc VARCHAR(20),
    displays_laterais_lfc_qtd VARCHAR(80),
    walk_in_cooler VARCHAR(20),
    walk_in_cooler_portas VARCHAR(80),
    quantidade_portas_bebidas VARCHAR(80),
    quantidade_portas_laticinios VARCHAR(80),
    quantidade_portas_congelados_refrigerados VARCHAR(80),
    quantidade_carrinhos VARCHAR(80),
    quantidade_cestas VARCHAR(80),
    quantidade_check_stands VARCHAR(80),
    pontas_gondola_refrigeradas_detalhes TEXT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_diagnostico_loja_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL,
    CONSTRAINT fk_diagnostico_loja_dados_loja FOREIGN KEY (dados_loja_id) REFERENCES dados_loja(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS fornecedores_import_lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    arquivo_path VARCHAR(500),
    mime_type VARCHAR(120),
    tamanho_bytes INT,
    total_linhas INT NOT NULL DEFAULT 0,
    status VARCHAR(40) NOT NULL DEFAULT 'em_analise',
    mensagem TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fornecedores_import_lotes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS fornecedores_importacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    lote_id INT,
    codigo_fornecedor VARCHAR(120),
    razao_social VARCHAR(255),
    nome_fantasia VARCHAR(255),
    cnpj VARCHAR(20),
    email VARCHAR(255),
    telefone VARCHAR(40),
    categoria VARCHAR(180),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fornecedores_importacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL,
    CONSTRAINT fk_fornecedores_importacao_lote FOREIGN KEY (lote_id) REFERENCES fornecedores_import_lotes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ativos_digitais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nome_ativo VARCHAR(255) NOT NULL,
    valor_custo DECIMAL(12,2),
    valor_venda DECIMAL(12,2),
    loja_digital VARCHAR(180),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ativos_digitais_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS alcadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nome VARCHAR(255) NOT NULL,
    alcada_percentual DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alcadas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE SET NULL
);
