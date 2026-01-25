<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['producao', 'gestor']);

// Filtros
$busca = $_GET['busca'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$status = $_GET['status'] ?? '';
$ordenar = $_GET['ordenar'] ?? 'nome';

// Query base
$where = ["p.ativo = true"];
$params = [];

if ($busca) {
    $where[] = "(p.nome ILIKE ? OR p.codigo LIKE ? OR p.descricao ILIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam]);
}

if ($categoria) {
    $where[] = "p.categoria_id = ?";
    $params[] = $categoria;
}

if ($status === 'baixo') {
    $where[] = "p.quantidade_atual <= p.estoque_minimo";
} elseif ($status === 'zerado') {
    $where[] = "p.quantidade_atual = 0";
} elseif ($status === 'excesso') {
    $where[] = "p.quantidade_atual > p.estoque_maximo";
}

$whereClause = implode(' AND ', $where);

// Buscar produtos com estatísticas
$sql = "SELECT p.*, c.nome as categoria_nome,
        COALESCE(
            (SELECT SUM(quantidade) FROM movimentacoes_estoque 
             WHERE produto_id = p.id AND tipo = 'saida' 
             AND created_at >= CURRENT_DATE - INTERVAL '30 days'), 0
        ) as saidas_30dias,
        CASE 
            WHEN p.quantidade_atual <= p.estoque_minimo THEN 'baixo'
            WHEN p.quantidade_atual = 0 THEN 'zerado'
            WHEN p.quantidade_atual > p.estoque_maximo THEN 'excesso'
            ELSE 'normal'
        END as status_estoque
        FROM produtos_estoque p
        LEFT JOIN categorias_estoque c ON p.categoria_id = c.id
        WHERE $whereClause
        ORDER BY " . ($ordenar === 'categoria' ? 'c.nome, p.nome' : 
                     ($ordenar === 'quantidade' ? 'p.quantidade_atual' : 'p.nome'));

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para filtro
$categorias = $pdo->query("SELECT * FROM categorias_estoque ORDER BY nome")->fetchAll();

// Estatísticas gerais
$stats = [
    'total_produtos' => count($produtos),
    'produtos_baixo_estoque' => count(array_filter($produtos, fn($p) => $p['status_estoque'] === 'baixo')),
    'produtos_zerados' => count(array_filter($produtos, fn($p) => $p['status_estoque'] === 'zerado')),
    'valor_total' => array_sum(array_map(fn($p) => $p['quantidade_atual'] * $p['valor_unitario'], $produtos))
];

$titulo = 'Controle de Estoque';
$breadcrumb = [
    ['label' => 'Estoque']
];
include '../views/_header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Controle de Estoque</h1>
    <p class="text-gray-600 mt-2">Gerencie materiais e produtos em estoque</p>
</div>

