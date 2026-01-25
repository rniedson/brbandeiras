<?php
// dashboard_vendedor.php - Dashboard para vendedores
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['vendedor']);

// Verificação adicional de segurança
$arquivo_atual = basename($_SERVER['PHP_SELF']);
if ($_SESSION['user_perfil'] !== 'vendedor') {
    registrarLog('acesso_negado_dashboard', 
        "Usuário {$_SESSION['user_nome']} (Perfil: {$_SESSION['user_perfil']}) tentou acessar {$arquivo_atual}");
    $_SESSION['erro'] = 'Acesso negado. Redirecionando para seu dashboard.';
    header('Location: dashboard.php');
    exit;
}

// Funções auxiliares
function getIconForStatus($status) {
    $icons = [
        'cancelado' => 'times-circle',
        'orcamento' => 'file-text',
        'arte' => 'palette',
        'pagamento_50' => 'credit-card',
        'producao' => 'cog',
        'pronto' => 'package',
        'pagamento_100' => 'dollar-sign',
        'entregue' => 'check-circle'
    ];
    return $icons[$status] ?? 'circle';
}

function formatarNomeCliente($nome, $telefone) {
    if (empty($nome)) return 'Cliente não encontrado';
    
    $palavras = explode(' ', trim($nome));
    if (count($palavras) <= 1) return $nome;
    
    $primeiro_nome = $palavras[0];
    $telefone_limpo = preg_replace('/\D/', '', $telefone);
    $ultimos_digitos = substr($telefone_limpo, -4);
    
    return $primeiro_nome . ($ultimos_digitos ? ' ...' . $ultimos_digitos : '');
}

function vendedorPodeAlterarStatus($status_atual) {
    return $status_atual !== 'producao';
}

function getProximosStatusVendedor($status_atual) {
    $fluxo = [
        'orcamento' => ['arte', 'pagamento_50', 'cancelado'],
        'arte' => ['pagamento_50', 'producao', 'cancelado'],
        'pagamento_50' => ['producao', 'cancelado'],
        'producao' => [], // Vendedor não pode alterar
        'pronto' => ['pagamento_100', 'entregue'],
        'pagamento_100' => ['entregue'],
        'entregue' => []
    ];
    
    return $fluxo[$status_atual] ?? [];
}

