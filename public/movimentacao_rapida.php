<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['producao', 'gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: estoque.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    $produtoId = $_POST['produto_id'];
    $tipo = $_POST['tipo'];
    $quantidade = floatval($_POST['quantidade']);
    $observacoes = $_POST['observacoes'];
    
    // Buscar produto
    $stmt = $pdo->prepare("SELECT * FROM produtos_estoque WHERE id = ?");
    $stmt->execute([$produtoId]);
    $produto = $stmt->fetch();
    
    if (!$produto) {
        throw new Exception('Produto não encontrado');
    }
    
    $quantidadeAnterior = $produto['quantidade_atual'];
    
    // Calcular nova quantidade
    if ($tipo === 'entrada') {
        $quantidadePosterior = $quantidadeAnterior + $quantidade;
    } elseif ($tipo === 'saida') {
        if ($quantidade > $quantidadeAnterior) {
            throw new Exception('Quantidade indisponível em estoque');
        }
        $quantidadePosterior = $quantidadeAnterior - $quantidade;
    } else { // ajuste
        $quantidadePosterior = $quantidade;
        $quantidade = $quantidade - $quantidadeAnterior; // diferença para registro
    }
    
    // Atualizar estoque
    $stmt = $pdo->prepare("UPDATE produtos_estoque SET quantidade_atual = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$quantidadePosterior, $produtoId]);
    
    // Registrar movimentação
    $stmt = $pdo->prepare("
        INSERT INTO movimentacoes_estoque (
            produto_id, tipo, quantidade, quantidade_anterior, 
            quantidade_posterior, observacoes, usuario_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $produtoId,
        $tipo,
        abs($quantidade),
        $quantidadeAnterior,
        $quantidadePosterior,
        $observacoes,
        $_SESSION['user_id']
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = 'Movimentação registrada com sucesso!';
    header('Location: estoque.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro na movimentação: ' . $e->getMessage();
    header('Location: estoque.php');
}
