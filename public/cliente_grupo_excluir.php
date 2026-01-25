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
    // Verificar se há clientes vinculados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE grupo_id = ?");
    $stmt->execute([$id]);
    $total_clientes = $stmt->fetchColumn();
    
    if ($total_clientes > 0) {
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir grupo com clientes vinculados']);
        exit;
    }
    
    // Buscar nome para log
    $stmt = $pdo->prepare("SELECT nome FROM grupos_clientes WHERE id = ?");
    $stmt->execute([$id]);
    $grupo = $stmt->fetch();
    
    if (!$grupo) {
        echo json_encode(['success' => false, 'message' => 'Grupo não encontrado']);
        exit;
    }
    
    // Excluir
    $stmt = $pdo->prepare("DELETE FROM grupos_clientes WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'excluir_grupo_cliente', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Excluiu grupo: {$grupo['nome']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Grupo excluído com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
