<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificar se a meta existe e está ativa
    $stmt = $pdo->prepare("SELECT id, status FROM metas_vendas WHERE id = ?");
    $stmt->execute([$id]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$meta) {
        echo json_encode(['success' => false, 'message' => 'Meta não encontrada']);
        exit;
    }
    
    if ($meta['status'] !== 'ativa') {
        echo json_encode(['success' => false, 'message' => 'Apenas metas ativas podem ser canceladas']);
        exit;
    }
    
    // Cancelar meta
    $stmt = $pdo->prepare("UPDATE metas_vendas SET status = 'cancelada', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Meta cancelada com sucesso']);
    
} catch (PDOException $e) {
    error_log("Erro ao cancelar meta: " . $e->getMessage());
    
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'A tabela metas_vendas não existe no banco de dados.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar meta: ' . $e->getMessage()]);
    }
}
