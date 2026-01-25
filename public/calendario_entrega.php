<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

// Obter mês/ano atual ou da query string
$mes = $_GET['mes'] ?? date('n');
$ano = $_GET['ano'] ?? date('Y');

// Validar mês e ano
$mes = max(1, min(12, intval($mes)));
$ano = max(2020, min(2030, intval($ano)));

// Criar data do primeiro dia do mês
$primeiroDia = mktime(0, 0, 0, $mes, 1, $ano);
$diasNoMes = date('t', $primeiroDia);
$diaDaSemana = date('w', $primeiroDia);

// Arrays para meses em português
$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

// Filtros
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_vendedor = $_GET['vendedor'] ?? 'todos';
$filtro_urgente = $_GET['urgente'] ?? 'todos';

// Buscar entregas do mês
$sql = "
    SELECT 
        p.id,
        p.numero,
        p.prazo_entrega,
        p.status,
        p.urgente,
        c.nome as cliente_nome,
        c.telefone as cliente_telefone,
        u.nome as vendedor_nome,
        COUNT(pi.id) as total_itens,
        GROUP_CONCAT(DISTINCT pc.nome SEPARATOR ', ') as produtos
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
    LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
    WHERE 
        EXTRACT(MONTH FROM p.prazo_entrega) = ? 
        AND EXTRACT(YEAR FROM p.prazo_entrega) = ?
        AND p.status != 'cancelado'
";

$params = [$mes, $ano];

// Aplicar filtros
if ($filtro_status !== 'todos') {
    $sql .= " AND p.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_vendedor !== 'todos') {
    $sql .= " AND u.id = ?";
    $params[] = $filtro_vendedor;
}

if ($filtro_urgente === 'sim') {
    $sql .= " AND p.urgente = true";
} elseif ($filtro_urgente === 'nao') {
    $sql .= " AND p.urgente = false";
}

$sql .= " GROUP BY p.id, p.numero, p.prazo_entrega, p.status, p.urgente, 
          c.nome, c.telefone, u.nome 
          ORDER BY p.prazo_entrega, p.urgente DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar entregas por dia
$entregasPorDia = [];
foreach ($entregas as $entrega) {
    $dia = date('j', strtotime($entrega['prazo_entrega']));
    if (!isset($entregasPorDia[$dia])) {
        $entregasPorDia[$dia] = [];
    }
    $entregasPorDia[$dia][] = $entrega;
}

// Buscar vendedores para filtro
$vendedores = $pdo->query("
    SELECT id, nome 
    FROM usuarios 
    WHERE perfil IN ('vendedor', 'gestor') 
    AND ativo = true 
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas do mês
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'orcamento' THEN 1 END) as orcamentos,
        COUNT(CASE WHEN status = 'producao' THEN 1 END) as producao,
        COUNT(CASE WHEN status = 'arte' THEN 1 END) as arte,
        COUNT(CASE WHEN status = 'finalizado' THEN 1 END) as finalizados,
        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as entregues,
        COUNT(CASE WHEN urgente = true THEN 1 END) as urgentes,
        COUNT(CASE WHEN prazo_entrega < CURRENT_DATE AND status NOT IN ('entregue', 'cancelado') THEN 1 END) as atrasados
    FROM pedidos
    WHERE 
        EXTRACT(MONTH FROM prazo_entrega) = ? 
        AND EXTRACT(YEAR FROM prazo_entrega) = ?
        AND status != 'cancelado'
");
$stats->execute([$mes, $ano]);
$estatisticas = $stats->fetch(PDO::FETCH_ASSOC);

$titulo = 'Calendário de Entregas';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Calendário de Entregas']
];

