<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

// Garantir que sempre retorna JSON
header('Content-Type: application/json');

// Capturar qualquer erro PHP
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    if (!$nome) {
        throw new Exception('Nome da categoria é obrigatório');
    }

    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id FROM categorias_produtos WHERE nome = ?");
    $stmt->execute([$nome]);
    
    if ($stmt->fetch()) {
        throw new Exception('Já existe uma categoria com este nome');
    }
    
    // Inserir nova categoria
    $stmt = $pdo->prepare("
        INSERT INTO categorias_produtos (nome, descricao, ativo) 
        VALUES (?, ?, true)
    ");
    $stmt->execute([$nome, $descricao ?: null]);
    
    $id = $pdo->lastInsertId();
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'criar_categoria_produto', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Criou categoria: $nome",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode([
        'success' => true, 
        'id' => $id,
        'message' => 'Categoria criada com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log('Erro PDO ao criar categoria: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao salvar no banco de dados'
    ]);
} catch (Exception $e) {
    error_log('Erro ao criar categoria: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
exit;