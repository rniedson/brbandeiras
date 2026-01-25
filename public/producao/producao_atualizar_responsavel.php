<?php
// ============================================================================ 
// producao_atualizar_responsavel.php - Atualizar responsável da produção
// ============================================================================
?>
<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

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
    $responsavelId = !empty($input['responsavel_id']) ? (int)$input['responsavel_id'] : null;
    
    $pdo->beginTransaction();
    
    // Verificar se o pedido está em produção
    $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?");
    $stmt->execute([$pedidoId]);
    $status = $stmt->fetchColumn();
    
    if ($status !== 'producao') {
        throw new Exception('Pedido não está em produção');
    }
    
    // Buscar nome do responsável se foi informado
    $responsavelNome = null;
    if ($responsavelId) {
        $stmt = $pdo->prepare("
            SELECT nome FROM usuarios 
            WHERE id = ? AND perfil IN ('producao', 'gestor') AND ativo = true
        ");
        $stmt->execute([$responsavelId]);
        $responsavelNome = $stmt->fetchColumn();
        
        if (!$responsavelNome) {
            throw new Exception('Responsável inválido');
        }
    }
    
    // Atualizar responsável no pedido
    $stmt = $pdo->prepare("
        UPDATE pedidos 
        SET responsavel_producao_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$responsavelId, $pedidoId]);
    
    // Atualizar responsável no checklist
    $stmt = $pdo->prepare("
        UPDATE producao_checklist 
        SET responsavel_id = ?, updated_at = NOW()
        WHERE pedido_id = ?
    ");
    $stmt->execute([$responsavelId, $pedidoId]);
    
    // Log
    $acao = $responsavelId ? "Responsável atribuído: $responsavelNome" : "Responsável removido";
    registrarLog('atribuir_responsavel_producao', "Pedido #$pedidoId: $acao");
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'responsavel_id' => $responsavelId,
        'responsavel_nome' => $responsavelNome
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
?>