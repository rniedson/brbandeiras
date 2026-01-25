<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireLogin();

$id = $_GET['id'] ?? null;
$motivo = $_GET['motivo'] ?? 'Não informado';

if (!$id) {
    $_SESSION['erro'] = 'Orçamento não informado';
    header('Location: orcamentos.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar orçamento
    $stmt = $pdo->prepare("
        SELECT * FROM pedidos 
        WHERE id = ? AND status = 'orcamento'
    ");
    $stmt->execute([$id]);
    $orcamento = $stmt->fetch();
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado ou já foi processado');
    }
    
    // Verificar permissão
    if ($_SESSION['user_perfil'] === 'vendedor' && $orcamento['vendedor_id'] != $_SESSION['user_id']) {
        throw new Exception('Você não tem permissão para reprovar este orçamento');
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
        "Orçamento reprovado. Motivo: " . $motivo,
        $_SESSION['user_id']
    ]);
    
    // Log do sistema
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'reprovar_orcamento', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Reprovou orçamento #{$orcamento['numero']}. Motivo: $motivo",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Orçamento #{$orcamento['numero']} reprovado";
    header('Location: orcamentos.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro ao reprovar orçamento: ' . $e->getMessage();
    header('Location: orcamento_detalhes.php?id=' . $id);
}