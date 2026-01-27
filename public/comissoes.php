<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros
$vendedor_id = $_GET['vendedor_id'] ?? '';
$mes = $_GET['mes'] ?? date('Y-m');
$status_pagamento = $_GET['status_pagamento'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = 25;
$offset = ($pagina - 1) * $limite;

// Calcular período
$data_inicio = $mes . '-01';
$data_fim = date('Y-m-t', strtotime($data_inicio));

// Query base para comissões
$where = ["p.status = 'entregue'"];
$params = [];

// Filtro por vendedor
if ($vendedor_id) {
    $where[] = "p.vendedor_id = ?";
    $params[] = $vendedor_id;
}

// Filtro por período
    $where[] = "p.created_at >= ?::date AND p.created_at < (?::date + INTERVAL '1 day')";
$params[] = $data_inicio;
$params[] = $data_fim;

$whereClause = implode(' AND ', $where);

// Taxa de comissão padrão (5%)
$taxa_comissao = 5.0;

// Buscar pedidos entregues para calcular comissões
try {
    $sql = "
        SELECT 
            p.id,
            p.numero,
            p.valor_final,
            p.created_at,
            p.vendedor_id,
            u.nome as vendedor_nome,
            c.nome as cliente_nome,
            (p.valor_final * ? / 100) as comissao_calculada,
            COALESCE(com.status_pagamento, 'pendente') as status_pagamento,
            com.data_pagamento,
            com.observacoes as obs_comissao
        FROM pedidos p
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN comissoes com ON com.pedido_id = p.id
        WHERE $whereClause
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params_comissao = array_merge([$taxa_comissao], $params, [intval($limite), intval($offset)]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_comissao);
    $comissoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $comissoes = [];
    $total_registros = 0;
    $total_paginas = 0;
}

// Buscar vendedores para filtro
$vendedores = $pdo->query("
    SELECT DISTINCT u.id, u.nome 
    FROM usuarios u
    INNER JOIN pedidos p ON p.vendedor_id = u.id
    WHERE u.perfil = 'vendedor'
    ORDER BY u.nome
")->fetchAll();

// Agrupar comissões por vendedor para estatísticas
$comissoes_por_vendedor = [];
foreach ($comissoes as $comissao) {
    $vendedor_key = $comissao['vendedor_id'];
    if (!isset($comissoes_por_vendedor[$vendedor_key])) {
        $comissoes_por_vendedor[$vendedor_key] = [
            'vendedor_nome' => $comissao['vendedor_nome'],
            'total_pedidos' => 0,
            'valor_total' => 0,
            'comissao_total' => 0,
            'comissao_paga' => 0,
            'comissao_pendente' => 0
        ];
    }
    
    $comissoes_por_vendedor[$vendedor_key]['total_pedidos']++;
    $comissoes_por_vendedor[$vendedor_key]['valor_total'] += floatval($comissao['valor_final']);
    $comissoes_por_vendedor[$vendedor_key]['comissao_total'] += floatval($comissao['comissao_calculada']);
    
    if (($comissao['status_pagamento'] ?? 'pendente') === 'pago') {
        $comissoes_por_vendedor[$vendedor_key]['comissao_paga'] += floatval($comissao['comissao_calculada']);
    } else {
        $comissoes_por_vendedor[$vendedor_key]['comissao_pendente'] += floatval($comissao['comissao_calculada']);
    }
}

// Estatísticas gerais
$stats = [
    'total_comissao' => array_sum(array_column($comissoes, 'comissao_calculada')),
    'total_paga' => array_sum(array_filter(array_column($comissoes, 'comissao_calculada'), function($c, $i) use ($comissoes) {
        return ($comissoes[$i]['status_pagamento'] ?? 'pendente') === 'pago';
    }, ARRAY_FILTER_USE_BOTH)),
    'total_pendente' => array_sum(array_filter(array_column($comissoes, 'comissao_calculada'), function($c, $i) use ($comissoes) {
        return ($comissoes[$i]['status_pagamento'] ?? 'pendente') !== 'pago';
    }, ARRAY_FILTER_USE_BOTH))
];

$titulo = 'Comissões de Vendas';
$breadcrumb = [
    ['label' => 'Financeiro', 'url' => 'financeiro_dashboard.php'],
    ['label' => 'Comissões']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Comissões de Vendas</h1>
            <p class="text-gray-600 mt-2">Gerencie as comissões dos vendedores</p>
        </div>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total de Comissões</div>
        <div class="text-2xl font-bold text-gray-800"><?= formatarMoeda($stats['total_comissao']) ?></div>
        <div class="text-xs text-gray-500 mt-1">Período selecionado</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Comissões Pagas</div>
        <div class="text-2xl font-bold text-green-600"><?= formatarMoeda($stats['total_paga']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Comissões Pendentes</div>
        <div class="text-2xl font-bold text-yellow-600"><?= formatarMoeda($stats['total_pendente']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Taxa de Comissão</div>
        <div class="text-2xl font-bold text-blue-600"><?= $taxa_comissao ?>%</div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Vendedor</label>
                <select name="vendedor_id" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <?php foreach ($vendedores as $vendedor): ?>
                    <option value="<?= $vendedor['id'] ?>" <?= $vendedor_id == $vendedor['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vendedor['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mês</label>
                <input type="month" name="mes" value="<?= $mes ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status Pagamento</label>
                <select name="status_pagamento" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="pendente" <?= $status_pagamento === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="pago" <?= $status_pagamento === 'pago' ? 'selected' : '' ?>>Pago</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumo por Vendedor -->
<?php if (!empty($comissoes_por_vendedor)): ?>
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b">
        <h2 class="text-lg font-semibold text-gray-800">Resumo por Vendedor</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pedidos</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Comissão Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paga</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pendente</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($comissoes_por_vendedor as $vendedor_data): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($vendedor_data['vendedor_nome']) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900"><?= $vendedor_data['total_pedidos'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm text-gray-900"><?= formatarMoeda($vendedor_data['valor_total']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-semibold text-gray-900"><?= formatarMoeda($vendedor_data['comissao_total']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm text-green-600"><?= formatarMoeda($vendedor_data['comissao_paga']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm text-yellow-600"><?= formatarMoeda($vendedor_data['comissao_pendente']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Lista Detalhada de Comissões -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h2 class="text-lg font-semibold text-gray-800">Detalhamento de Comissões</h2>
    </div>
    
    <?php if (empty($comissoes)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma comissão encontrada</h3>
        <p class="mt-1 text-sm text-gray-500">Não há pedidos entregues no período selecionado.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedido</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Pedido</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Comissão</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($comissoes as $comissao): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">
                            #<?= htmlspecialchars($comissao['numero'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?= htmlspecialchars($comissao['vendedor_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?= htmlspecialchars($comissao['cliente_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900">
                            <?= formatarData($comissao['created_at'] ?? '') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm text-gray-900">
                            <?= formatarMoeda($comissao['valor_final'] ?? 0) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-semibold text-gray-900">
                            <?= formatarMoeda($comissao['comissao_calculada'] ?? 0) ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            (<?= $taxa_comissao ?>%)
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php
                        $status_comissao = $comissao['status_pagamento'] ?? 'pendente';
                        $badge_color = $status_comissao === 'pago' ? 'green' : 'yellow';
                        $badge_text = $status_comissao === 'pago' ? 'Paga' : 'Pendente';
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $badge_color ?>-100 text-<?= $badge_color ?>-800">
                            <?= $badge_text ?>
                        </span>
                        <?php if ($status_comissao === 'pago' && !empty($comissao['data_pagamento'])): ?>
                        <div class="text-xs text-gray-500 mt-1">
                            <?= formatarData($comissao['data_pagamento']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end gap-2">
                            <a href="pedidos/detalhes/pedido_detalhes_gestor.php?id=<?= $comissao['id'] ?>" 
                               class="text-indigo-600 hover:text-indigo-900">
                                Ver Pedido
                            </a>
                            <?php if ($status_comissao === 'pendente'): ?>
                            <button onclick="marcarComissaoPaga(<?= $comissao['id'] ?>, <?= $comissao['comissao_calculada'] ?>)" 
                                    class="text-green-600 hover:text-green-900">
                                Pagar
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
    <div class="px-6 py-4 border-t flex items-center justify-between">
        <div class="text-sm text-gray-600">
            Mostrando <?= count($comissoes) ?> de <?= $total_registros ?> comissões
        </div>
        <div class="flex gap-2">
            <?php
            $query_params = [];
            if ($vendedor_id) $query_params['vendedor_id'] = $vendedor_id;
            if ($mes) $query_params['mes'] = $mes;
            if ($status_pagamento) $query_params['status_pagamento'] = $status_pagamento;
            $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
            ?>
            
            <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                Anterior
            </a>
            <?php endif; ?>
            
            <?php
            $inicio = max(1, $pagina - 2);
            $fim = min($total_paginas, $pagina + 2);
            
            if ($inicio > 1): ?>
                <a href="?pagina=1<?= $query_string ?>" class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">1</a>
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
                       class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($fim < $total_paginas): ?>
                <?php if ($fim < $total_paginas - 1): ?>
                    <span class="px-3 py-2 text-gray-400">...</span>
                <?php endif; ?>
                <a href="?pagina=<?= $total_paginas ?><?= $query_string ?>" 
                   class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                    <?= $total_paginas ?>
                </a>
            <?php endif; ?>
            
            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                Próxima
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function marcarComissaoPaga(pedidoId, valorComissao) {
    if (confirm(`Deseja marcar a comissão de ${valorComissao.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'})} como paga?`)) {
        fetch('comissao_pagar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'pedido_id=' + pedidoId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + (data.message || 'Erro ao processar pagamento'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar pagamento');
        });
    }
}
</script>

<?php include '../views/layouts/_footer.php'; ?>
