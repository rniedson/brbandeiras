<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['vendedor', 'producao', 'gestor']);

$produto_id = $_GET['id'] ?? null;

if (!$produto_id) {
    header('Location: catalogo.php');
    exit;
}

// Buscar produto
$stmt = $pdo->prepare("
    SELECT p.*, c.nome as categoria_nome,
           (SELECT COUNT(DISTINCT pi.pedido_id) 
            FROM pedido_itens pi 
            WHERE pi.produto_id = p.id) as total_pedidos,
           (SELECT SUM(pi.quantidade) 
            FROM pedido_itens pi 
            WHERE pi.produto_id = p.id) as total_vendido,
           (SELECT SUM(pi.valor_total) 
            FROM pedido_itens pi 
            WHERE pi.produto_id = p.id) as receita_total
    FROM produtos_catalogo p
    LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$produto_id]);
$produto = $stmt->fetch();

if (!$produto) {
    $_SESSION['erro'] = 'Produto não encontrado';
    header('Location: catalogo.php');
    exit;
}

// Buscar imagens adicionais
$stmt = $pdo->prepare("SELECT * FROM produtos_imagens WHERE produto_id = ? ORDER BY ordem");
$stmt->execute([$produto_id]);
$imagens = $stmt->fetchAll();

// Buscar últimos pedidos que incluem este produto
$stmt = $pdo->prepare("
    SELECT p.id, p.numero, p.created_at, pi.quantidade, pi.valor_total,
           c.nome as cliente_nome, u.nome as vendedor_nome
    FROM pedido_itens pi
    JOIN pedidos p ON pi.pedido_id = p.id
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    WHERE pi.produto_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$produto_id]);
$pedidos_recentes = $stmt->fetchAll();

// Incrementar contador de visualizações (popularidade)
$stmt = $pdo->prepare("UPDATE produtos_catalogo SET popularidade = popularidade + 1 WHERE id = ?");
$stmt->execute([$produto_id]);

