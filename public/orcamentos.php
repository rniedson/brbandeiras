<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

// Filtros
$status = $_GET['status'] ?? 'todos';
$busca = $_GET['busca'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Base da query
$where = ["p.status = 'orcamento'"];
$params = [];

// Filtros de perfil
if ($_SESSION['user_perfil'] === 'vendedor') {
    $where[] = "p.vendedor_id = ?";
    $params[] = $_SESSION['user_id'];
}

// Filtro de busca
if ($busca) {
    $where[] = "(p.numero LIKE ? OR c.nome LIKE ? OR c.telefone LIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam]);
}

// Filtro de datas
if ($data_inicio && $data_fim) {
    $where[] = "DATE(p.created_at) BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

$whereClause = implode(' AND ', $where);

// Query corrigida para PostgreSQL
$sql = "
    SELECT p.*, 
           c.nome as cliente_nome, 
           c.telefone as cliente_telefone,
           c.email as cliente_email,
           u.nome as vendedor_nome,
           COALESCE(
               (SELECT COUNT(*) FROM pedido_arquivos WHERE pedido_id = p.id), 0
           ) as total_arquivos,
           CASE 
               WHEN p.created_at IS NOT NULL 
               THEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - p.created_at)) / 86400
               ELSE 0
           END::INTEGER as dias_aberto
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    WHERE $whereClause
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stats = [
    'total' => count($orcamentos),
    'valor_total' => 0,
    'aguardando' => 0,
    'vencidos' => 0
];

// Calcular estatísticas
foreach ($orcamentos as $orcamento) {
    $stats['valor_total'] += floatval($orcamento['valor_final']);
    
    $dias = intval($orcamento['dias_aberto']);
    if ($dias <= 7) {
        $stats['aguardando']++;
    } else {
        $stats['vencidos']++;
    }
}

$titulo = 'Orçamentos';
$breadcrumb = [
    ['label' => 'Orçamentos']
];
include '../views/_header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Orçamentos</h1>
    <p class="text-gray-600 mt-2">Gerencie orçamentos pendentes de aprovação</p>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Total de Orçamentos</p>
                <p class="text-xl font-bold"><?= $stats['total'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Valor Total</p>
                <p class="text-xl font-bold"><?= formatarMoeda($stats['valor_total']) ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Aguardando</p>
                <p class="text-xl font-bold"><?= $stats['aguardando'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-lg">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Vencidos (>7 dias)</p>
                <p class="text-xl font-bold text-red-600"><?= $stats['vencidos'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Buscar por número, cliente ou telefone..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <input type="date" name="data_inicio" value="<?= $data_inicio ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <input type="date" name="data_fim" value="<?= $data_fim ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Filtrar
            </button>
            
            <a href="pedido_novo.php" 
               class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Novo Orçamento
            </a>
        </form>
    </div>
</div>

<!-- Lista de Orçamentos -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($orcamentos)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum orçamento encontrado</h3>
        <p class="mt-1 text-sm text-gray-500">Comece criando um novo orçamento.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Orçamento
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Cliente
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Valor
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Prazo
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Dias
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Ações
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($orcamentos as $orcamento): ?>
                <?php $dias = intval($orcamento['dias_aberto']); ?>
                <tr class="hover:bg-gray-50 <?= $dias > 7 ? 'bg-red-50' : '' ?>">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <?php if ($orcamento['urgente']): ?>
                            <span class="text-red-500 mr-2" title="Urgente">⚡</span>
                            <?php endif; ?>
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars($orcamento['numero']) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= formatarData($orcamento['created_at']) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($orcamento['cliente_nome']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($orcamento['cliente_telefone']) ?></div>
                    </td>
                    
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm font-medium text-gray-900">
                            <?= formatarMoeda($orcamento['valor_final']) ?>
                        </div>
                        <?php if ($orcamento['desconto'] > 0): ?>
                        <div class="text-xs text-gray-500">
                            Desc: <?= formatarMoeda($orcamento['desconto']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                        <?= formatarData($orcamento['prazo_entrega']) ?>
                    </td>
                    
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <?php if ($dias > 7): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                            <?= $dias ?> dias
                        </span>
                        <?php elseif ($dias > 3): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                            <?= $dias ?> dias
                        </span>
                        <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            <?= $dias ?> dias
                        </span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                            Aguardando
                        </span>
                    </td>
                    
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end gap-2">
                            <a href="orcamento_detalhes.php?id=<?= $orcamento['id'] ?>" 
                               class="text-blue-600 hover:text-blue-900" title="Ver Detalhes">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            
                            <?php if ($orcamento['cliente_email']): ?>
                            <button onclick="enviarOrcamento(<?= $orcamento['id'] ?>)"
                                    class="text-green-600 hover:text-green-900" title="Enviar por E-mail">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                            <?php endif; ?>
                            
                            <a href="orcamento_pdf.php?id=<?= $orcamento['id'] ?>" target="_blank"
                               class="text-gray-600 hover:text-gray-900" title="Gerar PDF">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </a>
                            
                            <?php if ($_SESSION['user_perfil'] === 'gestor' || $orcamento['vendedor_id'] == $_SESSION['user_id']): ?>
                            <button onclick="aprovarOrcamento(<?= $orcamento['id'] ?>)"
                                    class="text-green-600 hover:text-green-900" title="Aprovar Orçamento">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function enviarOrcamento(id) {
    if (confirm('Enviar orçamento por e-mail para o cliente?')) {
        fetch('orcamento_enviar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Orçamento enviado com sucesso!');
            } else {
                alert('Erro ao enviar: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao enviar orçamento');
        });
    }
}

function aprovarOrcamento(id) {
    if (confirm('Aprovar este orçamento? Será convertido em pedido.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'orcamento_aprovar.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id';
        input.value = id;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../views/_footer.php'; ?>