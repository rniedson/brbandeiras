<?php
// ============================================================================
// producao_dados_kanban.php - API para buscar dados atualizados do kanban
// ============================================================================
?>
<?php
require_once '../app/config.php';
require_once '../app/auth.php';

header('Content-Type: application/json');

requireLogin();
requireRole(['producao', 'gestor']);

try {
    // Buscar dados atualizados do kanban
    $stmt = $pdo->prepare("
        SELECT * FROM view_kanban_producao 
        ORDER BY 
            CASE 
                WHEN status = 'aprovado' THEN 1 
                WHEN status = 'producao' THEN 2 
                WHEN status = 'finalizado' THEN 3 
                ELSE 4 
            END,
            urgente DESC,
            prazo_entrega ASC
    ");
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar por status
    $kanban = [
        'aprovado' => [],
        'producao' => [],
        'finalizado' => []
    ];
    
    foreach ($pedidos as $pedido) {
        $status = $pedido['status'];
        if (isset($kanban[$status])) {
            $kanban[$status][] = $pedido;
        }
    }
    
    // Estatísticas
    $stats = [
        'total' => count($pedidos),
        'aprovado' => count($kanban['aprovado']),
        'producao' => count($kanban['producao']),
        'finalizado' => count($kanban['finalizado']),
        'urgentes' => count(array_filter($pedidos, function($p) { 
            return $p['urgente'] && $p['status'] !== 'finalizado'; 
        })),
        'atrasados' => count(array_filter($pedidos, function($p) { 
            return $p['dias_ate_prazo'] < 0 && $p['status'] !== 'finalizado'; 
        }))
    ];
    
    echo json_encode([
        'success' => true,
        'kanban' => $kanban,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>