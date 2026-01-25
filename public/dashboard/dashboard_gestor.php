<?php
// dashboard_gestor.php - Dashboard completo para gestores

// Enviar headers de performance ANTES de qualquer processamento pesado
if (!headers_sent()) {
    // Headers de cache e compressão
    header('Vary: Accept-Encoding');
    // Habilitar compressão de saída (reduz tamanho da resposta)
    // ob_gzhandler envia Content-Encoding automaticamente, não precisa enviar manualmente
    if (extension_loaded('zlib') && !ob_get_level()) {
        // Verificar se o cliente aceita compressão
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            ob_start('ob_gzhandler'); // ob_gzhandler envia Content-Encoding: gzip automaticamente
        } else {
            ob_start();
        }
    } else {
        ob_start();
    }
} else {
    if (!ob_get_level()) {
        ob_start();
    }
}

// Função para minificar CSS/JS inline (remove espaços desnecessários e comentários)
function minifyInline($code, $type = 'css') {
    // Remover comentários
    if ($type === 'css') {
        $code = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $code);
        // Remover espaços em branco desnecessários
        $code = preg_replace('/\s+/', ' ', $code);
        $code = preg_replace('/\s*([{}:;,])\s*/', '$1', $code);
        $code = str_replace([';}', '{ ', ' }', '( ', ' )'], ['}', '{', '}', '(', ')'], $code);
    } else {
        // Para JS, remover apenas comentários de linha única (preservar PHP interpolado)
        $code = preg_replace('/(?<!["\'])\/\/.*$/m', '', $code);
        $code = preg_replace('!/\*.*?\*/!s', '', $code);
        // Remover espaços em branco múltiplos, mas preservar quebras de linha importantes
        $code = preg_replace('/[ \t]+/', ' ', $code);
        $code = preg_replace('/\n\s*\n/', "\n", $code);
    }
    
    $code = trim($code);
    return $code;
}

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();
requireRole(['gestor']);

// Verificação adicional de segurança
$arquivo_atual = basename($_SERVER['PHP_SELF']);
if ($_SESSION['user_perfil'] !== 'gestor') {
    registrarLog('acesso_negado_dashboard', 
        "Usuário {$_SESSION['user_nome']} (Perfil: {$_SESSION['user_perfil']}) tentou acessar {$arquivo_atual}");
    $_SESSION['erro'] = 'Acesso negado. Redirecionando para seu dashboard.';
    header('Location: dashboard.php');
    exit;
}

// Funções auxiliares
function getIconForStatus($status) {
    $icons = [
        'cancelado' => 'times-circle',
        'orcamento' => 'file-text',
        'arte' => 'palette',
        'producao' => 'cog',
        'pronto' => 'package',
        'entregue' => 'check-circle'
    ];
    return $icons[$status] ?? 'circle';
}

function formatarNomeCliente($nome, $telefone) {
    if (empty($nome)) return 'Cliente não encontrado';
    
    $palavras = explode(' ', trim($nome));
    if (count($palavras) <= 1) return $nome;
    
    $primeiro_nome = $palavras[0];
    $telefone_limpo = preg_replace('/\D/', '', $telefone);
    $ultimos_digitos = substr($telefone_limpo, -4);
    
    return $primeiro_nome . ($ultimos_digitos ? ' ...' . $ultimos_digitos : '');
}

// Processar requisição AJAX para buscar atualizações
if (isset($_GET['check_updates'])) {
    header('Content-Type: application/json');
    
    $lastCheck = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, MAX(updated_at) as last_update
        FROM pedidos 
        WHERE updated_at > ?
    ");
    $stmt->execute([$lastCheck]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'has_updates' => $result['count'] > 0,
        'count' => $result['count'],
        'last_update' => $result['last_update']
    ]);
    exit;
}

try {
    // Cache de estatísticas (60 segundos) para reduzir carga no banco
    $cache_key = 'dashboard_stats_' . date('Y-m-d-H-i');
    $cache_file = sys_get_temp_dir() . '/brbandeiras_' . md5($cache_key) . '.cache';
    
    // Tentar carregar do cache
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 60) {
        $stats = json_decode(file_get_contents($cache_file), true);
        if ($stats === null) {
            $stats = [];
        }
    }
    
    // Se não tem cache válido, buscar do banco
    if (empty($stats) || !isset($stats['cancelado'])) {
        // ✅ OTIMIZAÇÃO: 1 query única em vez de 7 queries separadas
        // Usa FILTER do PostgreSQL para contar múltiplos status de uma vez
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) FILTER (WHERE status = 'cancelado') as cancelado,
                COUNT(*) FILTER (WHERE status = 'orcamento') as orcamento,
                COUNT(*) FILTER (WHERE status = 'arte') as arte,
                COUNT(*) FILTER (WHERE status = 'producao') as producao,
                COUNT(*) FILTER (WHERE status = 'pronto') as pronto,
                COUNT(*) FILTER (WHERE status = 'entregue') as entregue,
                COUNT(*) FILTER (WHERE urgente = true AND status NOT IN ('entregue', 'cancelado')) as urgentes
            FROM pedidos
        ");
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Garantir que todos os valores existam e sejam números
        $stats = array_map('intval', $stats);
        
        // Salvar no cache
        @file_put_contents($cache_file, json_encode($stats));
    }

} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    // Valores padrão em caso de erro
    $stats = [
        'cancelado' => 0,
        'orcamento' => 0,
        'arte' => 0,
        'producao' => 0,
        'pronto' => 0,
        'entregue' => 0,
        'urgentes' => 0
    ];
}

// Filtros
$filtroStatus = $_GET['status'] ?? 'todos';
$filtroUrgente = isset($_GET['urgente']) ? true : false;

