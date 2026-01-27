<?php
// dashboard_producao.php - Dashboard para equipe de produção
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();
requireRole(['producao']);

// Verificação adicional de segurança
$arquivo_atual = basename($_SERVER['PHP_SELF']);
if ($_SESSION['user_perfil'] !== 'producao') {
    registrarLog('acesso_negado_dashboard', 
        "Usuário {$_SESSION['user_nome']} (Perfil: {$_SESSION['user_perfil']}) tentou acessar {$arquivo_atual}");
    $_SESSION['erro'] = 'Acesso negado. Redirecionando para seu dashboard.';
    header('Location: dashboard.php');
    exit;
}

// Funções auxiliares
function getIconForStatus($status) {
    $icons = [
        'producao' => 'cog',
        'pronto' => 'package',
        'entregue' => 'check-circle'
    ];
    return $icons[$status] ?? 'circle';
}

// Função para determinar status de pagamento baseado no status atual
function getStatusPagamento($status) {
    // Se o status é pagamento_50 ou pagamento_100, retorna o percentual
    if ($status === 'pagamento_50') return 50;
    if ($status === 'pagamento_100') return 100;
    
    // Se está em produção ou pronto, assumir que 50% foi pago (já passou pela validação comercial)
    if (in_array($status, ['producao', 'pronto'])) return 50;
    
    // Se está entregue, assumir que 100% foi pago
    if ($status === 'entregue') return 100;
    
    return 0;
}

// Função para obter o status real de produção (converte pagamento_50 para producao, pagamento_100 para pronto)
function getStatusProducao($status) {
    if ($status === 'pagamento_50') return 'producao';
    if ($status === 'pagamento_100') return 'pronto';
    return $status;
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

try {
    // Estatísticas da produção - apenas status reais de produção
    $stats = [
        'producao' => 0,
        'pronto' => 0,
        'entregue' => 0
    ];

    // Contar incluindo pagamento_50 como producao e pagamento_100 como pronto
    $stats['producao'] = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('pagamento_50', 'producao')")->fetchColumn() ?: 0;
    $stats['pronto'] = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('pagamento_100', 'pronto')")->fetchColumn() ?: 0;
    $stats['entregue'] = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'entregue'")->fetchColumn() ?: 0;

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
                
                // Verificar se status é permitido para produção (apenas status reais)
                $status_permitidos = ['producao', 'pronto', 'entregue'];
                if (!in_array($novo_status, $status_permitidos)) {
                    throw new Exception('Status não permitido para produção');
                }
                
                // Validação: não pode entregar sem 100% pago
                if ($novo_status === 'entregue') {
                    // Verificar status atual do pedido
                    $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?");
                    $stmt->execute([$pedido_id]);
                    $status_atual = $stmt->fetchColumn();
                    
                    $percentual_pago = getStatusPagamento($status_atual);
                    if ($percentual_pago < 100) {
                        throw new Exception('Não é possível entregar: pagamento não está 100% confirmado. Verifique o pagamento antes de entregar.');
                    }
                }
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$novo_status, $pedido_id]);
                
                $stmt = $pdo->prepare("INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$pedido_id, $novo_status, "Status alterado pela produção", $_SESSION['user_id']]);
                
                registrarLog('pedido_status_atualizado', "Pedido #$pedido_id movido para $novo_status pela produção");
                
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
    // Query apenas para pedidos em produção
    $sql = "
        SELECT 
            p.id,
            p.numero,
            p.status,
            p.urgente,
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
        WHERE p.status IN ('pagamento_50', 'producao', 'pronto', 'pagamento_100', 'entregue')
        AND p.status NOT IN ('cancelado')
    ";

    $params = [];

    if ($filtroStatus !== 'todos') {
        // Mapear filtros: producao inclui pagamento_50, pronto inclui pagamento_100
        if ($filtroStatus === 'producao') {
            $sql .= " AND p.status IN ('pagamento_50', 'producao')";
        } elseif ($filtroStatus === 'pronto') {
            $sql .= " AND p.status IN ('pagamento_100', 'pronto')";
        } else {
            $sql .= " AND p.status = ?";
            $params[] = $filtroStatus;
        }
    }

    if ($filtroUrgente) {
        $sql .= " AND p.urgente = true";
    }

    $sql .= " ORDER BY p.urgente DESC, p.prazo_entrega ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro na consulta SQL: " . $e->getMessage());
}

