USE onboarding_system;

ALTER TABLE dados_loja
    ADD COLUMN IF NOT EXISTS faturamento DECIMAL(14,2) NULL AFTER regiao;

ALTER TABLE categorias
    MODIFY COLUMN categoria VARCHAR(180) NULL;
