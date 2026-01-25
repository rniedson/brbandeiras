<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor']);

// Filtros
$filtro_perfil = $_GET['perfil'] ?? '';
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$busca = $_GET['busca'] ?? '';

// Query base
$sql = "SELECT u.*,
        COUNT(DISTINCT p.id) as total_pedidos,
        MAX(la.created_at) as ultimo_acesso
        FROM usuarios u
        LEFT JOIN pedidos p ON p.vendedor_id = u.id
        LEFT JOIN logs_acesso la ON la.usuario_id = u.id
        WHERE 1=1";

$params = [];

// Aplicar filtros
if ($filtro_perfil) {
    $sql .= " AND u.perfil = ?";
    $params[] = $filtro_perfil;
}

if ($filtro_status !== '') {
    $sql .= " AND u.ativo = ?";
    $params[] = $filtro_status;
}

if ($busca) {
    $sql .= " AND (u.nome ILIKE ? OR u.email ILIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " GROUP BY u.id, u.nome, u.email, u.senha, u.perfil, u.ativo, u.telefone, u.observacoes, u.created_at, u.updated_at
          ORDER BY u.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stmt_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN ativo = true THEN 1 END) as ativos,
        COUNT(CASE WHEN perfil = 'gestor' THEN 1 END) as gestores,
        COUNT(CASE WHEN perfil = 'vendedor' THEN 1 END) as vendedores,
        COUNT(CASE WHEN perfil = 'producao' THEN 1 END) as producao,
        COUNT(CASE WHEN perfil = 'arte_finalista' THEN 1 END) as arte_finalistas
    FROM usuarios
");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$titulo = 'Usuários';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Usuários']
];

