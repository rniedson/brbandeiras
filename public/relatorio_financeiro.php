<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros
$periodo = $_GET['periodo'] ?? 'mes';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$tipo_conta = $_GET['tipo_conta'] ?? ''; // receber, pagar, ambos
$status = $_GET['status'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = 50;
$offset = ($pagina - 1) * $limite;

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

// Estatísticas de Contas a Receber
try {
    $sql_receber = "
        SELECT 
            COUNT(*) FILTER (WHERE status = 'aberto') as total_abertas,
            COUNT(*) FILTER (WHERE status = 'pago') as total_pagas,
            COUNT(*) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto') as vencidas,
            COUNT(*) FILTER (WHERE DATE(vencimento) BETWEEN ? AND ? AND status = 'aberto') as vencer_periodo,
            COALESCE(SUM(valor) FILTER (WHERE status = 'aberto'), 0) as valor_total_aberto,
            COALESCE(SUM(valor) FILTER (WHERE status = 'pago'), 0) as valor_total_pago,
            COALESCE(SUM(valor) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto'), 0) as valor_vencido,
            COALESCE(SUM(valor) FILTER (WHERE DATE(vencimento) BETWEEN ? AND ? AND status = 'aberto'), 0) as valor_vencer_periodo,
            COALESCE(SUM(valor) FILTER (WHERE DATE(created_at) BETWEEN ? AND ?), 0) as valor_criado_periodo
        FROM contas_receber
    ";
    
    $stmt_receber = $pdo->prepare($sql_receber);
    $stmt_receber->execute([$data_inicio, $data_fim, $data_inicio, $data_fim, $data_inicio, $data_fim]);
    $stats_receber = $stmt_receber->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats_receber = [
        'total_abertas' => 0,
        'total_pagas' => 0,
        'vencidas' => 0,
        'vencer_periodo' => 0,
        'valor_total_aberto' => 0,
        'valor_total_pago' => 0,
        'valor_vencido' => 0,
        'valor_vencer_periodo' => 0,
        'valor_criado_periodo' => 0
    ];
}

// Estatísticas de Contas a Pagar
try {
    $sql_pagar = "
        SELECT 
            COUNT(*) FILTER (WHERE status = 'aberto') as total_abertas,
            COUNT(*) FILTER (WHERE status = 'pago') as total_pagas,
            COUNT(*) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto') as vencidas,
            COUNT(*) FILTER (WHERE DATE(vencimento) BETWEEN ? AND ? AND status = 'aberto') as vencer_periodo,
            COALESCE(SUM(valor) FILTER (WHERE status = 'aberto'), 0) as valor_total_aberto,
            COALESCE(SUM(valor) FILTER (WHERE status = 'pago'), 0) as valor_total_pago,
            COALESCE(SUM(valor) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto'), 0) as valor_vencido,
            COALESCE(SUM(valor) FILTER (WHERE DATE(vencimento) BETWEEN ? AND ? AND status = 'aberto'), 0) as valor_vencer_periodo,
            COALESCE(SUM(valor) FILTER (WHERE DATE(created_at) BETWEEN ? AND ?), 0) as valor_criado_periodo
        FROM contas_pagar
    ";
    
    $stmt_pagar = $pdo->prepare($sql_pagar);
    $stmt_pagar->execute([$data_inicio, $data_fim, $data_inicio, $data_fim, $data_inicio, $data_fim]);
    $stats_pagar = $stmt_pagar->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats_pagar = [
        'total_abertas' => 0,
        'total_pagas' => 0,
        'vencidas' => 0,
        'vencer_periodo' => 0,
        'valor_total_aberto' => 0,
        'valor_total_pago' => 0,
        'valor_vencido' => 0,
        'valor_vencer_periodo' => 0,
        'valor_criado_periodo' => 0
    ];
}

// Receitas do período (pedidos entregues)
try {
    $sql_receitas = "
        SELECT 
            COALESCE(SUM(valor_final), 0) as total_receitas,
            COUNT(*) as total_pedidos
        FROM pedidos
        WHERE status = 'entregue'
        AND DATE(created_at) BETWEEN ? AND ?
    ";
    
    $stmt_receitas = $pdo->prepare($sql_receitas);
    $stmt_receitas->execute([$data_inicio, $data_fim]);
    $stats_receitas = $stmt_receitas->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats_receitas = ['total_receitas' => 0, 'total_pedidos' => 0];
}

// Fluxo de caixa
$fluxo_caixa = ($stats_receitas['total_receitas'] ?? 0) + ($stats_receber['valor_total_pago'] ?? 0) - ($stats_pagar['valor_total_pago'] ?? 0);

// Buscar contas para listagem
$where = [];
$params = [];

if ($tipo_conta === 'receber' || $tipo_conta === 'ambos' || empty($tipo_conta)) {
    // Contas a receber
    $where_receber = ["DATE(cr.created_at) BETWEEN ? AND ?"];
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
        $sql_receber_list = "
            SELECT 
                cr.*,
                c.nome as cliente_nome,
                'receber' as tipo
            FROM contas_receber cr
            LEFT JOIN clientes c ON cr.cliente_id = c.id
            WHERE " . implode(' AND ', $where_receber) . "
            ORDER BY cr.vencimento ASC
            LIMIT ? OFFSET ?
        ";
        
        $params_receber_query = array_merge($params_receber, [intval($limite), intval($offset)]);
        $stmt_receber_list = $pdo->prepare($sql_receber_list);
        $stmt_receber_list->execute($params_receber_query);
        $contas_receber = $stmt_receber_list->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $contas_receber = [];
    }
} else {
    $contas_receber = [];
}

if ($tipo_conta === 'pagar' || $tipo_conta === 'ambos' || empty($tipo_conta)) {
    // Contas a pagar
    $where_pagar = ["DATE(cp.created_at) BETWEEN ? AND ?"];
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
        $sql_pagar_list = "
            SELECT 
                cp.*,
                f.nome as fornecedor_nome,
                'pagar' as tipo
            FROM contas_pagar cp
            LEFT JOIN fornecedores f ON cp.fornecedor_id = f.id
            WHERE " . implode(' AND ', $where_pagar) . "
            ORDER BY cp.vencimento ASC
            LIMIT ? OFFSET ?
        ";
        
        $params_pagar_query = array_merge($params_pagar, [intval($limite), intval($offset)]);
        $stmt_pagar_list = $pdo->prepare($sql_pagar_list);
        $stmt_pagar_list->execute($params_pagar_query);
        $contas_pagar = $stmt_pagar_list->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $contas_pagar = [];
    }
} else {
    $contas_pagar = [];
}

// Combinar contas para exibição
$contas = array_merge($contas_receber ?? [], $contas_pagar ?? []);
usort($contas, function($a, $b) {
    $data_a = $a['vencimento'] ?? $a['created_at'] ?? '';
    $data_b = $b['vencimento'] ?? $b['created_at'] ?? '';
    return strcmp($data_a, $data_b);
});

$titulo = 'Relatório Financeiro';
$breadcrumb = [
    ['label' => 'Financeiro', 'url' => 'financeiro_dashboard.php'],
    ['label' => 'Relatórios', 'url' => '#'],
    ['label' => 'Financeiro']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Relatório Financeiro</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Análise detalhada das finanças da empresa</p>
        </div>
        <button onclick="exportarRelatorio()" 
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-download mr-2"></i>Exportar
        </button>
    </div>
</div>

<!-- Estatísticas Principais -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Receitas (Pedidos)</div>
        <div class="text-2xl font-bold text-green-600"><?= formatarMoeda($stats_receitas['total_receitas'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?= number_format($stats_receitas['total_pedidos'] ?? 0) ?> pedidos entregues
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">A Receber</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatarMoeda($stats_receber['valor_total_aberto'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?= number_format($stats_receber['total_abertas'] ?? 0) ?> contas abertas
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">A Pagar</div>
        <div class="text-2xl font-bold text-red-600"><?= formatarMoeda($stats_pagar['valor_total_aberto'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?= number_format($stats_pagar['total_abertas'] ?? 0) ?> contas abertas
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Fluxo de Caixa</div>
        <div class="text-2xl font-bold <?= $fluxo_caixa >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            <?= formatarMoeda($fluxo_caixa) ?>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Receitas - Despesas</div>
    </div>
</div>

<!-- Estatísticas Detalhadas -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Contas a Receber -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Contas a Receber</h2>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Aberto</span>
                    <span class="text-lg font-semibold text-gray-800 dark:text-white">
                        <?= formatarMoeda($stats_receber['valor_total_aberto'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Pago (Período)</span>
                    <span class="text-lg font-semibold text-green-600">
                        <?= formatarMoeda($stats_receber['valor_total_pago'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Vencidas</span>
                    <span class="text-lg font-semibold text-red-600">
                        <?= formatarMoeda($stats_receber['valor_vencido'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">A Vencer (Período)</span>
                    <span class="text-lg font-semibold text-yellow-600">
                        <?= formatarMoeda($stats_receber['valor_vencer_periodo'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center pt-4 border-t dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-800 dark:text-white">Criado no Período</span>
                    <span class="text-lg font-bold text-blue-600">
                        <?= formatarMoeda($stats_receber['valor_criado_periodo'] ?? 0) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contas a Pagar -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Contas a Pagar</h2>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Aberto</span>
                    <span class="text-lg font-semibold text-gray-800 dark:text-white">
                        <?= formatarMoeda($stats_pagar['valor_total_aberto'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Pago (Período)</span>
                    <span class="text-lg font-semibold text-green-600">
                        <?= formatarMoeda($stats_pagar['valor_total_pago'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Vencidas</span>
                    <span class="text-lg font-semibold text-red-600">
                        <?= formatarMoeda($stats_pagar['valor_vencido'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">A Vencer (Período)</span>
                    <span class="text-lg font-semibold text-yellow-600">
                        <?= formatarMoeda($stats_pagar['valor_vencer_periodo'] ?? 0) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center pt-4 border-t dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-800 dark:text-white">Criado no Período</span>
                    <span class="text-lg font-bold text-red-600">
                        <?= formatarMoeda($stats_pagar['valor_criado_periodo'] ?? 0) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Período Rápido</label>
                <select name="periodo" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                    <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                    <option value="ano" <?= $periodo === 'ano' ? 'selected' : '' ?>>Este Ano</option>
                    <option value="custom" <?= $periodo === 'custom' ? 'selected' : '' ?>>Personalizado</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data Início</label>
                <input type="date" name="data_inicio" value="<?= $data_inicio ?>"
                       class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data Fim</label>
                <input type="date" name="data_fim" value="<?= $data_fim ?>"
                       class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipo de Conta</label>
                <select name="tipo_conta" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="ambos" <?= $tipo_conta === 'ambos' || empty($tipo_conta) ? 'selected' : '' ?>>Ambos</option>
                    <option value="receber" <?= $tipo_conta === 'receber' ? 'selected' : '' ?>>A Receber</option>
                    <option value="pagar" <?= $tipo_conta === 'pagar' ? 'selected' : '' ?>>A Pagar</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="aberto" <?= $status === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                    <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="vencida" <?= $status === 'vencida' ? 'selected' : '' ?>>Vencida</option>
                </select>
            </div>
            
            <div class="lg:col-span-5 flex items-end gap-2">
                <button type="submit" class="px-6 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600">
                    Filtrar
                </button>
                <a href="relatorio_financeiro.php" class="px-6 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Contas -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Contas Detalhadas</h2>
    </div>
    
    <?php if (empty($contas)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhuma conta encontrada</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Não há contas no período selecionado.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descrição</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente/Fornecedor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vencimento</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pago</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($contas as $conta): ?>
                <?php
                $tipo = $conta['tipo'] ?? '';
                $status_conta = $conta['status'] ?? 'aberto';
                $vencimento = $conta['vencimento'] ?? '';
                $esta_vencida = $vencimento && strtotime($vencimento) < time() && $status_conta === 'aberto';
                
                $status_color = 'gray';
                $status_label = ucfirst($status_conta);
                
                if ($status_conta === 'pago') {
                    $status_color = 'green';
                    $status_label = 'Pago';
                } elseif ($esta_vencida) {
                    $status_color = 'red';
                    $status_label = 'Vencida';
                } elseif ($status_conta === 'aberto') {
                    $status_color = 'yellow';
                    $status_label = 'Aberto';
                }
                
                $nome_cliente_fornecedor = $tipo === 'receber' 
                    ? ($conta['cliente_nome'] ?? 'N/A')
                    : ($conta['fornecedor_nome'] ?? 'N/A');
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full <?= $tipo === 'receber' ? 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' ?>">
                            <?= $tipo === 'receber' ? 'A Receber' : 'A Pagar' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            <?= htmlspecialchars($conta['descricao'] ?? 'N/A') ?>
                        </div>
                        <?php if (!empty($conta['numero_documento'])): ?>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Doc: <?= htmlspecialchars($conta['numero_documento']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($nome_cliente_fornecedor) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= $vencimento ? formatarData($vencimento) : '-' ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-semibold <?= $tipo === 'receber' ? 'text-blue-600' : 'text-red-600' ?>">
                            <?= $tipo === 'receber' ? '+' : '-' ?><?= formatarMoeda($conta['valor'] ?? 0) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= formatarMoeda($conta['valor_pago'] ?? 0) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $status_color ?>-100 dark:bg-<?= $status_color ?>-900 text-<?= $status_color ?>-800 dark:text-<?= $status_color ?>-200">
                            <?= $status_label ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function exportarRelatorio() {
    // Criar URL com os parâmetros atuais
    const params = new URLSearchParams(window.location.search);
    params.set('exportar', 'csv');
    
    // Redirecionar para exportar
    window.location.href = 'relatorio_financeiro_exportar.php?' + params.toString();
}
</script>

<?php include '../views/layouts/_footer.php'; ?>
