<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['vendedor', 'gestor']);

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';
$filtro_periodo = $_GET['periodo'] ?? 'mes';

// Query base
$sql = "SELECT p.*, c.nome as cliente_nome, u.nome as vendedor_nome 
        FROM pedidos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        LEFT JOIN usuarios u ON p.vendedor_id = u.id 
        WHERE 1=1";

$params = [];

// Aplicar filtros
if ($filtro_status) {
    $sql .= " AND p.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_busca) {
    $sql .= " AND (p.numero LIKE ? OR c.nome LIKE ?)";
    $params[] = "%$filtro_busca%";
    $params[] = "%$filtro_busca%";
}

// Filtro de período
if ($filtro_periodo === 'hoje') {
    $sql .= " AND DATE(p.created_at) = CURRENT_DATE";
} elseif ($filtro_periodo === 'semana') {
    $sql .= " AND p.created_at >= CURRENT_DATE - INTERVAL '7 days'";
} elseif ($filtro_periodo === 'mes') {
    $sql .= " AND DATE_TRUNC('month', p.created_at) = DATE_TRUNC('month', CURRENT_DATE)";
}

// Vendedor vê apenas seus pedidos
if ($_SESSION['user_perfil'] === 'vendedor') {
    $sql .= " AND p.vendedor_id = ?";
    $params[] = $_SESSION['user_id'];
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Pedidos';
include '../../views/layouts/_header.php';
?>

<div class="bg-white rounded-lg shadow">
    <!-- Cabeçalho com filtros -->
    <div class="p-6 border-b border-gray-200">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h2 class="text-2xl font-bold text-gray-800">Pedidos</h2>
            
            <div class="flex flex-col md:flex-row gap-3">
                <!-- Busca -->
                <form method="GET" class="flex gap-2">
                    <input type="text" name="busca" value="<?= htmlspecialchars($filtro_busca) ?>" 
                           placeholder="Buscar pedido ou cliente..." 
                           class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    
                    <select name="status" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Todos os status</option>
                        <option value="orcamento" <?= $filtro_status === 'orcamento' ? 'selected' : '' ?>>Orçamento</option>
                        <option value="aprovado" <?= $filtro_status === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                        <option value="producao" <?= $filtro_status === 'producao' ? 'selected' : '' ?>>Produção</option>
                        <option value="finalizado" <?= $filtro_status === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                        <option value="cancelado" <?= $filtro_status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                    
                    <select name="periodo" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="mes" <?= $filtro_periodo === 'mes' ? 'selected' : '' ?>>Este mês</option>
                        <option value="semana" <?= $filtro_periodo === 'semana' ? 'selected' : '' ?>>Esta semana</option>
                        <option value="hoje" <?= $filtro_periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                        <option value="">Todos</option>
                    </select>
                    
                    <button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                        Filtrar
                    </button>
                </form>
                
                <a href="pedido_novo.php" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Novo Pedido
                </a>
            </div>
        </div>
    </div>
    
    <!-- Tabela de pedidos -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prazo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($pedidos as $pedido): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <?php if ($pedido['urgente']): ?>
                            <span class="text-red-500 mr-2" title="Urgente">⚡</span>
                            <?php endif; ?>
                            <span class="text-sm font-medium text-gray-900">#<?= $pedido['numero'] ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($pedido['cliente_nome']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= formatarMoeda($pedido['valor_final']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= getStatusColor($pedido['status']) ?> text-white">
                            <?= ucfirst($pedido['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            <?= $pedido['prazo_entrega'] ? formatarData($pedido['prazo_entrega']) : '-' ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($pedido['vendedor_nome']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">Ver</a>
                        <?php if ($pedido['status'] === 'orcamento'): ?>
                        <a href="pedido_editar.php?id=<?= $pedido['id'] ?>" class="text-green-600 hover:text-green-900">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($pedidos)): ?>
        <div class="text-center py-8 text-gray-500">
            Nenhum pedido encontrado
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../views/layouts/_footer.php'; ?>
