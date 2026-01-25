<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

header('Content-Type: application/json');

try {
    $data = $_GET['data'] ?? null;
    
    if (!$data || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Data inválida');
    }
    
    // Buscar entregas do dia
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.numero,
            p.prazo_entrega,
            p.status,
            p.urgente,
            p.valor_final,
            p.observacoes,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            c.endereco as cliente_endereco,
            u.nome as vendedor_nome,
            COUNT(pi.id) as total_itens,
            GROUP_CONCAT(DISTINCT CONCAT(pc.nome, ' (', pi.quantidade, ')') SEPARATOR ', ') as produtos_detalhados,
            CASE 
                WHEN p.prazo_entrega < CURRENT_DATE AND p.status NOT IN ('entregue', 'cancelado') THEN true
                ELSE false
            END as atrasado,
            pa.arte_finalista_id,
            ua.nome as arte_finalista_nome,
            (
                SELECT COUNT(*) 
                FROM pedido_arquivos 
                WHERE pedido_id = p.id
            ) as total_arquivos,
            (
                SELECT MAX(versao) 
                FROM arte_versoes 
                WHERE pedido_id = p.id
            ) as ultima_versao_arte
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios ua ON pa.arte_finalista_id = ua.id
        WHERE 
            DATE(p.prazo_entrega) = ?
            AND p.status != 'cancelado'
        GROUP BY 
            p.id, p.numero, p.prazo_entrega, p.status, p.urgente, 
            p.valor_final, p.observacoes, c.nome, c.telefone, c.email, 
            c.endereco, u.nome, pa.arte_finalista_id, ua.nome
        ORDER BY 
            p.urgente DESC, 
            p.prazo_entrega,
            p.numero
    ");
    
    $stmt->execute([$data]);
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados
    foreach ($entregas as &$entrega) {
        $entrega['valor_final_formatado'] = formatarMoeda($entrega['valor_final']);
        $entrega['prazo_entrega_formatado'] = formatarData($entrega['prazo_entrega']);
        $entrega['status_label'] = [
            'orcamento' => 'Orçamento',
            'arte' => 'Arte-Finalista',
            'producao' => 'Produção',
            'finalizado' => 'Finalizado',
            'entregue' => 'Entregue'
        ][$entrega['status']] ?? $entrega['status'];
        
        // Converter booleanos
        $entrega['urgente'] = (bool)$entrega['urgente'];
        $entrega['atrasado'] = (bool)$entrega['atrasado'];
        
        // Converter números
        $entrega['total_itens'] = (int)$entrega['total_itens'];
        $entrega['total_arquivos'] = (int)$entrega['total_arquivos'];
        $entrega['ultima_versao_arte'] = (int)$entrega['ultima_versao_arte'];
    }
    
    // Estatísticas do dia
    $stats = [
        'total' => count($entregas),
        'urgentes' => count(array_filter($entregas, function($e) { return $e['urgente']; })),
        'atrasados' => count(array_filter($entregas, function($e) { return $e['atrasado']; })),
        'por_status' => []
    ];
    
    foreach ($entregas as $entrega) {
        $status = $entrega['status'];
        if (!isset($stats['por_status'][$status])) {
            $stats['por_status'][$status] = 0;
        }
        $stats['por_status'][$status]++;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'entregas' => $entregas,
        'estatisticas' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}