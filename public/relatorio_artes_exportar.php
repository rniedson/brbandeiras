<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros (mesmos da página principal)
$periodo = $_GET['periodo'] ?? 'mes';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$arte_finalista_id = $_GET['arte_finalista_id'] ?? '';
$status_arte = $_GET['status_arte'] ?? '';
$pedido_id = $_GET['pedido_id'] ?? '';

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

// Query base para artes (otimizado: sem DATE() para permitir uso de índices)
$where = ["av.created_at >= ?::date AND av.created_at < (?::date + INTERVAL '1 day')"];
$params = [$data_inicio, $data_fim];

if ($arte_finalista_id) {
    $where[] = "av.usuario_id = ?";
    $params[] = $arte_finalista_id;
}

if ($status_arte) {
    $where[] = "av.status = ?";
    $params[] = $status_arte;
}

if ($pedido_id) {
    $where[] = "av.pedido_id = ?";
    $params[] = $pedido_id;
}

$whereClause = implode(' AND ', $where);

// Buscar todas as artes (sem paginação)
try {
    $sql = "
        SELECT 
            av.*,
            p.numero as pedido_numero,
            p.status as pedido_status,
            c.nome as cliente_nome,
            u.nome as arte_finalista_nome
        FROM arte_versoes av
        LEFT JOIN pedidos p ON av.pedido_id = p.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON av.usuario_id = u.id
        WHERE $whereClause
        ORDER BY av.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $artes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao exportar relatório: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao gerar relatório de exportação';
    header('Location: relatorio_artes.php');
    exit;
}

// Configurar headers para download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_artes_' . date('Y-m-d_His') . '.csv"');

// Abrir output stream
$output = fopen('php://output', 'w');

// Adicionar BOM para Excel reconhecer UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalhos
$headers = [
    'Pedido',
    'Cliente',
    'Arte-Finalista',
    'Versão',
    'Arquivo',
    'Status',
    'Data Criação',
    'Data Atualização',
    'Comentário Arte',
    'Comentário Cliente'
];
fputcsv($output, $headers, ';');

// Status labels
$status_labels = [
    'aprovado' => 'Aprovado',
    'reprovado' => 'Reprovado',
    'pendente' => 'Pendente',
    'ajuste' => 'Em Ajuste'
];

// Dados
foreach ($artes as $arte) {
    $status_arte_atual = $arte['status'] ?? 'pendente';
    $status_label = $status_labels[$status_arte_atual] ?? ucfirst($status_arte_atual);
    
    $row = [
        $arte['pedido_numero'] ?? 'N/A',
        $arte['cliente_nome'] ?? 'N/A',
        $arte['arte_finalista_nome'] ?? 'N/A',
        $arte['versao'] ?? 1,
        $arte['arquivo_nome'] ?? 'N/A',
        $status_label,
        formatarData($arte['created_at'] ?? ''),
        formatarData($arte['updated_at'] ?? ''),
        $arte['comentario_arte'] ?? '',
        $arte['comentario_cliente'] ?? ''
    ];
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
