<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros
$periodo = $_GET['periodo'] ?? 'mes';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$vendedor_id = $_GET['vendedor_id'] ?? '';
$cliente_id = $_GET['cliente_id'] ?? '';
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

// Query base para pedidos
$where = ["DATE(p.created_at) BETWEEN ? AND ?"];
$params = [$data_inicio, $data_fim];

// Filtro por vendedor
if ($vendedor_id) {
    $where[] = "p.vendedor_id = ?";
    $params[] = $vendedor_id;
}

// Filtro por cliente
if ($cliente_id) {
    $where[] = "p.cliente_id = ?";
    $params[] = $cliente_id;
}

// Filtro por status
if ($status) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Buscar pedidos
try {
    $sql = "
        SELECT 
            p.*,
            u.nome as vendedor_nome,
            c.nome as cliente_nome,
            c.email as cliente_email,
            COUNT(pi.id) as total_itens
        FROM pedidos p
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE $whereClause
        GROUP BY p.id, u.nome, c.nome, c.email
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params_query = array_merge($params, [intval($limite), intval($offset)]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_query);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total
    $sql_count = "
        SELECT COUNT(*) 
        FROM pedidos p
        WHERE $whereClause
    ";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $limite);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar pedidos: " . $e->getMessage());
    $pedidos = [];
    $total_registros = 0;
    $total_paginas = 0;
}

// Estatísticas gerais
try {
    $sql_stats = "
        SELECT 
            COUNT(*) as total_pedidos,
            COUNT(*) FILTER (WHERE status = 'entregue') as pedidos_entregues,
            COUNT(*) FILTER (WHERE status = 'orcamento') as pedidos_orcamento,
            COUNT(*) FILTER (WHERE status = 'novo') as pedidos_novos,
            COUNT(*) FILTER (WHERE status = 'producao') as pedidos_producao,
            COALESCE(SUM(valor_final) FILTER (WHERE status = 'entregue'), 0) as valor_total_entregue,
            COALESCE(SUM(valor_final), 0) as valor_total_geral,
            COALESCE(AVG(valor_final) FILTER (WHERE status = 'entregue'), 0) as ticket_medio
        FROM pedidos p
        WHERE $whereClause
    ";
    
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $stats = [
        'total_pedidos' => 0,
        'pedidos_entregues' => 0,
        'pedidos_orcamento' => 0,
        'pedidos_novos' => 0,
        'pedidos_producao' => 0,
        'valor_total_entregue' => 0,
        'valor_total_geral' => 0,
        'ticket_medio' => 0
    ];
}

// Vendas por vendedor
try {
    $sql_vendedor = "
        SELECT 
            u.id,
            u.nome as vendedor_nome,
            COUNT(p.id) as total_pedidos,
            COALESCE(SUM(p.valor_final) FILTER (WHERE p.status = 'entregue'), 0) as valor_total
        FROM usuarios u
        LEFT JOIN pedidos p ON p.vendedor_id = u.id AND DATE(p.created_at) BETWEEN ? AND ?
        WHERE u.perfil = 'vendedor'
        GROUP BY u.id, u.nome
        HAVING COUNT(p.id) > 0
        ORDER BY valor_total DESC
    ";
    
    $stmt_vendedor = $pdo->prepare($sql_vendedor);
    $stmt_vendedor->execute([$data_inicio, $data_fim]);
    $vendas_por_vendedor = $stmt_vendedor->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $vendas_por_vendedor = [];
}

// Vendas por status
try {
    $sql_status = "
        SELECT 
            status,
            COUNT(*) as total,
            COALESCE(SUM(valor_final), 0) as valor_total
        FROM pedidos
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
        ORDER BY total DESC
    ";
    
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->execute([$data_inicio, $data_fim]);
    $vendas_por_status = $stmt_status->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $vendas_por_status = [];
}

