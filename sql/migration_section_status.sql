USE onboarding_system;

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
