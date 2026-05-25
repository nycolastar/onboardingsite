CREATE DATABASE IF NOT EXISTS onboarding_system;

USE onboarding_system;

CREATE TABLE lojas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_loja VARCHAR(255),
    cidade VARCHAR(255),
    uf VARCHAR(2),
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
