USE onboarding_system;

ALTER TABLE usuarios_acesso ADD COLUMN IF NOT EXISTS drive_link VARCHAR(500) NULL AFTER pin;
