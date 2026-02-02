<?php
/**
 * Ordem de Serviço - Visualização e impressão de OS
 */
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor', 'producao', 'vendedor']);

$pedido_id = intval($_GET['id'] ?? 0);
$pedido = null;
$itens = [];
$cliente = null;

// Se um ID foi fornecido, buscar detalhes do pedido
if ($pedido_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            c.endereco as cliente_endereco,
            c.cidade as cliente_cidade,
            c.estado as cliente_estado,
            c.cep as cliente_cep,
            u.nome as vendedor_nome
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if ($pedido) {
        // Buscar itens do pedido
        $stmt = $pdo->prepare("
            SELECT 
                pi.*,
                pr.nome as produto_nome,
                pr.codigo as produto_codigo
            FROM pedido_itens pi
            LEFT JOIN produtos pr ON pi.produto_id = pr.id
            WHERE pi.pedido_id = ?
            ORDER BY pi.id
        ");
        $stmt->execute([$pedido_id]);
        $itens = $stmt->fetchAll();
    }
}

// Buscar lista de pedidos para seleção
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.numero,
        p.status,
        p.urgente,
        c.nome as cliente_nome
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 50
");
$stmt->execute();
$listaPedidos = $stmt->fetchAll();

$titulo = 'Ordem de Serviço';
include '../views/layouts/_header.php';
?>

<div class="flex-1 bg-gray-50">
    <div class="max-w-5xl mx-auto p-6">
        
        <!-- Seletor de Pedido -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                <i class="fas fa-file-alt mr-2 text-purple-600"></i>
                Ordem de Serviço
            </h1>
            
            <form method="GET" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Selecione o Pedido</label>
                    <select name="id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500">
                        <option value="">-- Selecione um pedido --</option>
                        <?php foreach ($listaPedidos as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $pedido_id ? 'selected' : '' ?>>
                                OS #<?= htmlspecialchars($p['numero']) ?> - <?= htmlspecialchars($p['cliente_nome'] ?? 'Sem cliente') ?>
                                <?= $p['urgente'] ? '🔴 URGENTE' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-search mr-2"></i>
                    Visualizar
                </button>
                <?php if ($pedido): ?>
                <button type="button" onclick="window.print()" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i>
                    Imprimir
                </button>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if ($pedido): ?>
        <!-- Ordem de Serviço para Impressão -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden print:shadow-none" id="ordem-servico">
            
            <!-- Cabeçalho da OS -->
            <div class="bg-gray-800 text-white p-6 print:bg-white print:text-black print:border-b-2 print:border-black">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-3xl font-bold"><?= NOME_EMPRESA ?></h2>
                        <p class="text-gray-300 print:text-gray-600"><?= ENDERECO_EMPRESA ?></p>
                        <p class="text-gray-300 print:text-gray-600"><?= TELEFONE_EMPRESA ?></p>
                    </div>
                    <div class="text-right">
                        <div class="text-4xl font-bold">OS #<?= htmlspecialchars($pedido['numero']) ?></div>
                        <div class="text-lg mt-2">
                            <?php if ($pedido['urgente']): ?>
                                <span class="bg-red-500 text-white px-3 py-1 rounded print:bg-red-100 print:text-red-800 print:border print:border-red-500">
                                    URGENTE
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-400 print:text-gray-600 mt-2">
                            Data: <?= date('d/m/Y', strtotime($pedido['created_at'])) ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Informações do Cliente e Pedido -->
            <div class="p-6 grid grid-cols-2 gap-6 border-b">
                <div>
                    <h3 class="font-bold text-gray-700 mb-2 border-b pb-1">
                        <i class="fas fa-user mr-2"></i>CLIENTE
                    </h3>
                    <p class="font-semibold text-lg"><?= htmlspecialchars($pedido['cliente_nome'] ?? 'Não informado') ?></p>
                    <?php if ($pedido['cliente_telefone']): ?>
                        <p class="text-gray-600"><i class="fas fa-phone mr-2"></i><?= htmlspecialchars($pedido['cliente_telefone']) ?></p>
                    <?php endif; ?>
                    <?php if ($pedido['cliente_email']): ?>
                        <p class="text-gray-600"><i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($pedido['cliente_email']) ?></p>
                    <?php endif; ?>
                    <?php if ($pedido['cliente_endereco']): ?>
                        <p class="text-gray-600 mt-2">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            <?= htmlspecialchars($pedido['cliente_endereco']) ?>
                            <?= $pedido['cliente_cidade'] ? ', ' . htmlspecialchars($pedido['cliente_cidade']) : '' ?>
                            <?= $pedido['cliente_estado'] ? '/' . htmlspecialchars($pedido['cliente_estado']) : '' ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="font-bold text-gray-700 mb-2 border-b pb-1">
                        <i class="fas fa-info-circle mr-2"></i>INFORMAÇÕES
                    </h3>
                    <div class="space-y-1">
                        <p><span class="text-gray-600">Vendedor:</span> <strong><?= htmlspecialchars($pedido['vendedor_nome'] ?? 'Não informado') ?></strong></p>
                        <p><span class="text-gray-600">Status:</span> <strong class="uppercase"><?= htmlspecialchars($pedido['status']) ?></strong></p>
                        <p><span class="text-gray-600">Prazo de Entrega:</span> <strong class="text-red-600"><?= formatarData($pedido['prazo_entrega']) ?></strong></p>
                    </div>
                </div>
            </div>
            
            <!-- Itens do Pedido -->
            <div class="p-6">
                <h3 class="font-bold text-gray-700 mb-4 border-b pb-1">
                    <i class="fas fa-list mr-2"></i>ITENS DO PEDIDO
                </h3>
                
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="text-left py-2 px-2">#</th>
                            <th class="text-left py-2 px-2">Produto</th>
                            <th class="text-left py-2 px-2">Descrição</th>
                            <th class="text-center py-2 px-2">Qtd</th>
                            <th class="text-center py-2 px-2">Tamanho</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $i => $item): ?>
                        <tr class="border-b <?= $i % 2 == 0 ? 'bg-gray-50' : '' ?>">
                            <td class="py-3 px-2"><?= $i + 1 ?></td>
                            <td class="py-3 px-2 font-medium">
                                <?= htmlspecialchars($item['produto_nome'] ?? $item['descricao'] ?? 'Produto') ?>
                                <?php if ($item['produto_codigo']): ?>
                                    <span class="text-xs text-gray-500">(<?= htmlspecialchars($item['produto_codigo']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-2 text-gray-600"><?= htmlspecialchars($item['observacoes'] ?? '-') ?></td>
                            <td class="py-3 px-2 text-center font-bold"><?= intval($item['quantidade']) ?></td>
                            <td class="py-3 px-2 text-center"><?= htmlspecialchars($item['tamanho'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Observações -->
            <?php if (!empty($pedido['observacoes'])): ?>
            <div class="p-6 border-t bg-yellow-50">
                <h3 class="font-bold text-gray-700 mb-2">
                    <i class="fas fa-sticky-note mr-2"></i>OBSERVAÇÕES
                </h3>
                <p class="text-gray-700"><?= nl2br(htmlspecialchars($pedido['observacoes'])) ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Rodapé para assinaturas -->
            <div class="p-6 border-t mt-8 print:mt-16">
                <div class="grid grid-cols-3 gap-8 pt-8">
                    <div class="text-center">
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-sm text-gray-600">Produção</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-sm text-gray-600">Conferência</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="border-t border-gray-400 pt-2">
                            <p class="text-sm text-gray-600">Expedição</p>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        <?php else: ?>
        <!-- Mensagem quando não há pedido selecionado -->
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-xl font-semibold text-gray-600 mb-2">Selecione um Pedido</h2>
            <p class="text-gray-500">Escolha um pedido na lista acima para visualizar a Ordem de Serviço</p>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #ordem-servico, #ordem-servico * {
        visibility: visible;
    }
    #ordem-servico {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .print\:shadow-none {
        box-shadow: none !important;
    }
}
</style>

<?php include '../views/layouts/_footer.php'; ?>
