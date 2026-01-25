<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireRole(['gestor']);

$produto_id = $_GET['id'] ?? null;
$acao = $_GET['acao'] ?? null;

if (!$produto_id || !in_array($acao, ['ativar', 'desativar'])) {
    $_SESSION['erro'] = 'Parâmetros inválidos';
    header('Location: catalogo.php');
    exit;
}

try {
    // Buscar produto
    $stmt = $pdo->prepare("SELECT nome, ativo FROM produtos_catalogo WHERE id = ?");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch();
    
    if (!$produto) {
        throw new Exception('Produto não encontrado');
    }
    
    $novo_status = $acao === 'ativar' ? 1 : 0;
    
    // Atualizar status
    $stmt = $pdo->prepare("UPDATE produtos_catalogo SET ativo = ? WHERE id = ?");
    $stmt->execute([$novo_status, $produto_id]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'alterar_status_produto', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        ($acao === 'ativar' ? 'Ativou' : 'Desativou') . " produto: {$produto['nome']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $_SESSION['mensagem'] = "Produto " . ($acao === 'ativar' ? 'ativado' : 'desativado') . " com sucesso!";
    
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
}

header('Location: catalogo_produto_detalhes.php?id=' . $produto_id);