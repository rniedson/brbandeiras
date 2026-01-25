<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$id = $_POST['id'] ?? null;
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$ativo = isset($_POST['ativo']) ? 1 : 0;

if (!$id || !$nome) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    // Verificar se existe
    $stmt = $pdo->prepare("SELECT nome FROM categorias_produtos WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
        exit;
    }
    
    // Verificar nome duplicado
    if ($nome !== $categoria['nome']) {
        $stmt = $pdo->prepare("SELECT id FROM categorias_produtos WHERE nome = ? AND id != ?");
        $stmt->execute([$nome, $id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Já existe outra categoria com este nome']);
            exit;
        }
    }
    
    // Atualizar
    $stmt = $pdo->prepare("
        UPDATE categorias_produtos 
        SET nome = ?, descricao = ?, ativo = ?
        WHERE id = ?
    ");
    $stmt->execute([$nome, $descricao ?: null, $ativo, $id]);
    
    // Log
    $mudancas = [];
    if ($categoria['nome'] != $nome) $mudancas[] = "nome";
    if ($ativo != 1) $mudancas[] = "desativada";
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'atualizar_categoria_produto', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Atualizou categoria: $nome" . (!empty($mudancas) ? " (" . implode(", ", $mudancas) . ")" : ""),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}