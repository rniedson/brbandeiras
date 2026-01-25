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

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    // Verificar se há produtos vinculados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos_catalogo WHERE categoria_id = ?");
    $stmt->execute([$id]);
    $total_produtos = $stmt->fetchColumn();
    
    if ($total_produtos > 0) {
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir categoria com produtos vinculados']);
        exit;
    }
    
    // Buscar nome para log
    $stmt = $pdo->prepare("SELECT nome FROM categorias_produtos WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
        exit;
    }
    
    // Excluir
    $stmt = $pdo->prepare("DELETE FROM categorias_produtos WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'excluir_categoria_produto', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Excluiu categoria: {$categoria['nome']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}