<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros
$vendedor_id = $_GET['vendedor_id'] ?? '';
$periodo_tipo = $_GET['periodo_tipo'] ?? 'mes';
$periodo_referencia = $_GET['periodo_referencia'] ?? date('Y-m');
$status = $_GET['status'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = 25;
$offset = ($pagina - 1) * $limite;

// Nota: O período de vendas é calculado individualmente para cada meta

// Query base para metas
$where = ["1=1"];
$params = [];

// Filtro por vendedor
if ($vendedor_id) {
    $where[] = "(m.vendedor_id = ? OR m.vendedor_id IS NULL)";
    $params[] = $vendedor_id;
}

// Filtro por tipo de período
if ($periodo_tipo) {
    $where[] = "m.periodo_tipo = ?";
    $params[] = $periodo_tipo;
}

// Filtro por período de referência
if ($periodo_referencia) {
    $where[] = "m.periodo_referencia = ?";
    $params[] = $periodo_referencia;
}

// Filtro por status
if ($status) {
    $where[] = "m.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Buscar metas
try {
    $sql = "
        SELECT 
            m.*,
            u.nome as vendedor_nome
        FROM metas_vendas m
        LEFT JOIN usuarios u ON m.vendedor_id = u.id
        WHERE $whereClause
        ORDER BY m.periodo_referencia DESC, m.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params_query = array_merge($params, [intval($limite), intval($offset)]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_query);
    $metas_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular valor atingido para todas as metas em UMA query (otimização N+1)
    $metas = [];
    
    if (!empty($metas_raw)) {
        // Preparar IDs das metas para query única
        $meta_ids = array_column($metas_raw, 'id');
        $placeholders = implode(',', array_fill(0, count($meta_ids), '?'));
        
        // Query única que calcula valores atingidos para todas as metas
        $sql_vendas = "
            SELECT 
                m.id as meta_id,
                COALESCE(SUM(p.valor_final), 0) as valor_atingido
            FROM metas_vendas m
            LEFT JOIN pedidos p ON (
                p.status = 'entregue'
                AND (
                    -- Período MÊS
                    (m.periodo_tipo = 'mes' 
                     AND p.created_at >= (m.periodo_referencia || '-01')::date
                     AND p.created_at < ((m.periodo_referencia || '-01')::date + INTERVAL '1 month'))
                    OR
                    -- Período TRIMESTRE
                    (m.periodo_tipo = 'trimestre'
                     AND p.created_at >= (
                         CASE 
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '1' THEN (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-01-01')::date
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '2' THEN (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-04-01')::date
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '3' THEN (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-07-01')::date
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '4' THEN (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-10-01')::date
                             ELSE (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-01-01')::date
                         END
                     )
                     AND p.created_at < (
                         CASE 
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '1' THEN (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-04-01')::date
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '2' THEN (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-07-01')::date
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '3' THEN (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-10-01')::date
                             WHEN SPLIT_PART(m.periodo_referencia, '-Q', 2) = '4' THEN ((SPLIT_PART(m.periodo_referencia, '-Q', 1)::integer + 1) || '-01-01')::date
                             ELSE (SPLIT_PART(m.periodo_referencia, '-Q', 1) || '-04-01')::date
                         END
                     ))
                    OR
                    -- Período ANO
                    (m.periodo_tipo = 'ano'
                     AND p.created_at >= (m.periodo_referencia || '-01-01')::date
                     AND p.created_at < ((m.periodo_referencia::integer + 1) || '-01-01')::date)
                )
                AND (m.vendedor_id IS NULL OR p.vendedor_id = m.vendedor_id)
            )
            WHERE m.id IN ($placeholders)
            GROUP BY m.id
        ";
        
        $stmt_vendas = $pdo->prepare($sql_vendas);
        $stmt_vendas->execute($meta_ids);
        $valores_atingidos = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
        
        // Criar mapa de valores atingidos por meta_id
        $valores_map = [];
        foreach ($valores_atingidos as $va) {
            $valores_map[$va['meta_id']] = floatval($va['valor_atingido']);
        }
        
        // Associar valores atingidos às metas
        foreach ($metas_raw as $meta) {
            $meta['valor_atingido'] = $valores_map[$meta['id']] ?? 0;
            $metas[] = $meta;
        }
    }
    
    // Contar total
    $sql_count = "
        SELECT COUNT(*) 
        FROM metas_vendas m
        WHERE $whereClause
    ";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $limite);
    
} catch (PDOException $e) {
    // Se a tabela não existir, criar estrutura básica
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        $metas = [];
        $total_registros = 0;
        $total_paginas = 0;
        $erro_tabela = true;
    } else {
        error_log("Erro ao buscar metas: " . $e->getMessage());
        $metas = [];
        $total_registros = 0;
        $total_paginas = 0;
    }
}

// Buscar vendedores para filtro (com cache de 5 minutos)
try {
    $vendedores = getCachedQuery($pdo, 'vendedores_ativos', "
        SELECT id, nome 
        FROM usuarios
        WHERE perfil = 'vendedor'
        ORDER BY nome
    ", [], 300);
} catch (PDOException $e) {
    $vendedores = [];
}

// Calcular estatísticas
$stats = [
    'total_metas' => 0,
    'total_atingido' => 0,
    'metas_atingidas' => 0,
    'metas_em_andamento' => 0
];

foreach ($metas as $meta) {
    $stats['total_metas'] += floatval($meta['valor_meta'] ?? 0);
    $stats['total_atingido'] += floatval($meta['valor_atingido'] ?? 0);
    
    $percentual = $meta['valor_meta'] > 0 
        ? (floatval($meta['valor_atingido'] ?? 0) / floatval($meta['valor_meta'])) * 100 
        : 0;
    
    if ($percentual >= 100) {
        $stats['metas_atingidas']++;
    } else {
        $stats['metas_em_andamento']++;
    }
}

$titulo = 'Metas de Vendas';
$breadcrumb = [
    ['label' => 'Financeiro', 'url' => 'financeiro_dashboard.php'],
    ['label' => 'Metas']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Metas de Vendas</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Gerencie e acompanhe as metas de vendas</p>
        </div>
        <button onclick="abrirModalNovaMeta()" 
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-plus mr-2"></i>Nova Meta
        </button>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Total de Metas</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white"><?= formatarMoeda($stats['total_metas']) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Valor total definido</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Total Atingido</div>
        <div class="text-2xl font-bold text-green-600"><?= formatarMoeda($stats['total_atingido']) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?= $stats['total_metas'] > 0 
                ? number_format(($stats['total_atingido'] / $stats['total_metas']) * 100, 1) 
                : 0 ?>% do total
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Metas Atingidas</div>
        <div class="text-2xl font-bold text-green-600"><?= $stats['metas_atingidas'] ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">100% ou mais</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Em Andamento</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $stats['metas_em_andamento'] ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Abaixo de 100%</div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipo de Período</label>
                <select name="periodo_tipo" id="periodo_tipo" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="mes" <?= $periodo_tipo === 'mes' ? 'selected' : '' ?>>Mensal</option>
                    <option value="trimestre" <?= $periodo_tipo === 'trimestre' ? 'selected' : '' ?>>Trimestral</option>
                    <option value="ano" <?= $periodo_tipo === 'ano' ? 'selected' : '' ?>>Anual</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Período</label>
                <?php if ($periodo_tipo === 'mes'): ?>
                <input type="month" name="periodo_referencia" value="<?= $periodo_referencia ?>"
                       class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                <?php elseif ($periodo_tipo === 'trimestre'): ?>
                <select name="periodo_referencia" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <?php
                    $ano_atual = date('Y');
                    for ($ano = $ano_atual; $ano >= $ano_atual - 2; $ano--):
                        for ($q = 1; $q <= 4; $q++):
                            $valor = $ano . '-Q' . $q;
                            $selected = $periodo_referencia === $valor ? 'selected' : '';
                    ?>
                    <option value="<?= $valor ?>" <?= $selected ?>><?= $ano ?> - Q<?= $q ?></option>
                    <?php endfor; endfor; ?>
                </select>
                <?php else: ?>
                <select name="periodo_referencia" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <?php
                    $ano_atual = date('Y');
                    for ($ano = $ano_atual; $ano >= $ano_atual - 2; $ano--):
                        $selected = $periodo_referencia == $ano ? 'selected' : '';
                    ?>
                    <option value="<?= $ano ?>" <?= $selected ?>><?= $ano ?></option>
                    <?php endfor; ?>
                </select>
                <?php endif; ?>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="ativa" <?= $status === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                    <option value="concluida" <?= $status === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                    <option value="cancelada" <?= $status === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600">
                    Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($erro_tabela)): ?>
<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                Tabela de metas não encontrada
            </h3>
            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <p>A tabela <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">metas_vendas</code> não existe no banco de dados. 
                É necessário criar a tabela para utilizar esta funcionalidade.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Lista de Metas -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Metas Cadastradas</h2>
    </div>
    
    <?php if (empty($metas)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhuma meta encontrada</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Crie uma nova meta para começar.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vendedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Período</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Meta</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Atingido</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">%</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($metas as $meta): ?>
                <?php
                $valor_meta = floatval($meta['valor_meta'] ?? 0);
                $valor_atingido = floatval($meta['valor_atingido'] ?? 0);
                $percentual = $valor_meta > 0 ? ($valor_atingido / $valor_meta) * 100 : 0;
                $status_meta = $meta['status'] ?? 'ativa';
                $cor_percentual = $percentual >= 100 ? 'green' : ($percentual >= 75 ? 'yellow' : 'red');
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            <?= htmlspecialchars($meta['vendedor_nome'] ?? 'Geral') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?php
                            $periodo_label = '';
                            if ($meta['periodo_tipo'] === 'mes') {
                                $periodo_label = date('m/Y', strtotime($meta['periodo_referencia'] . '-01'));
                            } elseif ($meta['periodo_tipo'] === 'trimestre') {
                                $periodo_label = $meta['periodo_referencia'];
                            } else {
                                $periodo_label = $meta['periodo_referencia'];
                            }
                            echo htmlspecialchars(ucfirst($meta['periodo_tipo']) . ' - ' . $periodo_label);
                            ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                            <?= formatarMoeda($valor_meta) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= formatarMoeda($valor_atingido) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="flex items-center justify-center">
                            <span class="px-2 py-1 text-xs rounded-full bg-<?= $cor_percentual ?>-100 dark:bg-<?= $cor_percentual ?>-900 text-<?= $cor_percentual ?>-800 dark:text-<?= $cor_percentual ?>-200 font-semibold">
                                <?= number_format($percentual, 1) ?>%
                            </span>
                        </div>
                        <div class="mt-1 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-<?= $cor_percentual ?>-500 h-2 rounded-full" 
                                 style="width: <?= min(100, $percentual) ?>%"></div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php
                        $badge_colors = [
                            'ativa' => 'blue',
                            'concluida' => 'green',
                            'cancelada' => 'red'
                        ];
                        $badge_labels = [
                            'ativa' => 'Ativa',
                            'concluida' => 'Concluída',
                            'cancelada' => 'Cancelada'
                        ];
                        $cor_status = $badge_colors[$status_meta] ?? 'gray';
                        $label_status = $badge_labels[$status_meta] ?? ucfirst($status_meta);
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $cor_status ?>-100 dark:bg-<?= $cor_status ?>-900 text-<?= $cor_status ?>-800 dark:text-<?= $cor_status ?>-200">
                            <?= $label_status ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end gap-2">
                            <button onclick="editarMeta(<?= $meta['id'] ?>)" 
                                    class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                Editar
                            </button>
                            <?php if ($status_meta === 'ativa'): ?>
                            <button onclick="cancelarMeta(<?= $meta['id'] ?>)" 
                                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">
                                Cancelar
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
    <div class="px-6 py-4 border-t dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Mostrando <?= count($metas) ?> de <?= $total_registros ?> metas
        </div>
        <div class="flex gap-2">
            <?php
            $query_params = [];
            if ($vendedor_id) $query_params['vendedor_id'] = $vendedor_id;
            if ($periodo_tipo) $query_params['periodo_tipo'] = $periodo_tipo;
            if ($periodo_referencia) $query_params['periodo_referencia'] = $periodo_referencia;
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

<!-- Modal Nova Meta -->
<div id="modalNovaMeta" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="fecharModalNovaMeta()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nova Meta de Vendas</h3>
                <button onclick="fecharModalNovaMeta()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formNovaMeta" onsubmit="salvarMeta(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendedor</label>
                        <select name="vendedor_id" id="form_vendedor_id" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                            <option value="">Geral (todos os vendedores)</option>
                            <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?= $vendedor['id'] ?>"><?= htmlspecialchars($vendedor['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipo de Período</label>
                        <select name="periodo_tipo" id="modal_periodo_tipo" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                            <option value="mes">Mensal</option>
                            <option value="trimestre">Trimestral</option>
                            <option value="ano">Anual</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Período</label>
                        <div id="modal_periodo_input">
                            <input type="month" name="periodo_referencia" id="form_periodo_referencia"
                                   class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Valor da Meta (R$)</label>
                        <input type="number" name="valor_meta" id="form_valor_meta" step="0.01" min="0" required
                               class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500"
                               placeholder="0.00">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="fecharModalNovaMeta()" 
                            class="px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalNovaMeta() {
    document.getElementById('modalNovaMeta').classList.remove('hidden');
}

function fecharModalNovaMeta() {
    document.getElementById('modalNovaMeta').classList.add('hidden');
}

function editarMeta(id) {
    alert('Funcionalidade de edição será implementada em breve.');
}

function salvarMeta(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('vendedor_id', document.getElementById('form_vendedor_id').value);
    formData.append('periodo_tipo', document.getElementById('modal_periodo_tipo').value);
    
    const periodoInput = document.querySelector('#modal_periodo_input input, #modal_periodo_input select');
    formData.append('periodo_referencia', periodoInput.value);
    formData.append('valor_meta', document.getElementById('form_valor_meta').value);
    
    fetch('meta_salvar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fecharModalNovaMeta();
            location.reload();
        } else {
            alert('Erro: ' + (data.message || 'Erro ao salvar meta'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar meta');
    });
}

function cancelarMeta(id) {
    if (confirm('Deseja cancelar esta meta?')) {
        fetch('meta_cancelar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + (data.message || 'Erro ao cancelar meta'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao cancelar meta');
        });
    }
}

// Atualizar campo de período no modal baseado no tipo
document.getElementById('modal_periodo_tipo').addEventListener('change', function() {
    const tipo = this.value;
    const container = document.getElementById('modal_periodo_input');
    
    if (tipo === 'mes') {
        container.innerHTML = '<input type="month" name="periodo_referencia" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">';
    } else if (tipo === 'trimestre') {
        let html = '<select name="periodo_referencia" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">';
        const ano = new Date().getFullYear();
        for (let a = ano; a >= ano - 2; a--) {
            for (let q = 1; q <= 4; q++) {
                html += `<option value="${a}-Q${q}">${a} - Q${q}</option>`;
            }
        }
        html += '</select>';
        container.innerHTML = html;
    } else {
        let html = '<select name="periodo_referencia" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">';
        const ano = new Date().getFullYear();
        for (let a = ano; a >= ano - 2; a--) {
            html += `<option value="${a}">${a}</option>`;
        }
        html += '</select>';
        container.innerHTML = html;
    }
});

// Atualizar campo de período nos filtros baseado no tipo
document.getElementById('periodo_tipo').addEventListener('change', function() {
    this.form.submit();
});
</script>

<?php include '../views/layouts/_footer.php'; ?>
