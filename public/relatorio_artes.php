<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Filtros
$periodo = $_GET['periodo'] ?? 'mes';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$arte_finalista_id = $_GET['arte_finalista_id'] ?? '';
$status_arte = $_GET['status_arte'] ?? '';
$pedido_id = $_GET['pedido_id'] ?? '';
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

// Query base para artes (otimizado: sem DATE() para permitir uso de índices)
$where = ["av.created_at >= ?::date AND av.created_at < (?::date + INTERVAL '1 day')"];
$params = [$data_inicio, $data_fim];

// Filtro por arte-finalista
if ($arte_finalista_id) {
    $where[] = "av.usuario_id = ?";
    $params[] = $arte_finalista_id;
}

// Filtro por status
if ($status_arte) {
    $where[] = "av.status = ?";
    $params[] = $status_arte;
}

// Filtro por pedido
if ($pedido_id) {
    $where[] = "av.pedido_id = ?";
    $params[] = $pedido_id;
}

$whereClause = implode(' AND ', $where);

// Buscar artes
try {
    $sql = "
        SELECT 
            av.*,
            p.numero as pedido_numero,
            p.status as pedido_status,
            c.nome as cliente_nome,
            u.nome as arte_finalista_nome,
            pa.arte_finalista_id,
            (SELECT COUNT(*) FROM arte_versoes av2 WHERE av2.pedido_id = av.pedido_id) as total_versoes_pedido
        FROM arte_versoes av
        LEFT JOIN pedidos p ON av.pedido_id = p.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON av.usuario_id = u.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        WHERE $whereClause
        ORDER BY av.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params_query = array_merge($params, [intval($limite), intval($offset)]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_query);
    $artes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total
    $sql_count = "
        SELECT COUNT(*) 
        FROM arte_versoes av
        WHERE $whereClause
    ";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $limite);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar artes: " . $e->getMessage());
    $artes = [];
    $total_registros = 0;
    $total_paginas = 0;
}

// Estatísticas gerais
try {
    $sql_stats = "
        SELECT 
            COUNT(*) as total_versoes,
            COUNT(DISTINCT av.pedido_id) as total_pedidos_com_arte,
            COUNT(*) FILTER (WHERE av.status = 'aprovado') as aprovadas,
            COUNT(*) FILTER (WHERE av.status = 'reprovado') as reprovadas,
            COUNT(*) FILTER (WHERE av.status = 'pendente' OR av.status IS NULL) as pendentes,
            COUNT(*) FILTER (WHERE av.status = 'ajuste') as em_ajuste,
            AVG(CASE 
                WHEN av.status = 'aprovado' AND av.created_at IS NOT NULL THEN
                    EXTRACT(EPOCH FROM (av.updated_at - av.created_at)) / 86400
                ELSE NULL
            END) as tempo_medio_aprovacao_dias
        FROM arte_versoes av
        WHERE $whereClause
    ";
    
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $stats = [
        'total_versoes' => 0,
        'total_pedidos_com_arte' => 0,
        'aprovadas' => 0,
        'reprovadas' => 0,
        'pendentes' => 0,
        'em_ajuste' => 0,
        'tempo_medio_aprovacao_dias' => 0
    ];
}

// Artes por arte-finalista
try {
    $sql_artista = "
        SELECT 
            u.id,
            u.nome as arte_finalista_nome,
            COUNT(av.id) as total_versoes,
            COUNT(DISTINCT av.pedido_id) as total_pedidos,
            COUNT(*) FILTER (WHERE av.status = 'aprovado') as aprovadas,
            COUNT(*) FILTER (WHERE av.status = 'reprovado') as reprovadas,
            COUNT(*) FILTER (WHERE av.status = 'pendente' OR av.status IS NULL) as pendentes
        FROM usuarios u
        INNER JOIN arte_versoes av ON av.usuario_id = u.id AND av.created_at >= ?::date AND av.created_at < (?::date + INTERVAL '1 day')
        WHERE u.perfil = 'arte_finalista'
        GROUP BY u.id, u.nome
        ORDER BY total_versoes DESC
    ";
    
    $stmt_artista = $pdo->prepare($sql_artista);
    $stmt_artista->execute([$data_inicio, $data_fim]);
    $artes_por_artista = $stmt_artista->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $artes_por_artista = [];
}

