<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/tradebook_auth.php';

tbRequireAccess();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'list') {
        $tradebookId = (int) ($_GET['tradebook_id'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT ti.id, ti.ativo_id, ti.quantidade_padrao, a.nome_ativo, a.valor_custo, a.valor_venda,
                    a.valor_total, a.medidas, a.tipo_midia
             FROM tb_template_itens ti
             JOIN ativos_catalogo a ON a.id = ti.ativo_id
             WHERE ti.tradebook_id = :tradebook_id
             ORDER BY a.tipo_midia, a.nome_ativo'
        );
        $stmt->execute([':tradebook_id' => $tradebookId]);

        tbRespond(200, true, 'ok', ['itens' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'add_item') {
        $tradebookId = (int) ($_POST['tradebook_id'] ?? 0);
        $ativoId = (int) ($_POST['ativo_id'] ?? 0);
        $quantidade = trim($_POST['quantidade_padrao'] ?? '') ?: '-';

        if ($tradebookId <= 0 || $ativoId <= 0) {
            tbRespond(422, false, 'Informe o tradebook e o ativo.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tb_template_itens (tradebook_id, ativo_id, quantidade_padrao)
             VALUES (:tradebook_id, :ativo_id, :quantidade)
             ON DUPLICATE KEY UPDATE quantidade_padrao = VALUES(quantidade_padrao)'
        );
        $stmt->execute([
            ':tradebook_id' => $tradebookId,
            ':ativo_id' => $ativoId,
            ':quantidade' => $quantidade
        ]);

        tbRespond(200, true, 'Ativo adicionado ao template.');
    }

    if ($action === 'update_item') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('UPDATE tb_template_itens SET quantidade_padrao = :quantidade WHERE id = :id');
        $stmt->execute([
            ':quantidade' => trim($_POST['quantidade_padrao'] ?? '') ?: '-',
            ':id' => $id
        ]);

        tbRespond(200, true, 'Quantidade padrao atualizada.');
    }

    if ($action === 'remove_item') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('DELETE FROM tb_template_itens WHERE id = :id');
        $stmt->execute([':id' => $id]);

        tbRespond(200, true, 'Ativo removido do template.');
    }

    tbRespond(400, false, 'Acao invalida.');
} catch (Throwable $error) {
    tbRespond(500, false, 'Erro: ' . $error->getMessage());
}
