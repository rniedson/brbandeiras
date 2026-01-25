<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['producao', 'gestor']);

// Filtros
$busca = $_GET['busca'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = $_GET['limite'] ?? 25;
$offset = ($pagina - 1) * $limite;

// Query base
$where = ["1=1"];
$params = [];

if ($busca) {
    $where[] = "(
        f.nome ILIKE ? OR 
        f.nome_fantasia ILIKE ? OR
        f.cpf_cnpj LIKE ? OR 
        f.telefone LIKE ? OR 
        f.email ILIKE ?
    )";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam, $buscaParam, $buscaParam]);
}

if ($tipo) {
    $where[] = "f.tipo_pessoa = ?";
    $params[] = $tipo;
}

$whereClause = implode(' AND ', $where);

// Contar total de registros
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fornecedores f WHERE $whereClause");
    $stmt->execute($params);
    $totalRegistros = $stmt->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $limite);
    
    // Buscar fornecedores
    $sql = "SELECT 
            f.*,
            COALESCE(f.nome_fantasia, f.nome) as nome_exibicao,
            COALESCE(f.celular, f.telefone) as telefone_principal,
            COUNT(DISTINCT p.id) as total_produtos
        FROM fornecedores f
        LEFT JOIN produtos_estoque p ON p.fornecedor_principal = f.nome
        WHERE $whereClause
        GROUP BY f.id
        ORDER BY f.nome
        LIMIT ? OFFSET ?";
    
    $params_paginacao = array_merge($params, [intval($limite), intval($offset)]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_paginacao);
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela não existir, criar lista vazia
    $fornecedores = [];
    $totalRegistros = 0;
    $totalPaginas = 0;
}

$titulo = 'Fornecedores';
$breadcrumb = [
    ['label' => 'Fornecedores']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Fornecedores</h1>
            <p class="text-gray-600 mt-2">Gerencie seus fornecedores</p>
        </div>
        <a href="fornecedor_novo.php" 
           class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Novo Fornecedor
        </a>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total de Fornecedores</div>
        <div class="text-2xl font-bold text-gray-800"><?= $totalRegistros ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Pessoa Jurídica</div>
        <div class="text-2xl font-bold text-blue-600">
            <?= count(array_filter($fornecedores, fn($f) => ($f['tipo_pessoa'] ?? 'J') === 'J')) ?>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Pessoa Física</div>
        <div class="text-2xl font-bold text-green-600">
            <?= count(array_filter($fornecedores, fn($f) => ($f['tipo_pessoa'] ?? 'J') === 'F')) ?>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Buscar por nome, CNPJ, telefone ou e-mail..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <select name="tipo" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="">Todos os tipos</option>
                <option value="J" <?= $tipo === 'J' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                <option value="F" <?= $tipo === 'F' ? 'selected' : '' ?>>Pessoa Física</option>
            </select>
            
            <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Filtrar
            </button>
        </form>
    </div>
</div>

<!-- Lista de Fornecedores -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($fornecedores)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum fornecedor cadastrado</h3>
        <p class="mt-1 text-sm text-gray-500">Comece cadastrando um novo fornecedor.</p>
        <div class="mt-6">
            <a href="fornecedor_novo.php" 
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Novo Fornecedor
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contato</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CNPJ/CPF</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Produtos</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($fornecedores as $fornecedor): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($fornecedor['nome_exibicao'] ?? $fornecedor['nome'] ?? '') ?>
                        </div>
                        <?php if (!empty($fornecedor['nome_fantasia']) && $fornecedor['nome_fantasia'] !== $fornecedor['nome']): ?>
                        <div class="text-sm text-gray-500">
                            <?= htmlspecialchars($fornecedor['nome_fantasia']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="text-xs text-gray-400 mt-1">
                            <?= ($fornecedor['tipo_pessoa'] ?? 'J') === 'J' ? 'Pessoa Jurídica' : 'Pessoa Física' ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?= htmlspecialchars($fornecedor['telefone_principal'] ?? $fornecedor['telefone'] ?? '-') ?>
                        </div>
                        <?php if (!empty($fornecedor['email'])): ?>
                        <div class="text-sm text-gray-500">
                            <?= htmlspecialchars($fornecedor['email']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?php 
                            $doc = $fornecedor['cpf_cnpj'] ?? '';
                            if ($doc) {
                                if (strlen($doc) == 11) {
                                    echo preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
                                } else {
                                    echo preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900">
                            <?= $fornecedor['total_produtos'] ?? 0 ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right text-sm font-medium">
                        <a href="fornecedor_detalhes.php?id=<?= $fornecedor['id'] ?>" 
                           class="text-indigo-600 hover:text-indigo-900 mr-3">
                            Ver Detalhes
                        </a>
                        <a href="fornecedor_editar.php?id=<?= $fornecedor['id'] ?>" 
                           class="text-blue-600 hover:text-blue-900">
                            Editar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
    <div class="px-6 py-4 border-t flex items-center justify-between">
        <div class="text-sm text-gray-600">
            Mostrando <?= count($fornecedores) ?> de <?= $totalRegistros ?> fornecedores
        </div>
        <div class="flex gap-2">
            <?php
            $query_params = [];
            if ($busca) $query_params['busca'] = $busca;
            if ($tipo) $query_params['tipo'] = $tipo;
            $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
            ?>
            
            <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                Anterior
            </a>
            <?php endif; ?>
            
            <?php
            $inicio = max(1, $pagina - 2);
            $fim = min($totalPaginas, $pagina + 2);
            
            if ($inicio > 1): ?>
                <a href="?pagina=1<?= $query_string ?>" class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">1</a>
                <?php if ($inicio > 2): ?>
                    <span class="px-3 py-2 text-gray-400">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                <?php if ($i == $pagina): ?>
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
            
            <?php if ($fim < $totalPaginas): ?>
                <?php if ($fim < $totalPaginas - 1): ?>
                    <span class="px-3 py-2 text-gray-400">...</span>
                <?php endif; ?>
                <a href="?pagina=<?= $totalPaginas ?><?= $query_string ?>" 
                   class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                    <?= $totalPaginas ?>
                </a>
            <?php endif; ?>
            
            <?php if ($pagina < $totalPaginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm">
                Próxima
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../views/layouts/_footer.php'; ?>