$titulo = $produto['nome'] . ' - Catálogo';
$breadcrumb = [
    ['label' => 'Catálogo', 'url' => 'catalogo.php'],
    ['label' => 'Detalhes do Produto']
];
include '../views/_header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Cabeçalho -->
    <div class="mb-6 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($produto['nome']) ?></h1>
            <p class="text-gray-600 mt-2">
                Código: <span class="font-semibold"><?= htmlspecialchars($produto['codigo']) ?></span>
                <?php if (!$produto['ativo']): ?>
                <span class="ml-3 px-3 py-1 bg-red-100 text-red-600 rounded-full text-sm">Inativo</span>
                <?php endif; ?>
            </p>
        </div>
        
        <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
        <div class="flex gap-2">
            <a href="catalogo_produto_editar.php?id=<?= $produto_id ?>" 
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Editar
            </a>
            
            <button onclick="alternarStatus()" 
                    class="px-4 py-2 <?= $produto['ativo'] ? 'bg-red-600' : 'bg-green-600' ?> text-white rounded-lg hover:opacity-90">
                <?= $produto['ativo'] ? 'Desativar' : 'Ativar' ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Coluna Principal -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Galeria de Imagens -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Imagens</h2>
                
                <!-- Imagem Principal -->
                <div class="mb-4">
                    <?php if ($produto['imagem_principal']): ?>
                    <img src="../<?= htmlspecialchars($produto['imagem_principal']) ?>" 
                         alt="<?= htmlspecialchars($produto['nome']) ?>"
                         class="w-full max-h-96 object-contain rounded-lg cursor-pointer"
                         onclick="abrirImagemModal(this.src)">
                    <?php else: ?>
                    <div class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Imagens Adicionais -->
                <?php if (!empty($imagens)): ?>
                <div class="grid grid-cols-4 gap-2">
                    <?php foreach ($imagens as $img): ?>
                    <img src="../<?= htmlspecialchars($img['caminho']) ?>" 
                         alt="Imagem adicional"
                         class="w-full h-24 object-cover rounded cursor-pointer hover:opacity-80"
                         onclick="abrirImagemModal(this.src)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Descrição e Especificações -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Descrição</h2>
                <?php if ($produto['descricao']): ?>
                <p class="text-gray-700 whitespace-pre-line"><?= htmlspecialchars($produto['descricao']) ?></p>
                <?php else: ?>
                <p class="text-gray-500 italic">Sem descrição cadastrada</p>
                <?php endif; ?>
                
                <?php 
                $especificacoes = json_decode($produto['especificacoes'], true);
                if (!empty($especificacoes)): 
                ?>
                <h3 class="text-lg font-semibold mt-6 mb-3">Especificações Técnicas</h3>
                <table class="w-full">
                    <tbody>
                        <?php foreach ($especificacoes as $spec): ?>
                        <tr class="border-b">
                            <td class="py-2 pr-4 font-medium text-gray-700"><?= htmlspecialchars($spec['nome']) ?>:</td>
                            <td class="py-2 text-gray-900"><?= htmlspecialchars($spec['valor']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- Histórico de Pedidos -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Últimos Pedidos</h2>
                
                <?php if (!empty($pedidos_recentes)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Pedido</th>
                                <th class="px-4 py-2 text-left">Cliente</th>
                                <th class="px-4 py-2 text-center">Qtd</th>
                                <th class="px-4 py-2 text-right">Valor</th>
                                <th class="px-4 py-2 text-left">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($pedidos_recentes as $pedido): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        #<?= htmlspecialchars($pedido['numero']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                                <td class="px-4 py-2 text-center"><?= number_format($pedido['quantidade'], 0) ?></td>
                                <td class="px-4 py-2 text-right"><?= formatarMoeda($pedido['valor_total']) ?></td>
                                <td class="px-4 py-2"><?= formatarData($pedido['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($produto['total_pedidos'] > 10): ?>
                <div class="mt-4 text-center">
                    <a href="catalogo_produto_pedidos.php?id=<?= $produto_id ?>" 
                       class="text-blue-600 hover:text-blue-800 text-sm">
                        Ver todos os <?= $produto['total_pedidos'] ?> pedidos →
                    </a>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <p class="text-gray-500 text-center py-4">Nenhum pedido registrado para este produto</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Coluna Lateral -->
        <div class="space-y-6">
            <!-- Informações de Preço -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Informações de Venda</h2>
                
                <!-- Preços -->
                <div class="mb-4">
                    <?php if ($produto['preco_promocional'] && $produto['preco_promocional'] < $produto['preco']): ?>
                    <div class="text-gray-500 line-through text-lg">
                        <?= formatarMoeda($produto['preco']) ?>
                    </div>
                    <div class="text-3xl font-bold text-green-600">
                        <?= formatarMoeda($produto['preco_promocional']) ?>
                    </div>
                    <div class="text-sm text-green-600">
                        <?php 
                        $desconto = (($produto['preco'] - $produto['preco_promocional']) / $produto['preco']) * 100;
                        echo number_format($desconto, 0) . '% de desconto';
                        ?>
                    </div>
                    <?php else: ?>
                    <div class="text-3xl font-bold text-gray-900">
                        <?= formatarMoeda($produto['preco']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="text-sm text-gray-600 mt-1">
                        por <?= htmlspecialchars($produto['unidade_venda']) ?>
                    </div>
                </div>
                
                <!-- Outras informações -->
                <div class="space-y-3 pt-4 border-t">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Categoria:</span>
                        <span class="font-medium"><?= htmlspecialchars($produto['categoria_nome']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tempo de Produção:</span>
                        <span class="font-medium"><?= $produto['tempo_producao'] ?> dia(s)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Disponível em Estoque:</span>
                        <span class="font-medium">
                            <?= $produto['estoque_disponivel'] ? 
                                '<span class="text-green-600">Sim</span>' : 
                                '<span class="text-red-600">Não</span>' ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($produto['tags']): ?>
                <div class="mt-4 pt-4 border-t">
                    <p class="text-sm text-gray-600 mb-2">Tags:</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (explode(',', $produto['tags']) as $tag): ?>
                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs">
                            <?= htmlspecialchars(trim($tag)) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Estatísticas -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Estatísticas</h2>
                
                <div class="space-y-4">
                    <div class="text-center p-4 bg-blue-50 rounded">
                        <div class="text-3xl font-bold text-blue-600">
                            <?= number_format($produto['total_pedidos'] ?? 0) ?>
                        </div>
                        <div class="text-sm text-gray-600">Pedidos Realizados</div>
                    </div>
                    
                    <div class="text-center p-4 bg-green-50 rounded">
                        <div class="text-3xl font-bold text-green-600">
                            <?= number_format($produto['total_vendido'] ?? 0, 0, ',', '.') ?>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?= $produto['unidade_venda'] ?> Vendidos
                        </div>
                    </div>
                    
                    <div class="text-center p-4 bg-purple-50 rounded">
                        <div class="text-2xl font-bold text-purple-600">
                            <?= formatarMoeda($produto['receita_total'] ?? 0) ?>
                        </div>
                        <div class="text-sm text-gray-600">Receita Total</div>
                    </div>
                    
                    <div class="text-center p-4 bg-gray-50 rounded">
                        <div class="text-xl font-bold text-gray-700">
                            <?= number_format($produto['popularidade']) ?>
                        </div>
                        <div class="text-sm text-gray-600">Visualizações</div>
                    </div>
                </div>
            </div>
            
            <!-- Ações Rápidas -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Ações Rápidas</h2>
                
                <div class="space-y-2">
                    <a href="pedido_novo.php" 
                       class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-center block">
                        Criar Pedido com este Produto
                    </a>
                    
                    <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
                    <button onclick="duplicarProduto()" 
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Duplicar Produto
                    </button>
                    
                    <button onclick="imprimirFicha()" 
                            class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        Imprimir Ficha Técnica
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Imagem -->
<div id="modalImagem" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50" 
     onclick="fecharImagemModal()">
    <img id="imagemModal" src="" alt="" class="max-w-full max-h-full">
</div>

<script>
function abrirImagemModal(src) {
    document.getElementById('imagemModal').src = src;
    document.getElementById('modalImagem').classList.remove('hidden');
    document.getElementById('modalImagem').classList.add('flex');
}

function fecharImagemModal() {
    document.getElementById('modalImagem').classList.add('hidden');
    document.getElementById('modalImagem').classList.remove('flex');
}

function alternarStatus() {
    if (confirm('Deseja realmente <?= $produto['ativo'] ? 'desativar' : 'ativar' ?> este produto?')) {
        window.location.href = 'catalogo_produto_status.php?id=<?= $produto_id ?>&acao=<?= $produto['ativo'] ? 'desativar' : 'ativar' ?>';
    }
}

function duplicarProduto() {
    if (confirm('Deseja criar uma cópia deste produto?')) {
        window.location.href = 'catalogo_produto_duplicar.php?id=<?= $produto_id ?>';
    }
}

function imprimirFicha() {
    window.open('catalogo_produto_ficha.php?id=<?= $produto_id ?>', '_blank');
}

// Atalho ESC para fechar modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharImagemModal();
    }
});
</script>

<!-- CSS para impressão -->
<style>
@media print {
    nav, footer, .no-print { display: none !important; }
    .shadow { box-shadow: none !important; }
}
</style>

<?php include '../views/_footer.php'; ?>
