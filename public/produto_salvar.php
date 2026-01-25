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
    
    // Preparar dados
    $dados = [
        'codigo' => trim($_POST['codigo']),
        'nome' => trim($_POST['nome']),
        'descricao' => $_POST['descricao'] ?: null,
        'categoria_id' => $_POST['categoria_id'],
        'unidade_medida' => $_POST['unidade_medida'],
        'estoque_minimo' => $_POST['estoque_minimo'],
        'estoque_maximo' => $_POST['estoque_maximo'],
        'quantidade_atual' => $_POST['quantidade_inicial'] ?: 0,
        'valor_unitario' => $_POST['valor_unitario'],
        'fornecedor_principal' => $_POST['fornecedor_principal'] ?: null,
        'localizacao_corredor' => $_POST['localizacao_corredor'] ?: null,
        'localizacao_prateleira' => $_POST['localizacao_prateleira'] ?: null,
        'localizacao_gaveta' => $_POST['localizacao_gaveta'] ?: null,
        'observacoes' => $_POST['observacoes'] ?: null
    ];
    
    // Validar código único
    $stmt = $pdo->prepare("SELECT id FROM produtos_estoque WHERE codigo = ? AND ativo = true");
    $stmt->execute([$dados['codigo']]);
    if ($stmt->fetch()) {
        $_SESSION['erro'] = 'Código já cadastrado no sistema';
        header('Location: produto_novo.php');
        exit;
    }
    
    // Inserir produto
    $sql = "INSERT INTO produtos_estoque (
                codigo, nome, descricao, categoria_id, unidade_medida,
                estoque_minimo, estoque_maximo, quantidade_atual, valor_unitario,
                fornecedor_principal, localizacao_corredor, localizacao_prateleira,
                localizacao_gaveta, observacoes
            ) VALUES (
                :codigo, :nome, :descricao, :categoria_id, :unidade_medida,
                :estoque_minimo, :estoque_maximo, :quantidade_atual, :valor_unitario,
                :fornecedor_principal, :localizacao_corredor, :localizacao_prateleira,
                :localizacao_gaveta, :observacoes
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dados);
    $produtoId = $pdo->lastInsertId();
    
    // Se houver estoque inicial, criar movimentação
    if ($dados['quantidade_atual'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO movimentacoes_estoque (
                produto_id, tipo, quantidade, observacoes, usuario_id
            ) VALUES (?, 'entrada', ?, 'Estoque inicial', ?)
        ");
        $stmt->execute([$produtoId, $dados['quantidade_atual'], $_SESSION['user_id']]);
    }
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = 'Produto cadastrado com sucesso!';
    header('Location: estoque.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro ao cadastrar produto: ' . $e->getMessage();
    header('Location: produto_novo.php');
}
