<?php
/**
 * API para dados do quiosque (AJAX)
 * Retorna JSON com estatísticas e próximas entregas
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once '../../app/config.php';

try {
    // Estatísticas de pedidos por status - Apenas Arte, Produção e Prontos
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) FILTER (WHERE status = 'arte') as arte,
            COUNT(*) FILTER (WHERE status = 'producao') as producao,
            COUNT(*) FILTER (WHERE status = 'pronto') as pronto
        FROM pedidos
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_map('intval', $stats);
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas do quiosque: " . $e->getMessage());
    $stats = [
        'arte' => 0,
        'producao' => 0,
        'pronto' => 0
    ];
}

// Buscar próximas entregas - Incluir todos os pedidos ativos
try {
    $stmt = $pdo->query("
        SELECT 
            p.numero,
            p.prazo_entrega,
            c.nome as cliente_nome,
            p.urgente,
            p.status,
            p.created_at
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.status NOT IN ('entregue', 'cancelado')
        ORDER BY 
            CASE WHEN p.prazo_entrega IS NOT NULL THEN 0 ELSE 1 END,
            p.prazo_entrega ASC NULLS LAST,
            p.created_at DESC
        LIMIT 20
    ");
    $proximas_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados para JSON
    $entregas_formatadas = [];
    foreach ($proximas_entregas as $entrega) {
        $entregas_formatadas[] = [
            'numero' => $entrega['numero'],
            'cliente_nome' => $entrega['cliente_nome'] ?: 'Cliente não informado',
            'prazo_entrega' => $entrega['prazo_entrega'] ? date('d/m/Y', strtotime($entrega['prazo_entrega'])) : null,
            'prazo_entrega_raw' => $entrega['prazo_entrega'],
            'urgente' => (bool)$entrega['urgente'],
            'status' => $entrega['status'],
            'created_at' => $entrega['created_at'] ? date('d/m/Y', strtotime($entrega['created_at'])) : null
        ];
    }
} catch (Exception $e) {
    error_log("Erro ao buscar próximas entregas: " . $e->getMessage());
    $entregas_formatadas = [];
}

// Retornar JSON
echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'stats' => $stats,
    'entregas' => $entregas_formatadas
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
