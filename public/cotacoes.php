<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['producao', 'gestor']);

// Filtros
$status = $_GET['status'] ?? '';
$fornecedor_id = $_GET['fornecedor_id'] ?? '';
$busca = $_GET['busca'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Query base
$where = ["1=1"];
$params = [];

// Filtro de status
if ($status) {
    $where[] = "c.status = ?";
    $params[] = $status;
}

// Filtro por fornecedor
if ($fornecedor_id) {
    $where[] = "c.fornecedor_id = ?";
    $params[] = $fornecedor_id;
}

// Filtro de busca
if ($busca) {
    $where[] = "(c.numero LIKE ? OR f.nome ILIKE ? OR c.observacoes ILIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam]);
}

// Filtro de datas
if ($data_inicio && $data_fim) {
    $where[] = "c.created_at >= ?::date AND c.created_at < (?::date + INTERVAL '1 day')";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

$whereClause = implode(' AND ', $where);

// Contar total de cotações
try {
    $sql_count = "SELECT COUNT(*) 
                  FROM cotacoes c
                  LEFT JOIN fornecedores f ON c.fornecedor_id = f.id
                  WHERE $whereClause";
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_cotacoes = $stmt_count->fetchColumn();
    
    // Buscar cotações
    $sql = "SELECT 
            c.*,
            f.nome as fornecedor_nome,
            f.telefone as fornecedor_telefone,
            f.email as fornecedor_email,
            u.nome as solicitante_nome,
            COUNT(DISTINCT ci.id) as total_itens
        FROM cotacoes c
        LEFT JOIN fornecedores f ON c.fornecedor_id = f.id
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        LEFT JOIN cotacao_itens ci ON ci.cotacao_id = c.id
        WHERE $whereClause
        GROUP BY c.id, f.nome, f.telefone, f.email, u.nome
        ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela não existir, criar lista vazia
    $cotacoes = [];
    $total_cotacoes = 0;
}

// Buscar fornecedores para filtro
try {
    $fornecedores = $pdo->query("SELECT id, nome FROM fornecedores ORDER BY nome")->fetchAll();
} catch (PDOException $e) {
    $fornecedores = [];
}

// Estatísticas
$stats = [
    'total' => count($cotacoes),
    'pendentes' => count(array_filter($cotacoes, fn($c) => ($c['status'] ?? 'pendente') === 'pendente')),
    'aprovadas' => count(array_filter($cotacoes, fn($c) => ($c['status'] ?? 'pendente') === 'aprovada')),
    'rejeitadas' => count(array_filter($cotacoes, fn($c) => ($c['status'] ?? 'pendente') === 'rejeitada'))
];

$titulo = 'Cotações de Fornecedores';
$breadcrumb = [
    ['label' => 'Fornecedores', 'url' => 'fornecedores.php'],
    ['label' => 'Cotações']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Cotações de Fornecedores</h1>
            <p class="text-gray-600 mt-2">Gerencie solicitações de preços aos fornecedores</p>
        </div>
        <a href="cotacao_nova.php" 
           class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nova Cotação
        </a>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total de Cotações</div>
        <div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Pendentes</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $stats['pendentes'] ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Aprovadas</div>
        <div class="text-2xl font-bold text-green-600"><?= $stats['aprovadas'] ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Rejeitadas</div>
        <div class="text-2xl font-bold text-red-600"><?= $stats['rejeitadas'] ?></div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="aprovada" <?= $status === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                    <option value="rejeitada" <?= $status === 'rejeitada' ? 'selected' : '' ?>>Rejeitada</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Fornecedor</label>
                <select name="fornecedor_id" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos</option>
                    <?php foreach ($fornecedores as $fornecedor): ?>
                    <option value="<?= $fornecedor['id'] ?>" <?= $fornecedor_id == $fornecedor['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fornecedor['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                <input type="date" name="data_inicio" value="<?= $data_inicio ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                <input type="date" name="data_fim" value="<?= $data_fim ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Número, fornecedor..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div class="md:col-span-2 lg:col-span-5 flex gap-2">
                <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    Filtrar
                </button>
                <a href="cotacoes.php" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Cotações -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($cotacoes)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma cotação encontrada</h3>
        <p class="mt-1 text-sm text-gray-500">Comece criando uma nova cotação.</p>
        <div class="mt-6">
            <a href="cotacao_nova.php" 
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nova Cotação
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cotação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solicitante</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Itens</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($cotacoes as $cotacao): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">
                            #<?= htmlspecialchars($cotacao['numero'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?= htmlspecialchars($cotacao['fornecedor_nome'] ?? 'N/A') ?>
                        </div>
                        <?php if (!empty($cotacao['fornecedor_telefone'])): ?>
                        <div class="text-xs text-gray-500">
                            <?= htmlspecialchars($cotacao['fornecedor_telefone']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            <?= htmlspecialchars($cotacao['solicitante_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900">
                            <?= $cotacao['total_itens'] ?? 0 ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <div class="text-sm font-medium text-gray-900">
                            <?= formatarMoeda($cotacao['valor_total'] ?? 0) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900">
                            <?= formatarData($cotacao['created_at'] ?? date('Y-m-d')) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php
                        $status_cotacao = $cotacao['status'] ?? 'pendente';
                        $status_colors = [
                            'pendente' => ['bg' => 'yellow', 'text' => 'yellow'],
                            'aprovada' => ['bg' => 'green', 'text' => 'green'],
                            'rejeitada' => ['bg' => 'red', 'text' => 'red']
                        ];
                        $color = $status_colors[$status_cotacao] ?? ['bg' => 'gray', 'text' => 'gray'];
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-<?= $color['bg'] ?>-100 text-<?= $color['text'] ?>-800">
                            <?= ucfirst($status_cotacao) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="cotacao_detalhes.php?id=<?= $cotacao['id'] ?>" 
                           class="text-indigo-600 hover:text-indigo-900">
                            Ver Detalhes
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include '../views/layouts/_footer.php'; ?>
