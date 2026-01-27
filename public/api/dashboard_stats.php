<?php
/**
 * API de Estatísticas do Dashboard com Cache
 * 
 * Retorna estatísticas gerais com cache de 1 minuto
 */

define('AJAX_REQUEST', true);

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/cache.php';
require_once '../../app/ajax_helper.php';

AjaxResponse::init();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

$userId = $_SESSION['user_id'];
$perfil = $_SESSION['user_perfil'];

// Chave de cache por usuário e perfil
$cacheKey = Cache::key('dashboard_stats', $perfil, $userId);

// Buscar do cache ou calcular (cache de 60 segundos)
$stats = Cache::remember($cacheKey, 60, function() use ($pdo, $perfil, $userId) {
    $stats = [];
    
    // Total de pedidos por status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as total 
        FROM pedidos 
        WHERE status NOT IN ('entregue', 'cancelado')
        GROUP BY status
    ");
    $statusCounts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = (int)$row['total'];
    }
    $stats['por_status'] = $statusCounts;
    
    // Total geral ativo
    $stats['total_ativos'] = array_sum($statusCounts);
    
    // Pedidos urgentes
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM pedidos 
        WHERE urgente = true AND status NOT IN ('entregue', 'cancelado')
    ");
    $stats['urgentes'] = (int)$stmt->fetchColumn();
    
    // Entregas hoje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pedidos 
        WHERE DATE(prazo_entrega) = CURRENT_DATE 
        AND status NOT IN ('entregue', 'cancelado')
    ");
    $stmt->execute();
    $stats['entregas_hoje'] = (int)$stmt->fetchColumn();
    
    // Entregas atrasadas
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM pedidos 
        WHERE prazo_entrega < CURRENT_DATE 
        AND status NOT IN ('entregue', 'cancelado')
    ");
    $stats['atrasados'] = (int)$stmt->fetchColumn();
    
    // Faturamento do mês
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(valor_total), 0) FROM pedidos 
        WHERE EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
        AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)
        AND status NOT IN ('cancelado')
    ");
    $stats['faturamento_mes'] = (float)$stmt->fetchColumn();
    
    // Timestamp do cache
    $stats['cached_at'] = date('Y-m-d H:i:s');
    
    return $stats;
});

// Headers de cache para o cliente (1 minuto)
CacheHeaders::shortCache(60);

AjaxResponse::success($stats);
