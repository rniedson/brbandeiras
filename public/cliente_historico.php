<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['vendedor', 'gestor']);

// Filtros
$cliente_id = $_GET['cliente_id'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';
$busca = $_GET['busca'] ?? '';

// Query base
$where = ["1=1"];
$params = [];

// Filtro por cliente específico
if ($cliente_id) {
    $where[] = "p.cliente_id = ?";
    $params[] = $cliente_id;
}

// Filtro de datas
if ($data_inicio && $data_fim) {
    $where[] = "DATE(p.created_at) BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

// Filtro de status
if ($status) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

// Filtro de busca
if ($busca) {
    $where[] = "(c.nome ILIKE ? OR c.nome_fantasia ILIKE ? OR p.numero LIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam]);
}

// Vendedor vê apenas seus pedidos
if ($_SESSION['user_perfil'] === 'vendedor') {
    $where[] = "p.vendedor_id = ?";
    $params[] = $_SESSION['user_id'];
}

$whereClause = implode(' AND ', $where);

// Query para buscar pedidos com informações do cliente
$sql = "
    SELECT 
        p.*,
        c.id as cliente_id,
        c.nome as cliente_nome,
        c.nome_fantasia as cliente_nome_fantasia,
        c.cpf_cnpj as cliente_cpf_cnpj,
        c.telefone as cliente_telefone,
        c.email as cliente_email,
        u.nome as vendedor_nome,
        COUNT(DISTINCT pi.id) as total_itens,
        COALESCE(SUM(pi.valor_total), 0) as valor_total_itens
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
    WHERE $whereClause
    GROUP BY p.id, c.id, c.nome, c.nome_fantasia, c.cpf_cnpj, c.telefone, c.email, u.nome
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar pedidos por cliente
$historico_por_cliente = [];
foreach ($pedidos as $pedido) {
    $cliente_key = $pedido['cliente_id'];
    if (!isset($historico_por_cliente[$cliente_key])) {
        $historico_por_cliente[$cliente_key] = [
            'cliente' => [
                'id' => $pedido['cliente_id'],
                'nome' => $pedido['cliente_nome'],
                'nome_fantasia' => $pedido['cliente_nome_fantasia'],
                'cpf_cnpj' => $pedido['cliente_cpf_cnpj'],
                'telefone' => $pedido['cliente_telefone'],
                'email' => $pedido['cliente_email']
            ],
            'pedidos' => [],
            'total_pedidos' => 0,
            'valor_total' => 0,
            'status_count' => []
        ];
    }
    
    $historico_por_cliente[$cliente_key]['pedidos'][] = $pedido;
    $historico_por_cliente[$cliente_key]['total_pedidos']++;
    $historico_por_cliente[$cliente_key]['valor_total'] += floatval($pedido['valor_final']);
    
    $status = $pedido['status'];
    if (!isset($historico_por_cliente[$cliente_key]['status_count'][$status])) {
        $historico_por_cliente[$cliente_key]['status_count'][$status] = 0;
    }
    $historico_por_cliente[$cliente_key]['status_count'][$status]++;
}

// Buscar lista de clientes para filtro
$clientes_lista = $pdo->query("
    SELECT id, nome, nome_fantasia 
    FROM clientes 
    WHERE ativo = true 
    ORDER BY nome
")->fetchAll();

// Estatísticas gerais
$stats = [
    'total_clientes' => count($historico_por_cliente),
    'total_pedidos' => count($pedidos),
    'valor_total' => array_sum(array_column($pedidos, 'valor_final'))
];

$titulo = 'Histórico de Compras';
$breadcrumb = [
    ['label' => 'Clientes', 'url' => 'clientes/clientes.php'],
    ['label' => 'Histórico de Compras']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Histórico de Compras</h1>
    <p class="text-gray-600 mt-2">Visualize o histórico de pedidos por cliente</p>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Clientes com Pedidos</p>
                <p class="text-xl font-bold"><?= $stats['total_clientes'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Total de Pedidos</p>
                <p class="text-xl font-bold"><?= $stats['total_pedidos'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Valor Total</p>
                <p class="text-xl font-bold"><?= formatarMoeda($stats['valor_total']) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Cliente</label>
                <select name="cliente_id" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos os clientes</option>
                    <?php foreach ($clientes_lista as $cliente): ?>
                    <option value="<?= $cliente['id'] ?>" <?= $cliente_id == $cliente['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cliente['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="orcamento" <?= $status === 'orcamento' ? 'selected' : '' ?>>Orçamento</option>
                    <option value="aprovado" <?= $status === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                    <option value="producao" <?= $status === 'producao' ? 'selected' : '' ?>>Produção</option>
                    <option value="pronto" <?= $status === 'pronto' ? 'selected' : '' ?>>Pronto</option>
                    <option value="entregue" <?= $status === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                    <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Nome, pedido..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div class="md:col-span-2 lg:col-span-5 flex gap-2">
                <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    Filtrar
                </button>
                <a href="cliente_historico.php" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Histórico por Cliente -->
<?php if (empty($historico_por_cliente)): ?>
<div class="bg-white rounded-lg shadow p-12 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum pedido encontrado</h3>
    <p class="mt-1 text-sm text-gray-500">Ajuste os filtros para ver mais resultados.</p>
</div>
<?php else: ?>
<div class="space-y-6">
    <?php foreach ($historico_por_cliente as $cliente_data): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <!-- Cabeçalho do Cliente -->
        <div class="bg-gray-50 px-6 py-4 border-b">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        <?= htmlspecialchars($cliente_data['cliente']['nome']) ?>
                    </h3>
                    <?php if ($cliente_data['cliente']['nome_fantasia']): ?>
                    <p class="text-sm text-gray-600">
                        <?= htmlspecialchars($cliente_data['cliente']['nome_fantasia']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($cliente_data['cliente']['telefone']): ?>
                    <p class="text-xs text-gray-500 mt-1">
                        <?= htmlspecialchars($cliente_data['cliente']['telefone']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Total de Pedidos</div>
                    <div class="text-2xl font-bold text-gray-900"><?= $cliente_data['total_pedidos'] ?></div>
                    <div class="text-sm text-gray-500 mt-1">Valor Total</div>
                    <div class="text-lg font-semibold text-green-600">
                        <?= formatarMoeda($cliente_data['valor_total']) ?>
                    </div>
                </div>
            </div>
            
            <!-- Status badges -->
            <?php if (!empty($cliente_data['status_count'])): ?>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php 
                $status_labels = [
                    'orcamento' => ['label' => 'Orçamentos', 'color' => 'blue'],
                    'aprovado' => ['label' => 'Aprovados', 'color' => 'green'],
                    'producao' => ['label' => 'Produção', 'color' => 'yellow'],
                    'pronto' => ['label' => 'Prontos', 'color' => 'purple'],
                    'entregue' => ['label' => 'Entregues', 'color' => 'green'],
                    'cancelado' => ['label' => 'Cancelados', 'color' => 'red']
                ];
                foreach ($cliente_data['status_count'] as $status_key => $count): 
                    $status_info = $status_labels[$status_key] ?? ['label' => ucfirst($status_key), 'color' => 'gray'];
                ?>
                <span class="px-2 py-1 text-xs rounded-full bg-<?= $status_info['color'] ?>-100 text-<?= $status_info['color'] ?>-800">
                    <?= $status_info['label'] ?>: <?= $count ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Lista de Pedidos -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedido</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Itens</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($cliente_data['pedidos'] as $pedido): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                #<?= htmlspecialchars($pedido['numero']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?= formatarData($pedido['created_at']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?= htmlspecialchars($pedido['vendedor_nome'] ?? 'N/A') ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="text-sm text-gray-900">
                                <?= $pedido['total_itens'] ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="text-sm font-medium text-gray-900">
                                <?= formatarMoeda($pedido['valor_final']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php
                            $status_colors = [
                                'orcamento' => 'blue',
                                'aprovado' => 'green',
                                'producao' => 'yellow',
                                'pronto' => 'purple',
                                'entregue' => 'green',
                                'cancelado' => 'red'
                            ];
                            $color = $status_colors[$pedido['status']] ?? 'gray';
                            ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-<?= $color ?>-100 text-<?= $color ?>-800">
                                <?= ucfirst($pedido['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="pedidos/detalhes/pedido_detalhes_gestor.php?id=<?= $pedido['id'] ?>" 
                               class="text-indigo-600 hover:text-indigo-900">
                                Ver Detalhes
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../views/layouts/_footer.php'; ?>
