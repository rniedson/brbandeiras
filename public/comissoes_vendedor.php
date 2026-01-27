<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['vendedor']);

// Verificação adicional de segurança
if ($_SESSION['user_perfil'] !== 'vendedor') {
    $_SESSION['erro'] = 'Acesso negado.';
    header('Location: dashboard.php');
    exit;
}

// Filtros
$mes = $_GET['mes'] ?? date('Y-m');
$status_pagamento = $_GET['status_pagamento'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = 25;
$offset = ($pagina - 1) * $limite;

// Calcular período
$data_inicio = $mes . '-01';
$data_fim = date('Y-m-t', strtotime($data_inicio));

// Query base para comissões - APENAS DO VENDEDOR LOGADO
$where = [
    "p.status = 'entregue'",
    "p.vendedor_id = ?" // FILTRO CRÍTICO: apenas pedidos do vendedor logado
];
$params = [$_SESSION['user_id']];

// Filtro por período
$where[] = "p.created_at >= ?::date AND p.created_at < (?::date + INTERVAL '1 day')";
$params[] = $data_inicio;
$params[] = $data_fim;

// Filtro por status de pagamento
if ($status_pagamento) {
    if ($status_pagamento === 'pago') {
        $where[] = "COALESCE(com.status_pagamento, 'pendente') = 'pago'";
    } else {
        $where[] = "COALESCE(com.status_pagamento, 'pendente') = 'pendente'";
    }
}

$whereClause = implode(' AND ', $where);

// Taxa de comissão padrão (5%)
$taxa_comissao = 5.0;

// Buscar pedidos entregues do vendedor para calcular comissões
try {
    $sql = "
        SELECT 
            p.id,
            p.numero,
            p.valor_final,
            p.created_at,
            p.vendedor_id,
            c.nome as cliente_nome,
            (p.valor_final * ? / 100) as comissao_calculada,
            COALESCE(com.status_pagamento, 'pendente') as status_pagamento,
            com.data_pagamento,
            com.observacoes as obs_comissao
        FROM pedidos p
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
    
    // Calcular estatísticas do vendedor
    $sql_stats = "
        SELECT 
            COUNT(*) as total_pedidos,
            SUM(p.valor_final) as valor_total,
            SUM(p.valor_final * ? / 100) as comissao_total,
            SUM(CASE WHEN COALESCE(com.status_pagamento, 'pendente') = 'pago' THEN p.valor_final * ? / 100 ELSE 0 END) as comissao_paga,
            SUM(CASE WHEN COALESCE(com.status_pagamento, 'pendente') = 'pendente' THEN p.valor_final * ? / 100 ELSE 0 END) as comissao_pendente
        FROM pedidos p
        LEFT JOIN comissoes com ON com.pedido_id = p.id
        WHERE $whereClause
    ";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute(array_merge([$taxa_comissao, $taxa_comissao, $taxa_comissao], $params));
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar comissões do vendedor: " . $e->getMessage());
    $comissoes = [];
    $total_registros = 0;
    $total_paginas = 0;
    $stats = [
        'total_pedidos' => 0,
        'valor_total' => 0,
        'comissao_total' => 0,
        'comissao_paga' => 0,
        'comissao_pendente' => 0
    ];
}

$titulo = 'Minhas Comissões';
include '../views/layouts/_header.php';
?>

<div class="flex-1 bg-gray-50">
    <div class="max-w-7xl mx-auto p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Minhas Comissões</h1>
            <p class="text-gray-600 mt-2">Visualize suas comissões de vendas</p>
        </div>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Total de Pedidos</div>
                <div class="text-2xl font-bold text-gray-800"><?= number_format($stats['total_pedidos'] ?? 0) ?></div>
                <div class="text-xs text-gray-500 mt-1">Período selecionado</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Valor Total Vendido</div>
                <div class="text-2xl font-bold text-green-600"><?= formatarMoeda($stats['valor_total'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Comissões Pagas</div>
                <div class="text-2xl font-bold text-green-600"><?= formatarMoeda($stats['comissao_paga'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Comissões Pendentes</div>
                <div class="text-2xl font-bold text-yellow-600"><?= formatarMoeda($stats['comissao_pendente'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mês</label>
                        <input type="month" name="mes" value="<?= $mes ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Pagamento</label>
                        <select name="status_pagamento" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="">Todos</option>
                            <option value="pendente" <?= $status_pagamento === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="pago" <?= $status_pagamento === 'pago' ? 'selected' : '' ?>>Pago</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Comissões -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Detalhamento de Comissões</h2>
            </div>
            
            <?php if (empty($comissoes)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma comissão encontrada</h3>
                <p class="mt-1 text-sm text-gray-500">Não há pedidos entregues no período selecionado.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedido</th>
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
                                <a href="pedidos/pedido_detalhes.php?id=<?= $comissao['id'] ?>" 
                                   class="text-blue-600 hover:text-blue-900">
                                    Ver Pedido
                                </a>
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
                            <span class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium">
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
                            <span class="px-3 py-2 text-gray-500">...</span>
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
    </div>
</div>

<?php include '../views/layouts/_footer.php'; ?>
