-- Modulo Tradebook
-- Nao estava presente em nenhum dos dois zips enviados (provavelmente ja rodado
-- direto no banco em algum momento). Reconstruido aqui com IF NOT EXISTS em tudo,
-- entao rodar de novo num banco que ja tem essas tabelas nao faz nada (seguro).

USE onboarding_system;

CREATE TABLE IF NOT EXISTS ativos_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_ativo VARCHAR(255) NOT NULL,
    valor_custo DECIMAL(12,2) NOT NULL DEFAULT 0,
    valor_venda DECIMAL(12,2) NOT NULL DEFAULT 0,
    valor_total DECIMAL(12,2) GENERATED ALWAYS AS (valor_custo + valor_venda) STORED,
    medidas VARCHAR(255),
    tipo_midia ENUM('fisico','grafico','midias','material_fornecedor','digital') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tb_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    foto_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tb_tradebooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    public_token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tb_tradebooks_cliente FOREIGN KEY (cliente_id) REFERENCES tb_clientes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tb_template_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tradebook_id INT NOT NULL,
    ativo_id INT NOT NULL,
    quantidade_padrao VARCHAR(20) NOT NULL DEFAULT '-',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tb_template_item (tradebook_id, ativo_id),
    CONSTRAINT fk_tb_template_tradebook FOREIGN KEY (tradebook_id) REFERENCES tb_tradebooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tb_template_ativo FOREIGN KEY (ativo_id) REFERENCES ativos_catalogo(id) ON DELETE CASCADE
);

-- faturamento/regiao/ticket_medio ja incluidos aqui (sao os campos que o
-- tb_lojas.php atual usa). Quem ja tem a tabela sem eles, roda o
-- migration_tb_lojas_extra_campos.sql em vez de recriar.
CREATE TABLE IF NOT EXISTS tb_lojas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tradebook_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    endereco VARCHAR(255),
    tipo ENUM('fisica','digital') NOT NULL DEFAULT 'fisica',
    faturamento DECIMAL(14,2) NULL,
    regiao VARCHAR(120) NULL,
    ticket_medio DECIMAL(14,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tb_lojas_tradebook FOREIGN KEY (tradebook_id) REFERENCES tb_tradebooks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tb_loja_ativos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loja_id INT NOT NULL,
    ativo_id INT NOT NULL,
    quantidade VARCHAR(20) NOT NULL DEFAULT '-',
    disponivel_para_trade TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tb_loja_ativo (loja_id, ativo_id),
    CONSTRAINT fk_tb_loja_ativos_loja FOREIGN KEY (loja_id) REFERENCES tb_lojas(id) ON DELETE CASCADE,
    CONSTRAINT fk_tb_loja_ativos_ativo FOREIGN KEY (ativo_id) REFERENCES ativos_catalogo(id) ON DELETE CASCADE
);