include '../views/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Cabeçalho com estatísticas -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Gerenciar Usuários</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Gerencie os usuários do sistema</p>
            </div>
            <a href="usuario_novo.php" 
               class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-2 rounded-lg transition inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Novo Usuário
            </a>
        </div>
        
        <!-- Estatísticas -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-800 dark:text-gray-100"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600"><?= $stats['ativos'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Ativos</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-purple-600"><?= $stats['gestores'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Gestores</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-600"><?= $stats['vendedores'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Vendedores</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-orange-600"><?= $stats['producao'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Produção</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-indigo-600"><?= $stats['arte_finalistas'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Arte-Finalistas</div>
            </div>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (isset($_SESSION['mensagem'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
            <span class="block sm:inline"><?= $_SESSION['mensagem'] ?></span>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['erro'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
            <span class="block sm:inline"><?= $_SESSION['erro'] ?></span>
        </div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Buscar</label>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" 
                       placeholder="Nome ou e-mail..." 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Perfil</label>
                <select name="perfil" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                    <option value="">Todos</option>
                    <option value="gestor" <?= $filtro_perfil === 'gestor' ? 'selected' : '' ?>>Gestor</option>
                    <option value="vendedor" <?= $filtro_perfil === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                    <option value="producao" <?= $filtro_perfil === 'producao' ? 'selected' : '' ?>>Produção</option>
                    <option value="arte_finalista" <?= $filtro_perfil === 'arte_finalista' ? 'selected' : '' ?>>Arte-Finalista</option>
                    <option value="financeiro" <?= $filtro_perfil === 'financeiro' ? 'selected' : '' ?>>Financeiro</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                    <option value="">Todos</option>
                    <option value="1" <?= $filtro_status === '1' ? 'selected' : '' ?>>Ativos</option>
                    <option value="0" <?= $filtro_status === '0' ? 'selected' : '' ?>>Inativos</option>
                </select>
            </div>
            
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-2 rounded-lg transition">
                    Filtrar
                </button>
                <a href="usuarios.php" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-medium px-4 py-2 rounded-lg transition">
                    Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista de usuários -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Usuário
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Perfil
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Último Acesso
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Pedidos
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-gray-600 dark:text-gray-300 font-medium text-sm">
                                            <?= strtoupper(substr($usuario['nome'], 0, 2)) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <?= htmlspecialchars($usuario['nome']) ?>
                                        <?php if ($usuario['id'] == $_SESSION['user_id']): ?>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">(você)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= htmlspecialchars($usuario['email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $perfil_badges = [
                                'gestor' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                'vendedor' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                'producao' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                                'arte_finalista' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
                                'financeiro' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                            ];
                            $badge_class = $perfil_badges[$usuario['perfil']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $badge_class ?>">
                                <?= ucfirst(str_replace('_', ' ', $usuario['perfil'])) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($usuario['ativo']): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                    Ativo
                                </span>
                            <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                    Inativo
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?= $usuario['ultimo_acesso'] ? formatarDataHora($usuario['ultimo_acesso']) : 'Nunca' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php if ($usuario['perfil'] === 'vendedor' && $usuario['total_pedidos'] > 0): ?>
                                <span class="font-medium"><?= $usuario['total_pedidos'] ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <?php if ($usuario['id'] != $_SESSION['user_id'] && $usuario['ativo']): ?>
                                    <!-- Botão Ver Como - Apenas para usuários diferentes do gestor logado e ativos -->
                                    <button onclick="verComo(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nome']) ?>', '<?= htmlspecialchars($usuario['perfil']) ?>')"
                                            class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300 relative group"
                                            title="Visualizar o sistema como este usuário">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <!-- Tooltip -->
                                        <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-900 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                                            Ver como este usuário
                                        </span>
                                    </button>
                                <?php endif; ?>
                                
                                <a href="usuario_editar.php?id=<?= $usuario['id'] ?>" 
                                   class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                   title="Editar usuário">
                                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Editar
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($usuarios)): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Nenhum usuário encontrado</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Tente ajustar os filtros ou adicione um novo usuário.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de confirmação Ver Como -->
<div id="modalVerComo" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-purple-100">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Modo "Ver Como"</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Você está prestes a visualizar o sistema como:
                </p>
                <p class="mt-2 font-semibold text-gray-900" id="verComoNome">-</p>
                <p class="text-sm text-gray-600" id="verComoPerfil">-</p>
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-xs text-yellow-800">
                        <strong>Atenção:</strong> No modo "Ver Como", você terá a mesma visão e permissões do usuário selecionado. 
                        As ações de alteração estarão desabilitadas por segurança.
                    </p>
                </div>
            </div>
            <div class="items-center px-4 py-3">
                <button id="btnConfirmarVerComo" 
                        class="px-4 py-2 bg-purple-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    Confirmar e Continuar
                </button>
                <button onclick="fecharModalVerComo()" 
                        class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let usuarioVerComoId = null;

function verComo(userId, userName, userProfile) {
    usuarioVerComoId = userId;
    
    // Traduzir perfil para exibição
    const perfilTexto = {
        'gestor': 'Gestor',
        'vendedor': 'Vendedor',
        'producao': 'Produção',
        'arte_finalista': 'Arte-Finalista',
        'financeiro': 'Financeiro'
    };
    
    document.getElementById('verComoNome').textContent = userName;
    document.getElementById('verComoPerfil').textContent = 'Perfil: ' + (perfilTexto[userProfile] || userProfile);
    document.getElementById('modalVerComo').classList.remove('hidden');
}

function fecharModalVerComo() {
    document.getElementById('modalVerComo').classList.add('hidden');
    usuarioVerComoId = null;
}

document.getElementById('btnConfirmarVerComo').addEventListener('click', function() {
    if (usuarioVerComoId) {
        // Registrar no log antes de ativar
        fetch('ver_como_ativar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'usuario_id=' + usuarioVerComoId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirecionar para o dashboard com o modo ativado
                window.location.href = 'dashboard.php';
            } else {
                alert('Erro ao ativar modo "Ver Como": ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            alert('Erro ao processar solicitação');
            console.error('Error:', error);
        });
    }
});

// Fechar modal ao clicar fora
document.getElementById('modalVerComo').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalVerComo();
    }
});
</script>

<?php include '../views/_footer.php'; ?>