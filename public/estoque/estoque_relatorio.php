<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireRole(['producao', 'gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$nome = trim($_POST['nome'] ?? '');

if (!$nome) {
    echo json_encode(['success' => false, 'message' => 'Nome da categoria é obrigatório']);
    exit;
}

try {
    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id FROM categorias_estoque WHERE nome = ?");
    $stmt->execute([$nome]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Categoria já existe']);
        exit;
    }
    
    // Inserir nova categoria
    $stmt = $pdo->prepare("INSERT INTO categorias_estoque (nome) VALUES (?)");
    $stmt->execute([$nome]);
    
    $id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'id' => $id,
        'message' => 'Categoria criada com sucesso'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
