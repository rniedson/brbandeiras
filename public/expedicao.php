<?php
/**
 * Expedição - Gerenciamento de pedidos prontos para entrega
 */
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor', 'producao']);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pedido_id = intval($_POST['pedido_id'] ?? 0);
        
        switch ($_POST['action']) {
            case 'marcar_entregue':
                $stmt = $pdo->prepare("UPDATE pedidos SET status = 'entregue', updated_at = NOW() WHERE id = ? AND status = 'pronto'");
                $stmt->execute([$pedido_id]);
                registrarLog('pedido_entregue', "Pedido #$pedido_id marcado como entregue");
                $_SESSION['sucesso'] = 'Pedido marcado como entregue!';
                break;
                
            case 'voltar_producao':
                $stmt = $pdo->prepare("UPDATE pedidos SET status = 'producao', updated_at = NOW() WHERE id = ? AND status = 'pronto'");
                $stmt->execute([$pedido_id]);
                registrarLog('pedido_retornado', "Pedido #$pedido_id retornado para produção");
                $_SESSION['sucesso'] = 'Pedido retornado para produção!';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['erro'] = 'Erro: ' . $e->getMessage();
    }
    
    header('Location: expedicao.php');
    exit;
}

// Buscar pedidos prontos para expedição
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.numero,
        p.urgente,
        p.prazo_entrega,
        p.status,
        p.created_at,
        p.updated_at,
        c.nome as cliente_nome,
        c.telefone as cliente_telefone,
        c.cidade as cliente_cidade,
        u.nome as vendedor_nome,
        (SELECT COUNT(*) FROM pedido_itens WHERE pedido_id = p.id) as total_itens
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    WHERE p.status = 'pronto'
    ORDER BY p.urgente DESC, p.prazo_entrega ASC
");
$stmt->execute();
$pedidosProntos = $stmt->fetchAll();

// Buscar pedidos entregues hoje
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.numero,
        p.updated_at,
        c.nome as cliente_nome
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    WHERE p.status = 'entregue' AND DATE(p.updated_at) = CURRENT_DATE
    ORDER BY p.updated_at DESC
");
$stmt->execute();
$entreguesHoje = $stmt->fetchAll();

$titulo = 'Expedição';
include '../views/layouts/_header.php';
?>

<div class="flex-1 bg-gray-50">
    <div class="max-w-7xl mx-auto p-6">
        
        <!-- Cabeçalho -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-truck mr-2 text-amber-600"></i>
                        Expedição
                    </h1>
                    <p class="text-gray-600 mt-1">Gerencie os pedidos prontos para entrega</p>
                </div>
                <div class="flex gap-4">
                    <div class="text-center px-4 py-2 bg-amber-100 rounded-lg">
                        <p class="text-2xl font-bold text-amber-700"><?= count($pedidosProntos) ?></p>
                        <p class="text-xs text-amber-600">Prontos p/ Entrega</p>
                    </div>
                    <div class="text-center px-4 py-2 bg-green-100 rounded-lg">
                        <p class="text-2xl font-bold text-green-700"><?= count($entreguesHoje) ?></p>
                        <p class="text-xs text-green-600">Entregues Hoje</p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['sucesso']) ?>
            </div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['erro'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['erro']) ?>
            </div>
            <?php unset($_SESSION['erro']); ?>
        <?php endif; ?>
        
        <!-- Lista de Pedidos Prontos -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
            <div class="bg-amber-500 text-white px-6 py-4">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <i class="fas fa-box"></i>
                    Pedidos Prontos para Entrega
                    <span class="ml-auto bg-amber-600 px-3 py-1 rounded-full">
                        <?= count($pedidosProntos) ?>
                    </span>
                </h2>
            </div>
            
            <?php if (empty($pedidosProntos)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Nenhum pedido pronto para expedição</p>
                    <p class="text-gray-400 text-sm mt-1">Os pedidos aparecerão aqui quando estiverem prontos</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left py-3 px-4 font-semibold text-gray-600">OS</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-600">Cliente</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-600">Cidade</th>
                                <th class="text-center py-3 px-4 font-semibold text-gray-600">Itens</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-600">Prazo</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-600">Vendedor</th>
                                <th class="text-center py-3 px-4 font-semibold text-gray-600">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($pedidosProntos as $pedido): 
                                $prazo = new DateTime($pedido['prazo_entrega']);
                                $hoje = new DateTime();
                                $atrasado = $hoje > $prazo;
                            ?>
                            <tr class="hover:bg-gray-50 <?= $pedido['urgente'] ? 'bg-red-50' : '' ?>">
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">#<?= htmlspecialchars($pedido['numero']) ?></span>
                                        <?php if ($pedido['urgente']): ?>
                                            <span class="px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">URGENTE</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($pedido['cliente_nome'] ?? 'Não informado') ?></p>
                                        <?php if ($pedido['cliente_telefone']): ?>
                                            <p class="text-sm text-gray-500">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?= htmlspecialchars($pedido['cliente_telefone']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-gray-600">
                                    <?= htmlspecialchars($pedido['cliente_cidade'] ?? '-') ?>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <span class="px-2 py-1 bg-gray-100 rounded-full text-sm font-medium">
                                        <?= $pedido['total_itens'] ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="<?= $atrasado ? 'text-red-600 font-bold' : 'text-gray-600' ?>">
                                        <?= formatarData($pedido['prazo_entrega']) ?>
                                        <?php if ($atrasado): ?>
                                            <i class="fas fa-exclamation-triangle ml-1"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-gray-600">
                                    <?= htmlspecialchars($pedido['vendedor_nome'] ?? '-') ?>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <form method="POST" class="inline" onsubmit="return confirm('Confirmar entrega do pedido?')">
                                            <input type="hidden" name="action" value="marcar_entregue">
                                            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                            <button type="submit" 
                                                    class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
                                                    title="Marcar como Entregue">
                                                <i class="fas fa-check mr-1"></i>
                                                Entregar
                                            </button>
                                        </form>
                                        
                                        <a href="ordem_servico.php?id=<?= $pedido['id'] ?>" 
                                           class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm"
                                           title="Ver OS">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Retornar pedido para produção?')">
                                            <input type="hidden" name="action" value="voltar_producao">
                                            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                            <button type="submit" 
                                                    class="px-3 py-1.5 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 text-sm"
                                                    title="Retornar para Produção">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Entregues Hoje -->
        <?php if (!empty($entreguesHoje)): ?>
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-green-500 text-white px-6 py-4">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <i class="fas fa-check-double"></i>
                    Entregues Hoje
                    <span class="ml-auto bg-green-600 px-3 py-1 rounded-full">
                        <?= count($entreguesHoje) ?>
                    </span>
                </h2>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <?php foreach ($entreguesHoje as $pedido): ?>
                    <div class="border border-green-200 bg-green-50 rounded-lg p-3 text-center">
                        <p class="font-bold text-green-800">#<?= htmlspecialchars($pedido['numero']) ?></p>
                        <p class="text-sm text-green-600 truncate"><?= htmlspecialchars($pedido['cliente_nome'] ?? '-') ?></p>
                        <p class="text-xs text-green-500 mt-1">
                            <i class="fas fa-clock mr-1"></i>
                            <?= date('H:i', strtotime($pedido['updated_at'])) ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php include '../views/layouts/_footer.php'; ?>
