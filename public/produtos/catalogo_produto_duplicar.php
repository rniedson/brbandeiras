<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireRole(['gestor']);

$produto_id = $_GET['id'] ?? null;

if (!$produto_id) {
    $_SESSION['erro'] = 'Produto não informado';
    header('Location: catalogo.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar produto original
    $stmt = $pdo->prepare("SELECT * FROM produtos_catalogo WHERE id = ?");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch();
    
    if (!$produto) {
        throw new Exception('Produto não encontrado');
    }
    
    // Gerar novo código
    $novo_codigo = $produto['codigo'] . '-COPIA-' . uniqid();
    $novo_nome = $produto['nome'] . ' (Cópia)';
    
    // Inserir cópia
    $stmt = $pdo->prepare("
        INSERT INTO produtos_catalogo (
            codigo, nome, descricao, categoria_id, preco, preco_promocional,
            unidade_venda, tempo_producao, estoque_disponivel, imagem_principal,
            especificacoes, tags, ativo
        ) 
        SELECT 
            ?, ?, descricao, categoria_id, preco, preco_promocional,
            unidade_venda, tempo_producao, estoque_disponivel, imagem_principal,
            especificacoes, tags, false
        FROM produtos_catalogo 
        WHERE id = ?
    ");
    
    $stmt->execute([$novo_codigo, $novo_nome, $produto_id]);
    $novo_id = $pdo->lastInsertId();
    
    // Copiar imagens adicionais
    $stmt = $pdo->prepare("
        INSERT INTO produtos_imagens (produto_id, caminho, descricao, ordem)
        SELECT ?, caminho, descricao, ordem
        FROM produtos_imagens
        WHERE produto_id = ?
    ");
    $stmt->execute([$novo_id, $produto_id]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'duplicar_produto', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Duplicou produto: {$produto['nome']} → $novo_nome",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Produto duplicado com sucesso! Edite as informações necessárias.";
    header('Location: catalogo_produto_editar.php?id=' . $novo_id);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro ao duplicar produto: ' . $e->getMessage();
    header('Location: catalogo_produto_detalhes.php?id=' . $produto_id);
}