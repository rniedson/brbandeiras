<?php
// dashboard_arte_finalista.php - Interface Visual Otimizada
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();
requireRole(['arte_finalista']);

// Verificação de segurança
if ($_SESSION['user_perfil'] !== 'arte_finalista') {
    registrarLog('acesso_negado_dashboard', 
        "Usuário {$_SESSION['user_nome']} tentou acessar dashboard arte-finalista");
    $_SESSION['erro'] = 'Acesso negado.';
    header('Location: dashboard.php');
    exit;
}

function formatarNomeCliente($nome, $telefone) {
    if (empty($nome)) return 'OS';
    $primeiro = explode(' ', trim($nome))[0];
    $digitos = substr(preg_replace('/\D/', '', $telefone), -4);
    return $primeiro . ($digitos ? '...' . $digitos : '');
}

// Processar AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pedido_id = intval($_POST['pedido_id']);
        
        switch ($_POST['action']) {
            case 'assumir':
                $pdo->beginTransaction();
                
                // Verificar disponibilidade
                $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND status = 'arte'");
                $stmt->execute([$pedido_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('OS não disponível');
                }
                
                // Atribuir ou criar
                $stmt = $pdo->prepare("
                    INSERT INTO pedido_arte (pedido_id, arte_finalista_id) 
                    VALUES (?, ?) 
                    ON CONFLICT (pedido_id) 
                    DO UPDATE SET arte_finalista_id = ?, updated_at = NOW()
                ");
                $stmt->execute([$pedido_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                
                registrarLog('arte_assumida', "OS #$pedido_id assumida");
                $pdo->commit();
                
                echo json_encode(['success' => true]);
                break;
                
            case 'entregar':
                $pdo->beginTransaction();
                
                // Verificar responsabilidade
                $stmt = $pdo->prepare("
                    SELECT arte_finalista_id FROM pedido_arte 
                    WHERE pedido_id = ? AND arte_finalista_id = ?
                ");
                $stmt->execute([$pedido_id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception('Você não é responsável por esta OS');
                }
                
                // Mudar status
                $stmt = $pdo->prepare("UPDATE pedidos SET status = 'aprovado', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$pedido_id]);
                
                registrarLog('arte_entregue', "OS #$pedido_id entregue para aprovação");
                $pdo->commit();
                
                echo json_encode(['success' => true]);
                break;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Buscar OSs do arte-finalista
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.numero,
        p.urgente,
        p.prazo_entrega,
        c.nome as cliente_nome,
        c.telefone as cliente_telefone,
        COUNT(av.id) as total_versoes,
        MAX(av.created_at) as ultima_versao_data,
        (SELECT arquivo_caminho FROM arte_versoes WHERE pedido_id = p.id ORDER BY versao DESC LIMIT 1) as ultima_arte
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
    LEFT JOIN arte_versoes av ON av.pedido_id = p.id
    WHERE p.status = 'arte' AND pa.arte_finalista_id = ?
    GROUP BY p.id, p.numero, p.urgente, p.prazo_entrega, c.nome, c.telefone
    ORDER BY p.urgente DESC, p.prazo_entrega ASC
");
$stmt->execute([$_SESSION['user_id']]);
$minhas_os = $stmt->fetchAll();

// Buscar OSs disponíveis
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.numero,
        p.urgente,
        p.prazo_entrega,
        c.nome as cliente_nome,
        c.telefone as cliente_telefone,
        u.nome as vendedor_nome
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
    WHERE p.status = 'arte' AND (pa.arte_finalista_id IS NULL OR pa.arte_finalista_id = 0)
    ORDER BY p.urgente DESC, p.prazo_entrega ASC
");
$stmt->execute();
$os_disponiveis = $stmt->fetchAll();

$titulo = 'Área de Arte';
include '../../views/layouts/_header.php';
?>

<style>
.os-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}
.os-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(147, 51, 234, 0.15);
    border-color: rgb(147, 51, 234);
}
.preview-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.tab-active {
    border-bottom: 3px solid rgb(147, 51, 234);
    color: rgb(147, 51, 234);
}
</style>

<div class="min-h-screen bg-gray-50" x-data="dashboardArte()">
    <!-- Header Compacto -->
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="text-white">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fas fa-palette"></i>
                    Área de Arte
                </h1>
                <p class="text-purple-100 text-sm">
                    <?= count($minhas_os) ?> OS em andamento • 
                    <?= count($os_disponiveis) ?> disponíveis
                </p>
            </div>
            <div class="text-white text-sm">
                <i class="fas fa-user-circle mr-1"></i>
                <?= htmlspecialchars($_SESSION['user_nome']) ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-7xl mx-auto">
            <div class="flex">
                <button @click="tab = 'minhas'" 
                        :class="tab === 'minhas' ? 'tab-active' : ''"
                        class="px-6 py-3 font-medium text-gray-700 hover:text-purple-600 transition border-b-3 border-transparent">
                    <i class="fas fa-paint-brush mr-2"></i>
                    Minhas OSs
                    <?php if (count($minhas_os) > 0): ?>
                        <span class="ml-2 px-2 py-1 bg-purple-100 text-purple-700 rounded-full text-xs">
                            <?= count($minhas_os) ?>
                        </span>
                    <?php endif; ?>
                </button>
                <button @click="tab = 'disponiveis'" 
                        :class="tab === 'disponiveis' ? 'tab-active' : ''"
                        class="px-6 py-3 font-medium text-gray-700 hover:text-purple-600 transition border-b-3 border-transparent">
                    <i class="fas fa-inbox mr-2"></i>
                    Disponíveis
                    <?php if (count($os_disponiveis) > 0): ?>
                        <span class="ml-2 px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs">
                            <?= count($os_disponiveis) ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Tab: Minhas OSs -->
        <div x-show="tab === 'minhas'" x-transition>
            <?php if (empty($minhas_os)): ?>
                <div class="text-center py-16">
                    <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                    <p class="text-xl text-gray-600">Nenhuma OS em andamento</p>
                    <p class="text-gray-500 mt-2">Pegue uma OS disponível para começar</p>
                    <button @click="tab = 'disponiveis'" 
                            class="mt-4 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Ver OSs Disponíveis
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($minhas_os as $os): 
                        $prazo = new DateTime($os['prazo_entrega']);
                        $hoje = new DateTime();
                        $dias = $hoje->diff($prazo)->days;
                        $atrasado = $hoje > $prazo;
                    ?>
                    <div class="os-card bg-white rounded-xl shadow-md overflow-hidden cursor-pointer"
                         onclick="window.location.href='../pedidos/pedido_detalhes.php?id=<?= $os['id'] ?>'">
                        
                        <!-- Preview da Arte -->
                        <div class="preview-thumb h-40 flex items-center justify-center relative">
                            <?php if ($os['ultima_arte']): ?>
                                <?php 
                                $ext = strtolower(pathinfo($os['ultima_arte'], PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                                ?>
                                    <img src="../<?= htmlspecialchars($os['ultima_arte']) ?>" 
                                         alt="Preview" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-file-<?= $ext === 'pdf' ? 'pdf' : 'image' ?> text-4xl text-white opacity-50"></i>
                                <?php endif; ?>
                            <?php else: ?>
                                <i class="fas fa-image text-4xl text-white opacity-50"></i>
                            <?php endif; ?>
                            
                            <!-- Badges -->
                            <?php if ($os['urgente']): ?>
                                <div class="absolute top-2 left-2">
                                    <span class="px-2 py-1 bg-red-500 text-white text-xs rounded-full font-bold animate-pulse">
                                        URGENTE
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($os['total_versoes'] > 0): ?>
                                <div class="absolute top-2 right-2">
                                    <span class="px-2 py-1 bg-black bg-opacity-50 text-white text-xs rounded-full">
                                        v<?= $os['total_versoes'] ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info -->
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-gray-900">
                                    OS #<?= htmlspecialchars($os['numero']) ?>
                                </h3>
                                <span class="text-xs <?= $atrasado ? 'text-red-600 font-bold' : ($dias <= 2 ? 'text-yellow-600' : 'text-gray-500') ?>">
                                    <?= $atrasado ? abs($dias) . 'd atraso' : $dias . 'd' ?>
                                </span>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3">
                                <?= htmlspecialchars(formatarNomeCliente($os['cliente_nome'], $os['cliente_telefone'])) ?>
                            </p>
                            
                            <!-- Ações Rápidas -->
                            <div class="flex gap-2">
                                <button onclick="event.stopPropagation(); window.location.href='arte_upload.php?pedido_id=<?= $os['id'] ?>'"
                                        class="flex-1 px-3 py-2 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 transition">
                                    <i class="fas fa-plus mr-1"></i>
                                    Nova Versão
                                </button>
                                <button onclick="event.stopPropagation(); entregarOS(<?= $os['id'] ?>)"
                                        class="px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                            
                            <?php if ($os['ultima_versao_data']): ?>
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-clock mr-1"></i>
                                    Última: <?= date('d/m H:i', strtotime($os['ultima_versao_data'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: OSs Disponíveis -->
        <div x-show="tab === 'disponiveis'" x-transition>
            <?php if (empty($os_disponiveis)): ?>
                <div class="text-center py-16">
                    <i class="fas fa-check-circle text-6xl text-green-400 mb-4"></i>
                    <p class="text-xl text-gray-600">Todas as OSs estão sendo atendidas</p>
                    <p class="text-gray-500 mt-2">Volte mais tarde para ver novas OSs</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($os_disponiveis as $os): 
                        $prazo = new DateTime($os['prazo_entrega']);
                        $hoje = new DateTime();
                        $dias = $hoje->diff($prazo)->days;
                        $atrasado = $hoje > $prazo;
                    ?>
                    <div class="bg-white rounded-lg shadow p-4 border-2 border-gray-200 hover:border-purple-400 transition">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                    OS #<?= htmlspecialchars($os['numero']) ?>
                                    <?php if ($os['urgente']): ?>
                                        <span class="px-2 py-1 bg-red-500 text-white text-xs rounded-full animate-pulse">
                                            URGENTE
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?= htmlspecialchars(formatarNomeCliente($os['cliente_nome'], $os['cliente_telefone'])) ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm <?= $atrasado ? 'text-red-600 font-bold' : ($dias <= 2 ? 'text-yellow-600 font-semibold' : 'text-gray-600') ?>">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <?php if ($atrasado): ?>
                                        <?= abs($dias) ?> dias atraso
                                    <?php elseif ($dias == 0): ?>
                                        Hoje
                                    <?php else: ?>
                                        <?= $dias ?> dias
                                    <?php endif; ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= formatarData($os['prazo_entrega']) ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="border-t pt-3 mt-3">
                            <p class="text-xs text-gray-500 mb-2">
                                <i class="fas fa-user mr-1"></i>
                                Vendedor: <?= htmlspecialchars($os['vendedor_nome'] ?? 'Não informado') ?>
                            </p>
                            <button onclick="assumirOS(<?= $os['id'] ?>)"
                                    class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                                <i class="fas fa-hand-paper mr-2"></i>
                                Pegar OS
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notificação -->
    <div x-show="notification" x-cloak 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed bottom-4 right-4 z-50">
        <div :class="notification?.type === 'success' ? 'bg-green-500' : 'bg-red-500'" 
             class="text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3">
            <i :class="notification?.type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
            <span x-text="notification?.message"></span>
        </div>
    </div>
</div>

<script>
function dashboardArte() {
    return {
        tab: 'minhas',
        notification: null,

        showNotification(message, type = 'success') {
            this.notification = { message, type };
            setTimeout(() => this.notification = null, 3000);
        }
    }
}

async function assumirOS(id) {
    if (!confirm('Pegar esta OS?')) return;
    
    const formData = new FormData();
    formData.append('action', 'assumir');
    formData.append('pedido_id', id);
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Erro ao pegar OS');
        }
    } catch (error) {
        alert('Erro ao processar requisição');
    }
}

async function entregarOS(id) {
    if (!confirm('Entregar arte para aprovação?')) return;
    
    const formData = new FormData();
    formData.append('action', 'entregar');
    formData.append('pedido_id', id);
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Erro ao entregar OS');
        }
    } catch (error) {
        alert('Erro ao processar requisição');
    }
}
</script>

<?php include '../../views/layouts/_footer.php'; ?>