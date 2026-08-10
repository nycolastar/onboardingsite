<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once '../config/db.php';
require_once '../config/tradebook_auth.php';

tbRequireAccess();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'list_clientes') {
        $stmt = $pdo->query('SELECT * FROM tb_clientes ORDER BY nome');
        tbRespond(200, true, 'ok', ['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'create_cliente') {
        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            tbRespond(422, false, 'Informe o nome do cliente.');
        }

        // Lógica de upload de imagem (Ctrl+V)
        $fotoPath = trim($_POST['foto_path'] ?? ''); // Fallback para URL em texto
        
        if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/clientes/';
            
            // Cria a pasta se não existir
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = strtolower(pathinfo($_FILES['foto_file']['name'], PATHINFO_EXTENSION));
            // Garante que é uma imagem
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $fileName = uniqid('cliente_') . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_file']['tmp_name'], $uploadDir . $fileName)) {
                    $fotoPath = 'backend/uploads/clientes/' . $fileName; // Caminho pro banco
                }
            }
        }

        $stmt = $pdo->prepare('INSERT INTO tb_clientes (nome, foto_path) VALUES (:nome, :foto)');
        $stmt->execute([
            ':nome' => $nome,
            ':foto' => $fotoPath ?: null
        ]);

        tbRespond(200, true, 'Cliente cadastrado.', ['id' => (int) $pdo->lastInsertId()]);
    }

    if ($action === 'list_tradebooks') {
        $clienteId = (int) ($_GET['cliente_id'] ?? 0);

        if ($clienteId > 0) {
            $stmt = $pdo->prepare(
                'SELECT tb.*, c.nome AS cliente_nome, c.foto_path AS cliente_foto
                 FROM tb_tradebooks tb
                 JOIN tb_clientes c ON c.id = tb.cliente_id
                 WHERE tb.cliente_id = :cliente_id
                 ORDER BY tb.created_at DESC'
            );
            $stmt->execute([':cliente_id' => $clienteId]);
        } else {
            $stmt = $pdo->query(
                'SELECT tb.*, c.nome AS cliente_nome, c.foto_path AS cliente_foto
                 FROM tb_tradebooks tb
                 JOIN tb_clientes c ON c.id = tb.cliente_id
                 ORDER BY tb.created_at DESC'
            );
        }

        tbRespond(200, true, 'ok', ['tradebooks' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'create_tradebook') {
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');

        if ($clienteId <= 0 || $nome === '') {
            tbRespond(422, false, 'Informe o cliente e o nome do tradebook.');
        }

        $token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare(
            'INSERT INTO tb_tradebooks (cliente_id, nome, public_token) VALUES (:cliente_id, :nome, :token)'
        );
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':nome' => $nome,
            ':token' => $token
        ]);

        tbRespond(200, true, 'Tradebook criado.', [
            'id' => (int) $pdo->lastInsertId(),
            'public_token' => $token
        ]);
    }

    if ($action === 'get_tradebook') {
        $id = (int) ($_GET['id'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT tb.*, c.nome AS cliente_nome, c.foto_path AS cliente_foto
             FROM tb_tradebooks tb
             JOIN tb_clientes c ON c.id = tb.cliente_id
             WHERE tb.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $tradebook = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tradebook) {
            tbRespond(404, false, 'Tradebook nao encontrado.');
        }

        tbRespond(200, true, 'ok', ['tradebook' => $tradebook]);
    }

    tbRespond(400, false, 'Acao invalida.');
} catch (Throwable $error) {
    tbRespond(500, false, 'Erro: ' . $error->getMessage());
}