include '../views/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Cabeçalho com navegação de mês -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h1 class="text-2xl font-bold text-gray-800">
                    <svg class="w-8 h-8 inline-block mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Calendário de Entregas
                </h1>
            </div>
            
            <!-- Navegação de Mês -->
            <div class="flex items-center space-x-4">
                <a href="?mes=<?= $mes == 1 ? 12 : $mes - 1 ?>&ano=<?= $mes == 1 ? $ano - 1 : $ano ?>&status=<?= $filtro_status ?>&vendedor=<?= $filtro_vendedor ?>" 
                   class="p-2 hover:bg-gray-100 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                
                <h2 class="text-xl font-semibold">
                    <?= $meses[$mes] ?> <?= $ano ?>
                </h2>
                
                <a href="?mes=<?= $mes == 12 ? 1 : $mes + 1 ?>&ano=<?= $mes == 12 ? $ano + 1 : $ano ?>&status=<?= $filtro_status ?>&vendedor=<?= $filtro_vendedor ?>" 
                   class="p-2 hover:bg-gray-100 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                
                <a href="" class="ml-4 text-sm text-green-600 hover:text-green-700 font-medium">
                    Hoje
                </a>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-gray-800"><?= $estatisticas['total'] ?></div>
            <div class="text-xs text-gray-600">Total</div>
        </div>
        <div class="bg-yellow-50 rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-yellow-600"><?= $estatisticas['orcamentos'] ?></div>
            <div class="text-xs text-gray-600">Orçamentos</div>
        </div>
        <div class="bg-purple-50 rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-purple-600"><?= $estatisticas['arte'] ?></div>
            <div class="text-xs text-gray-600">Arte</div>
        </div>
        <div class="bg-orange-50 rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-orange-600"><?= $estatisticas['producao'] ?></div>
            <div class="text-xs text-gray-600">Produção</div>
        </div>
        <div class="bg-blue-50 rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-blue-600"><?= $estatisticas['finalizados'] ?></div>
            <div class="text-xs text-gray-600">Finalizados</div>
        </div>
        <div class="bg-green-50 rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-green-600"><?= $estatisticas['entregues'] ?></div>
            <div class="text-xs text-gray-600">Entregues</div>
        </div>
        <div class="bg-red-50 rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-red-600"><?= $estatisticas['urgentes'] ?></div>
            <div class="text-xs text-gray-600">Urgentes</div>
        </div>
        <div class="bg-gray-50 rounded-lg shadow p-4 text-center <?= $estatisticas['atrasados'] > 0 ? 'border-2 border-red-500' : '' ?>">
            <div class="text-2xl font-bold <?= $estatisticas['atrasados'] > 0 ? 'text-red-600' : 'text-gray-800' ?>">
                <?= $estatisticas['atrasados'] ?>
            </div>
            <div class="text-xs text-gray-600">Atrasados</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-lg p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <input type="hidden" name="mes" value="<?= $mes ?>">
            <input type="hidden" name="ano" value="<?= $ano ?>">
            
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <span class="font-medium text-gray-700">Filtros:</span>
            </div>
            
            <select name="status" onchange="this.form.submit()" 
                    class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="todos">Todos os Status</option>
                <option value="orcamento" <?= $filtro_status == 'orcamento' ? 'selected' : '' ?>>Orçamento</option>
                <option value="arte" <?= $filtro_status == 'arte' ? 'selected' : '' ?>>Arte</option>
                <option value="producao" <?= $filtro_status == 'producao' ? 'selected' : '' ?>>Produção</option>
                <option value="finalizado" <?= $filtro_status == 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                <option value="entregue" <?= $filtro_status == 'entregue' ? 'selected' : '' ?>>Entregue</option>
            </select>
            
            <select name="vendedor" onchange="this.form.submit()" 
                    class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="todos">Todos os Vendedores</option>
                <?php foreach ($vendedores as $vendedor): ?>
                <option value="<?= $vendedor['id'] ?>" <?= $filtro_vendedor == $vendedor['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($vendedor['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="urgente" onchange="this.form.submit()" 
                    class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="todos">Todas as Prioridades</option>
                <option value="sim" <?= $filtro_urgente == 'sim' ? 'selected' : '' ?>>Urgentes</option>
                <option value="nao" <?= $filtro_urgente == 'nao' ? 'selected' : '' ?>>Normal</option>
            </select>
            
            <?php if ($filtro_status != 'todos' || $filtro_vendedor != 'todos' || $filtro_urgente != 'todos'): ?>
            <a href="" 
               class="text-sm text-red-600 hover:text-red-700 font-medium">
                Limpar Filtros
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Calendário -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <!-- Cabeçalho dos dias da semana -->
        <div class="grid grid-cols-7 bg-gray-50 border-b">
            <?php 
            $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            foreach ($diasSemana as $dia): 
            ?>
            <div class="p-3 text-center font-semibold text-gray-700 border-r last:border-r-0">
                <?= $dia ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Dias do calendário -->
        <div class="grid grid-cols-7">
            <?php
            // Células vazias antes do primeiro dia
            for ($i = 0; $i < $diaDaSemana; $i++): ?>
            <div class="min-h-[120px] p-2 border-r border-b bg-gray-50"></div>
            <?php endfor; ?>
            
            <?php
            // Dias do mês
            for ($dia = 1; $dia <= $diasNoMes; $dia++):
                $dataCompleta = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                $isHoje = $dataCompleta == date('Y-m-d');
                $isPassado = $dataCompleta < date('Y-m-d');
                $temEntregas = isset($entregasPorDia[$dia]);
                $numEntregas = $temEntregas ? count($entregasPorDia[$dia]) : 0;
            ?>
            <div class="min-h-[120px] p-2 border-r border-b relative <?= $isHoje ? 'bg-green-50' : ($isPassado ? 'bg-gray-50' : '') ?>">
                <!-- Número do dia -->
                <div class="flex justify-between items-start mb-2">
                    <span class="text-sm font-semibold <?= $isHoje ? 'text-green-600' : 'text-gray-700' ?>">
                        <?= $dia ?>
                    </span>
                    <?php if ($numEntregas > 0): ?>
                    <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full">
                        <?= $numEntregas ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- Entregas do dia -->
                <?php if ($temEntregas): ?>
                <div class="space-y-1" x-data>
                    <?php 
                    $maxMostrar = 3;
                    $entregasDia = $entregasPorDia[$dia];
                    foreach (array_slice($entregasDia, 0, $maxMostrar) as $entrega): 
                        $corStatus = [
                            'orcamento' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                            'arte' => 'bg-purple-100 text-purple-800 border-purple-200',
                            'producao' => 'bg-orange-100 text-orange-800 border-orange-200',
                            'finalizado' => 'bg-blue-100 text-blue-800 border-blue-200',
                            'entregue' => 'bg-green-100 text-green-800 border-green-200'
                        ][$entrega['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <div class="text-xs p-1 rounded border cursor-pointer hover:shadow-md transition <?= $corStatus ?>"
                         @click="mostrarDetalhes(<?= htmlspecialchars(json_encode($entrega)) ?>)">
                        <div class="flex items-center justify-between">
                            <span class="truncate flex-1 font-medium">
                                #<?= $entrega['numero'] ?>
                            </span>
                            <?php if ($entrega['urgente']): ?>
                            <span class="text-red-600 ml-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="truncate text-xs opacity-75">
                            <?= htmlspecialchars($entrega['cliente_nome']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($numEntregas > $maxMostrar): ?>
                    <button @click="mostrarTodasEntregas(<?= $dia ?>)" 
                            class="text-xs text-green-600 hover:text-green-700 font-medium">
                        +<?= $numEntregas - $maxMostrar ?> mais...
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
            
            <?php
            // Células vazias depois do último dia
            $celulasRestantes = 42 - ($diaDaSemana + $diasNoMes); // 6 semanas x 7 dias
            for ($i = 0; $i < $celulasRestantes; $i++): ?>
            <div class="min-h-[120px] p-2 border-r border-b bg-gray-50"></div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- Legenda -->
    <div class="mt-6 bg-white rounded-lg shadow-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Legenda:</h3>
        <div class="flex flex-wrap gap-4">
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-yellow-100 border border-yellow-200 rounded"></div>
                <span class="text-sm text-gray-600">Orçamento</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-purple-100 border border-purple-200 rounded"></div>
                <span class="text-sm text-gray-600">Arte</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-orange-100 border border-orange-200 rounded"></div>
                <span class="text-sm text-gray-600">Produção</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-blue-100 border border-blue-200 rounded"></div>
                <span class="text-sm text-gray-600">Finalizado</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-green-100 border border-green-200 rounded"></div>
                <span class="text-sm text-gray-600">Entregue</span>
            </div>
            <div class="flex items-center space-x-2">
                <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-sm text-gray-600">Urgente</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalhes -->
<div id="modalDetalhes" 
     x-data="{ open: false, entrega: {} }" 
     x-show="open" 
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="open" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <div x-show="open" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             @click.away="open = false"
             class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            
            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        Detalhes da Entrega
                    </h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Pedido</p>
                        <p class="font-semibold" x-text="'#' + entrega.numero"></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Cliente</p>
                        <p class="font-semibold" x-text="entrega.cliente_nome"></p>
                        <p class="text-sm text-gray-600" x-show="entrega.cliente_telefone" x-text="entrega.cliente_telefone"></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Produtos</p>
                        <p class="font-semibold" x-text="entrega.produtos || 'Sem produtos especificados'"></p>
                        <p class="text-sm text-gray-600" x-text="entrega.total_itens + ' item(ns)'"></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Vendedor</p>
                        <p class="font-semibold" x-text="entrega.vendedor_nome"></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <div class="mt-1">
                            <span x-show="entrega.status == 'orcamento'" class="px-3 py-1 text-sm rounded-full bg-yellow-100 text-yellow-800">Orçamento</span>
                            <span x-show="entrega.status == 'arte'" class="px-3 py-1 text-sm rounded-full bg-purple-100 text-purple-800">Arte</span>
                            <span x-show="entrega.status == 'producao'" class="px-3 py-1 text-sm rounded-full bg-orange-100 text-orange-800">Produção</span>
                            <span x-show="entrega.status == 'finalizado'" class="px-3 py-1 text-sm rounded-full bg-blue-100 text-blue-800">Finalizado</span>
                            <span x-show="entrega.status == 'entregue'" class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">Entregue</span>
                            <span x-show="entrega.urgente" class="ml-2 px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">
                                URGENTE
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Data de Entrega</p>
                        <p class="font-semibold" x-text="formatarData(entrega.prazo_entrega)"></p>
                    </div>
                </div>
            </div>
            
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse">
                <a :href="'pedido_detalhes.php?id=' + entrega.id" 
                   class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Ver Pedido Completo
                </a>
                <button @click="open = false" 
                        class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Lista de Entregas do Dia -->
<div id="modalListaEntregas" 
     x-data="{ open: false, dia: 0, entregas: [] }" 
     x-show="open" 
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="open" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <div x-show="open" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             @click.away="open = false"
             class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            
            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        Entregas do dia <span x-text="dia"></span> de <?= $meses[$mes] ?>
                    </h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <template x-for="entrega in entregas" :key="entrega.id">
                        <div class="p-4 border rounded-lg hover:bg-gray-50 cursor-pointer"
                             @click="mostrarDetalhes(entrega); open = false;">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-semibold" x-text="'#' + entrega.numero"></span>
                                        <span x-show="entrega.urgente" class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                            URGENTE
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1" x-text="entrega.cliente_nome"></p>
                                    <p class="text-xs text-gray-500 mt-1" x-text="entrega.produtos || 'Sem produtos especificados'"></p>
                                </div>
                                <div class="text-right">
                                    <span x-show="entrega.status == 'orcamento'" class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Orçamento</span>
                                    <span x-show="entrega.status == 'arte'" class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Arte</span>
                                    <span x-show="entrega.status == 'producao'" class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800">Produção</span>
                                    <span x-show="entrega.status == 'finalizado'" class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Finalizado</span>
                                    <span x-show="entrega.status == 'entregue'" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Entregue</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <div class="px-4 py-3 bg-gray-50 sm:px-6">
                <button @click="open = false" 
                        class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none sm:w-auto sm:text-sm">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Dados de entregas por dia para JavaScript
const entregasPorDiaJS = <?= json_encode($entregasPorDia) ?>;

function mostrarDetalhes(entrega) {
    const modal = document.querySelector('#modalDetalhes').__x.$data;
    modal.entrega = entrega;
    modal.open = true;
}

function mostrarTodasEntregas(dia) {
    const modal = document.querySelector('#modalListaEntregas').__x.$data;
    modal.dia = dia;
    modal.entregas = entregasPorDiaJS[dia] || [];
    modal.open = true;
}

function formatarData(data) {
    const [ano, mes, dia] = data.split('-');
    return `${dia}/${mes}/${ano}`;
}
</script>

<?php include '../views/_footer.php'; ?>