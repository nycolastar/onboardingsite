<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/onboarding_sections.php';

$schemas = array_map(fn($section) => $section['columns'], $onboardingSections);

try {
    ensureSectionStatusTable($pdo);

    if (($_SESSION['role'] ?? '') !== 'user' || empty($_SESSION['user']['id'])) {
        respond(401, false, 'Faca login com seu PIN para confirmar a aba.');
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $section = $_POST['section'] ?? '';

    if ($action !== 'finalize') {
        respond(400, false, 'Acao invalida.');
    }

    if (!isset($schemas[$section])) {
        respond(400, false, 'Aba de cadastro invalida.');
    }

    $userId = (int) $_SESSION['user']['id'];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$section} WHERE usuario_id = :usuario_id");
    $countStmt->execute([':usuario_id' => $userId]);

    if ((int) $countStmt->fetchColumn() === 0) {
        respond(422, false, 'Envie pelo menos um registro antes de confirmar a aba.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO onboarding_section_status (usuario_id, section_key, finalized_at)
         VALUES (:usuario_id, :section_key, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE finalized_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':usuario_id' => $userId,
        ':section_key' => $section
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Aba finalizada com sucesso.',
        'status' => [
            'section' => $section,
            'finalized_at' => findFinalizedAt($pdo, $userId, $section)
        ]
    ]);
} catch (Throwable $error) {
    respond(500, false, 'Erro ao confirmar: ' . $error->getMessage());
}

function ensureSectionStatusTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS onboarding_section_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            section_key VARCHAR(80) NOT NULL,
            finalized_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_onboarding_section_status (usuario_id, section_key),
            CONSTRAINT fk_onboarding_section_status_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_acesso(id) ON DELETE CASCADE
        )'
    );
}

function findFinalizedAt(PDO $pdo, int $userId, string $section): ?string
{
    $stmt = $pdo->prepare('SELECT finalized_at FROM onboarding_section_status WHERE usuario_id = :usuario_id AND section_key = :section_key');
    $stmt->execute([
        ':usuario_id' => $userId,
        ':section_key' => $section
    ]);

    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}

function respond(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}
