<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros (mesmos da página principal)
$periodo = $_GET['periodo'] ?? 'mes';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$vendedor_id = $_GET['vendedor_id'] ?? '';
$cliente_id = $_GET['cliente_id'] ?? '';
$status = $_GET['status'] ?? '';

// Ajustar período se selecionado
if ($periodo === 'hoje') {
    $data_inicio = date('Y-m-d');
    $data_fim = date('Y-m-d');
} elseif ($periodo === 'semana') {
    $data_inicio = date('Y-m-d', strtotime('monday this week'));
    $data_fim = date('Y-m-d');
} elseif ($periodo === 'mes') {
    $data_inicio = date('Y-m-01');
    $data_fim = date('Y-m-t');
} elseif ($periodo === 'ano') {
    $data_inicio = date('Y-01-01');
    $data_fim = date('Y-12-31');
}

// Query base para pedidos
$where = ["DATE(p.created_at) BETWEEN ? AND ?"];
$params = [$data_inicio, $data_fim];

if ($vendedor_id) {
    $where[] = "p.vendedor_id = ?";
    $params[] = $vendedor_id;
}

if ($cliente_id) {
    $where[] = "p.cliente_id = ?";
    $params[] = $cliente_id;
}

if ($status) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Buscar todos os pedidos (sem paginação)
try {
    $sql = "
        SELECT 
            p.numero,
            p.created_at,
            c.nome as cliente_nome,
            c.email as cliente_email,
            u.nome as vendedor_nome,
            p.status,
            p.valor_final,
            COUNT(pi.id) as total_itens
        FROM pedidos p
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE $whereClause
        GROUP BY p.id, u.nome, c.nome, c.email
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao exportar relatório: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao gerar relatório de exportação';
    header('Location: relatorio_vendas.php');
    exit;
}

// Configurar headers para download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_vendas_' . date('Y-m-d_His') . '.csv"');

// Abrir output stream
$output = fopen('php://output', 'w');

// Adicionar BOM para Excel reconhecer UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalhos
$headers = [
    'Pedido',
    'Data',
    'Cliente',
    'Email Cliente',
    'Vendedor',
    'Status',
    'Total de Itens',
    'Valor Total'
];
fputcsv($output, $headers, ';');

// Status labels
$status_labels = [
    'novo' => 'Novo',
    'orcamento' => 'Orçamento',
    'producao' => 'Produção',
    'pronto' => 'Pronto',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado'
];

// Dados
foreach ($pedidos as $pedido) {
    $row = [
        $pedido['numero'] ?? 'N/A',
        formatarData($pedido['created_at'] ?? ''),
        $pedido['cliente_nome'] ?? 'N/A',
        $pedido['cliente_email'] ?? '',
        $pedido['vendedor_nome'] ?? 'N/A',
        $status_labels[$pedido['status']] ?? ucfirst($pedido['status'] ?? ''),
        $pedido['total_itens'] ?? 0,
        number_format($pedido['valor_final'] ?? 0, 2, ',', '.')
    ];
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
