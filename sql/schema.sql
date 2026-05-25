CREATE DATABASE IF NOT EXISTS onboarding_system
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE onboarding_system;

DROP TABLE IF EXISTS cronograma_implantacao;

CREATE TABLE IF NOT EXISTS dados_loja (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(180) NOT NULL,
    setor VARCHAR(180),
    departamento VARCHAR(180),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ativos_fisicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_ativo VARCHAR(255) NOT NULL,
    valor_custo DECIMAL(12,2),
    valor_venda DECIMAL(12,2),
    loja VARCHAR(120),
    quantidade VARCHAR(80),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios_internos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    whatsapp VARCHAR(30),
    area VARCHAR(180),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS gerentes_loja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    whatsapp VARCHAR(30),
    loja_responsavel VARCHAR(120),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS industrias (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dados_bancarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_pagamento VARCHAR(120) NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS plantas_loja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pasta_upload VARCHAR(255) NOT NULL,
    link_pasta VARCHAR(500),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ativos_digitais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_ativo VARCHAR(255) NOT NULL,
    valor_custo DECIMAL(12,2),
    valor_venda DECIMAL(12,2),
    loja_digital VARCHAR(180),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS alcadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    alcada_percentual DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS header_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campo VARCHAR(180) NOT NULL,
    descricao TEXT,
    tipo VARCHAR(120),
    manutencao VARCHAR(120),
    fonte VARCHAR(180),
    comentario TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
