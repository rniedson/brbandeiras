<?php
/**
 * Debug de Perfil - Verificar perfil do usuário atual
 */

require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

$titulo = 'Debug de Perfil';
include '../views/layouts/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Debug de Perfil do Usuário</h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Informações da Sessão</h2>
        <div class="space-y-2">
            <div><strong>user_perfil (sessão):</strong> <code class="bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($_SESSION['user_perfil'] ?? 'NÃO DEFINIDO') ?></code></div>
            <div><strong>perfilAtual (getPerfilEfetivo):</strong> <code class="bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars(getPerfilEfetivo() ?? 'NÃO DEFINIDO') ?></code></div>
            <div><strong>modoVerComo:</strong> <?= isVerComoAtivo() ? '<span class="text-red-600 font-bold">SIM</span>' : '<span class="text-green-600">NÃO</span>' ?></div>
        </div>
    </div>

    <?php if (isVerComoAtivo()): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4 text-yellow-800">⚠️ MODO VER COMO ATIVO</h2>
        <div class="space-y-2">
            <div><strong>ver_como_usuario[perfil]:</strong> <code class="bg-yellow-100 px-2 py-1 rounded"><?= htmlspecialchars($_SESSION['ver_como_usuario']['perfil'] ?? 'NÃO DEFINIDO') ?></code></div>
            <div><strong>ver_como_usuario[nome]:</strong> <?= htmlspecialchars($_SESSION['ver_como_usuario']['nome'] ?? 'NÃO DEFINIDO') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Verificação de Permissões</h2>
        <?php 
        $perfilAtual = getPerfilEfetivo();
        $perfilNormalizado = strtolower(trim($perfilAtual));
        ?>
        <div class="space-y-2">
            <div><strong>Perfil atual:</strong> <code class="bg-gray-100 px-2 py-1 rounded">'<?= htmlspecialchars($perfilAtual) ?>'</code></div>
            <div><strong>Perfil normalizado:</strong> <code class="bg-gray-100 px-2 py-1 rounded">'<?= htmlspecialchars($perfilNormalizado) ?>'</code></div>
            <div><strong>É gestor?</strong> <?= in_array($perfilNormalizado, ['gestor']) ? '<span class="text-green-600 font-bold">SIM</span>' : '<span class="text-red-600">NÃO</span>' ?></div>
            <div><strong>É administrador?</strong> <?= in_array($perfilNormalizado, ['administrador']) ? '<span class="text-green-600 font-bold">SIM</span>' : '<span class="text-red-600">NÃO</span>' ?></div>
            <div><strong>Tem acesso à auditoria?</strong> <?= in_array($perfilNormalizado, ['gestor', 'administrador']) ? '<span class="text-green-600 font-bold">SIM</span>' : '<span class="text-red-600">NÃO</span>' ?></div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Teste de Menu</h2>
        <?php
        // Simular verificação do menu
        $itemAuditoria = ['label' => 'Auditoria', 'url' => 'auditoria/auditoria.php', 'perfis' => ['gestor', 'administrador']];
        $temAcesso = temPermissao($itemAuditoria, $perfilAtual);
        ?>
        <div class="space-y-2">
            <div><strong>Item de menu (Auditoria):</strong></div>
            <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto"><?= print_r($itemAuditoria, true) ?></pre>
            <div><strong>temPermissao() retorna:</strong> <?= $temAcesso ? '<span class="text-green-600 font-bold">TRUE (deve aparecer)</span>' : '<span class="text-red-600 font-bold">FALSE (não aparece)</span>' ?></div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Todas as Variáveis de Sessão</h2>
        <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto"><?= print_r($_SESSION, true) ?></pre>
    </div>

    <div class="mt-6">
        <a href="dashboard/dashboard.php" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
            Voltar ao Dashboard
        </a>
    </div>
</div>

<?php include '../views/layouts/_footer.php'; ?>
