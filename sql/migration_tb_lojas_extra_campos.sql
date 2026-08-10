USE onboarding_system;

ALTER TABLE tb_lojas
    ADD COLUMN IF NOT EXISTS faturamento DECIMAL(14,2) NULL AFTER tipo,
    ADD COLUMN IF NOT EXISTS regiao VARCHAR(120) NULL AFTER faturamento,
    ADD COLUMN IF NOT EXISTS ticket_medio DECIMAL(14,2) NULL AFTER regiao;
