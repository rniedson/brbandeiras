<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    if (!$nome) {
        throw new Exception('Nome do grupo é obrigatório');
    }

    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id FROM grupos_clientes WHERE nome = ?");
    $stmt->execute([$nome]);
    
    if ($stmt->fetch()) {
        throw new Exception('Já existe um grupo com este nome');
    }
    
    // Inserir novo grupo
    $stmt = $pdo->prepare("
        INSERT INTO grupos_clientes (nome, descricao, ativo) 
        VALUES (?, ?, true)
    ");
    $stmt->execute([$nome, $descricao ?: null]);
    
    $id = $pdo->lastInsertId();
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'criar_grupo_cliente', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Criou grupo: $nome",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode([
        'success' => true, 
        'id' => $id,
        'message' => 'Grupo criado com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log('Erro PDO ao criar grupo: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao salvar no banco de dados'
    ]);
} catch (Exception $e) {
    error_log('Erro ao criar grupo: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
exit;