// Artes por status
try {
    $sql_status = "
        SELECT 
            COALESCE(status, 'pendente') as status_arte,
            COUNT(*) as total,
            COUNT(DISTINCT pedido_id) as total_pedidos
        FROM arte_versoes
        WHERE created_at >= ?::date AND created_at < (?::date + INTERVAL '1 day')
        GROUP BY status
        ORDER BY total DESC
    ";
    
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->execute([$data_inicio, $data_fim]);
    $artes_por_status = $stmt_status->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $artes_por_status = [];
}

// Buscar arte-finalistas para filtro
try {
    $arte_finalistas = $pdo->query("
        SELECT id, nome 
        FROM usuarios
        WHERE perfil = 'arte_finalista'
        ORDER BY nome
    ")->fetchAll();
} catch (PDOException $e) {
    $arte_finalistas = [];
}

$titulo = 'Relatório de Artes';
$breadcrumb = [
    ['label' => 'Financeiro', 'url' => 'financeiro_dashboard.php'],
    ['label' => 'Relatórios', 'url' => '#'],
    ['label' => 'Artes']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Relatório de Artes</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Análise detalhada das artes criadas</p>
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
        <div class="text-sm text-gray-500 dark:text-gray-400">Total de Versões</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['total_versoes'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">No período selecionado</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Artes Aprovadas</div>
        <div class="text-2xl font-bold text-green-600"><?= number_format($stats['aprovadas'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?= $stats['total_versoes'] > 0 
                ? number_format(($stats['aprovadas'] / $stats['total_versoes']) * 100, 1) 
                : 0 ?>% do total
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Artes Reprovadas</div>
        <div class="text-2xl font-bold text-red-600"><?= number_format($stats['reprovadas'] ?? 0) ?></div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?= $stats['total_versoes'] > 0 
                ? number_format(($stats['reprovadas'] / $stats['total_versoes']) * 100, 1) 
                : 0 ?>% do total
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Tempo Médio Aprovação</div>
        <div class="text-2xl font-bold text-purple-600">
            <?= $stats['tempo_medio_aprovacao_dias'] 
                ? number_format($stats['tempo_medio_aprovacao_dias'], 1) 
                : '-' ?> <?= $stats['tempo_medio_aprovacao_dias'] ? 'dias' : '' ?>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Para artes aprovadas</div>
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
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Arte-Finalista</label>
                <select name="arte_finalista_id" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <?php foreach ($arte_finalistas as $artista): ?>
                    <option value="<?= $artista['id'] ?>" <?= $arte_finalista_id == $artista['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($artista['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <select name="status_arte" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="aprovado" <?= $status_arte === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                    <option value="reprovado" <?= $status_arte === 'reprovado' ? 'selected' : '' ?>>Reprovado</option>
                    <option value="pendente" <?= $status_arte === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="ajuste" <?= $status_arte === 'ajuste' ? 'selected' : '' ?>>Em Ajuste</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pedido #</label>
                <input type="text" name="pedido_id" value="<?= htmlspecialchars($pedido_id) ?>"
                       placeholder="Número do pedido"
                       class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div class="lg:col-span-6 flex items-end gap-2">
                <button type="submit" class="px-6 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600">
                    Filtrar
                </button>
                <a href="relatorio_artes.php" class="px-6 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Resumo por Arte-Finalista -->
<?php if (!empty($artes_por_artista)): ?>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Artes por Arte-Finalista</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Arte-Finalista</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pedidos</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Versões</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Aprovadas</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reprovadas</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pendentes</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Taxa Aprovação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($artes_por_artista as $artista): 
                    $taxa_aprovacao = $artista['total_versoes'] > 0 
                        ? ($artista['aprovadas'] / $artista['total_versoes']) * 100 
                        : 0;
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            <?= htmlspecialchars($artista['arte_finalista_nome']) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900 dark:text-white"><?= $artista['total_pedidos'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900 dark:text-white"><?= $artista['total_versoes'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-green-600 font-semibold"><?= $artista['aprovadas'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-red-600 font-semibold"><?= $artista['reprovadas'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-yellow-600 font-semibold"><?= $artista['pendentes'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" 
                                     style="width: <?= min(100, $taxa_aprovacao) ?>%"></div>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-400 w-12 text-right">
                                <?= number_format($taxa_aprovacao, 1) ?>%
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
<?php if (!empty($artes_por_status)): ?>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Artes por Status</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Versões</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pedidos</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">% do Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php 
                $status_labels = [
                    'aprovado' => ['label' => 'Aprovado', 'color' => 'green'],
                    'reprovado' => ['label' => 'Reprovado', 'color' => 'red'],
                    'pendente' => ['label' => 'Pendente', 'color' => 'yellow'],
                    'ajuste' => ['label' => 'Em Ajuste', 'color' => 'orange']
                ];
                
                foreach ($artes_por_status as $status_data): 
                    $status_nome = $status_data['status_arte'];
                    $status_info = $status_labels[$status_nome] ?? ['label' => ucfirst($status_nome), 'color' => 'gray'];
                    $percentual = $stats['total_versoes'] > 0 
                        ? ($status_data['total'] / $stats['total_versoes']) * 100 
                        : 0;
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $status_info['color'] ?>-100 dark:bg-<?= $status_info['color'] ?>-900 text-<?= $status_info['color'] ?>-800 dark:text-<?= $status_info['color'] ?>-200">
                            <?= $status_info['label'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900 dark:text-white"><?= $status_data['total'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900 dark:text-white"><?= $status_data['total_pedidos'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            <?= number_format($percentual, 1) ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Lista Detalhada de Artes -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Artes Detalhadas</h2>
    </div>
    
    <?php if (empty($artes)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhuma arte encontrada</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Não há artes no período selecionado.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pedido</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Arte-Finalista</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Versão</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Arquivo</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data Criação</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($artes as $arte): 
                    $status_arte_atual = $arte['status'] ?? 'pendente';
                    $status_labels = [
                        'aprovado' => ['label' => 'Aprovado', 'color' => 'green'],
                        'reprovado' => ['label' => 'Reprovado', 'color' => 'red'],
                        'pendente' => ['label' => 'Pendente', 'color' => 'yellow'],
                        'ajuste' => ['label' => 'Em Ajuste', 'color' => 'orange']
                    ];
                    $status_info = $status_labels[$status_arte_atual] ?? ['label' => ucfirst($status_arte_atual), 'color' => 'gray'];
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            #<?= htmlspecialchars($arte['pedido_numero'] ?? 'N/A') ?>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <?= htmlspecialchars($arte['pedido_status'] ?? '') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($arte['cliente_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($arte['arte_finalista_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 font-semibold">
                            V<?= $arte['versao'] ?? 1 ?>
                        </span>
                        <?php if (($arte['total_versoes_pedido'] ?? 0) > 1): ?>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            de <?= $arte['total_versoes_pedido'] ?> versões
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($arte['arquivo_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= formatarData($arte['created_at'] ?? '') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $status_info['color'] ?>-100 dark:bg-<?= $status_info['color'] ?>-900 text-<?= $status_info['color'] ?>-800 dark:text-<?= $status_info['color'] ?>-200">
                            <?= $status_info['label'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="pedidos/detalhes/pedido_detalhes_gestor.php?id=<?= $arte['pedido_id'] ?>" 
                           class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
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
    <div class="px-6 py-4 border-t dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Mostrando <?= count($artes) ?> de <?= $total_registros ?> artes
        </div>
        <div class="flex gap-2">
            <?php
            $query_params = [];
            if ($periodo) $query_params['periodo'] = $periodo;
            if ($data_inicio) $query_params['data_inicio'] = $data_inicio;
            if ($data_fim) $query_params['data_fim'] = $data_fim;
            if ($arte_finalista_id) $query_params['arte_finalista_id'] = $arte_finalista_id;
            if ($status_arte) $query_params['status_arte'] = $status_arte;
            if ($pedido_id) $query_params['pedido_id'] = $pedido_id;
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
    window.location.href = 'relatorio_artes_exportar.php?' + params.toString();
}
</script>

<?php include '../views/layouts/_footer.php'; ?>
