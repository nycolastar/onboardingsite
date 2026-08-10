<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/tradebook_auth.php';

tbRequireAccess();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'list') {
        $tipo = $_GET['tipo_midia'] ?? '';

        if ($tipo !== '') {
            $stmt = $pdo->prepare('SELECT * FROM ativos_catalogo WHERE tipo_midia = :tipo ORDER BY nome_ativo');
            $stmt->execute([':tipo' => $tipo]);
        } else {
            $stmt = $pdo->query('SELECT * FROM ativos_catalogo ORDER BY tipo_midia, nome_ativo');
        }

        tbRespond(200, true, 'ok', ['ativos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'create') {
        $nome = trim($_POST['nome_ativo'] ?? '');
        $tipoMidia = $_POST['tipo_midia'] ?? '';
        $tiposValidos = ['fisico', 'grafico', 'midias', 'material_fornecedor', 'digital'];

        if ($nome === '') {
            tbRespond(422, false, 'Informe o nome do ativo.');
        }

        if (!in_array($tipoMidia, $tiposValidos, true)) {
            tbRespond(422, false, 'Tipo de midia invalido.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO ativos_catalogo (nome_ativo, valor_custo, valor_venda, medidas, tipo_midia)
             VALUES (:nome, :custo, :venda, :medidas, :tipo)'
        );
        $stmt->execute([
            ':nome' => $nome,
            ':custo' => (float) ($_POST['valor_custo'] ?? 0),
            ':venda' => (float) ($_POST['valor_venda'] ?? 0),
            ':medidas' => trim($_POST['medidas'] ?? '') ?: null,
            ':tipo' => $tipoMidia
        ]);

        tbRespond(200, true, 'Ativo cadastrado.', ['id' => (int) $pdo->lastInsertId()]);
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            tbRespond(422, false, 'Ativo invalido.');
        }

        $stmt = $pdo->prepare(
            'UPDATE ativos_catalogo
             SET nome_ativo = :nome, valor_custo = :custo, valor_venda = :venda, medidas = :medidas, tipo_midia = :tipo
             WHERE id = :id'
        );
        $stmt->execute([
            ':nome' => trim($_POST['nome_ativo'] ?? ''),
            ':custo' => (float) ($_POST['valor_custo'] ?? 0),
            ':venda' => (float) ($_POST['valor_venda'] ?? 0),
            ':medidas' => trim($_POST['medidas'] ?? '') ?: null,
            ':tipo' => $_POST['tipo_midia'] ?? '',
            ':id' => $id
        ]);

        tbRespond(200, true, 'Ativo atualizado.');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('DELETE FROM ativos_catalogo WHERE id = :id');
        $stmt->execute([':id' => $id]);

        tbRespond(200, true, 'Ativo removido.');
    }

    tbRespond(400, false, 'Acao invalida.');
} catch (Throwable $error) {
    tbRespond(500, false, 'Erro: ' . $error->getMessage());
}
