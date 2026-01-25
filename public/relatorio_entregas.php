<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor']);

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês
$data_fim = $_GET['data_fim'] ?? date('Y-m-t'); // Último dia do mês
$vendedor_id = $_GET['vendedor'] ?? 'todos';

// Relatório de entregas
$sql = "
    SELECT 
        DATE(p.prazo_entrega) as data_entrega,
        COUNT(*) as total_pedidos,
        COUNT(CASE WHEN p.status = 'entregue' THEN 1 END) as entregues,
        COUNT(CASE WHEN p.status != 'entregue' AND p.status != 'cancelado' THEN 1 END) as pendentes,
        COUNT(CASE WHEN p.prazo_entrega < CURRENT_DATE AND p.status NOT IN ('entregue', 'cancelado') THEN 1 END) as atrasados,
        COUNT(CASE WHEN p.urgente = true THEN 1 END) as urgentes,
        SUM(p.valor_final) as valor_total,
        AVG(EXTRACT(DAY FROM p.updated_at - p.created_at)) as tempo_medio_conclusao
    FROM pedidos p
    WHERE 
        p.prazo_entrega BETWEEN ? AND ?
        AND p.status != 'cancelado'
";

$params = [$data_inicio, $data_fim];

if ($vendedor_id !== 'todos') {
    $sql .= " AND p.vendedor_id = ?";
    $params[] = $vendedor_id;
}

$sql .= " GROUP BY DATE(p.prazo_entrega) ORDER BY data_entrega";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados_diarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas gerais do período
$sql_stats = "
    SELECT 
        COUNT(*) as total_pedidos,
        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as total_entregues,
        COUNT(CASE WHEN prazo_entrega < CURRENT_DATE AND status NOT IN ('entregue', 'cancelado') THEN 1 END) as total_atrasados,
        SUM(valor_final) as valor_total,
        AVG(valor_final) as ticket_medio,
        COUNT(DISTINCT cliente_id) as total_clientes,
        COUNT(DISTINCT vendedor_id) as total_vendedores
    FROM pedidos
    WHERE 
        prazo_entrega BETWEEN ? AND ?
        AND status != 'cancelado'
";

if ($vendedor_id !== 'todos') {
    $sql_stats .= " AND vendedor_id = ?";
}

$stmt = $pdo->prepare($sql_stats);
$stmt->execute($params);
$estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Taxa de entrega no prazo
$taxa_entrega_prazo = $estatisticas['total_pedidos'] > 0 
    ? (($estatisticas['total_entregues'] - $estatisticas['total_atrasados']) / $estatisticas['total_pedidos']) * 100 
    : 0;

// Performance por vendedor
$sql_vendedores = "
    SELECT 
        u.nome as vendedor,
        COUNT(p.id) as total_pedidos,
        COUNT(CASE WHEN p.status = 'entregue' THEN 1 END) as entregues,
        COUNT(CASE WHEN p.prazo_entrega < CURRENT_DATE AND p.status NOT IN ('entregue', 'cancelado') THEN 1 END) as atrasados,
        SUM(p.valor_final) as valor_total,
        AVG(p.valor_final) as ticket_medio
    FROM pedidos p
    JOIN usuarios u ON p.vendedor_id = u.id
    WHERE 
        p.prazo_entrega BETWEEN ? AND ?
        AND p.status != 'cancelado'
";

if ($vendedor_id !== 'todos') {
    $sql_vendedores .= " AND p.vendedor_id = ?";
}

$sql_vendedores .= " GROUP BY u.id, u.nome ORDER BY valor_total DESC";

$stmt = $pdo->prepare($sql_vendedores);
$stmt->execute($params);
$performance_vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar vendedores para filtro
$vendedores = $pdo->query("
    SELECT id, nome 
    FROM usuarios 
    WHERE perfil IN ('vendedor', 'gestor') 
    AND ativo = true 
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Relatório de Entregas';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Relatório de Entregas']
];

