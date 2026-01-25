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
    // Buscar grupo
    $stmt = $pdo->prepare("SELECT nome, ativo FROM grupos_clientes WHERE id = ?");
    $stmt->execute([$id]);
    $grupo = $stmt->fetch();
    
    if (!$grupo) {
        echo json_encode(['success' => false, 'message' => 'Grupo não encontrado']);
        exit;
    }
    
    $novo_status = !$grupo['ativo'];
    
    // Atualizar status
    $stmt = $pdo->prepare("UPDATE grupos_clientes SET ativo = ? WHERE id = ?");
    $stmt->execute([$novo_status, $id]);
    
    // Log
    $detalhes = $novo_status ? "Ativou grupo: {$grupo['nome']}" : "Desativou grupo: {$grupo['nome']}";
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'alterar_status_grupo_cliente', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $detalhes,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Status alterado com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
