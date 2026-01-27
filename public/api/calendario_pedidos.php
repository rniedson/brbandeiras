<?php
/**
 * API de Pedidos para Calendário com Cache
 * 
 * Retorna pedidos organizados por data para renderização do calendário
 * Cache de 2 minutos (dados mudam com frequência moderada)
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

// Parâmetros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');

// Validar
if ($mes < 1) { $mes = 12; $ano--; }
if ($mes > 12) { $mes = 1; $ano++; }
if ($ano < 2020) $ano = 2020;
if ($ano > 2030) $ano = 2030;

// Chave de cache
$cacheKey = Cache::key('calendario_pedidos', $mes, $ano);

// Buscar do cache ou banco (cache de 2 minutos)
$data = Cache::remember($cacheKey, 120, function() use ($pdo, $mes, $ano) {
    // Calcular primeiro e último dia do mês
    $primeiroDia = sprintf('%04d-%02d-01', $ano, $mes);
    $ultimoDia = date('Y-m-t', strtotime($primeiroDia));
    
    // =====================================================
    // BUSCAR PEDIDOS POR DATA DE CRIAÇÃO (COMERCIAL)
    // =====================================================
    $stmtCriacao = $pdo->prepare("
        SELECT 
            p.id, p.numero, p.status, p.urgente, p.valor_total,
            p.prazo_entrega, p.created_at,
            c.nome as cliente_nome,
            'criacao' as tipo_evento,
            DATE(p.created_at) as data_evento
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE DATE(p.created_at) BETWEEN :inicio AND :fim
        AND p.status NOT IN ('cancelado')
        ORDER BY p.created_at, p.urgente DESC
    ");
    $stmtCriacao->execute([
        'inicio' => $primeiroDia,
        'fim' => $ultimoDia
    ]);
    $pedidosCriacao = $stmtCriacao->fetchAll(PDO::FETCH_ASSOC);
    
    // =====================================================
    // BUSCAR PEDIDOS POR DATA DE ENTREGA (EXPEDIÇÃO)
    // =====================================================
    $stmtEntrega = $pdo->prepare("
        SELECT 
            p.id, p.numero, p.status, p.urgente, p.valor_total,
            p.prazo_entrega, p.created_at,
            c.nome as cliente_nome,
            'entrega' as tipo_evento,
            DATE(p.prazo_entrega) as data_evento
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.prazo_entrega BETWEEN :inicio AND :fim
        AND p.status NOT IN ('cancelado')
        ORDER BY p.prazo_entrega, p.urgente DESC
    ");
    $stmtEntrega->execute([
        'inicio' => $primeiroDia,
        'fim' => $ultimoDia
    ]);
    $pedidosEntrega = $stmtEntrega->fetchAll(PDO::FETCH_ASSOC);
    
    // =====================================================
    // ORGANIZAR POR DATA COM TIPO DE EVENTO
    // =====================================================
    $pedidosPorData = [];
    $totalEventos = 0;
    
    // Adicionar eventos de criação (COMERCIAL)
    foreach ($pedidosCriacao as $pedido) {
        $dataEvento = $pedido['data_evento'];
        if (!isset($pedidosPorData[$dataEvento])) {
            $pedidosPorData[$dataEvento] = [];
        }
        $pedidosPorData[$dataEvento][] = $pedido;
        $totalEventos++;
    }
    
    // Adicionar eventos de entrega (EXPEDIÇÃO)
    foreach ($pedidosEntrega as $pedido) {
        $dataEvento = $pedido['data_evento'];
        if (!isset($pedidosPorData[$dataEvento])) {
            $pedidosPorData[$dataEvento] = [];
        }
        $pedidosPorData[$dataEvento][] = $pedido;
        $totalEventos++;
    }
    
    // Ordenar eventos dentro de cada dia (criação primeiro, depois entrega)
    foreach ($pedidosPorData as $data => &$eventos) {
        usort($eventos, function($a, $b) {
            // Criação vem antes de entrega
            if ($a['tipo_evento'] !== $b['tipo_evento']) {
                return $a['tipo_evento'] === 'criacao' ? -1 : 1;
            }
            // Urgentes primeiro
            if ($a['urgente'] != $b['urgente']) {
                return $b['urgente'] - $a['urgente'];
            }
            // Por número do pedido
            return strcmp($a['numero'], $b['numero']);
        });
    }
    unset($eventos);
    
    // Informações do mês
    $primeiroDiaObj = mktime(0, 0, 0, $mes, 1, $ano);
    
    return [
        'mes' => $mes,
        'ano' => $ano,
        'dias_no_mes' => (int)date('t', $primeiroDiaObj),
        'dia_semana_inicio' => (int)date('w', $primeiroDiaObj),
        'pedidos_por_data' => $pedidosPorData,
        'total_pedidos' => $totalEventos,
        'total_criacao' => count($pedidosCriacao),
        'total_entrega' => count($pedidosEntrega),
        'cached_at' => date('Y-m-d H:i:s')
    ];
});

// Headers de cache para o cliente (2 minutos)
CacheHeaders::shortCache(120);

AjaxResponse::success($data);
