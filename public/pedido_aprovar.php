<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = $_GET['id'] ?? null;
} else {
    $id = $_POST['id'] ?? null;
}

if (!$id) {
    $_SESSION['erro'] = 'Pedido não informado';
    header('Location: aprovacoes.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar pedido
    $stmt = $pdo->prepare("
        SELECT p.*, c.email as cliente_email, c.nome as cliente_nome
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = ? AND p.status = 'orcamento'
    ");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado ou já foi processado');
    }
    
    // Verificar permissão
    if ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] != $_SESSION['user_id']) {
        throw new Exception('Você não tem permissão para aprovar este pedido');
    }
    
    // Verificar se gestor pode aprovar
    if ($_SESSION['user_perfil'] !== 'gestor' && $_SESSION['user_perfil'] !== 'vendedor') {
        throw new Exception('Você não tem permissão para aprovar pedidos');
    }
    
    // Atualizar status para aprovado
    $stmt = $pdo->prepare("
        UPDATE pedidos 
        SET status = 'aprovado', 
            data_aprovacao = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    
    // Registrar no histórico
    $observacoes = $_POST['observacoes'] ?? 'Pedido aprovado';
    $stmt = $pdo->prepare("
        INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id) 
        VALUES (?, 'aprovado', ?, ?)
    ");
    $stmt->execute([$id, $observacoes, $_SESSION['user_id']]);
    
    // Log do sistema
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'aprovar_pedido', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Aprovou pedido #{$pedido['numero']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Pedido #{$pedido['numero']} aprovado com sucesso!";
    header('Location: pedidos/detalhes/pedido_detalhes_gestor.php?id=' . $id);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro ao aprovar pedido: ' . $e->getMessage();
    header('Location: aprovacoes.php');
}
