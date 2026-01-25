<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['vendedor', 'producao', 'gestor']);

// Filtros
$busca = $_GET['busca'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$status = $_GET['status'] ?? 'ativos';
$ordenar = $_GET['ordenar'] ?? 'nome';

// Paginação
$itens_por_pagina = 24; // 4 colunas x 6 linhas
$pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Query base
$where = ["1=1"];
$params = [];

if ($busca) {
    $where[] = "(p.nome ILIKE ? OR p.codigo LIKE ? OR p.descricao ILIKE ? OR p.tags ILIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam, $buscaParam]);
}

if ($categoria) {
    $where[] = "p.categoria_id = ?";
    $params[] = $categoria;
}

if ($status === 'ativos') {
    $where[] = "p.ativo = true";
} elseif ($status === 'inativos') {
    $where[] = "p.ativo = false";
}

$whereClause = implode(' AND ', $where);

// Ordenação
$orderBy = match($ordenar) {
    'codigo' => 'p.codigo',
    'preco' => 'p.preco',
    'popularidade' => 'p.popularidade DESC',
    default => 'p.nome'
};

// Contar total de produtos (antes da paginação)
$sql_count = "SELECT COUNT(*) 
              FROM produtos_catalogo p
              LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
              WHERE $whereClause";

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_produtos = $stmt_count->fetchColumn();
$total_paginas = ceil($total_produtos / $itens_por_pagina);

// Contar produtos ativos (para estatística) - apenas se não houver filtros específicos
if (!$busca && !$categoria && ($status === 'ativos' || !$status)) {
    $stmt_ativos = $pdo->query("SELECT COUNT(*) FROM produtos_catalogo WHERE ativo = true");
    $total_ativos = $stmt_ativos->fetchColumn();
} else {
    // Se houver filtros, usar o total de produtos encontrados
    $total_ativos = $total_produtos;
}

// Buscar produtos com paginação
$sql = "SELECT p.*, c.nome as categoria_nome,
        (SELECT COUNT(*) FROM pedido_itens WHERE descricao LIKE '%' || p.nome || '%') as total_vendas
        FROM produtos_catalogo p
        LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

$params_paginacao = array_merge($params, [intval($itens_por_pagina), intval($offset)]);

$stmt = $pdo->prepare($sql);
$stmt->execute($params_paginacao);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para filtro
$categorias = $pdo->query("SELECT * FROM categorias_produtos WHERE ativo = true ORDER BY nome")->fetchAll();

$titulo = 'Catálogo de Produtos';
$breadcrumb = [
    ['label' => 'Catálogo']
];
include '../../views/layouts/_header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Catálogo de Produtos</h1>
    <p class="text-gray-600 mt-2">Gerencie os produtos disponíveis para venda</p>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total de Produtos</div>
        <div class="text-2xl font-bold text-gray-800"><?= $total_produtos ?></div>
        <div class="text-xs text-gray-500 mt-1">
            Mostrando <?= count($produtos) ?> de <?= $total_produtos ?>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Produtos Ativos</div>
        <div class="text-2xl font-bold text-green-600">
            <?= $total_ativos ?>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Categorias</div>
        <div class="text-2xl font-bold text-blue-600"><?= count($categorias) ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Mais Vendido</div>
        <div class="text-sm font-bold text-purple-600">
            <?php 
            $maisVendido = array_reduce($produtos, function($carry, $item) {
                return (!$carry || $item['total_vendas'] > $carry['total_vendas']) ? $item : $carry;
            });
            echo $maisVendido ? htmlspecialchars($maisVendido['nome']) : '-';
            ?>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <!-- Manter página ao filtrar (resetar para página 1) -->
            <input type="hidden" name="pagina" value="1">
            <div class="flex-1">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Buscar por nome, código, descrição ou tags..."
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
                <option value="ativos" <?= $status === 'ativos' ? 'selected' : '' ?>>Ativos</option>
                <option value="inativos" <?= $status === 'inativos' ? 'selected' : '' ?>>Inativos</option>
                <option value="">Todos</option>
            </select>
            
            <select name="ordenar" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="nome" <?= $ordenar === 'nome' ? 'selected' : '' ?>>Nome</option>
                <option value="codigo" <?= $ordenar === 'codigo' ? 'selected' : '' ?>>Código</option>
                <option value="preco" <?= $ordenar === 'preco' ? 'selected' : '' ?>>Preço</option>
                <option value="popularidade" <?= $ordenar === 'popularidade' ? 'selected' : '' ?>>Mais vendidos</option>
            </select>
            
            <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Filtrar
            </button>
            
            <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
            <a href="catalogo_produto_novo.php" 
               class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Novo Produto
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Grid de Produtos -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php foreach ($produtos as $produto): ?>
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
        <!-- Imagem -->
        <div class="aspect-w-16 aspect-h-9 bg-gray-100 rounded-t-lg overflow-hidden">
            <?php if (!empty($produto['imagem_principal'] ?? null)): ?>
            <img src="../<?= htmlspecialchars($produto['imagem_principal'] ?? '') ?>" 
                 alt="<?= htmlspecialchars($produto['nome'] ?? '') ?>"
                 class="w-full h-48 object-cover">
            <?php else: ?>
            <div class="w-full h-48 flex items-center justify-center text-gray-400">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Conteúdo -->
        <div class="p-4">
            <!-- Status e Categoria -->
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded">
                    <?= htmlspecialchars($produto['categoria_nome'] ?? 'Sem Categoria') ?>
                </span>
                <?php if (!($produto['ativo'] ?? true)): ?>
                <span class="text-xs px-2 py-1 bg-red-100 text-red-600 rounded">
                    Inativo
                </span>
                <?php endif; ?>
            </div>
            
            <!-- Código e Nome -->
            <div class="mb-2">
                <p class="text-xs text-gray-500"><?= htmlspecialchars($produto['codigo'] ?? '') ?></p>
                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($produto['nome'] ?? '') ?></h3>
            </div>
            
            <!-- Descrição -->
            <?php if (!empty($produto['descricao'] ?? null)): ?>
            <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                <?= htmlspecialchars($produto['descricao'] ?? '') ?>
            </p>
            <?php endif; ?>
            
            <!-- Preço -->
            <div class="mb-3">
                <?php if (!empty($produto['preco_promocional'] ?? null) && ($produto['preco_promocional'] ?? 0) < ($produto['preco'] ?? 0)): ?>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-400 line-through">
                        <?= formatarMoeda($produto['preco'] ?? 0) ?>
                    </span>
                    <span class="text-lg font-bold text-green-600">
                        <?= formatarMoeda($produto['preco_promocional'] ?? 0) ?>
                    </span>
                </div>
                <?php else: ?>
                <span class="text-lg font-bold text-gray-800">
                    <?= formatarMoeda($produto['preco'] ?? 0) ?>
                </span>
                <?php endif; ?>
                <span class="text-sm text-gray-500">/ <?= htmlspecialchars($produto['unidade_venda'] ?? 'UN') ?></span>
            </div>
            
            <!-- Informações adicionais -->
            <div class="flex justify-between items-center text-xs text-gray-500 mb-3">
                <span>Prazo: <?= $produto['tempo_producao'] ?? 1 ?> dia(s)</span>
                <span>Vendas: <?= $produto['total_vendas'] ?? 0 ?></span>
            </div>
            
            <!-- Ações -->
            <div class="flex gap-2">
                <a href="catalogo_produto_detalhes.php?id=<?= $produto['id'] ?>" 
                   class="flex-1 text-center px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                    Ver Detalhes
                </a>
                <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
                <a href="catalogo_produto_editar.php?id=<?= $produto['id'] ?>" 
                   class="px-3 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Paginação -->
<?php if ($total_paginas > 1): ?>
<div class="mt-6 flex items-center justify-between bg-white rounded-lg shadow p-4">
    <div class="text-sm text-gray-600">
        Página <span class="font-medium"><?= $pagina_atual ?></span> de <span class="font-medium"><?= $total_paginas ?></span>
        (<?= $total_produtos ?> produtos)
    </div>
    
    <div class="flex gap-2">
        <?php
        // Construir query string mantendo filtros
        $query_params = [];
        if ($busca) $query_params['busca'] = $busca;
        if ($categoria) $query_params['categoria'] = $categoria;
        if ($status && $status !== 'ativos') $query_params['status'] = $status;
        if ($ordenar && $ordenar !== 'nome') $query_params['ordenar'] = $ordenar;
        $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
        ?>
        
        <!-- Primeira página -->
        <?php if ($pagina_atual > 1): ?>
        <a href="?pagina=1<?= $query_string ?>" 
           class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
            « Primeira
        </a>
        <?php endif; ?>
        
        <!-- Página anterior -->
        <?php if ($pagina_atual > 1): ?>
        <a href="?pagina=<?= $pagina_atual - 1 ?><?= $query_string ?>" 
           class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
            ‹ Anterior
        </a>
        <?php endif; ?>
        
        <!-- Números das páginas -->
        <?php
        $inicio = max(1, $pagina_atual - 2);
        $fim = min($total_paginas, $pagina_atual + 2);
        
        if ($inicio > 1): ?>
            <a href="?pagina=1<?= $query_string ?>" class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">1</a>
            <?php if ($inicio > 2): ?>
                <span class="px-3 py-2 text-gray-400">...</span>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $inicio; $i <= $fim; $i++): ?>
            <?php if ($i == $pagina_atual): ?>
                <span class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">
                    <?= $i ?>
                </span>
            <?php else: ?>
                <a href="?pagina=<?= $i ?><?= $query_string ?>" 
                   class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($fim < $total_paginas): ?>
            <?php if ($fim < $total_paginas - 1): ?>
                <span class="px-3 py-2 text-gray-400">...</span>
            <?php endif; ?>
            <a href="?pagina=<?= $total_paginas ?><?= $query_string ?>" 
               class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                <?= $total_paginas ?>
            </a>
        <?php endif; ?>
        
        <!-- Próxima página -->
        <?php if ($pagina_atual < $total_paginas): ?>
        <a href="?pagina=<?= $pagina_atual + 1 ?><?= $query_string ?>" 
           class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
            Próxima ›
        </a>
        <?php endif; ?>
        
        <!-- Última página -->
        <?php if ($pagina_atual < $total_paginas): ?>
        <a href="?pagina=<?= $total_paginas ?><?= $query_string ?>" 
           class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
            Última »
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($produtos)): ?>
<div class="bg-white rounded-lg shadow p-12 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
    </svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum produto encontrado</h3>
    <p class="mt-1 text-sm text-gray-500">Comece adicionando produtos ao catálogo.</p>
    <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
    <div class="mt-6">
        <a href="catalogo_produto_novo.php" 
           class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Novo Produto
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include '../../views/layouts/_footer.php'; ?>
