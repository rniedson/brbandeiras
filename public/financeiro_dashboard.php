<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Período padrão: mês atual
$periodo = $_GET['periodo'] ?? 'mes';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

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
            COUNT(*) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto') as vencidas,
            COUNT(*) FILTER (WHERE vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days' AND status = 'aberto') as vencer_7dias,
            COALESCE(SUM(valor) FILTER (WHERE status = 'aberto'), 0) as valor_total_aberto,
            COALESCE(SUM(valor) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto'), 0) as valor_vencido,
            COALESCE(SUM(valor) FILTER (WHERE DATE(vencimento) BETWEEN ? AND ? AND status = 'aberto'), 0) as valor_periodo
        FROM contas_receber
    ";
    
    $stmt_receber = $pdo->prepare($sql_receber);
    $stmt_receber->execute([$data_inicio, $data_fim]);
    $stats_receber = $stmt_receber->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats_receber = [
        'total_abertas' => 0,
        'vencidas' => 0,
        'vencer_7dias' => 0,
        'valor_total_aberto' => 0,
        'valor_vencido' => 0,
        'valor_periodo' => 0
    ];
}

// Estatísticas de Contas a Pagar
try {
    $sql_pagar = "
        SELECT 
            COUNT(*) FILTER (WHERE status = 'aberto') as total_abertas,
            COUNT(*) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto') as vencidas,
            COUNT(*) FILTER (WHERE vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days' AND status = 'aberto') as vencer_7dias,
            COALESCE(SUM(valor) FILTER (WHERE status = 'aberto'), 0) as valor_total_aberto,
            COALESCE(SUM(valor) FILTER (WHERE vencimento < CURRENT_DATE AND status = 'aberto'), 0) as valor_vencido,
            COALESCE(SUM(valor) FILTER (WHERE DATE(vencimento) BETWEEN ? AND ? AND status = 'aberto'), 0) as valor_periodo
        FROM contas_pagar
    ";
    
    $stmt_pagar = $pdo->prepare($sql_pagar);
    $stmt_pagar->execute([$data_inicio, $data_fim]);
    $stats_pagar = $stmt_pagar->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats_pagar = [
        'total_abertas' => 0,
        'vencidas' => 0,
        'vencer_7dias' => 0,
        'valor_total_aberto' => 0,
        'valor_vencido' => 0,
        'valor_periodo' => 0
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

// Fluxo de caixa (receitas - despesas)
$fluxo_caixa = ($stats_receitas['total_receitas'] ?? 0) - ($stats_pagar['valor_periodo'] ?? 0);

// Contas a receber próximas do vencimento
try {
    $sql_proximas = "
        SELECT cr.*, c.nome as cliente_nome
        FROM contas_receber cr
        LEFT JOIN clientes c ON cr.cliente_id = c.id
        WHERE cr.status = 'aberto'
        AND cr.vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
        ORDER BY cr.vencimento ASC
        LIMIT 10
    ";
    $proximas_receber = $pdo->query($sql_proximas)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $proximas_receber = [];
}

// Contas a pagar próximas do vencimento
try {
    $sql_proximas_pagar = "
        SELECT cp.*, f.nome as fornecedor_nome
        FROM contas_pagar cp
        LEFT JOIN fornecedores f ON cp.fornecedor_id = f.id
        WHERE cp.status = 'aberto'
        AND cp.vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
        ORDER BY cp.vencimento ASC
        LIMIT 10
    ";
    $proximas_pagar = $pdo->query($sql_proximas_pagar)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $proximas_pagar = [];
}

$titulo = 'Dashboard Financeiro';
$breadcrumb = [
    ['label' => 'Financeiro', 'url' => '#'],
    ['label' => 'Dashboard']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Dashboard Financeiro</h1>
            <p class="text-gray-600 mt-2">Visão geral das finanças da empresa</p>
        </div>
        
        <!-- Filtro de Período -->
        <form method="GET" class="flex gap-2">
            <select name="periodo" onchange="this.form.submit()"
                    class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                <option value="ano" <?= $periodo === 'ano' ? 'selected' : '' ?>>Este Ano</option>
                <option value="custom" <?= $periodo === 'custom' ? 'selected' : '' ?>>Personalizado</option>
            </select>
            
            <?php if ($periodo === 'custom'): ?>
            <input type="date" name="data_inicio" value="<?= $data_inicio ?>"
                   class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            <input type="date" name="data_fim" value="<?= $data_fim ?>"
                   class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Filtrar
            </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Cards de Resumo -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Receitas -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Receitas (<?= $periodo === 'mes' ? 'Mês' : ucfirst($periodo) ?>)</p>
                <p class="text-2xl font-bold text-green-600 mt-1">
                    <?= formatarMoeda($stats_receitas['total_receitas'] ?? 0) ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <?= $stats_receitas['total_pedidos'] ?? 0 ?> pedidos entregues
                </p>
            </div>
            <div class="p-3 bg-green-100 rounded-lg">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <!-- Contas a Receber -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">A Receber</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">
                    <?= formatarMoeda($stats_receber['valor_total_aberto'] ?? 0) ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <?= $stats_receber['total_abertas'] ?? 0 ?> contas abertas
                </p>
            </div>
            <div class="p-3 bg-blue-100 rounded-lg">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <!-- Contas a Pagar -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">A Pagar</p>
                <p class="text-2xl font-bold text-red-600 mt-1">
                    <?= formatarMoeda($stats_pagar['valor_total_aberto'] ?? 0) ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    <?= $stats_pagar['total_abertas'] ?? 0 ?> contas abertas
                </p>
            </div>
            <div class="p-3 bg-red-100 rounded-lg">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <!-- Fluxo de Caixa -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Fluxo de Caixa</p>
                <p class="text-2xl font-bold <?= $fluxo_caixa >= 0 ? 'text-green-600' : 'text-red-600' ?> mt-1">
                    <?= formatarMoeda($fluxo_caixa) ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Receitas - Despesas
                </p>
            </div>
            <div class="p-3 <?= $fluxo_caixa >= 0 ? 'bg-green-100' : 'bg-red-100' ?> rounded-lg">
                <svg class="w-8 h-8 <?= $fluxo_caixa >= 0 ? 'text-green-600' : 'text-red-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Alertas -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <!-- Contas Vencidas a Receber -->
    <?php if (($stats_receber['vencidas'] ?? 0) > 0): ?>
    <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-red-800">Contas Vencidas a Receber</h3>
                <p class="text-sm text-red-700 mt-1">
                    <?= $stats_receber['vencidas'] ?> conta(s) vencida(s) totalizando 
                    <strong><?= formatarMoeda($stats_receber['valor_vencido'] ?? 0) ?></strong>
                </p>
                <a href="contas_receber.php?status=vencida" class="text-sm text-red-600 hover:text-red-800 mt-2 inline-block">
                    Ver detalhes →
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Contas Vencidas a Pagar -->
    <?php if (($stats_pagar['vencidas'] ?? 0) > 0): ?>
    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-lg">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-orange-800">Contas Vencidas a Pagar</h3>
                <p class="text-sm text-orange-700 mt-1">
                    <?= $stats_pagar['vencidas'] ?> conta(s) vencida(s) totalizando 
                    <strong><?= formatarMoeda($stats_pagar['valor_vencido'] ?? 0) ?></strong>
                </p>
                <a href="contas_pagar.php?status=vencida" class="text-sm text-orange-600 hover:text-orange-800 mt-2 inline-block">
                    Ver detalhes →
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Próximas Contas -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Próximas Contas a Receber -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Próximas Contas a Receber</h2>
            <p class="text-sm text-gray-500">Vencimento nos próximos 7 dias</p>
        </div>
        <div class="divide-y">
            <?php if (empty($proximas_receber)): ?>
            <div class="p-6 text-center text-gray-500">
                <p>Nenhuma conta a receber nos próximos 7 dias</p>
            </div>
            <?php else: ?>
            <?php foreach ($proximas_receber as $conta): ?>
            <div class="px-6 py-4 hover:bg-gray-50">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($conta['cliente_nome'] ?? 'Cliente não informado') ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?= htmlspecialchars($conta['descricao'] ?? 'Sem descrição') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">
                            <?= formatarMoeda($conta['valor'] ?? 0) ?>
                        </p>
                        <p class="text-xs <?= strtotime($conta['vencimento']) < strtotime('today') ? 'text-red-600' : 'text-gray-500' ?>">
                            Vence: <?= formatarData($conta['vencimento']) ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($proximas_receber)): ?>
        <div class="px-6 py-4 border-t">
            <a href="contas_receber.php" class="text-sm text-blue-600 hover:text-blue-800">
                Ver todas as contas a receber →
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Próximas Contas a Pagar -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Próximas Contas a Pagar</h2>
            <p class="text-sm text-gray-500">Vencimento nos próximos 7 dias</p>
        </div>
        <div class="divide-y">
            <?php if (empty($proximas_pagar)): ?>
            <div class="p-6 text-center text-gray-500">
                <p>Nenhuma conta a pagar nos próximos 7 dias</p>
            </div>
            <?php else: ?>
            <?php foreach ($proximas_pagar as $conta): ?>
            <div class="px-6 py-4 hover:bg-gray-50">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($conta['fornecedor_nome'] ?? 'Fornecedor não informado') ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?= htmlspecialchars($conta['descricao'] ?? 'Sem descrição') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">
                            <?= formatarMoeda($conta['valor'] ?? 0) ?>
                        </p>
                        <p class="text-xs <?= strtotime($conta['vencimento']) < strtotime('today') ? 'text-red-600' : 'text-gray-500' ?>">
                            Vence: <?= formatarData($conta['vencimento']) ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($proximas_pagar)): ?>
        <div class="px-6 py-4 border-t">
            <a href="contas_pagar.php" class="text-sm text-blue-600 hover:text-blue-800">
                Ver todas as contas a pagar →
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../views/layouts/_footer.php'; ?>
