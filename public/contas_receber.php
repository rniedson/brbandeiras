<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros
$status_filtro = $_GET['status'] ?? '';
$cliente_id = $_GET['cliente_id'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$busca = $_GET['busca'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = 25;
$offset = ($pagina - 1) * $limite;

// Query base
$where = ["1=1"];
$params = [];

// Filtro de status
if ($status_filtro === 'aberto') {
    $where[] = "cr.status = 'aberto'";
} elseif ($status_filtro === 'pago') {
    $where[] = "cr.status = 'pago'";
} elseif ($status_filtro === 'vencida') {
    $where[] = "cr.status = 'aberto' AND cr.vencimento < CURRENT_DATE";
} elseif ($status_filtro === 'vencer_hoje') {
    $where[] = "cr.status = 'aberto' AND DATE(cr.vencimento) = CURRENT_DATE";
} elseif ($status_filtro === 'vencer_7dias') {
    $where[] = "cr.status = 'aberto' AND cr.vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'";
}

// Filtro por cliente
if ($cliente_id) {
    $where[] = "cr.cliente_id = ?";
    $params[] = $cliente_id;
}

// Filtro de datas
if ($data_inicio && $data_fim) {
    $where[] = "DATE(cr.vencimento) BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

// Filtro de busca
if ($busca) {
    $where[] = "(cr.descricao ILIKE ? OR c.nome ILIKE ? OR cr.numero_documento LIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam]);
}

$whereClause = implode(' AND ', $where);

// Contar total
try {
    $sql_count = "SELECT COUNT(*) 
                  FROM contas_receber cr
                  LEFT JOIN clientes c ON cr.cliente_id = c.id
                  WHERE $whereClause";
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $limite);
    
    // Buscar contas
    $sql = "SELECT 
            cr.*,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            CASE 
                WHEN cr.status = 'pago' THEN 'pago'
                WHEN cr.vencimento < CURRENT_DATE THEN 'vencida'
                WHEN DATE(cr.vencimento) = CURRENT_DATE THEN 'vencer_hoje'
                WHEN cr.vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days' THEN 'vencer_7dias'
                ELSE 'normal'
            END as status_visual
        FROM contas_receber cr
        LEFT JOIN clientes c ON cr.cliente_id = c.id
        WHERE $whereClause
        ORDER BY cr.vencimento ASC, cr.created_at DESC
        LIMIT ? OFFSET ?";
    
    $params_paginacao = array_merge($params, [intval($limite), intval($offset)]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_paginacao);
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contas = [];
    $total_registros = 0;
    $total_paginas = 0;
}

// Buscar clientes para filtro
$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE ativo = true ORDER BY nome")->fetchAll();

// Estatísticas
$stats = [
    'total' => count($contas),
    'valor_total' => array_sum(array_column($contas, 'valor')),
    'vencidas' => count(array_filter($contas, fn($c) => ($c['status_visual'] ?? '') === 'vencida')),
    'vencer_hoje' => count(array_filter($contas, fn($c) => ($c['status_visual'] ?? '') === 'vencer_hoje'))
];

$titulo = 'Contas a Receber';
$breadcrumb = [
    ['label' => 'Financeiro', 'url' => 'financeiro_dashboard.php'],
    ['label' => 'Contas a Receber']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Contas a Receber</h1>
            <p class="text-gray-600 mt-2">Gerencie as contas a receber da empresa</p>
        </div>
        <a href="conta_receber_nova.php" 
           class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nova Conta
        </a>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total de Contas</div>
        <div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Valor Total</div>
        <div class="text-2xl font-bold text-blue-600"><?= formatarMoeda($stats['valor_total']) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Vencidas</div>
        <div class="text-2xl font-bold text-red-600"><?= $stats['vencidas'] ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Vencem Hoje</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $stats['vencer_hoje'] ?></div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="aberto" <?= $status_filtro === 'aberto' ? 'selected' : '' ?>>Abertas</option>
                    <option value="pago" <?= $status_filtro === 'pago' ? 'selected' : '' ?>>Pagas</option>
                    <option value="vencida" <?= $status_filtro === 'vencida' ? 'selected' : '' ?>>Vencidas</option>
                    <option value="vencer_hoje" <?= $status_filtro === 'vencer_hoje' ? 'selected' : '' ?>>Vencem Hoje</option>
                    <option value="vencer_7dias" <?= $status_filtro === 'vencer_7dias' ? 'selected' : '' ?>>Próximos 7 dias</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Cliente</label>
                <select name="cliente_id" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <?php foreach ($clientes as $cliente): ?>
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Descrição, cliente..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div class="md:col-span-2 lg:col-span-5 flex gap-2">
                <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    Filtrar
                </button>
                <a href="contas_receber.php" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Contas -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($contas)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma conta encontrada</h3>
        <p class="mt-1 text-sm text-gray-500">Ajuste os filtros ou cadastre uma nova conta.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($contas as $conta): ?>
                <?php
                $status_visual = $conta['status_visual'] ?? 'normal';
                $status_atual = $conta['status'] ?? 'aberto';
                $vencimento = strtotime($conta['vencimento'] ?? '');
                $hoje = strtotime('today');
                $dias_vencimento = $vencimento ? floor(($vencimento - $hoje) / 86400) : 0;
                ?>
                <tr class="hover:bg-gray-50 <?= $status_visual === 'vencida' ? 'bg-red-50' : ($status_visual === 'vencer_hoje' ? 'bg-yellow-50' : '') ?>">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($conta['cliente_nome'] ?? 'Cliente não informado') ?>
                        </div>
                        <?php if (!empty($conta['cliente_telefone'])): ?>
                        <div class="text-xs text-gray-500">
                            <?= htmlspecialchars($conta['cliente_telefone']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?= htmlspecialchars($conta['descricao'] ?? 'Sem descrição') ?>
                        </div>
                        <?php if (!empty($conta['numero_documento'])): ?>
                        <div class="text-xs text-gray-500">
                            Doc: <?= htmlspecialchars($conta['numero_documento']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900">
                            <?= formatarData($conta['vencimento'] ?? '') ?>
                        </div>
                        <?php if ($status_atual === 'aberto' && $vencimento): ?>
                        <div class="text-xs <?= $dias_vencimento < 0 ? 'text-red-600' : ($dias_vencimento === 0 ? 'text-yellow-600' : 'text-gray-500') ?>">
                            <?php if ($dias_vencimento < 0): ?>
                                Vencida há <?= abs($dias_vencimento) ?> dia(s)
                            <?php elseif ($dias_vencimento === 0): ?>
                                Vence hoje
                            <?php else: ?>
                                Vence em <?= $dias_vencimento ?> dia(s)
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-medium text-gray-900">
                            <?= formatarMoeda($conta['valor'] ?? 0) ?>
                        </div>
                        <?php if (!empty($conta['valor_pago']) && $conta['valor_pago'] > 0): ?>
                        <div class="text-xs text-gray-500">
                            Pago: <?= formatarMoeda($conta['valor_pago']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php
                        if ($status_atual === 'pago') {
                            $badge_color = 'green';
                            $badge_text = 'Paga';
                        } elseif ($status_visual === 'vencida') {
                            $badge_color = 'red';
                            $badge_text = 'Vencida';
                        } elseif ($status_visual === 'vencer_hoje') {
                            $badge_color = 'yellow';
                            $badge_text = 'Vence Hoje';
                        } elseif ($status_visual === 'vencer_7dias') {
                            $badge_color = 'orange';
                            $badge_text = 'Próxima';
                        } else {
                            $badge_color = 'blue';
                            $badge_text = 'Aberta';
                        }
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $badge_color ?>-100 text-<?= $badge_color ?>-800">
                            <?= $badge_text ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end gap-2">
                            <a href="conta_receber_detalhes.php?id=<?= $conta['id'] ?>" 
                               class="text-indigo-600 hover:text-indigo-900">
                                Ver
                            </a>
                            <?php if ($status_atual === 'aberto'): ?>
                            <button onclick="marcarComoPaga(<?= $conta['id'] ?>)" 
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
            Mostrando <?= count($contas) ?> de <?= $total_registros ?> contas
        </div>
        <div class="flex gap-2">
            <?php
            $query_params = [];
            if ($status_filtro) $query_params['status'] = $status_filtro;
            if ($cliente_id) $query_params['cliente_id'] = $cliente_id;
            if ($data_inicio) $query_params['data_inicio'] = $data_inicio;
            if ($data_fim) $query_params['data_fim'] = $data_fim;
            if ($busca) $query_params['busca'] = $busca;
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
function marcarComoPaga(id) {
    if (confirm('Deseja marcar esta conta como paga?')) {
        const valorPago = prompt('Valor pago (deixe em branco para valor total):');
        
        fetch('conta_receber_pagar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + id + (valorPago ? '&valor_pago=' + valorPago : '')
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
