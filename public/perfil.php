<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

// Buscar dados completos do usuário
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as total_pedidos,
           COUNT(DISTINCT CASE WHEN p.created_at >= CURRENT_DATE - INTERVAL '30 days' THEN p.id END) as pedidos_mes,
           COUNT(DISTINCT l.id) as total_acessos,
           MAX(l.created_at) as ultimo_acesso
    FROM usuarios u
    LEFT JOIN pedidos p ON p.vendedor_id = u.id
    LEFT JOIN logs_acesso l ON l.usuario_id = u.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Buscar atividades recentes
$stmt = $pdo->prepare("
    SELECT ls.*, 
           CASE 
               WHEN ls.acao LIKE '%pedido%' THEN 'pedido'
               WHEN ls.acao LIKE '%cliente%' THEN 'cliente'
               WHEN ls.acao LIKE '%arte%' THEN 'arte'
               ELSE 'sistema'
           END as tipo_acao
    FROM logs_sistema ls
    WHERE ls.usuario_id = ?
    ORDER BY ls.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas específicas por perfil
$stats_especificas = [];

if ($_SESSION['user_perfil'] === 'vendedor') {
    // Estatísticas de vendas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_vendas,
            SUM(valor_final) as valor_total,
            AVG(valor_final) as ticket_medio,
            COUNT(CASE WHEN status = 'concluido' THEN 1 END) as vendas_concluidas
        FROM pedidos 
        WHERE vendedor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats_especificas = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($_SESSION['user_perfil'] === 'arte_finalista') {
    // Estatísticas de arte
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT av.pedido_id) as total_artes,
            COUNT(av.id) as total_versoes,
            COUNT(CASE WHEN av.status = 'aprovado' THEN 1 END) as artes_aprovadas,
            COUNT(CASE WHEN av.status = 'reprovado' THEN 1 END) as artes_reprovadas
        FROM arte_versoes av
        WHERE av.usuario_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats_especificas = $stmt->fetch(PDO::FETCH_ASSOC);
}

$titulo = 'Meu Perfil';
$breadcrumb = [
    ['label' => 'Meu Perfil']
];
include '../views/_header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Cabeçalho do Perfil -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="bg-gradient-to-r from-green-600 to-green-700 h-32 rounded-t-lg"></div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-end sm:space-x-5 -mt-12">
                <div class="relative">
                    <div class="h-24 w-24 rounded-full bg-white p-1">
                        <div class="h-full w-full rounded-full bg-gray-300 flex items-center justify-center text-3xl font-bold text-gray-600">
                            <?= strtoupper(substr($usuario['nome'], 0, 1)) ?>
                        </div>
                    </div>
                    <?php if ($usuario['ativo']): ?>
                    <span class="absolute bottom-0 right-0 h-6 w-6 bg-green-400 border-2 border-white rounded-full"></span>
                    <?php endif; ?>
                </div>
                <div class="mt-6 sm:mt-0 text-center sm:text-left flex-1">
                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($usuario['nome']) ?></h1>
                    <p class="text-gray-600"><?= htmlspecialchars($usuario['email']) ?></p>
                    <div class="mt-2 flex flex-wrap gap-2 justify-center sm:justify-start">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <?= ucfirst(str_replace('_', ' ', $usuario['perfil'])) ?>
                        </span>
                        <?php if ($usuario['ultimo_acesso']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                            Último acesso: <?= formatarDataHora($usuario['ultimo_acesso']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <a href="configuracoes_usuario.php" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Configurações
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estatísticas Gerais -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-500">Total de Pedidos</p>
                    <p class="text-xl font-bold"><?= $usuario['total_pedidos'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-500">Pedidos este Mês</p>
                    <p class="text-xl font-bold"><?= $usuario['pedidos_mes'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-500">Total de Acessos</p>
                    <p class="text-xl font-bold"><?= $usuario['total_acessos'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-500">Desde</p>
                    <p class="text-xl font-bold"><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Estatísticas Específicas -->
        <?php if (!empty($stats_especificas)): ?>
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">
                    <?= $_SESSION['user_perfil'] === 'vendedor' ? 'Estatísticas de Vendas' : 'Estatísticas de Arte' ?>
                </h2>
                
                <?php if ($_SESSION['user_perfil'] === 'vendedor'): ?>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Total de Vendas</p>
                        <p class="text-2xl font-bold"><?= $stats_especificas['total_vendas'] ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Vendas Concluídas</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats_especificas['vendas_concluidas'] ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Valor Total</p>
                        <p class="text-2xl font-bold"><?= formatarMoeda($stats_especificas['valor_total'] ?? 0) ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Ticket Médio</p>
                        <p class="text-2xl font-bold"><?= formatarMoeda($stats_especificas['ticket_medio'] ?? 0) ?></p>
                    </div>
                </div>
                
                <?php elseif ($_SESSION['user_perfil'] === 'arte_finalista'): ?>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Total de Artes</p>
                        <p class="text-2xl font-bold"><?= $stats_especificas['total_artes'] ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Total de Versões</p>
                        <p class="text-2xl font-bold"><?= $stats_especificas['total_versoes'] ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Artes Aprovadas</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats_especificas['artes_aprovadas'] ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded">
                        <p class="text-sm text-gray-500">Artes Reprovadas</p>
                        <p class="text-2xl font-bold text-red-600"><?= $stats_especificas['artes_reprovadas'] ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Atividades Recentes -->
        <div class="<?= empty($stats_especificas) ? 'lg:col-span-3' : '' ?>">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Atividades Recentes</h2>
                
                <?php if (empty($atividades)): ?>
                <p class="text-gray-500 text-center py-8">Nenhuma atividade registrada</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($atividades as $atividade): ?>
                    <div class="flex items-start gap-3 pb-3 border-b last:border-0">
                        <?php
                        $icon_color = 'text-gray-400';
                        $icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
                        
                        switch ($atividade['tipo_acao']) {
                            case 'pedido':
                                $icon_color = 'text-blue-500';
                                $icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
                                break;
                            case 'cliente':
                                $icon_color = 'text-green-500';
                                $icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>';
                                break;
                            case 'arte':
                                $icon_color = 'text-purple-500';
                                $icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>';
                                break;
                        }
                        ?>
                        <svg class="w-5 h-5 <?= $icon_color ?> mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <?= $icon ?>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900"><?= htmlspecialchars($atividade['detalhes']) ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?= formatarDataHora($atividade['created_at']) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../views/_footer.php'; ?>