<!-- Cards de Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Total de Produtos</p>
                <p class="text-2xl font-bold text-gray-800"><?= $stats['total_produtos'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Estoque Baixo</p>
                <p class="text-2xl font-bold text-yellow-600"><?= $stats['produtos_baixo_estoque'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-lg">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Produtos Zerados</p>
                <p class="text-2xl font-bold text-red-600"><?= $stats['produtos_zerados'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Valor Total</p>
                <p class="text-2xl font-bold text-green-600"><?= formatarMoeda($stats['valor_total']) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros e Ações -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Buscar por nome, código ou descrição..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <select name="categoria" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoria == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="">Todos os status</option>
                <option value="baixo" <?= $status === 'baixo' ? 'selected' : '' ?>>Estoque Baixo</option>
                <option value="zerado" <?= $status === 'zerado' ? 'selected' : '' ?>>Zerado</option>
                <option value="excesso" <?= $status === 'excesso' ? 'selected' : '' ?>>Em Excesso</option>
            </select>
            
            <select name="ordenar" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="nome" <?= $ordenar === 'nome' ? 'selected' : '' ?>>Nome</option>
                <option value="categoria" <?= $ordenar === 'categoria' ? 'selected' : '' ?>>Categoria</option>
                <option value="quantidade" <?= $ordenar === 'quantidade' ? 'selected' : '' ?>>Quantidade</option>
            </select>
            
            <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Filtrar
            </button>
        </form>
        
        <div class="flex gap-3 mt-4">
            <a href="produto_novo.php" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Novo Produto
            </a>
            
            <a href="movimentacao_nova.php" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                </svg>
                Nova Movimentação
            </a>
            
            <a href="estoque_relatorio.php" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Relatório
            </a>
        </div>
    </div>
</div>

<!-- Tabela de Produtos -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if ($produtos): ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Código
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Produto
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Categoria
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Estoque Atual
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Mín/Máx
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Valor Unit.
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Saídas 30d
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Ações
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($produtos as $produto): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($produto['codigo']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($produto['nome']) ?>
                            </div>
                            <?php if ($produto['descricao']): ?>
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars(substr($produto['descricao'], 0, 50)) ?>...
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                            <?= htmlspecialchars($produto['categoria_nome']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm font-medium 
                            <?= $produto['status_estoque'] === 'baixo' ? 'text-yellow-600' : 
                                ($produto['status_estoque'] === 'zerado' ? 'text-red-600' : 
                                ($produto['status_estoque'] === 'excesso' ? 'text-blue-600' : 'text-gray-900')) ?>">
                            <?= number_format($produto['quantidade_atual'], 2, ',', '.') ?> 
                            <span class="text-xs"><?= $produto['unidade_medida'] ?></span>
                        </div>
                        
                        <?php if ($produto['status_estoque'] !== 'normal'): ?>
                        <div class="mt-1">
                            <?php if ($produto['status_estoque'] === 'baixo'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                Baixo
                            </span>
                            <?php elseif ($produto['status_estoque'] === 'zerado'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                                Zerado
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                Excesso
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                        <?= $produto['estoque_minimo'] ?> / <?= $produto['estoque_maximo'] ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                        <?= formatarMoeda($produto['valor_unitario']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php if ($produto['saidas_30dias'] > 0): ?>
                        <span class="text-sm text-gray-900"><?= $produto['saidas_30dias'] ?></span>
                        <?php else: ?>
                        <span class="text-sm text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="flex justify-center space-x-2">
                            <button onclick="abrirMovimentacao(<?= $produto['id'] ?>, '<?= htmlspecialchars($produto['nome']) ?>')"
                                    class="text-blue-600 hover:text-blue-900" title="Movimentar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                </svg>
                            </button>
                            
                            <a href="produto_historico.php?id=<?= $produto['id'] ?>" 
                               class="text-purple-600 hover:text-purple-900" title="Histórico">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </a>
                            
                            <a href="produto_editar.php?id=<?= $produto['id'] ?>" 
                               class="text-green-600 hover:text-green-900" title="Editar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum produto encontrado</h3>
        <p class="mt-1 text-sm text-gray-500">Comece cadastrando um novo produto.</p>
        <div class="mt-6">
            <a href="produto_novo.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Novo Produto
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Movimentação Rápida -->
<div id="modalMovimentacao" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <form id="formMovimentacao" method="POST" action="movimentacao_rapida.php" onsubmit="return showLoading(this)">
            <input type="hidden" name="produto_id" id="movimento_produto_id">
            
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                Movimentação de Estoque
            </h3>
            
            <p class="text-sm text-gray-600 mb-4" id="movimento_produto_nome"></p>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Movimentação</label>
                <select name="tipo" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Selecione...</option>
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                    <option value="ajuste">Ajuste de Inventário</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantidade</label>
                <input type="number" name="quantidade" step="0.01" min="0.01" required
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Motivo/Observações</label>
                <textarea name="observacoes" rows="2" required
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
            </div>
            
            <div class="flex justify-between">
                <button type="button" onclick="fecharModalMovimentacao()"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Confirmar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirMovimentacao(produtoId, produtoNome) {
    document.getElementById('movimento_produto_id').value = produtoId;
    document.getElementById('movimento_produto_nome').textContent = produtoNome;
    document.getElementById('modalMovimentacao').classList.remove('hidden');
}

function fecharModalMovimentacao() {
    document.getElementById('modalMovimentacao').classList.add('hidden');
    document.getElementById('formMovimentacao').reset();
}

// Fechar modal ao clicar fora
document.getElementById('modalMovimentacao').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalMovimentacao();
    }
});
</script>

<?php include '../views/_footer.php'; ?>