include '../views/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Cabeçalho -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">
            <svg class="w-8 h-8 inline-block mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Relatório de Entregas
        </h1>
        
        <!-- Filtros -->
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                <input type="date" name="data_inicio" value="<?= $data_inicio ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                <input type="date" name="data_fim" value="<?= $data_fim ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Vendedor</label>
                <select name="vendedor" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="todos">Todos</option>
                    <?php foreach ($vendedores as $vendedor): ?>
                    <option value="<?= $vendedor['id'] ?>" <?= $vendedor_id == $vendedor['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vendedor['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-2 rounded-lg transition">
                    Filtrar
                </button>
            </div>
        </form>
    </div>
    
    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total de Pedidos</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $estatisticas['total_pedidos'] ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Taxa de Entrega</p>
                    <p class="text-2xl font-bold <?= $taxa_entrega_prazo >= 80 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= number_format($taxa_entrega_prazo, 1) ?>%
                    </p>
                </div>
                <div class="p-3 <?= $taxa_entrega_prazo >= 80 ? 'bg-green-100' : 'bg-red-100' ?> rounded-full">
                    <svg class="w-8 h-8 <?= $taxa_entrega_prazo >= 80 ? 'text-green-600' : 'text-red-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Faturamento</p>
                    <p class="text-2xl font-bold text-gray-800"><?= formatarMoeda($estatisticas['valor_total']) ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Pedidos Atrasados</p>
                    <p class="text-2xl font-bold <?= $estatisticas['total_atrasados'] > 0 ? 'text-red-600' : 'text-gray-800' ?>">
                        <?= $estatisticas['total_atrasados'] ?>
                    </p>
                </div>
                <div class="p-3 <?= $estatisticas['total_atrasados'] > 0 ? 'bg-red-100' : 'bg-gray-100' ?> rounded-full">
                    <svg class="w-8 h-8 <?= $estatisticas['total_atrasados'] > 0 ? 'text-red-600' : 'text-gray-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráfico de Entregas Diárias -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Entregas por Dia</h2>
        <div class="overflow-x-auto">
            <canvas id="graficoEntregas" width="400" height="100"></canvas>
        </div>
    </div>
    
    <!-- Performance por Vendedor -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Performance por Vendedor</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Vendedor
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Pedidos
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Entregues
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Atrasados
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Taxa Entrega
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Faturamento
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ticket Médio
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($performance_vendedores as $vendedor): 
                        $taxa_vendedor = $vendedor['total_pedidos'] > 0 
                            ? ($vendedor['entregues'] / $vendedor['total_pedidos']) * 100 
                            : 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($vendedor['vendedor']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                            <?= $vendedor['total_pedidos'] ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                            <span class="text-green-600 font-medium"><?= $vendedor['entregues'] ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                            <span class="<?= $vendedor['atrasados'] > 0 ? 'text-red-600 font-medium' : '' ?>">
                                <?= $vendedor['atrasados'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="px-2 py-1 text-xs rounded-full <?= $taxa_vendedor >= 80 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= number_format($taxa_vendedor, 1) ?>%
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            <?= formatarMoeda($vendedor['valor_total']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            <?= formatarMoeda($vendedor['ticket_medio']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-6 py-4 text-sm text-gray-900">Total</td>
                        <td class="px-6 py-4 text-sm text-gray-900 text-center"><?= $estatisticas['total_pedidos'] ?></td>
                        <td class="px-6 py-4 text-sm text-green-600 text-center"><?= $estatisticas['total_entregues'] ?></td>
                        <td class="px-6 py-4 text-sm text-red-600 text-center"><?= $estatisticas['total_atrasados'] ?></td>
                        <td class="px-6 py-4 text-sm text-center">
                            <span class="px-2 py-1 text-xs rounded-full <?= $taxa_entrega_prazo >= 80 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= number_format($taxa_entrega_prazo, 1) ?>%
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 text-right"><?= formatarMoeda($estatisticas['valor_total']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900 text-right"><?= formatarMoeda($estatisticas['ticket_medio']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dados para o gráfico
const dadosGrafico = <?= json_encode(array_map(function($d) {
    return [
        'data' => formatarData($d['data_entrega']),
        'total' => $d['total_pedidos'],
        'entregues' => $d['entregues'],
        'pendentes' => $d['pendentes'],
        'atrasados' => $d['atrasados']
    ];
}, $dados_diarios)) ?>;

// Configurar gráfico
const ctx = document.getElementById('graficoEntregas').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: dadosGrafico.map(d => d.data),
        datasets: [
            {
                label: 'Total',
                data: dadosGrafico.map(d => d.total),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.1
            },
            {
                label: 'Entregues',
                data: dadosGrafico.map(d => d.entregues),
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.1
            },
            {
                label: 'Atrasados',
                data: dadosGrafico.map(d => d.atrasados),
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php include '../views/_footer.php'; ?>