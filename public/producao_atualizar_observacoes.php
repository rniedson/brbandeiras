<?php
// ============================================================================
// producao_atualizar_observacoes.php - Atualizar observações da produção
// ============================================================================
?>
<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

header('Content-Type: application/json');

requireLogin();
requireRole(['producao', 'gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['pedido_id'])) {
        throw new Exception('Dados incompletos');
    }
    
    $pedidoId = (int)$input['pedido_id'];
    $observacoes = trim($input['observacoes'] ?? '');
    
    // Verificar se existe checklist
    $stmt = $pdo->prepare("SELECT id FROM producao_checklist WHERE pedido_id = ?");
    $stmt->execute([$pedidoId]);
    $checklistId = $stmt->fetchColumn();
    
    if (!$checklistId) {
        // Criar checklist se não existir
        $stmt = $pdo->prepare("
            INSERT INTO producao_checklist (pedido_id, responsavel_id, observacoes, iniciado_em)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$pedidoId, $_SESSION['usuario_id'], $observacoes]);
    } else {
        // Atualizar observações
        $stmt = $pdo->prepare("
            UPDATE producao_checklist 
            SET observacoes = ?, updated_at = NOW()
            WHERE pedido_id = ?
        ");
        $stmt->execute([$observacoes, $pedidoId]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>