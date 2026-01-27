<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros (mesmos da página principal)
$periodo = $_GET['periodo'] ?? 'mes';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$tipo_conta = $_GET['tipo_conta'] ?? '';
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

// Buscar contas a receber
$contas_receber = [];
if ($tipo_conta === 'receber' || $tipo_conta === 'ambos' || empty($tipo_conta)) {
    $where_receber = ["cr.created_at >= ?::date AND cr.created_at < (?::date + INTERVAL '1 day')"];
    $params_receber = [$data_inicio, $data_fim];
    
    if ($status) {
        if ($status === 'vencida') {
            $where_receber[] = "cr.status = 'aberto' AND cr.vencimento < CURRENT_DATE";
        } else {
            $where_receber[] = "cr.status = ?";
            $params_receber[] = $status;
        }
    }
    
    try {
        $sql_receber = "
            SELECT 
                cr.*,
                c.nome as cliente_nome,
                'A Receber' as tipo_conta
            FROM contas_receber cr
            LEFT JOIN clientes c ON cr.cliente_id = c.id
            WHERE " . implode(' AND ', $where_receber) . "
            ORDER BY cr.vencimento ASC
        ";
        
        $stmt_receber = $pdo->prepare($sql_receber);
        $stmt_receber->execute($params_receber);
        $contas_receber = $stmt_receber->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao exportar contas a receber: " . $e->getMessage());
    }
}

// Buscar contas a pagar
$contas_pagar = [];
if ($tipo_conta === 'pagar' || $tipo_conta === 'ambos' || empty($tipo_conta)) {
    $where_pagar = ["cp.created_at >= ?::date AND cp.created_at < (?::date + INTERVAL '1 day')"];
    $params_pagar = [$data_inicio, $data_fim];
    
    if ($status) {
        if ($status === 'vencida') {
            $where_pagar[] = "cp.status = 'aberto' AND cp.vencimento < CURRENT_DATE";
        } else {
            $where_pagar[] = "cp.status = ?";
            $params_pagar[] = $status;
        }
    }
    
    try {
        $sql_pagar = "
            SELECT 
                cp.*,
                f.nome as fornecedor_nome,
                'A Pagar' as tipo_conta
            FROM contas_pagar cp
            LEFT JOIN fornecedores f ON cp.fornecedor_id = f.id
            WHERE " . implode(' AND ', $where_pagar) . "
            ORDER BY cp.vencimento ASC
        ";
        
        $stmt_pagar = $pdo->prepare($sql_pagar);
        $stmt_pagar->execute($params_pagar);
        $contas_pagar = $stmt_pagar->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao exportar contas a pagar: " . $e->getMessage());
    }
}

// Combinar contas
$contas = array_merge($contas_receber, $contas_pagar);
usort($contas, function($a, $b) {
    $data_a = $a['vencimento'] ?? $a['created_at'] ?? '';
    $data_b = $b['vencimento'] ?? $b['created_at'] ?? '';
    return strcmp($data_a, $data_b);
});

// Configurar headers para download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_financeiro_' . date('Y-m-d_His') . '.csv"');

// Abrir output stream
$output = fopen('php://output', 'w');

// Adicionar BOM para Excel reconhecer UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalhos
$headers = [
    'Tipo',
    'Descrição',
    'Número Documento',
    'Cliente/Fornecedor',
    'Vencimento',
    'Valor',
    'Valor Pago',
    'Status',
    'Data Criação'
];
fputcsv($output, $headers, ';');

// Dados
foreach ($contas as $conta) {
    $status_conta = $conta['status'] ?? 'aberto';
    $vencimento = $conta['vencimento'] ?? '';
    $esta_vencida = $vencimento && strtotime($vencimento) < time() && $status_conta === 'aberto';
    
    $status_label = ucfirst($status_conta);
    if ($status_conta === 'pago') {
        $status_label = 'Pago';
    } elseif ($esta_vencida) {
        $status_label = 'Vencida';
    }
    
    $nome_cliente_fornecedor = $conta['tipo_conta'] === 'A Receber' 
        ? ($conta['cliente_nome'] ?? 'N/A')
        : ($conta['fornecedor_nome'] ?? 'N/A');
    
    $row = [
        $conta['tipo_conta'] ?? 'N/A',
        $conta['descricao'] ?? 'N/A',
        $conta['numero_documento'] ?? '',
        $nome_cliente_fornecedor,
        $vencimento ? formatarData($vencimento) : '',
        number_format($conta['valor'] ?? 0, 2, ',', '.'),
        number_format($conta['valor_pago'] ?? 0, 2, ',', '.'),
        $status_label,
        formatarData($conta['created_at'] ?? '')
    ];
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