try {
    // Estatísticas do vendedor (apenas suas OS)
    $stats = [
        'cancelado' => 0,
        'orcamento' => 0,
        'arte' => 0,
        'pagamento_50' => 0,
        'producao' => 0,
        'pronto' => 0,
        'pagamento_100' => 0,
        'entregue' => 0,
        'urgentes' => 0
    ];

    $statusQueries = [
        'cancelado' => "SELECT COUNT(*) FROM pedidos WHERE status = 'cancelado' AND vendedor_id = {$_SESSION['user_id']}",
        'orcamento' => "SELECT COUNT(*) FROM pedidos WHERE status = 'orcamento' AND vendedor_id = {$_SESSION['user_id']}",
        'arte' => "SELECT COUNT(*) FROM pedidos WHERE status = 'arte' AND vendedor_id = {$_SESSION['user_id']}",
        'pagamento_50' => "SELECT COUNT(*) FROM pedidos WHERE status = 'pagamento_50' AND vendedor_id = {$_SESSION['user_id']}",
        'producao' => "SELECT COUNT(*) FROM pedidos WHERE status = 'producao' AND vendedor_id = {$_SESSION['user_id']}",
        'pronto' => "SELECT COUNT(*) FROM pedidos WHERE status = 'pronto' AND vendedor_id = {$_SESSION['user_id']}",
        'pagamento_100' => "SELECT COUNT(*) FROM pedidos WHERE status = 'pagamento_100' AND vendedor_id = {$_SESSION['user_id']}",
        'entregue' => "SELECT COUNT(*) FROM pedidos WHERE status = 'entregue' AND vendedor_id = {$_SESSION['user_id']}",
        'urgentes' => "SELECT COUNT(*) FROM pedidos WHERE urgente = true AND status NOT IN ('entregue', 'cancelado') AND vendedor_id = {$_SESSION['user_id']}"
    ];

    foreach ($statusQueries as $key => $query) {
        try {
            $result = $pdo->query($query);
            if ($result) {
                $stats[$key] = $result->fetchColumn() ?: 0;
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatística $key: " . $e->getMessage());
            $stats[$key] = 0;
        }
    }

} catch (Exception $e) {
    die("Erro ao buscar estatísticas: " . $e->getMessage());
}

// Filtros
$filtroStatus = $_GET['status'] ?? 'todos';
$filtroUrgente = isset($_GET['urgente']) ? true : false;

// Processar AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'updateStatus':
                $pedido_id = intval($_POST['pedido_id']);
                $novo_status = $_POST['status'];
                
                // Verificar se é pedido do vendedor
                $stmt = $pdo->prepare("SELECT vendedor_id, status FROM pedidos WHERE id = ?");
                $stmt->execute([$pedido_id]);
                $pedido = $stmt->fetch();
                
                if (!$pedido || $pedido['vendedor_id'] != $_SESSION['user_id']) {
                    throw new Exception('Você não tem permissão para alterar este pedido');
                }
                
                if (!vendedorPodeAlterarStatus($pedido['status'])) {
                    throw new Exception('Não é possível alterar o status durante a produção');
                }
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$novo_status, $pedido_id]);
                
                $stmt = $pdo->prepare("INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$pedido_id, $novo_status, "Status alterado pelo vendedor", $_SESSION['user_id']]);
                
                registrarLog('pedido_status_atualizado', "Pedido #$pedido_id movido para $novo_status");
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
                break;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

try {
    // Query apenas para pedidos do vendedor
    $sql = "
        SELECT 
            p.id,
            p.numero,
            p.status,
            p.urgente,
            p.valor_total,
            p.valor_final,
            p.prazo_entrega,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            u.nome as vendedor_nome,
            ua.nome as arte_finalista_nome,
            (SELECT pc.nome FROM pedido_itens pi LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id WHERE pi.pedido_id = p.id ORDER BY pi.id LIMIT 1) as primeiro_produto
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios ua ON pa.arte_finalista_id = ua.id
        WHERE p.vendedor_id = ?
    ";

    $params = [$_SESSION['user_id']];

    if ($filtroStatus !== 'todos') {
        $sql .= " AND p.status = ?";
        $params[] = $filtroStatus;
    }

    if ($filtroUrgente) {
        $sql .= " AND p.urgente = true";
    }

    $sql .= " ORDER BY p.urgente DESC, p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro na consulta SQL: " . $e->getMessage());
}

$statusConfig = [
    'cancelado' => ['color' => 'bg-red-500', 'label' => 'CANCELADO'],
    'orcamento' => ['color' => 'bg-blue-500', 'label' => 'ORÇAMENTO'],
    'arte' => ['color' => 'bg-purple-500', 'label' => 'ARTE'],
    'pagamento_50' => ['color' => 'bg-orange-500', 'label' => '50%'],
    'producao' => ['color' => 'bg-amber-500', 'label' => 'PRODUÇÃO'],
    'pronto' => ['color' => 'bg-green-500', 'label' => 'PRONTO'],
    'pagamento_100' => ['color' => 'bg-emerald-500', 'label' => '100% PAGO'],
    'entregue' => ['color' => 'bg-gray-500', 'label' => 'ENTREGUE']
];

$titulo = 'Dashboard - Vendedor';
include '../views/_header.php';
?>

