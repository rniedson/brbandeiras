<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();
requireRole(['gestor']);

// Resposta JSON
header('Content-Type: application/json');

try {
    // Aceitar apenas POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Obter dados do JSON
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? intval($data['id']) : null;
    
    if (!$id) {
        throw new Exception('ID do usuário não fornecido');
    }
    
    // Não permitir excluir o próprio usuário
    if ($id == $_SESSION['user_id']) {
        throw new Exception('Você não pode excluir sua própria conta');
    }
    
    // Verificar se o usuário existe
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        throw new Exception('Usuário não encontrado');
    }
    
    // Verificar se o usuário tem pedidos associados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE vendedor_id = ?");
    $stmt->execute([$id]);
    $total_pedidos = $stmt->fetchColumn();
    
    if ($total_pedidos > 0) {
        throw new Exception('Não é possível excluir usuário com pedidos associados');
    }
    
    // Verificar se é arte-finalista com trabalhos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedido_arte WHERE arte_finalista_id = ?");
    $stmt->execute([$id]);
    $total_artes = $stmt->fetchColumn();
    
    if ($total_artes > 0) {
        throw new Exception('Não é possível excluir arte-finalista com trabalhos associados');
    }
    
    // Excluir usuário
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log da ação
    registrarLog('usuario_excluido', "Excluiu usuário: {$usuario['nome']} ({$usuario['email']})");
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuário excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}