// Processar AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'updateStatus':
                $pedido_id = intval($_POST['pedido_id']);
                $novo_status = $_POST['status'];
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$novo_status, $pedido_id]);
                
                $stmt = $pdo->prepare("INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$pedido_id, $novo_status, "Status alterado via dashboard", $_SESSION['user_id']]);
                
                registrarLog('pedido_status_atualizado', "Pedido #$pedido_id movido para $novo_status");
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
                break;
                
            case 'updateStatusBatch':
                $ids = json_decode($_POST['ids']);
                $novo_status = $_POST['status'];
                
                $pdo->beginTransaction();
                
                foreach ($ids as $id) {
                    $id = intval($id);
                    $stmt = $pdo->prepare("UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$novo_status, $id]);
                    
                    $stmt = $pdo->prepare("INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$id, $novo_status, "Status alterado em lote via dashboard", $_SESSION['user_id']]);
                }
                
                registrarLog('pedido_status_lote', count($ids) . " pedidos movidos para $novo_status");
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => count($ids) . ' pedidos atualizados']);
                break;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

try {
    // Query completa para gestor - ORDENADA POR ATIVIDADE RECENTE
    $sql = "
        SELECT 
            p.id,
            p.numero,
            p.status,
            p.urgente,
            p.valor_total,
            p.valor_final,
            p.prazo_entrega,
            p.arte_finalista_id,
            p.created_at,
            p.updated_at,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            u.nome as vendedor_nome,
            pa.arte_finalista_id as arte_responsavel_id,
            ua.nome as arte_finalista_nome,
            (SELECT pc.nome FROM pedido_itens pi LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id WHERE pi.pedido_id = p.id ORDER BY pi.id LIMIT 1) as primeiro_produto
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios ua ON pa.arte_finalista_id = ua.id
        WHERE 1=1
    ";

    $params = [];

    // Aplicar filtros
    if ($filtroStatus !== 'todos') {
        $sql .= " AND p.status = ?";
        $params[] = $filtroStatus;
    }

    if ($filtroUrgente) {
        $sql .= " AND p.urgente = true";
    }

    // ORDENAR POR ATIVIDADE MAIS RECENTE
    $sql .= " ORDER BY p.updated_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar pedidos por status para Kanban (apenas 4 status principais)
    $pedidosPorStatus = [
        'orcamento' => [],
        'arte' => [],
        'producao' => [],
        'pronto' => []
    ];
    
    // Organizar pedidos por data para calendário
    $pedidosPorData = [];
    
    foreach ($pedidos as $pedido) {
        // Para Kanban
        if (isset($pedidosPorStatus[$pedido['status']])) {
            $pedidosPorStatus[$pedido['status']][] = $pedido;
        }
        
        // Para Calendário - organizar por prazo de entrega
        if ($pedido['prazo_entrega']) {
            $data = date('Y-m-d', strtotime($pedido['prazo_entrega']));
            if (!isset($pedidosPorData[$data])) {
                $pedidosPorData[$data] = [];
            }
            $pedidosPorData[$data][] = $pedido;
        }
    }

} catch (PDOException $e) {
    die("Erro na consulta SQL: " . $e->getMessage());
}

// Configuração de status com as novas cores
$statusConfig = [
    'cancelado' => ['color' => 'bg-red-500', 'label' => 'CANCELADO', 'textColor' => 'text-red-600', 'borderColor' => 'border-red-200', 'bgLight' => 'bg-red-50'],
    'orcamento' => ['color' => 'bg-green-800', 'label' => 'COMERCIAL', 'textColor' => 'text-green-800', 'borderColor' => 'border-green-300', 'bgLight' => 'bg-green-50'],
    'arte' => ['color' => 'bg-lime-600', 'label' => 'ARTE', 'textColor' => 'text-lime-700', 'borderColor' => 'border-lime-300', 'bgLight' => 'bg-lime-50'],
    'producao' => ['color' => 'bg-yellow-500', 'label' => 'PRODUÇÃO', 'textColor' => 'text-yellow-700', 'borderColor' => 'border-yellow-300', 'bgLight' => 'bg-yellow-50'],
    'pronto' => ['color' => 'bg-amber-400', 'label' => 'EXPEDIÇÃO', 'textColor' => 'text-amber-700', 'borderColor' => 'border-amber-300', 'bgLight' => 'bg-amber-50'],
    'entregue' => ['color' => 'bg-gray-500', 'label' => 'ENTREGUE', 'textColor' => 'text-gray-600', 'borderColor' => 'border-gray-200', 'bgLight' => 'bg-gray-50']
];

// Gerar dias do mês para o calendário
$mesAtual = date('n');
$anoAtual = date('Y');
$primeiroDia = mktime(0, 0, 0, $mesAtual, 1, $anoAtual);
$diasNoMes = date('t', $primeiroDia);
$diaSemanaInicio = date('w', $primeiroDia);

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$titulo = 'Dashboard - Gestor';
include '../../views/layouts/_header.php';

// CSS inline minificado
$cssInline = '
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.new-item {
    animation: slideDown 0.5s ease-out;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.updating {
    animation: pulse 1s infinite;
}

@keyframes fadeOutScale {
    to {
        opacity: 0;
        transform: scale(0.9);
    }
}

.removing {
    animation: fadeOutScale 0.3s ease-out forwards;
}

/* Estilos do Kanban */
.kanban-column {
    min-height: 500px;
    max-height: calc(100vh - 320px);
    overflow-y: auto;
    overflow-x: hidden;
}

/* Responsividade para mobile */
@media (max-width: 768px) {
    .kanban-column {
        min-height: 400px;
        max-height: 500px;
    }
}

/* Scrollbar customizada para Kanban */
.kanban-column::-webkit-scrollbar {
    width: 6px;
}

.kanban-column::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 3px;
}

.kanban-column::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.kanban-column::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.kanban-card {
    transition: all 0.3s ease;
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    cursor: grab;
}

.kanban-card:active {
    cursor: grabbing;
}

.drag-over {
    background-color: #f0f9ff;
    border-color: #3b82f6;
}

/* Container do Kanban responsivo */
.kanban-container {
    display: grid;
    gap: 1rem;
    min-height: 600px; /* Altura mínima para evitar layout shift (CLS) */
    /* Skeleton loader enquanto carrega */
    background: linear-gradient(90deg, #f9fafb 25%, #f3f4f6 50%, #f9fafb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

/* Remover skeleton quando conteúdo carregar */
.kanban-container:not(:empty) {
    background: transparent;
    animation: none;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Desktop - 4 colunas */
@media (min-width: 1280px) {
    .kanban-container {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Tablet landscape - 2 colunas */
@media (min-width: 768px) and (max-width: 1279px) {
    .kanban-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile - 1 coluna */
@media (max-width: 767px) {
    .kanban-container {
        grid-template-columns: 1fr;
    }
    
    .kanban-column {
        min-height: 300px;
        max-height: 400px;
    }
}

/* Estilos da Tabela aprimorados */
.table-container {
    border-radius: 0.75rem;
    overflow: hidden;
}

.table-striped tbody tr:nth-child(odd) {
    background-color: #fafafa;
}

.table-striped tbody tr:nth-child(even) {
    background-color: #ffffff;
}

.table-striped tbody tr:hover {
    background-color: #f0f9ff !important;
    transition: background-color 0.2s ease;
}

.table-striped td {
    padding: 1rem 1.25rem;
    vertical-align: middle;
}

.table-striped th {
    padding: 1rem 1.25rem;
    background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}

/* Estilos do Calendário aprimorados */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #e5e7eb;
}

.calendar-day {
    background-color: white;
    min-height: 120px;
    padding: 0.5rem;
    position: relative;
    transition: all 0.2s ease;
}

.calendar-day:hover {
    background-color: #f9fafb;
    box-shadow: inset 0 0 0 2px #3b82f6;
}

.calendar-day-header {
    font-weight: bold;
    color: #374151;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.calendar-day-today {
    background-color: #eff6ff;
    border: 2px solid #3b82f6;
}

.calendar-day-content {
    max-height: 100px;
    overflow-y: auto;
    padding-right: 2px;
}

.calendar-day-content::-webkit-scrollbar {
    width: 4px;
}

.calendar-day-content::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 2px;
}

.calendar-item {
    font-size: 0.7rem;
    padding: 3px 6px;
    margin-bottom: 3px;
    border-radius: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    border-left: 3px solid;
    transition: all 0.2s ease;
}

.calendar-item:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.calendar-more-items {
    font-size: 0.65rem;
    color: #6b7280;
    text-align: center;
    margin-top: 4px;
    cursor: pointer;
    padding: 2px;
    border-radius: 3px;
    background-color: #f3f4f6;
}

.calendar-more-items:hover {
    background-color: #e5e7eb;
}

@media (max-width: 768px) {
    .calendar-day {
        min-height: 80px;
    }
    
    .calendar-item {
        font-size: 0.6rem;
        padding: 2px 4px;
    }
}
';
?><style><?= minifyInline($cssInline, 'css') ?></style>

<div class="flex-1 bg-gray-50" x-data="dashboardGestor()">
    <div class="p-4 md:p-6">
        <!-- Tabs de Visualização e Botão Novo Orçamento -->
        <div class="bg-white rounded-xl shadow-sm border flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 mb-6">
            <div class="flex gap-1 mb-3 sm:mb-0">
                <button 
                    @click="viewMode = 'kanban'" 
                    :class="viewMode === 'kanban' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-3 md:px-4 py-2 rounded-lg font-medium transition-all flex items-center gap-2 text-sm md:text-base"
                >
                    <i class="fas fa-columns"></i>
                    <span class="hidden sm:inline">Kanban</span>
                </button>
                <button 
                    @click="viewMode = 'table'" 
                    :class="viewMode === 'table' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-3 md:px-4 py-2 rounded-lg font-medium transition-all flex items-center gap-2 text-sm md:text-base"
                >
                    <i class="fas fa-table"></i>
                    <span class="hidden sm:inline">Tabela</span>
                </button>
                <button 
                    @click="viewMode = 'calendar'" 
                    :class="viewMode === 'calendar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-3 md:px-4 py-2 rounded-lg font-medium transition-all flex items-center gap-2 text-sm md:text-base"
                >
                    <i class="fas fa-calendar"></i>
                    <span class="hidden sm:inline">Calendário</span>
                </button>
            </div>
            
            <!-- Indicador de Atualização e Botão Novo -->
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div x-show="checking" class="flex items-center gap-2 text-gray-500 text-sm">
                    <i class="fas fa-sync-alt fa-spin"></i>
                    <span class="hidden md:inline">Verificando...</span>
                </div>
                <div x-show="hasUpdates" x-cloak class="flex items-center gap-2 text-green-600">
                    <i class="fas fa-bell animate-bounce"></i>
                    <button @click="location.reload()" class="bg-green-100 hover:bg-green-200 px-3 py-1 rounded text-sm">
                        Atualizar
                    </button>
                </div>
             
				<button onclick="abrirNovoPedidoModal()" 
        class="px-4 py-2 bg-green-600 text-white rounded-lg">
    Novo Pedido de Arte
</button>

<script>
function abrirNovoPedidoModal(clienteId = null) {
    const baseUrl = '<?= $baseUrl ?>';
    const url = clienteId ? 
        `${baseUrl}pedidos/pedido_novo.php?cliente_id=${clienteId}` : 
        `${baseUrl}pedidos/pedido_novo.php`;
    
    // Opção 1: Modal em iframe
    const modal = document.createElement('div');
    modal.innerHTML = `
        <div class="fixed inset-0 z-50 bg-black bg-opacity-50">
            <iframe src="${url}" 
                    class="w-full h-full" 
                    frameborder="0">
            </iframe>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Opção 2: Nova janela
    // window.open(url, 'novo_pedido', 'width=1200,height=800');
}
</script>
				
				
	
            </div>
        </div>
        
        <!-- Cards de Status (KPIs) - Apenas na visualização de tabela -->
        <div x-show="viewMode === 'table'" x-cloak class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-6">
            <?php 
            $kpiStatus = ['orcamento', 'arte', 'producao', 'pronto'];
            foreach ($kpiStatus as $status): 
                $config = $statusConfig[$status];
            ?>
            <div 
                class="<?= $config['color'] ?> p-4 md:p-6 rounded-xl relative overflow-hidden transition-all hover:scale-105 cursor-pointer shadow-md"
                :class="{ 'ring-4 ring-blue-400 scale-105': activeFilter === '<?= $status ?>' }"
                @click="toggleStatusFilter('<?= $status ?>')"
            >
                <div class="flex items-center justify-between mb-2 md:mb-3">
                    <i class="fas fa-<?= getIconForStatus($status) ?> text-white text-2xl md:text-3xl"></i>
                    <span class="text-3xl md:text-4xl font-bold text-white"><?= $stats[$status] ?></span>
                </div>
                <div class="text-white font-semibold text-base md:text-lg"><?= $config['label'] ?></div>
                <div class="text-xs mt-1 md:mt-2 opacity-80 text-white">
                    <span x-show="activeFilter === '<?= $status ?>'">Filtro ativo</span>
                    <span x-show="activeFilter !== '<?= $status ?>'">Clique para filtrar</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Ações em Lote (apenas na view de tabela) -->
        <div x-show="selectedIds.length > 0 && viewMode === 'table'" x-cloak class="bg-blue-50 border border-blue-200 p-3 md:p-4 rounded-xl mb-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <span class="text-blue-800 font-medium">
                <span x-text="selectedIds.length"></span> OS selecionada(s)
            </span>
            <div class="flex flex-wrap gap-2">
                <button @click="updateStatusBatch('orcamento')" class="bg-green-800 hover:bg-green-900 text-white px-3 md:px-4 py-1.5 md:py-2 rounded-lg text-sm font-semibold">Comercial</button>
                <button @click="updateStatusBatch('arte')" class="bg-lime-600 hover:bg-lime-700 text-white px-3 md:px-4 py-1.5 md:py-2 rounded-lg text-sm font-semibold">Arte</button>
                <button @click="updateStatusBatch('producao')" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 md:px-4 py-1.5 md:py-2 rounded-lg text-sm font-semibold">Produção</button>
                <button @click="updateStatusBatch('pronto')" class="bg-amber-400 hover:bg-amber-500 text-white px-3 md:px-4 py-1.5 md:py-2 rounded-lg text-sm font-semibold">Expedição</button>
                <button @click="selectedIds = []" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 md:px-4 py-1.5 md:py-2 rounded-lg text-sm">Limpar</button>
            </div>
        </div>

        <!-- Visualização Kanban - Default -->
        <div x-show="viewMode === 'kanban'" class="kanban-container">
            <?php 
            $kanbanStatus = ['orcamento', 'arte', 'producao', 'pronto'];
            foreach ($kanbanStatus as $status): 
                $config = $statusConfig[$status];
            ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Header estilo KPI -->
                <div class="<?= $config['color'] ?> text-white p-4 md:p-6">
                    <div class="flex items-center justify-between mb-3">
                        <i class="fas fa-<?= getIconForStatus($status) ?> text-3xl md:text-4xl"></i>
                        <span class="text-4xl md:text-5xl font-bold"><?= count($pedidosPorStatus[$status] ?? []) ?></span>
                    </div>
                    <div class="font-bold text-lg md:text-xl"><?= $config['label'] ?></div>
                    <div class="text-xs md:text-sm opacity-90 mt-1">
                        <?php 
                        $total = 0;
                        foreach ($pedidosPorStatus[$status] ?? [] as $p) {
                            $total += $p['valor_final'] ?? $p['valor_total'] ?? 0;
                        }
                        echo "Total: " . formatarMoeda($total);
                        ?>
                    </div>
                </div>
                <div 
                    class="p-3 md:p-4 kanban-column space-y-3 bg-gray-50"
                    @dragover.prevent="$el.classList.add('drag-over')"
                    @dragleave="$el.classList.remove('drag-over')"
                    @drop="handleDropKanban($event, '<?= $status ?>')"
                >
                    <?php if (isset($pedidosPorStatus[$status])): ?>
                        <?php foreach ($pedidosPorStatus[$status] as $pedido): ?>
                        <div 
                            class="kanban-card bg-white border-2 <?= $config['borderColor'] ?> rounded-lg p-3 md:p-4 cursor-move"
                            draggable="true"
                            @dragstart="handleDragStart($event, <?= $pedido['id'] ?>)"
                            data-pedido-id="<?= $pedido['id'] ?>"
                        >
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-bold <?= $config['textColor'] ?> text-sm md:text-base">
                                    #<?= htmlspecialchars($pedido['numero']) ?>
                                </span>
                                <?php if ($pedido['urgente']): ?>
                                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded animate-pulse">URG</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-sm text-gray-600 space-y-1">
                                <div class="font-medium truncate">
                                    <?= htmlspecialchars(formatarNomeCliente($pedido['cliente_nome'] ?? '', $pedido['cliente_telefone'] ?? '')) ?>
                                </div>
                                <div class="text-xs text-gray-500 truncate">
                                    <?= htmlspecialchars($pedido['primeiro_produto'] ?? 'Sem produto') ?>
                                </div>
                                <?php if ($pedido['arte_finalista_nome']): ?>
                                <div class="text-xs <?= $config['textColor'] ?> flex items-center gap-1">
                                    <i class="fas fa-palette"></i>
                                    <?= htmlspecialchars($pedido['arte_finalista_nome']) ?>
                                </div>
                                <?php endif; ?>
                                <div class="font-bold <?= $config['textColor'] ?> text-right pt-2 text-sm md:text-base">
                                    <?= formatarMoeda($pedido['valor_final'] ?? $pedido['valor_total'] ?? 0) ?>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center mt-3 pt-3 border-t">
                                <span class="text-xs text-gray-400">
                                    <?php
                                    $updated = new DateTime($pedido['updated_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($updated);
                                    
                                    if ($diff->days == 0) {
                                        echo $diff->h == 0 ? $diff->i . 'min' : $diff->h . 'h';
                                    } else {
                                        echo $diff->days . 'd';
                                    }
                                    ?>
                                </span>
                                <div class="flex gap-1">
                                    <?php if ($status === 'orcamento' || $status === 'arte'): ?>
                                    <button 
                                        @click.stop="quickUpdateStatus(<?= $pedido['id'] ?>, 'cancelado')"
                                        class="text-red-600 hover:text-red-700 hover:bg-red-50 p-1 rounded transition-all"
                                        title="Cancelar OS"
                                    >
                                        <i class="fas fa-times-circle text-sm"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($status === 'pronto'): ?>
                                    <button 
                                        @click.stop="pendingStatus = 'entregue'; pendingSingleId = <?= $pedido['id'] ?>; paymentAlert = true;"
                                        class="text-gray-600 hover:text-gray-700 hover:bg-gray-50 p-1 rounded transition-all"
                                        title="Marcar como Entregue"
                                    >
                                        <i class="fas fa-check-circle text-sm"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" class="text-blue-600 hover:text-blue-700 p-1" title="Ver">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <a href="pedido_editar.php?id=<?= $pedido['id'] ?>" class="text-green-600 hover:text-green-700 p-1" title="Editar">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (empty($pedidosPorStatus[$status])): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-2"></i>
                        <p class="text-sm">Nenhuma OS</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Visualização Tabela com zebra striping -->
        <div x-show="viewMode === 'table'" x-cloak class="table-container bg-white shadow-lg border">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] table-striped">
                    <thead>
                        <tr>
                            <th class="w-12 text-left"><input type="checkbox" @change="toggleSelectAll($event)" class="w-4 h-4 text-blue-600 rounded"></th>
                            <th class="text-left">OS</th>
                            <th class="text-left">Produto</th>
                            <th class="text-left">Cliente</th>
                            <th class="text-left hidden lg:table-cell">Vendedor</th>
                            <th class="text-left hidden xl:table-cell">Arte-finalista</th>
                            <th class="text-left">Valor</th>
                            <th class="text-left">Status</th>
                            <th class="text-left hidden md:table-cell">Atualizado</th>
                            <th class="text-left">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-12">
                                <div class="flex flex-col items-center gap-4">
                                    <i class="fas fa-inbox text-5xl md:text-6xl text-gray-300"></i>
                                    <p class="text-lg md:text-xl text-gray-500">Nenhuma OS encontrada</p>
                                    <a href="<?= $baseUrl ?>pedidos/pedido_novo.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 md:px-6 py-2 md:py-3 rounded-lg inline-flex items-center gap-2">
                                        <i class="fas fa-plus"></i>
                                        Criar Primeira OS
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($pedidos as $index => $pedido): 
                            $status = $pedido['status'];
                            $config = $statusConfig[$status] ?? $statusConfig['orcamento'];
                            $isRecent = strtotime($pedido['updated_at']) > strtotime('-5 minutes');
                        ?>
                        <tr class="<?= !in_array($pedido['status'], ['cancelado', 'entregue']) ? 'cursor-move' : '' ?> <?= $isRecent ? 'new-item' : '' ?> border-b border-gray-100" 
                            <?php if (!in_array($pedido['status'], ['cancelado', 'entregue'])): ?>
                            draggable="true" 
                            @dragstart="handleDragStart($event, <?= $pedido['id'] ?>)"
                            <?php endif; ?>
                            data-pedido-id="<?= $pedido['id'] ?>"
                            data-updated="<?= $pedido['updated_at'] ?>">
                            <td>
                                <input type="checkbox" value="<?= $pedido['id'] ?>" @change="toggleSelect(<?= $pedido['id'] ?>)" :checked="selectedIds.includes(<?= $pedido['id'] ?>)" class="w-4 h-4 text-blue-600 rounded">
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-900 font-bold text-sm md:text-base"><?= htmlspecialchars($pedido['numero']) ?></span>
                                    <?php if ($pedido['urgente']): ?>
                                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse font-semibold">URGENTE</span>
                                    <?php endif; ?>
                                    <?php if ($isRecent): ?>
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">NOVO</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="text-gray-700 text-xs md:text-sm">
                                    <?= htmlspecialchars(substr($pedido['primeiro_produto'] ?? 'Sem produto', 0, 25)) ?>
                                    <?php if (strlen($pedido['primeiro_produto'] ?? '') > 25): ?>...<?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700 text-sm md:text-base">
                                    <?= htmlspecialchars(formatarNomeCliente($pedido['cliente_nome'] ?? '', $pedido['cliente_telefone'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="text-gray-600 hidden lg:table-cell text-sm md:text-base"><?= htmlspecialchars($pedido['vendedor_nome'] ?? '') ?></td>
                            <td class="hidden xl:table-cell">
                                <?php if ($status === 'arte' || $pedido['arte_finalista_nome']): ?>
                                    <?php if ($pedido['arte_finalista_nome']): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-user-check text-lime-600 text-sm"></i>
                                            <span class="text-lime-700 font-medium text-sm"><?= htmlspecialchars($pedido['arte_finalista_nome']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-clock text-orange-500 text-sm"></i>
                                            <span class="text-orange-600 italic text-sm">Aguardando</span>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= $pedido['urgente'] ? 'text-red-600 font-bold' : 'text-gray-700' ?> font-medium text-sm md:text-base">
                                    <?= formatarMoeda($pedido['valor_final'] ?? $pedido['valor_total'] ?? 0) ?>
                                </span>
                            </td>
                            <td>
                                <div class="inline-flex items-center gap-2 px-2 md:px-3 py-1 rounded-full <?= $config['color'] ?> shadow-sm">
                                    <i class="fas fa-<?= getIconForStatus($status) ?> text-white text-xs md:text-sm"></i>
                                    <span class="text-white text-xs md:text-sm font-semibold"><?= $config['label'] ?></span>
                                    <?php if (in_array($status, ['cancelado', 'entregue'])): ?>
                                    <i class="fas fa-lock text-white text-xs" title="Status final"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-gray-500 text-xs md:text-sm hidden md:table-cell">
                                <?php
                                $updated = new DateTime($pedido['updated_at']);
                                $now = new DateTime();
                                $diff = $now->diff($updated);
                                
                                if ($diff->days == 0) {
                                    if ($diff->h == 0) {
                                        echo $diff->i . ' min atrás';
                                    } else {
                                        echo $diff->h . 'h atrás';
                                    }
                                } elseif ($diff->days == 1) {
                                    echo 'Ontem';
                                } else {
                                    echo $diff->days . ' dias atrás';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="flex items-center gap-1 md:gap-2">
                                    <?php if ($pedido['status'] === 'orcamento' || $pedido['status'] === 'arte'): ?>
                                    <button 
                                        @click="quickUpdateStatus(<?= $pedido['id'] ?>, 'cancelado')"
                                        class="text-red-600 hover:text-red-700 p-1.5 rounded hover:bg-red-50 transition-all"
                                        title="Cancelar OS"
                                    >
                                        <i class="fas fa-times-circle text-sm"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($pedido['status'] === 'pronto'): ?>
                                    <button 
                                        @click="pendingStatus = 'entregue'; pendingSingleId = <?= $pedido['id'] ?>; paymentAlert = true;"
                                        class="text-gray-600 hover:text-gray-700 p-1.5 rounded hover:bg-gray-50 transition-all"
                                        title="Marcar como Entregue"
                                    >
                                        <i class="fas fa-check-circle text-sm"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" class="text-blue-600 hover:text-blue-700 p-1.5 rounded hover:bg-blue-50 transition-all" title="Visualizar">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <a href="pedido_editar.php?id=<?= $pedido['id'] ?>" class="text-green-600 hover:text-green-700 p-1.5 rounded hover:bg-green-50 transition-all" title="Editar">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Visualização Calendário Aprimorada -->
        <div x-show="viewMode === 'calendar'" x-cloak class="bg-white rounded-xl shadow-lg p-4">
            <!-- Header do Calendário com Filtros -->
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-4 gap-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-800">
                        <?= $meses[$mesAtual] ?> <?= $anoAtual ?>
                    </h2>
                    <div class="flex gap-2">
                        <button @click="previousMonth()" class="p-2 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button @click="nextMonth()" class="p-2 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Filtros do Calendário -->
                <div class="flex flex-wrap gap-2">
                    <button 
                        @click="calendarFilter = 'todos'"
                        :class="calendarFilter === 'todos' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="px-3 py-1 rounded-lg text-sm font-medium transition-all"
                    >
                        Todos
                    </button>
                    <?php foreach ($kanbanStatus as $status): 
                        $config = $statusConfig[$status];
                    ?>
                    <button 
                        @click="calendarFilter = '<?= $status ?>'"
                        :class="calendarFilter === '<?= $status ?>' ? '<?= $config['color'] ?> text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="px-3 py-1 rounded-lg text-sm font-medium transition-all"
                    >
                        <?= $config['label'] ?>
                    </button>
                    <?php endforeach; ?>
                    
                    <label class="flex items-center gap-2 ml-3">
                        <input type="checkbox" x-model="showOnlyUrgent" class="w-4 h-4 text-red-600 rounded">
                        <span class="text-sm text-gray-700">Apenas Urgentes</span>
                    </label>
                </div>
            </div>
            
            <!-- Dias da Semana -->
            <div class="grid grid-cols-7 gap-1 mb-1">
                <?php 
                $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                foreach ($diasSemana as $dia): 
                ?>
                <div class="text-center font-bold text-gray-600 p-2 text-sm md:text-base bg-gray-50 rounded">
                    <?= $dia ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Grid do Calendário -->
            <div class="calendar-grid rounded-lg overflow-hidden border">
                <?php 
                // Dias vazios antes do primeiro dia do mês
                for ($i = 0; $i < $diaSemanaInicio; $i++): 
                ?>
                <div class="calendar-day bg-gray-50"></div>
                <?php endfor; ?>
                
                <?php 
                // Dias do mês
                for ($dia = 1; $dia <= $diasNoMes; $dia++): 
                    $dataAtual = sprintf('%04d-%02d-%02d', $anoAtual, $mesAtual, $dia);
                    $pedidosDoDia = $pedidosPorData[$dataAtual] ?? [];
                    $isToday = $dataAtual === date('Y-m-d');
                ?>
                <div class="calendar-day <?= $isToday ? 'calendar-day-today' : '' ?>">
                    <div class="calendar-day-header">
                        <span><?= $dia ?></span>
                        <?php if (count($pedidosDoDia) > 0): ?>
                        <span class="bg-gray-200 text-gray-700 text-xs px-2 py-0.5 rounded-full">
                            <?= count($pedidosDoDia) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="calendar-day-content">
                        <?php 
                        $itemsShown = 0;
                        $maxItems = 4; // Máximo de itens visíveis por dia
                        foreach ($pedidosDoDia as $pedido): 
                            $config = $statusConfig[$pedido['status']] ?? $statusConfig['orcamento'];
                            if ($itemsShown >= $maxItems) break;
                            $itemsShown++;
                        ?>
                        <div 
                            class="calendar-item <?= $config['bgLight'] ?> <?= $config['textColor'] ?> <?= $config['borderColor'] ?>"
                            x-show="calendarFilter === 'todos' || calendarFilter === '<?= $pedido['status'] ?>'"
                            x-show="!showOnlyUrgent || <?= $pedido['urgente'] ? 'true' : 'false' ?>"
                            @click="showPedidoDetails(<?= $pedido['id'] ?>)"
                            title="#<?= htmlspecialchars($pedido['numero']) ?> - <?= htmlspecialchars($pedido['cliente_nome'] ?? 'Sem cliente') ?>"
                        >
                            <div class="flex items-center gap-1">
                                <?php if ($pedido['urgente']): ?>
                                <span class="text-red-500 font-bold">!</span>
                                <?php endif; ?>
                                <span class="font-semibold">#<?= htmlspecialchars($pedido['numero']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($pedidosDoDia) > $maxItems): ?>
                        <div 
                            class="calendar-more-items"
                            @click="showDayDetails('<?= $dataAtual ?>')"
                        >
                            +<?= count($pedidosDoDia) - $maxItems ?> mais
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
                
                <?php 
                // Dias vazios após o último dia do mês
                $diasRestantes = 7 - (($diaSemanaInicio + $diasNoMes) % 7);
                if ($diasRestantes < 7):
                    for ($i = 0; $i < $diasRestantes; $i++): 
                ?>
                <div class="calendar-day bg-gray-50"></div>
                <?php 
                    endfor;
                endif;
                ?>
            </div>
            
            <!-- Resumo e Legenda -->
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Legenda de Status -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 mb-3">Legenda de Status</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($kanbanStatus as $status): 
                            $config = $statusConfig[$status];
                        ?>
                        <div class="flex items-center gap-2 text-sm">
                            <div class="w-4 h-4 <?= $config['color'] ?> rounded border <?= $config['borderColor'] ?>"></div>
                            <span class="text-gray-600"><?= $config['label'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Resumo do Mês -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 mb-3">Resumo do Mês</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <?php 
                        $totalMes = 0;
                        $urgentesMes = 0;
                        foreach ($pedidosPorData as $data => $pedidosDia) {
                            if (strpos($data, date('Y-m')) === 0) {
                                $totalMes += count($pedidosDia);
                                foreach ($pedidosDia as $p) {
                                    if ($p['urgente']) $urgentesMes++;
                                }
                            }
                        }
                        ?>
                        <div>
                            <span class="text-gray-500">Total de OS:</span>
                            <span class="font-semibold text-gray-800 ml-2"><?= $totalMes ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Urgentes:</span>
                            <span class="font-semibold text-red-600 ml-2"><?= $urgentesMes ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Alerta de Pagamento -->
    <div x-show="paymentAlert" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="paymentAlert = false" class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            
            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-4 md:p-6 transform transition-all">
                <div class="flex items-center gap-3 md:gap-4 mb-4">
                    <div class="bg-yellow-100 p-2 md:p-3 rounded-full">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl md:text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg md:text-xl font-bold text-gray-900">Atenção ao Pagamento!</h3>
                        <p class="text-gray-600 text-xs md:text-sm mt-1">Verificação obrigatória antes de prosseguir</p>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 md:p-4 mb-4 md:mb-6">
                    <p class="text-gray-800 font-medium mb-2 text-sm md:text-base">
                        <i class="fas fa-hand-holding-usd text-yellow-600 mr-2"></i>
                        Antes de mover para <span x-text="pendingStatus?.toUpperCase()" class="font-bold text-yellow-700"></span>:
                    </p>
                    <ul class="space-y-2 ml-4 md:ml-6">
                        <li class="text-gray-700 flex items-start gap-2 text-sm md:text-base">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <span>Confirme que o pagamento foi recebido</span>
                        </li>
                        <li class="text-gray-700 flex items-start gap-2 text-sm md:text-base">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <span>Verifique o valor total do pedido</span>
                        </li>
                        <li class="text-gray-700 flex items-start gap-2 text-sm md:text-base" x-show="pendingStatus === 'entregue'">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <span>Garanta que o pagamento final foi efetuado</span>
                        </li>
                    </ul>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2 md:gap-3">
                    <button 
                        @click="confirmPaymentAndUpdate()" 
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 md:py-3 px-4 rounded-lg transition-all text-sm md:text-base"
                    >
                        <i class="fas fa-check mr-2"></i>
                        Pagamento OK, Continuar
                    </button>
                    <button 
                        @click="paymentAlert = false; pendingStatus = null; pendingIds = null" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2.5 md:py-3 px-4 rounded-lg transition-all text-sm md:text-base"
                    >
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notificação -->
    <div x-show="notification" x-cloak class="fixed bottom-4 md:bottom-8 right-4 md:right-8 z-50">
        <div :class="notification?.type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-4 md:px-6 py-3 md:py-4 rounded-xl shadow-2xl flex items-center gap-3 text-sm md:text-base">
            <i class="fas" :class="notification?.type === 'success' ? 'fa-check-circle' : 'fa-times-circle'"></i>
            <span x-text="notification?.message"></span>
        </div>
    </div>
</div>

<script>
function dashboardGestor() {
    return {
        selectedIds: [],
        notification: null,
        draggedId: null,
        activeFilter: '<?= $filtroStatus === 'todos' ? '' : $filtroStatus ?>',
        urgenteFilter: <?= $filtroUrgente ? 'true' : 'false' ?>,
        paymentAlert: false,
        pendingStatus: null,
        pendingIds: null,
        pendingSingleId: null,
        viewMode: 'kanban', // Kanban como default
        checking: false,
        hasUpdates: false,
        lastCheck: '<?= date('Y-m-d H:i:s') ?>',
        currentMonth: <?= $mesAtual ?>,
        currentYear: <?= $anoAtual ?>,
        calendarFilter: 'todos',
        showOnlyUrgent: false,

        init() {
            // Verificar atualizações a cada 30 segundos
            this.checkUpdates();
            setInterval(() => this.checkUpdates(), 30000);
        },

        async checkUpdates() {
            this.checking = true;
            try {
                const response = await fetch(`dashboard_gestor.php?check_updates=1&last_check=${this.lastCheck}`);
                const data = await response.json();
                
                if (data.has_updates) {
                    this.hasUpdates = true;
                }
                
                if (data.last_update) {
                    this.lastCheck = data.last_update;
                }
            } catch (error) {
                console.error('Erro ao verificar atualizações:', error);
            } finally {
                this.checking = false;
            }
        },

        toggleStatusFilter(status) {
            if (this.activeFilter === status) {
                this.activeFilter = '';
                window.location.href = 'dashboard_gestor.php';
            } else {
                this.activeFilter = status;
                window.location.href = 'dashboard_gestor.php?status=' + status;
            }
        },

        toggleSelect(id) {
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter(i => i !== id);
            } else {
                this.selectedIds.push(id);
            }
        },

        toggleSelectAll(event) {
            if (event.target.checked) {
                const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
                this.selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            } else {
                this.selectedIds = [];
            }
        },

        handleDragStart(event, id) {
            this.draggedId = id;
            event.dataTransfer.effectAllowed = 'move';
            event.target.style.opacity = '0.5';
        },

        handleDropKanban(event, newStatus) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            if (this.draggedId) {
                if (newStatus === 'cancelado' || newStatus === 'entregue') {
                    this.showNotification('Use os botões de ação rápida para cancelar ou marcar como entregue', 'error');
                    this.resetDraggedCard();
                    return;
                }
                
                if (newStatus === 'producao') {
                    this.pendingStatus = newStatus;
                    this.pendingSingleId = this.draggedId;
                    this.paymentAlert = true;
                } else {
                    this.quickUpdateStatus(this.draggedId, newStatus);
                }
                
                this.resetDraggedCard();
            }
        },
        
        resetDraggedCard() {
            const draggedCard = document.querySelector(`[data-pedido-id="${this.draggedId}"]`);
            if (draggedCard) {
                draggedCard.style.opacity = '1';
            }
            this.draggedId = null;
        },

        async quickUpdateStatus(id, status) {
            if (status === 'cancelado') {
                if (!confirm('Tem certeza que deseja CANCELAR esta OS? Esta ação não pode ser desfeita.')) {
                    return;
                }
            }
            
            const formData = new FormData();
            formData.append('action', 'updateStatus');
            formData.append('pedido_id', id);
            formData.append('status', status);

            try {
                const response = await fetch('dashboard_gestor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('OS atualizada com sucesso', 'success');
                    
                    const element = document.querySelector(`[data-pedido-id="${id}"]`);
                    if (element) {
                        if (status === 'cancelado') {
                            element.classList.add('removing');
                        } else {
                            element.classList.add('updating');
                        }
                    }
                    
                    setTimeout(() => location.reload(), status === 'cancelado' ? 500 : 1000);
                } else {
                    this.showNotification(data.message, 'error');
                }
            } catch (error) {
                this.showNotification('Erro ao atualizar status', 'error');
            }
        },

        async updateStatusBatch(status) {
            if (this.selectedIds.length === 0) {
                alert('Selecione pelo menos uma OS');
                return;
            }

            if (status === 'producao' || status === 'entregue') {
                this.pendingStatus = status;
                this.pendingIds = [...this.selectedIds];
                this.paymentAlert = true;
                return;
            }

            if (!confirm(`Atualizar ${this.selectedIds.length} OS para ${status.toUpperCase()}?`)) {
                return;
            }

            this.performBatchUpdate(this.selectedIds, status);
        },

        async performBatchUpdate(ids, status) {
            const formData = new FormData();
            formData.append('action', 'updateStatusBatch');
            formData.append('ids', JSON.stringify(ids));
            formData.append('status', status);

            try {
                const response = await fetch('dashboard_gestor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification(data.message, 'success');
                    this.selectedIds = [];
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotification(data.message, 'error');
                }
            } catch (error) {
                this.showNotification('Erro ao atualizar status', 'error');
            }
        },

        confirmPaymentAndUpdate() {
            this.paymentAlert = false;
            
            if (this.pendingSingleId) {
                this.quickUpdateStatus(this.pendingSingleId, this.pendingStatus);
                this.pendingSingleId = null;
            } else if (this.pendingIds) {
                this.performBatchUpdate(this.pendingIds, this.pendingStatus);
                this.pendingIds = null;
            }
            
            this.pendingStatus = null;
        },

        showNotification(message, type) {
            this.notification = { message, type };
            setTimeout(() => {
                this.notification = null;
            }, 3000);
        },

        showPedidoDetails(id) {
            window.location.href = `pedido_detalhes.php?id=${id}`;
        },
        
        showDayDetails(date) {
            // Você pode implementar um modal com todos os pedidos do dia
            alert(`Mostrando todos os pedidos de ${date}`);
        },

        previousMonth() {
            // Por enquanto apenas visual, você pode implementar navegação real depois
            alert('Navegação do calendário será implementada');
        },

        nextMonth() {
            // Por enquanto apenas visual, você pode implementar navegação real depois  
            alert('Navegação do calendário será implementada');
        }
    }
}
</script>

<?php include '../../views/layouts/_footer.php'; ?>