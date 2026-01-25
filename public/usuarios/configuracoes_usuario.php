<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            
            if (!$nome || !$email) {
                $_SESSION['erro'] = 'Nome e e-mail são obrigatórios';
            } else {
                // Verificar se email já existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $_SESSION['erro'] = 'Este e-mail já está em uso';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nome = ?, email = ?, telefone = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmt->execute([$nome, $email, $telefone, $_SESSION['user_id']]);
                    
                    $_SESSION['user_nome'] = $nome;
                    $_SESSION['mensagem'] = 'Perfil atualizado com sucesso!';
                    
                    // Log
                    $stmt = $pdo->prepare("
                        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
                        VALUES (?, 'atualizar_perfil', 'Perfil atualizado', ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
                }
            }
            break;
            
        case 'change_password':
            $senha_atual = $_POST['senha_atual'] ?? '';
            $senha_nova = $_POST['senha_nova'] ?? '';
            $senha_confirma = $_POST['senha_confirma'] ?? '';
            
            if (!$senha_atual || !$senha_nova || !$senha_confirma) {
                $_SESSION['erro'] = 'Preencha todos os campos de senha';
            } elseif ($senha_nova !== $senha_confirma) {
                $_SESSION['erro'] = 'As senhas não coincidem';
            } elseif (strlen($senha_nova) < 6) {
                $_SESSION['erro'] = 'A nova senha deve ter pelo menos 6 caracteres';
            } elseif (!password_verify($senha_atual, $usuario['senha'])) {
                $_SESSION['erro'] = 'Senha atual incorreta';
            } else {
                $senha_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET senha = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$senha_hash, $_SESSION['user_id']]);
                
                $_SESSION['mensagem'] = 'Senha alterada com sucesso!';
                
                // Log
                $stmt = $pdo->prepare("
                    INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
                    VALUES (?, 'alterar_senha', 'Senha alterada', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
            }
            break;
    }
    
    // Recarregar dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
}

$titulo = 'Configurações';
$breadcrumb = [
    ['label' => 'Meu Perfil', 'url' => 'perfil.php'],
    ['label' => 'Configurações']
];
include '../../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Configurações da Conta</h1>
        <p class="text-gray-600 mt-2">Gerencie suas informações pessoais e preferências</p>
    </div>
    
    <?php if (isset($_SESSION['mensagem'])): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
        <?= htmlspecialchars($_SESSION['mensagem']) ?>
        <?php unset($_SESSION['mensagem']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['erro'])): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
        <?= htmlspecialchars($_SESSION['erro']) ?>
        <?php unset($_SESSION['erro']); ?>
    </div>
    <?php endif; ?>
    
    <!-- Informações Pessoais -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Informações Pessoais</h2>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome Completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="nome" 
                           value="<?= htmlspecialchars($usuario['nome']) ?>"
                           required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        E-mail <span class="text-red-500">*</span>
                    </label>
                    <input type="email" 
                           name="email" 
                           value="<?= htmlspecialchars($usuario['email']) ?>"
                           required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Telefone
                    </label>
                    <input type="text" 
                           name="telefone" 
                           value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>"
                           placeholder="(00) 00000-0000"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Perfil
                    </label>
                    <input type="text" 
                           value="<?= ucfirst(str_replace('_', ' ', $usuario['perfil'])) ?>"
                           disabled
                           class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="submit" 
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
    
    <!-- Alterar Senha -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Alterar Senha</h2>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="change_password">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Senha Atual <span class="text-red-500">*</span>
                    </label>
                    <input type="password" 
                           name="senha_atual" 
                           required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nova Senha <span class="text-red-500">*</span>
                        </label>
                        <input type="password" 
                               name="senha_nova" 
                               required
                               minlength="6"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <p class="text-xs text-gray-500 mt-1">Mínimo 6 caracteres</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Confirmar Nova Senha <span class="text-red-500">*</span>
                        </label>
                        <input type="password" 
                               name="senha_confirma" 
                               required
                               minlength="6"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Alterar Senha
                </button>
            </div>
        </form>
    </div>
    
    <!-- Preferências -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Preferências do Sistema</h2>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium">Notificações por E-mail</h3>
                        <p class="text-sm text-gray-500">Receber atualizações sobre pedidos</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                    </label>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium">Notificações do Sistema</h3>
                        <p class="text-sm text-gray-500">Alertas e lembretes na plataforma</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                    </label>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium">Modo Compacto</h3>
                        <p class="text-sm text-gray-500">Visualização reduzida nas listas</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Informações da Conta -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Informações da Conta</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Data de Criação</p>
                    <p class="font-medium"><?= formatarDataHora($usuario['created_at']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Última Atualização</p>
                    <p class="font-medium"><?= formatarDataHora($usuario['updated_at'] ?? $usuario['created_at']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status da Conta</p>
                    <p class="font-medium">
                        <?php if ($usuario['ativo']): ?>
                        <span class="text-green-600">Ativa</span>
                        <?php else: ?>
                        <span class="text-red-600">Inativa</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">ID da Conta</p>
                    <p class="font-medium">#<?= $usuario['id'] ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Formatar telefone
document.querySelector('input[name="telefone"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        if (value.length > 6) {
            value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        } else if (value.length > 2) {
            value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        } else if (value.length > 0) {
            value = value.replace(/^(\d{0,2})/, '($1');
        }
    }
    e.target.value = value;
});

// Validar senha
document.querySelector('form[action*="change_password"]').addEventListener('submit', function(e) {
    const senha = document.querySelector('input[name="senha_nova"]').value;
    const confirma = document.querySelector('input[name="senha_confirma"]').value;
    
    if (senha !== confirma) {
        e.preventDefault();
        alert('As senhas não coincidem!');
    }
});
</script>

<?php include '../../views/layouts/_footer.php'; ?>