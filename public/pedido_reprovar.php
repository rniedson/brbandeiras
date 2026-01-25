<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

$id = $_GET['id'] ?? null;
$motivo = $_GET['motivo'] ?? 'Não informado';

if (!$id) {
    $_SESSION['erro'] = 'Pedido não informado';
    header('Location: aprovacoes.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar pedido
    $stmt = $pdo->prepare("
        SELECT * FROM pedidos 
        WHERE id = ? AND status = 'orcamento'
    ");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado ou já foi processado');
    }
    
    // Verificar permissão
    if ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] != $_SESSION['user_id']) {
        throw new Exception('Você não tem permissão para reprovar este pedido');
    }
    
    // Verificar se gestor pode reprovar
    if ($_SESSION['user_perfil'] !== 'gestor' && $_SESSION['user_perfil'] !== 'vendedor') {
        throw new Exception('Você não tem permissão para reprovar pedidos');
    }
    
    // Atualizar status para cancelado
    $stmt = $pdo->prepare("
        UPDATE pedidos 
        SET status = 'cancelado', 
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    
    // Registrar no histórico
    $stmt = $pdo->prepare("
        INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id) 
        VALUES (?, 'cancelado', ?, ?)
    ");
    $stmt->execute([
        $id, 
        "Pedido reprovado. Motivo: " . $motivo,
        $_SESSION['user_id']
    ]);
    
    // Log do sistema
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'reprovar_pedido', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Reprovou pedido #{$pedido['numero']}. Motivo: $motivo",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Pedido #{$pedido['numero']} reprovado";
    header('Location: aprovacoes.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro ao reprovar pedido: ' . $e->getMessage();
    header('Location: aprovacoes.php');
}
