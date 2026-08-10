<?php

// API publica da apresentacao. NAO usa sessao nem PIN de proposito:
// quem tem o link com o token certo, ve. Ninguem mais.

header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';

function tbPublicRespond(int $status, bool $success, string $message, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'view') {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            tbPublicRespond(422, false, 'Link invalido.');
        }

        $stmt = $pdo->prepare(
            'SELECT tb.id, tb.nome, c.nome AS cliente_nome, c.foto_path AS cliente_foto
             FROM tb_tradebooks tb
             JOIN tb_clientes c ON c.id = tb.cliente_id
             WHERE tb.public_token = :token'
        );
        $stmt->execute([':token' => $token]);
        $tradebook = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tradebook) {
            tbPublicRespond(404, false, 'Tradebook nao encontrado.');
        }

        $lojasStmt = $pdo->prepare(
            'SELECT id, nome, endereco, tipo FROM tb_lojas WHERE tradebook_id = :tradebook_id ORDER BY tipo DESC, nome'
        );
        $lojasStmt->execute([':tradebook_id' => $tradebook['id']]);
        $lojas = $lojasStmt->fetchAll(PDO::FETCH_ASSOC);

        $itensStmt = $pdo->prepare(
            'SELECT la.loja_id, a.nome_ativo, a.valor_custo, a.valor_venda, a.valor_total,
                    a.medidas, a.tipo_midia, la.quantidade, la.disponivel_para_trade
             FROM tb_loja_ativos la
             JOIN ativos_catalogo a ON a.id = la.ativo_id
             WHERE la.loja_id = :loja_id
             ORDER BY a.tipo_midia, a.nome_ativo'
        );

        foreach ($lojas as &$loja) {
            $itensStmt->execute([':loja_id' => $loja['id']]);
            $loja['ativos'] = $itensStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($loja);

        tbPublicRespond(200, true, 'ok', [
            'tradebook' => $tradebook,
            'lojas' => $lojas
        ]);
    }

    tbPublicRespond(400, false, 'Acao invalida.');
} catch (Throwable $error) {
    tbPublicRespond(500, false, 'Erro ao carregar o tradebook.');
}
