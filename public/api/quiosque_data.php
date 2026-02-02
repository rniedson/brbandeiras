<?php
/**
 * API para dados do quiosque (AJAX)
 * Retorna JSON com estatísticas e próximas entregas
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once '../../app/config.php';

// Função para formatar identificação do cliente (primeiro nome + 4 últimos dígitos)
function formatarClienteQuiosque($nome, $telefone) {
    // Pegar primeiro nome
    $primeiroNome = $nome ? explode(' ', trim($nome))[0] : 'Cliente';
    
    // Pegar últimos 4 dígitos do telefone
    $telefoneNumeros = preg_replace('/\D/', '', $telefone ?? '');
    $ultimos4 = strlen($telefoneNumeros) >= 4 ? substr($telefoneNumeros, -4) : '****';
    
    return $primeiroNome . ' - ' . $ultimos4;
}

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

// Função para formatar tempo relativo
function formatarTempoRelativo($dataHora) {
    if (!$dataHora) return '—';
    $agora = new DateTime();
    $data = new DateTime($dataHora);
    $diff = $agora->diff($data);
    
    if ($diff->days > 0) {
        return $diff->days . 'd';
    } elseif ($diff->h > 0) {
        return $diff->h . 'h';
    } else {
        return max(1, $diff->i) . 'min';
    }
}

// Buscar próximas entregas - Incluir todos os pedidos ativos
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.numero,
            p.prazo_entrega,
            c.nome as cliente_nome,
            COALESCE(c.celular, c.whatsapp, c.telefone) as cliente_telefone,
            p.urgente,
            p.status,
            p.created_at,
            p.updated_at,
            u.nome as vendedor_nome,
            ua.nome as arte_finalista_nome,
            (
                SELECT pc.nome 
                FROM pedido_itens pi 
                LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id 
                WHERE pi.pedido_id = p.id 
                ORDER BY pi.id 
                LIMIT 1
            ) as primeiro_produto,
            (
                SELECT paq.caminho 
                FROM pedido_arquivos paq 
                WHERE paq.pedido_id = p.id 
                AND LOWER(paq.nome_arquivo) ~ '\\.(jpg|jpeg|png|gif|webp)$'
                ORDER BY paq.created_at DESC
                LIMIT 1
            ) as imagem_caminho,
            GREATEST(
                p.updated_at,
                COALESCE((
                    SELECT MAX(l.created_at) 
                    FROM logs_sistema l 
                    WHERE l.detalhes LIKE '%Pedido #' || p.numero || '%'
                ), p.updated_at)
            ) as ultima_atualizacao
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios ua ON pa.arte_finalista_id = ua.id
        WHERE p.status NOT IN ('entregue', 'cancelado')
        ORDER BY 
            ultima_atualizacao DESC
        LIMIT 20
    ");
    $proximas_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados para JSON
    $entregas_formatadas = [];
    foreach ($proximas_entregas as $entrega) {
        $entregas_formatadas[] = [
            'numero' => $entrega['numero'],
            'cliente_nome' => formatarClienteQuiosque($entrega['cliente_nome'], $entrega['cliente_telefone']),
            'vendedor_nome' => $entrega['vendedor_nome'],
            'arte_finalista_nome' => $entrega['arte_finalista_nome'],
            'primeiro_produto' => $entrega['primeiro_produto'],
            'imagem_caminho' => $entrega['imagem_caminho'],
            'tempo_atualizado' => formatarTempoRelativo($entrega['ultima_atualizacao']),
            'urgente' => (bool)$entrega['urgente'],
            'status' => $entrega['status']
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