// Buscar vendedores para filtro
try {
    $vendedores = $pdo->query("
        SELECT id, nome 
        FROM usuarios
        WHERE perfil = 'vendedor'
        ORDER BY nome
    ")->fetchAll();
} catch (PDOException $e) {
    $vendedores = [];
}

// Buscar clientes para filtro
try {
    $clientes = $pdo->query("
        SELECT DISTINCT c.id, c.nome 
        FROM clientes c
        INNER JOIN pedidos p ON p.cliente_id = c.id
        ORDER BY c.nome
        LIMIT 100
    ")->fetchAll();
} catch (PDOException $e) {
    $clientes = [];
}

$titulo = 'Relatório de Vendas';
$breadcrumb = [
    ['label' => 'Financeiro', 'url' => 'financeiro_dashboard.php'],
    ['label' => 'Relatórios', 'url' => '#'],
    ['label' => 'Vendas']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Relatório de Vendas</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Análise detalhada das vendas realizadas</p>
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
        <div class="text-sm text-gray-500 dark:text-gray-400">Total de Pedidos</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['total_pedidos'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">No período selecionado</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Valor Total Entregue</div>
        <div class="text-2xl font-bold text-green-600"><?= formatarMoeda($stats['valor_total_entregue'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?= number_format($stats['pedidos_entregues'] ?? 0) ?> pedidos entregues
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Valor Total Geral</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatarMoeda($stats['valor_total_geral'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Todos os status</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Ticket Médio</div>
        <div class="text-2xl font-bold text-purple-600"><?= formatarMoeda($stats['ticket_medio'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Por pedido entregue</div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendedor</label>
                <select name="vendedor_id" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <?php foreach ($vendedores as $vendedor): ?>
                    <option value="<?= $vendedor['id'] ?>" <?= $vendedor_id == $vendedor['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vendedor['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cliente</label>
                <select name="cliente_id" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= $cliente['id'] ?>" <?= $cliente_id == $cliente['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cliente['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="novo" <?= $status === 'novo' ? 'selected' : '' ?>>Novo</option>
                    <option value="orcamento" <?= $status === 'orcamento' ? 'selected' : '' ?>>Orçamento</option>
                    <option value="producao" <?= $status === 'producao' ? 'selected' : '' ?>>Produção</option>
                    <option value="pronto" <?= $status === 'pronto' ? 'selected' : '' ?>>Pronto</option>
                    <option value="entregue" <?= $status === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                    <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            
            <div class="lg:col-span-6 flex items-end gap-2">
                <button type="submit" class="px-6 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600">
                    Filtrar
                </button>
                <a href="relatorio_vendas.php" class="px-6 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Resumo por Vendedor -->
<?php if (!empty($vendas_por_vendedor)): ?>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Vendas por Vendedor</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vendedor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pedidos</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">% do Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php 
                $total_vendedores = array_sum(array_column($vendas_por_vendedor, 'valor_total'));
                foreach ($vendas_por_vendedor as $vendedor): 
                    $percentual = $total_vendedores > 0 
                        ? ($vendedor['valor_total'] / $total_vendedores) * 100 
                        : 0;
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            <?= htmlspecialchars($vendedor['vendedor_nome']) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900 dark:text-white"><?= $vendedor['total_pedidos'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            <?= formatarMoeda($vendedor['valor_total']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" 
                                     style="width: <?= min(100, $percentual) ?>%"></div>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-400 w-12 text-right">
                                <?= number_format($percentual, 1) ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Resumo por Status -->
<?php if (!empty($vendas_por_status)): ?>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Vendas por Status</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quantidade</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php 
                $status_labels = [
                    'novo' => 'Novo',
                    'orcamento' => 'Orçamento',
                    'producao' => 'Produção',
                    'pronto' => 'Pronto',
                    'entregue' => 'Entregue',
                    'cancelado' => 'Cancelado'
                ];
                foreach ($vendas_por_status as $status_data): 
                    $status_nome = $status_labels[$status_data['status']] ?? ucfirst($status_data['status']);
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            <?= htmlspecialchars($status_nome) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900 dark:text-white"><?= $status_data['total'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            <?= formatarMoeda($status_data['valor_total']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Lista Detalhada de Pedidos -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Pedidos Detalhados</h2>
    </div>
    
    <?php if (empty($pedidos)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhum pedido encontrado</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Não há pedidos no período selecionado.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pedido</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vendedor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Itens</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($pedidos as $pedido): ?>
                <?php
                $status_pedido = $pedido['status'] ?? 'novo';
                $status_labels = [
                    'novo' => ['label' => 'Novo', 'color' => 'blue'],
                    'orcamento' => ['label' => 'Orçamento', 'color' => 'yellow'],
                    'producao' => ['label' => 'Produção', 'color' => 'purple'],
                    'pronto' => ['label' => 'Pronto', 'color' => 'indigo'],
                    'entregue' => ['label' => 'Entregue', 'color' => 'green'],
                    'cancelado' => ['label' => 'Cancelado', 'color' => 'red']
                ];
                $status_info = $status_labels[$status_pedido] ?? ['label' => ucfirst($status_pedido), 'color' => 'gray'];
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            #<?= htmlspecialchars($pedido['numero'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') ?>
                        </div>
                        <?php if (!empty($pedido['cliente_email'])): ?>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <?= htmlspecialchars($pedido['cliente_email']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($pedido['vendedor_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= formatarData($pedido['created_at'] ?? '') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="text-sm text-gray-900 dark:text-white">
                            <?= $pedido['total_itens'] ?? 0 ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                            <?= formatarMoeda($pedido['valor_final'] ?? 0) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $status_info['color'] ?>-100 dark:bg-<?= $status_info['color'] ?>-900 text-<?= $status_info['color'] ?>-800 dark:text-<?= $status_info['color'] ?>-200">
                            <?= $status_info['label'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="pedidos/detalhes/pedido_detalhes_gestor.php?id=<?= $pedido['id'] ?>" 
                           class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                            Ver Detalhes
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
    <div class="px-6 py-4 border-t dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Mostrando <?= count($pedidos) ?> de <?= $total_registros ?> pedidos
        </div>
        <div class="flex gap-2">
            <?php
            $query_params = [];
            if ($periodo) $query_params['periodo'] = $periodo;
            if ($data_inicio) $query_params['data_inicio'] = $data_inicio;
            if ($data_fim) $query_params['data_fim'] = $data_fim;
            if ($vendedor_id) $query_params['vendedor_id'] = $vendedor_id;
            if ($cliente_id) $query_params['cliente_id'] = $cliente_id;
            if ($status) $query_params['status'] = $status;
            $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
            ?>
            
            <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                Anterior
            </a>
            <?php endif; ?>
            
            <?php
            $inicio = max(1, $pagina - 2);
            $fim = min($total_paginas, $pagina + 2);
            
            if ($inicio > 1): ?>
                <a href="?pagina=1<?= $query_string ?>" class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">1</a>
                <?php if ($inicio > 2): ?>
                    <span class="px-3 py-2 text-gray-400">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                <?php if ($i == $pagina): ?>
                    <span class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">
                        <?= $i ?>
                    </span>
                <?php else: ?>
                    <a href="?pagina=<?= $i ?><?= $query_string ?>" 
                       class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($fim < $total_paginas): ?>
                <?php if ($fim < $total_paginas - 1): ?>
                    <span class="px-3 py-2 text-gray-400">...</span>
                <?php endif; ?>
                <a href="?pagina=<?= $total_paginas ?><?= $query_string ?>" 
                   class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                    <?= $total_paginas ?>
                </a>
            <?php endif; ?>
            
            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                Próxima
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function exportarRelatorio() {
    // Criar URL com os parâmetros atuais
    const params = new URLSearchParams(window.location.search);
    params.set('exportar', 'csv');
    
    // Redirecionar para exportar
    window.location.href = 'relatorio_vendas_exportar.php?' + params.toString();
}
</script>

<?php include '../views/layouts/_footer.php'; ?>
