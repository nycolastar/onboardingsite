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

        $stmt = $pdo->prepare('SELECT * FROM tb_lojas WHERE tradebook_id = :tradebook_id ORDER BY nome');
        $stmt->execute([':tradebook_id' => $tradebookId]);

        tbRespond(200, true, 'ok', ['lojas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // Cria a loja e, se for fisica, clona o template do tradebook pra tb_loja_ativos.
    // Lojas digitais nascem vazias: os ativos digitais sao adicionados via add_ativo.
    if ($action === 'create') {
        $tradebookId = (int) ($_POST['tradebook_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? 'fisica';

        if ($tradebookId <= 0 || $nome === '') {
            tbRespond(422, false, 'Informe o tradebook e o nome da loja.');
        }

        if (!in_array($tipo, ['fisica', 'digital'], true)) {
            tbRespond(422, false, 'Tipo de loja invalido.');
        }

        $pdo->beginTransaction();

        $faturamento = isset($_POST['faturamento']) && $_POST['faturamento'] !== '' ? (float) $_POST['faturamento'] : null;
        $ticketMedio = isset($_POST['ticket_medio']) && $_POST['ticket_medio'] !== '' ? (float) $_POST['ticket_medio'] : null;
        $regiao = trim($_POST['regiao'] ?? '') ?: null;

        $stmt = $pdo->prepare(
            'INSERT INTO tb_lojas (tradebook_id, nome, endereco, tipo, faturamento, regiao, ticket_medio) 
             VALUES (:tradebook_id, :nome, :endereco, :tipo, :faturamento, :regiao, :ticket_medio)'
        );
        $stmt->execute([
            ':tradebook_id' => $tradebookId,
            ':nome' => $nome,
            ':endereco' => trim($_POST['endereco'] ?? '') ?: null,
            ':tipo' => $tipo,
            ':faturamento' => $faturamento,
            ':regiao' => $regiao,
            ':ticket_medio' => $ticketMedio
        ]);
        $lojaId = (int) $pdo->lastInsertId();
        if ($tipo === 'fisica') {
            $cloneStmt = $pdo->prepare(
                'INSERT INTO tb_loja_ativos (loja_id, ativo_id, quantidade, disponivel_para_trade)
                 SELECT :loja_id, ativo_id, quantidade_padrao, 0
                 FROM tb_template_itens
                 WHERE tradebook_id = :tradebook_id'
            );
            $cloneStmt->execute([
                ':loja_id' => $lojaId,
                ':tradebook_id' => $tradebookId
            ]);
        }

        $pdo->commit();

        tbRespond(200, true, 'Loja criada.', ['id' => $lojaId]);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('DELETE FROM tb_lojas WHERE id = :id');
        $stmt->execute([':id' => $id]);

        tbRespond(200, true, 'Loja removida.');
    }

    if ($action === 'list_ativos') {
        $lojaId = (int) ($_GET['loja_id'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT la.id, la.ativo_id, la.quantidade, la.disponivel_para_trade,
                    a.nome_ativo, a.valor_custo, a.valor_venda, a.valor_total, a.medidas, a.tipo_midia
             FROM tb_loja_ativos la
             JOIN ativos_catalogo a ON a.id = la.ativo_id
             WHERE la.loja_id = :loja_id
             ORDER BY a.tipo_midia, a.nome_ativo'
        );
        $stmt->execute([':loja_id' => $lojaId]);

        tbRespond(200, true, 'ok', ['itens' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // Adiciona um ativo que nao veio do template (ex: item extra fisico, ou qualquer ativo digital).
    if ($action === 'add_ativo') {
        $lojaId = (int) ($_POST['loja_id'] ?? 0);
        $ativoId = (int) ($_POST['ativo_id'] ?? 0);

        if ($lojaId <= 0 || $ativoId <= 0) {
            tbRespond(422, false, 'Informe a loja e o ativo.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tb_loja_ativos (loja_id, ativo_id, quantidade, disponivel_para_trade)
             VALUES (:loja_id, :ativo_id, :quantidade, :disponivel)
             ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)'
        );
        $stmt->execute([
            ':loja_id' => $lojaId,
            ':ativo_id' => $ativoId,
            ':quantidade' => trim($_POST['quantidade'] ?? '') ?: '-',
            ':disponivel' => (int) ($_POST['disponivel_para_trade'] ?? 0)
        ]);

        tbRespond(200, true, 'Ativo adicionado a loja.');
    }

    if ($action === 'update_ativo') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare(
            'UPDATE tb_loja_ativos SET quantidade = :quantidade, disponivel_para_trade = :disponivel WHERE id = :id'
        );
        $stmt->execute([
            ':quantidade' => trim($_POST['quantidade'] ?? '') ?: '-',
            ':disponivel' => (int) ($_POST['disponivel_para_trade'] ?? 0),
            ':id' => $id
        ]);

        tbRespond(200, true, 'Ativo atualizado.');
    }

    if ($action === 'remove_ativo') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('DELETE FROM tb_loja_ativos WHERE id = :id');
        $stmt->execute([':id' => $id]);

        tbRespond(200, true, 'Ativo removido da loja.');
    }

    tbRespond(400, false, 'Acao invalida.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    tbRespond(500, false, 'Erro: ' . $error->getMessage());
}
