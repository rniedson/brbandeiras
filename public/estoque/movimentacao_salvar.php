<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireRole(['producao', 'gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: estoque.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    $tipo = $_POST['tipo'];
    $produtos = $_POST['produtos'] ?? [];
    $pedidoId = $_POST['pedido_id'] ?: null;
    $observacoes = $_POST['observacoes'];
    $dataMovimentacao = $_POST['data_movimentacao'] ?: date('Y-m-d H:i:s');
    $documentoReferencia = $_POST['documento_referencia'] ?: null;
    
    foreach ($produtos as $item) {
        if (empty($item['produto_id'])) continue;
        
        $produtoId = $item['produto_id'];
        $quantidade = floatval($item['quantidade']);
        
        // Buscar produto atual
        $stmt = $pdo->prepare("SELECT * FROM produtos_estoque WHERE id = ?");
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch();
        
        if (!$produto) {
            throw new Exception('Produto não encontrado: ID ' . $produtoId);
        }
        
        $quantidadeAnterior = $produto['quantidade_atual'];
        
        // Calcular nova quantidade
        if ($tipo === 'entrada') {
            $quantidadePosterior = $quantidadeAnterior + $quantidade;
        } elseif ($tipo === 'saida') {
            if ($quantidade > $quantidadeAnterior) {
                throw new Exception("Estoque insuficiente para {$produto['nome']}");
            }
            $quantidadePosterior = $quantidadeAnterior - $quantidade;
        } else { // ajuste
            $quantidadePosterior = $quantidade;
            $quantidade = $quantidade - $quantidadeAnterior;
        }
        
        // Atualizar estoque
        $stmt = $pdo->prepare("UPDATE produtos_estoque SET quantidade_atual = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$quantidadePosterior, $produtoId]);
        
        // Registrar movimentação
        $stmt = $pdo->prepare("
            INSERT INTO movimentacoes_estoque (
                produto_id, tipo, quantidade, quantidade_anterior, 
                quantidade_posterior, pedido_id, observacoes, 
                documento_referencia, usuario_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $produtoId,
            $tipo,
            abs($quantidade),
            $quantidadeAnterior,
            $quantidadePosterior,
            $pedidoId,
            $observacoes,
            $documentoReferencia,
            $_SESSION['user_id'],
            $dataMovimentacao
        ]);
    }
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = 'Movimentação registrada com sucesso!';
    header('Location: estoque.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro na movimentação: ' . $e->getMessage();
    header('Location: movimentacao_nova.php');
}
