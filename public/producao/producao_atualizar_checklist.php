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
    
    if (!$input || !isset($input['pedido_id']) || !isset($input['campo'])) {
        throw new Exception('Dados incompletos');
    }
    
    $pedidoId = (int)$input['pedido_id'];
    $campo = $input['campo'];
    
    // Validar campo
    $camposPermitidos = ['corte', 'costura', 'acabamento', 'qualidade'];
    if (!in_array($campo, $camposPermitidos)) {
        throw new Exception('Campo inválido');
    }
    
    $pdo->beginTransaction();
    
    // Verificar se existe checklist
    $stmt = $pdo->prepare("SELECT * FROM producao_checklist WHERE pedido_id = ?");
    $stmt->execute([$pedidoId]);
    $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$checklist) {
        // Criar checklist se não existir
        $stmt = $pdo->prepare("
            INSERT INTO producao_checklist (pedido_id, responsavel_id, iniciado_em)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$pedidoId, $_SESSION['usuario_id']]);
        
        $checklist = ['corte' => false, 'costura' => false, 'acabamento' => false, 'qualidade' => false];
    }
    
    // Inverter o valor do campo
    $novoValor = !$checklist[$campo];
    
    // Atualizar checklist
    $stmt = $pdo->prepare("
        UPDATE producao_checklist 
        SET $campo = ?, updated_at = NOW()
        WHERE pedido_id = ?
    ");
    $stmt->execute([$novoValor, $pedidoId]);
    
    // Calcular novo progresso
    $stmt = $pdo->prepare("
        SELECT 
            (CASE WHEN corte THEN 1 ELSE 0 END +
             CASE WHEN costura THEN 1 ELSE 0 END +
             CASE WHEN acabamento THEN 1 ELSE 0 END +
             CASE WHEN qualidade THEN 1 ELSE 0 END) as progresso
        FROM producao_checklist 
        WHERE pedido_id = ?
    ");
    $stmt->execute([$pedidoId]);
    $progresso = $stmt->fetchColumn();
    
    // Log
    registrarLog(
        'atualizar_checklist',
        "Pedido #$pedidoId: item '$campo' " . ($novoValor ? 'marcado' : 'desmarcado')
    );
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'progresso' => (int)$progresso,
        'campo' => $campo,
        'valor' => $novoValor
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