// Configuração apenas dos status reais de produção
$statusConfig = [
    'producao' => ['color' => 'bg-amber-500', 'label' => 'PRODUÇÃO', 'icon' => 'cog'],
    'pronto' => ['color' => 'bg-green-500', 'label' => 'PRONTO', 'icon' => 'package'],
    'entregue' => ['color' => 'bg-gray-500', 'label' => 'ENTREGUE', 'icon' => 'check-circle']
];

$titulo = 'Dashboard - Produção';
include '../../views/layouts/_header.php';
?>

<div class="flex-1 bg-gray-50" x-data="dashboardProducao()">
    <div class="p-6">
        <!-- Cards de Status - Apenas 3 cards principais -->
        <div class="grid-cols-3 grid gap-4 mb-6">
            <?php foreach ($statusConfig as $status => $config): ?>
            <div class="<?= $config['color'] ?> p-4 rounded-xl relative overflow-hidden transition-all hover:scale-105 cursor-pointer shadow-md"
                 :class="{ 'ring-4 ring-blue-400 scale-105': activeFilter === '<?= $status ?>' }"
                 @click="toggleStatusFilter('<?= $status ?>')"
                 @dragover.prevent="$el.classList.add('ring-4', 'ring-blue-300')"
                 @dragleave="$el.classList.remove('ring-4', 'ring-blue-300')"
                 @drop="handleDrop($event, '<?= $status ?>')">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-<?= $config['icon'] ?> text-white text-2xl"></i>
                    <span class="text-3xl font-bold text-white"><?= $stats[$status] ?></span>
                </div>
                <div class="text-white font-semibold text-base"><?= $config['label'] ?></div>
                <div class="text-xs mt-1 opacity-80 text-white">
                    <span x-show="activeFilter === '<?= $status ?>'">Filtro ativo</span>
                    <span x-show="activeFilter !== '<?= $status ?>'">Arraste OS aqui</span>
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
        </div>

        <!-- Tabela -->
        <div class="bg-white rounded-xl shadow-lg border">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 text-gray-700 text-left border-b">
                            <th class="p-4 font-semibold">OS</th>
                            <th class="p-4 font-semibold">Produto</th>
                            <th class="p-4 font-semibold">Cliente</th>
                            <th class="p-4 font-semibold">Vendedor</th>
                            <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
                                <th class="p-4 font-semibold">Valor</th>
                            <?php endif; ?>
                            <th class="p-4 font-semibold">Prazo</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold" x-show="showQuickActions">Ações Rápidas</th>
                            <th class="p-4 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="<?= $_SESSION['user_perfil'] === 'gestor' ? '9' : '8' ?>" class="p-12 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-4">
                                    <i class="fas fa-tools text-6xl text-gray-300"></i>
                                    <p class="text-xl">Nenhuma OS na produção</p>
                                    <p class="text-sm text-gray-400">Aguardando chegada de novos pedidos</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($pedidos as $pedido): 
                            $status_original = $pedido['status'];
                            $status_producao = getStatusProducao($status_original); // Converte pagamento_50/100 para producao/pronto
                            $config = $statusConfig[$status_producao] ?? $statusConfig['producao'];
                            $percentual_pago = getStatusPagamento($status_original);
                            
                            // Calcular dias restantes
                            $prazo = new DateTime($pedido['prazo_entrega']);
                            $hoje = new DateTime();
                            $diff = $hoje->diff($prazo);
                            $dias_restantes = $diff->invert ? -$diff->days : $diff->days;
                        ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors" 
                            draggable="true" 
                            @dragstart="handleDragStart($event, <?= $pedido['id'] ?>)"
                            data-pedido-id="<?= $pedido['id'] ?>"
                            data-percentual-pago="<?= $percentual_pago ?>">
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
                                    <?= htmlspecialchars(substr($pedido['primeiro_produto'] ?? 'Sem produto', 0, 30)) ?>
                                    <?php if (strlen($pedido['primeiro_produto'] ?? '') > 30): ?>...<?php endif; ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="text-gray-700">
                                    <?= htmlspecialchars(formatarNomeCliente($pedido['cliente_nome'] ?? '', $pedido['cliente_telefone'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-600"><?= htmlspecialchars($pedido['vendedor_nome'] ?? '') ?></td>
                            <td class="p-4">
                                <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
                                    <span class="<?= $pedido['urgente'] ? 'text-red-600 font-bold' : 'text-gray-700' ?> font-medium">
                                        <?= formatarMoeda($pedido['valor_final'] ?? 0) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 italic text-sm">***</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-1">
                                    <?php if ($dias_restantes < 0): ?>
                                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                                        <span class="text-red-600 font-bold"><?= abs($dias_restantes) ?> dias atrasado</span>
                                    <?php elseif ($dias_restantes == 0): ?>
                                        <i class="fas fa-clock text-yellow-500"></i>
                                        <span class="text-yellow-600 font-semibold">Hoje</span>
                                    <?php elseif ($dias_restantes <= 2): ?>
                                        <i class="fas fa-clock text-orange-500"></i>
                                        <span class="text-orange-600 font-semibold"><?= $dias_restantes ?> dias</span>
                                    <?php else: ?>
                                        <i class="fas fa-calendar text-gray-500"></i>
                                        <span class="text-gray-700"><?= $dias_restantes ?> dias</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex flex-col gap-2">
                                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full <?= $config['color'] ?> shadow-sm">
                                        <i class="fas fa-<?= $config['icon'] ?> text-white text-sm"></i>
                                        <span class="text-white text-sm font-semibold"><?= $config['label'] ?></span>
                                    </div>
                                    <!-- Badges de Pagamento -->
                                    <?php if ($percentual_pago >= 50 && $percentual_pago < 100): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 text-xs font-medium">
                                            <i class="fas fa-credit-card text-xs"></i>
                                            50% Pago
                                        </span>
                                    <?php elseif ($percentual_pago >= 100): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium">
                                            <i class="fas fa-check-circle text-xs"></i>
                                            100% Pago
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4" x-show="showQuickActions">
                                <div class="flex gap-1">
                                    <?php 
                                    // Apenas status reais de produção
                                    $statusPermitidos = ['producao', 'pronto', 'entregue'];
                                    foreach($statusPermitidos as $quickStatus): 
                                        if ($status_producao !== $quickStatus): ?>
                                    <button 
                                        @click="quickUpdateStatus(<?= $pedido['id'] ?>, '<?= $quickStatus ?>', <?= $percentual_pago ?>)" 
                                        class="<?= $statusConfig[$quickStatus]['color'] ?> hover:opacity-80 text-white p-2 rounded-lg text-sm shadow-sm transition-all" 
                                        title="Mover para <?= $statusConfig[$quickStatus]['label'] ?>"
                                    >
                                        <i class="fas fa-<?= $statusConfig[$quickStatus]['icon'] ?> text-base"></i>
                                    </button>
                                    <?php endif; endforeach; ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <a href="../pedidos/pedido_detalhes.php?id=<?= $pedido['id'] ?>" class="text-blue-600 hover:text-blue-700 p-2 rounded hover:bg-blue-50 transition-all" title="Visualizar">
                                        <i class="fas fa-eye"></i>
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
function dashboardProducao() {
    return {
        showQuickActions: true,
        notification: null,
        activeFilter: '<?= $filtroStatus === 'todos' ? '' : $filtroStatus ?>',
        draggedId: null,

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

        handleDragStart(event, id) {
            this.draggedId = id;
            event.dataTransfer.effectAllowed = 'move';
        },

        handleDrop(event, newStatus) {
            event.preventDefault();
            event.target.classList.remove('ring-4', 'ring-blue-300');
            
            if (this.draggedId) {
                // Buscar percentual de pagamento do elemento arrastado
                const row = event.target.closest('tbody')?.querySelector(`[data-pedido-id="${this.draggedId}"]`);
                const percentualPago = row ? parseInt(row.dataset.percentualPago || '0') : 0;
                this.quickUpdateStatus(this.draggedId, newStatus, percentualPago);
                this.draggedId = null;
            }
        },

        async quickUpdateStatus(id, status, percentualPago = 0) {
            // Validação: não pode entregar sem 100% pago
            if (status === 'entregue' && percentualPago < 100) {
                if (!confirm('⚠️ ATENÇÃO: Este pedido não está com 100% do pagamento confirmado.\n\nConfirme que o pagamento foi recebido antes de entregar.\n\nDeseja continuar mesmo assim?')) {
                    return;
                }
            }
            
            const formData = new FormData();
            formData.append('action', 'updateStatus');
            formData.append('pedido_id', id);
            formData.append('status', status);

            try {
                const response = await fetch('dashboard_producao.php', {
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
            
            window.location.href = 'dashboard_producao.php' + (params.toString() ? '?' + params.toString() : '');
        }
    }
}
</script>

<?php include '../../views/layouts/_footer.php'; ?>