<div class="flex-1 bg-gray-50" x-data="dashboardVendedor()">
    <div class="p-6">
        <!-- Cards de Status -->
        <div class="grid-cols-8 grid gap-4 mb-6">
            <?php foreach ($statusConfig as $status => $config): ?>
            <div class="<?= $config['color'] ?> p-4 rounded-xl relative overflow-hidden transition-all hover:scale-105 cursor-pointer shadow-md"
                 :class="{ 'ring-4 ring-blue-400 scale-105': activeFilter === '<?= $status ?>' }"
                 @click="toggleStatusFilter('<?= $status ?>')">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-<?= getIconForStatus($status) ?> text-white text-2xl"></i>
                    <span class="text-3xl font-bold text-white"><?= $stats[$status] ?></span>
                </div>
                <div class="text-white font-semibold text-sm"><?= $config['label'] ?></div>
                <div class="text-xs mt-1 opacity-80 text-white">
                    <span x-show="activeFilter === '<?= $status ?>'">Filtro ativo</span>
                    <span x-show="activeFilter !== '<?= $status ?>'">Clique para filtrar</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-4 rounded-xl mb-6 flex items-center gap-4 shadow-sm border">
            <i class="fas fa-filter text-gray-500"></i>
            
            <select id="filtroStatus" class="bg-gray-50 text-gray-700 px-4 py-2 rounded-lg border border-gray-200 focus:border-blue-400 focus:outline-none">
                <option value="todos">Todos Status</option>
                <?php foreach ($statusConfig as $status => $config): ?>
                <option value="<?= $status ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= $config['label'] ?></option>
                <?php endforeach; ?>
            </select>

            <label class="flex items-center gap-2 text-gray-700 cursor-pointer">
                <input type="checkbox" id="filtroUrgente" class="w-4 h-4 text-red-600" <?= $filtroUrgente ? 'checked' : '' ?>>
                <span class="text-sm font-medium">Apenas Urgentes</span>
            </label>

            <label class="flex items-center gap-2 text-gray-700 cursor-pointer">
                <input type="checkbox" x-model="showQuickActions" class="w-4 h-4 text-blue-600">
                <span class="text-sm font-medium">Ações Rápidas</span>
            </label>

            <div class="ml-auto">
                <a href="pedido_novo.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded-lg inline-flex items-center gap-2 transition-all shadow-md">
                    <i class="fas fa-plus"></i>
                    Nova OS
                </a>
            </div>
        </div>

        <!-- Tabela -->
        <div class="bg-white rounded-xl shadow-lg border">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 text-left border-b">
                            <th class="p-4">OS</th>
                            <th class="p-4">Produto</th>
                            <th class="p-4">Cliente</th>
                            <th class="p-4">Arte-finalista</th>
                            <th class="p-4">Valor</th>
                            <th class="p-4">Status</th>
                            <th class="p-4" x-show="showQuickActions">Ações Rápidas</th>
                            <th class="p-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="8" class="p-12 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-4">
                                    <i class="fas fa-inbox text-6xl text-gray-300"></i>
                                    <p class="text-xl">Nenhuma OS encontrada</p>
                                    <a href="pedido_novo.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-lg inline-flex items-center gap-2">
                                        <i class="fas fa-plus"></i>
                                        Criar OS
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($pedidos as $pedido): 
                            $status = $pedido['status'];
                            $config = $statusConfig[$status] ?? $statusConfig['orcamento'];
                        ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-900 font-bold"><?= htmlspecialchars($pedido['numero']) ?></span>
                                    <?php if ($pedido['urgente']): ?>
                                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse font-semibold">URGENTE</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="text-gray-700 text-sm">
                                    <?= htmlspecialchars(substr($pedido['primeiro_produto'] ?? 'Sem produto', 0, 25)) ?>
                                    <?php if (strlen($pedido['primeiro_produto'] ?? '') > 25): ?>...<?php endif; ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="text-gray-700">
                                    <?= htmlspecialchars(formatarNomeCliente($pedido['cliente_nome'] ?? '', $pedido['cliente_telefone'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <?php if ($status === 'arte' || $pedido['arte_finalista_nome']): ?>
                                    <?php if ($pedido['arte_finalista_nome']): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-user-check text-purple-600"></i>
                                            <span class="text-purple-700 font-medium"><?= htmlspecialchars($pedido['arte_finalista_nome']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-clock text-orange-500"></i>
                                            <span class="text-orange-600 italic">Aguardando</span>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="<?= $pedido['urgente'] ? 'text-red-600 font-bold' : 'text-gray-700' ?> font-medium">
                                    <?= formatarMoeda($pedido['valor_final'] ?? $pedido['valor_total'] ?? 0) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full <?= $config['color'] ?> shadow-sm">
                                    <i class="fas fa-<?= getIconForStatus($status) ?> text-white text-sm"></i>
                                    <span class="text-white text-sm font-semibold"><?= $config['label'] ?></span>
                                </div>
                            </td>
                            <td class="p-4" x-show="showQuickActions">
                                <div class="flex gap-1">
                                    <?php if (vendedorPodeAlterarStatus($status)): ?>
                                        <?php $proximosStatus = getProximosStatusVendedor($status);
                                        foreach($proximosStatus as $quickStatus): 
                                            if (isset($statusConfig[$quickStatus])): ?>
                                        <button 
                                            @click="quickUpdateStatus(<?= $pedido['id'] ?>, '<?= $quickStatus ?>')" 
                                            class="<?= $statusConfig[$quickStatus]['color'] ?> hover:opacity-80 text-white p-2 rounded-lg text-sm shadow-sm transition-all" 
                                            title="Mover para <?= $statusConfig[$quickStatus]['label'] ?>"
                                        >
                                            <i class="fas fa-<?= getIconForStatus($quickStatus) ?> text-base"></i>
                                        </button>
                                        <?php endif; endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500 italic">Em produção</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" class="text-blue-600 hover:text-blue-700 p-2 rounded hover:bg-blue-50 transition-all" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="pedido_editar.php?id=<?= $pedido['id'] ?>" class="text-green-600 hover:text-green-700 p-2 rounded hover:bg-green-50 transition-all" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Notificação -->
    <div x-show="notification" x-cloak class="fixed bottom-8 right-8 z-50">
        <div :class="notification?.type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3">
            <i class="fas fa-check-circle"></i>
            <span x-text="notification?.message"></span>
        </div>
    </div>
</div>

<script>
function dashboardVendedor() {
    return {
        showQuickActions: true,
        notification: null,
        activeFilter: '<?= $filtroStatus === 'todos' ? '' : $filtroStatus ?>',

        init() {
            document.getElementById('filtroStatus').addEventListener('change', () => this.applyFilters());
            document.getElementById('filtroUrgente').addEventListener('change', () => this.applyFilters());
        },

        toggleStatusFilter(status) {
            if (this.activeFilter === status) {
                this.activeFilter = '';
                document.getElementById('filtroStatus').value = 'todos';
            } else {
                this.activeFilter = status;
                document.getElementById('filtroStatus').value = status;
            }
            this.applyFilters();
        },

        async quickUpdateStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'updateStatus');
            formData.append('pedido_id', id);
            formData.append('status', status);

            try {
                const response = await fetch('dashboard_vendedor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('OS atualizada com sucesso', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showNotification(data.message, 'error');
                }
            } catch (error) {
                this.showNotification('Erro ao atualizar status', 'error');
            }
        },

        showNotification(message, type) {
            this.notification = { message, type };
            setTimeout(() => {
                this.notification = null;
            }, 3000);
        },

        applyFilters() {
            const params = new URLSearchParams();
            
            const status = document.getElementById('filtroStatus').value;
            if (status !== 'todos') params.set('status', status);
            
            if (document.getElementById('filtroUrgente').checked) {
                params.set('urgente', '1');
            }
            
            window.location.href = 'dashboard_vendedor.php' + (params.toString() ? '?' + params.toString() : '');
        }
    }
}
</script>

<?php include '../views/_footer.php'; ?>