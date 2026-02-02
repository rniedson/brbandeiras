<?php
/**
 * Fila de Impressão - Gerenciamento de pedidos para impressão
 */
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor', 'producao']);

// Buscar pedidos na fila de impressão/produção
// Status válidos: aprovado (aguardando), producao (em produção), expedicao (pronto p/ expedir)
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.numero,
        p.urgente,
        p.prazo_entrega,
        p.status,
        p.created_at,
        c.nome as cliente_nome,
        u.nome as vendedor_nome,
        (SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = p.id) as total_itens
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    WHERE p.status IN ('aprovado', 'producao', 'expedicao')
    ORDER BY p.urgente DESC, p.prazo_entrega ASC, p.created_at ASC
");
$stmt->execute();
$pedidos = $stmt->fetchAll();

// Agrupar por status
$aguardandoImpressao = array_filter($pedidos, fn($p) => $p['status'] === 'aprovado');
$emProducao = array_filter($pedidos, fn($p) => $p['status'] === 'producao');
$emExpedicao = array_filter($pedidos, fn($p) => $p['status'] === 'expedicao');

$titulo = 'Fila de Impressão';
include '../views/layouts/_header.php';
?>

<div class="flex-1 bg-gray-50">
    <div class="max-w-7xl mx-auto p-6">
        
        <!-- Cabeçalho -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-print mr-2 text-blue-600"></i>
                        Fila de Impressão
                    </h1>
                    <p class="text-gray-600 mt-1">Gerencie os pedidos aguardando impressão</p>
                </div>
                <div class="flex gap-4">
                    <div class="text-center px-4 py-2 bg-yellow-100 rounded-lg">
                        <p class="text-2xl font-bold text-yellow-700"><?= count($aguardandoImpressao) ?></p>
                        <p class="text-xs text-yellow-600">Aguardando</p>
                    </div>
                    <div class="text-center px-4 py-2 bg-blue-100 rounded-lg">
                        <p class="text-2xl font-bold text-blue-700"><?= count($emProducao) ?></p>
                        <p class="text-xs text-blue-600">Em Produção</p>
                    </div>
                    <div class="text-center px-4 py-2 bg-green-100 rounded-lg">
                        <p class="text-2xl font-bold text-green-700"><?= count($emExpedicao) ?></p>
                        <p class="text-xs text-green-600">Expedição</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grid de Colunas -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Aguardando Impressão -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-yellow-500 text-white px-4 py-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-clock"></i>
                        Aguardando Impressão
                        <span class="ml-auto bg-yellow-600 px-2 py-0.5 rounded-full text-sm">
                            <?= count($aguardandoImpressao) ?>
                        </span>
                    </h2>
                </div>
                <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                    <?php if (empty($aguardandoImpressao)): ?>
                        <p class="text-center text-gray-500 py-8">Nenhum pedido aguardando</p>
                    <?php else: ?>
                        <?php foreach ($aguardandoImpressao as $pedido): ?>
                            <div class="border rounded-lg p-3 hover:shadow-md transition <?= $pedido['urgente'] ? 'border-red-300 bg-red-50' : 'border-gray-200' ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-bold text-gray-900">OS #<?= htmlspecialchars($pedido['numero']) ?></span>
                                        <?php if ($pedido['urgente']): ?>
                                            <span class="ml-2 px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">URGENTE</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-500"><?= $pedido['total_itens'] ?> itens</span>
                                </div>
                                <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars($pedido['cliente_nome'] ?? 'Cliente não informado') ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?= formatarData($pedido['prazo_entrega']) ?>
                                    </span>
                                    <a href="pedidos/detalhes/pedido_detalhes_gestor.php?id=<?= $pedido['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm">
                                        Ver <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Em Produção -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-blue-500 text-white px-4 py-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-cogs"></i>
                        Em Produção
                        <span class="ml-auto bg-blue-600 px-2 py-0.5 rounded-full text-sm">
                            <?= count($emProducao) ?>
                        </span>
                    </h2>
                </div>
                <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                    <?php if (empty($emProducao)): ?>
                        <p class="text-center text-gray-500 py-8">Nenhum pedido em produção</p>
                    <?php else: ?>
                        <?php foreach ($emProducao as $pedido): ?>
                            <div class="border rounded-lg p-3 hover:shadow-md transition <?= $pedido['urgente'] ? 'border-red-300 bg-red-50' : 'border-gray-200' ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-bold text-gray-900">OS #<?= htmlspecialchars($pedido['numero']) ?></span>
                                        <?php if ($pedido['urgente']): ?>
                                            <span class="ml-2 px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">URGENTE</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-500"><?= $pedido['total_itens'] ?> itens</span>
                                </div>
                                <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars($pedido['cliente_nome'] ?? 'Cliente não informado') ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?= formatarData($pedido['prazo_entrega']) ?>
                                    </span>
                                    <a href="pedidos/detalhes/pedido_detalhes_gestor.php?id=<?= $pedido['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm">
                                        Ver <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Expedição -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-green-500 text-white px-4 py-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-truck"></i>
                        Expedição
                        <span class="ml-auto bg-green-600 px-2 py-0.5 rounded-full text-sm">
                            <?= count($emExpedicao) ?>
                        </span>
                    </h2>
                </div>
                <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                    <?php if (empty($emExpedicao)): ?>
                        <p class="text-center text-gray-500 py-8">Nenhum pedido em expedição</p>
                    <?php else: ?>
                        <?php foreach ($emExpedicao as $pedido): ?>
                            <div class="border rounded-lg p-3 hover:shadow-md transition <?= $pedido['urgente'] ? 'border-red-300 bg-red-50' : 'border-gray-200' ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-bold text-gray-900">OS #<?= htmlspecialchars($pedido['numero']) ?></span>
                                        <?php if ($pedido['urgente']): ?>
                                            <span class="ml-2 px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">URGENTE</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-500"><?= $pedido['total_itens'] ?> itens</span>
                                </div>
                                <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars($pedido['cliente_nome'] ?? 'Cliente não informado') ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?= formatarData($pedido['prazo_entrega']) ?>
                                    </span>
                                    <a href="pedidos/detalhes/pedido_detalhes_gestor.php?id=<?= $pedido['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm">
                                        Ver <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
    </div>
</div>

<?php include '../views/layouts/_footer.php'; ?>
