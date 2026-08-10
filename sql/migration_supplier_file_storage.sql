USE onboarding_system;

ALTER TABLE fornecedores_import_lotes ADD COLUMN IF NOT EXISTS arquivo_path VARCHAR(500) NULL AFTER nome_arquivo;
ALTER TABLE fornecedores_import_lotes ADD COLUMN IF NOT EXISTS mime_type VARCHAR(120) NULL AFTER arquivo_path;
ALTER TABLE fornecedores_import_lotes ADD COLUMN IF NOT EXISTS tamanho_bytes INT NULL AFTER mime_type;
