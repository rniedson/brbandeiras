<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: catalogo.php');
    exit;
}

$produto_id = $_POST['produto_id'] ?? null;

if (!$produto_id) {
    $_SESSION['erro'] = 'ID do produto não informado';
    header('Location: catalogo.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar produto atual
    $stmt = $pdo->prepare("SELECT * FROM produtos_catalogo WHERE id = ?");
    $stmt->execute([$produto_id]);
    $produto_atual = $stmt->fetch();
    
    if (!$produto_atual) {
        throw new Exception('Produto não encontrado');
    }
    
    // Validar dados obrigatórios
    $codigo = trim($_POST['codigo'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $categoria_id = $_POST['categoria_id'] ?? null;
    $preco = floatval($_POST['preco'] ?? 0);
    
    if (!$codigo || !$nome || !$categoria_id || $preco <= 0) {
        throw new Exception('Preencha todos os campos obrigatórios');
    }
    
    // Verificar se código mudou e já existe
    if ($codigo !== $produto_atual['codigo']) {
        $stmt = $pdo->prepare("SELECT id FROM produtos_catalogo WHERE codigo = ? AND id != ?");
        $stmt->execute([$codigo, $produto_id]);
        if ($stmt->fetch()) {
            throw new Exception("Código '$codigo' já está em uso por outro produto");
        }
    }
    
    // Atualizar produto (usando apenas as colunas que existem na tabela)
    $stmt = $pdo->prepare("
        UPDATE produtos_catalogo SET
            codigo = ?,
            nome = ?,
            descricao = ?,
            categoria_id = ?,
            preco = ?,
            ativo = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([
        $codigo,
        $nome,
        $_POST['descricao'] ?: null,
        $categoria_id,
        $preco,
        isset($_POST['ativo']) ? true : false,
        $produto_id
    ]);
    
    // Registrar mudanças no log
    $mudancas = [];
    if (($produto_atual['codigo'] ?? '') != $codigo) $mudancas[] = "código alterado";
    if (($produto_atual['nome'] ?? '') != $nome) $mudancas[] = "nome alterado";
    if (($produto_atual['preco'] ?? 0) != $preco) $mudancas[] = "preço alterado";
    $ativo_atual = $produto_atual['ativo'] ?? false;
    $ativo_novo = isset($_POST['ativo']);
    if ($ativo_atual != $ativo_novo) {
        $mudancas[] = $ativo_novo ? "produto ativado" : "produto desativado";
    }
    
    $detalhes_log = "Atualizou produto: $codigo - $nome";
    if (!empty($mudancas)) {
        $detalhes_log .= " (" . implode(", ", $mudancas) . ")";
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'atualizar_produto_catalogo', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $detalhes_log,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Produto '$nome' atualizado com sucesso!";
    header('Location: catalogo.php');
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    error_log('Erro ao atualizar produto: ' . $e->getMessage());
    
    $_SESSION['erro'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    
    header('Location: catalogo_produto_editar.php?id=' . $produto_id);
    exit;
}