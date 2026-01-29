<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();
requireRole(['gestor']);

$titulo = 'Novo Usuário';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Usuários', 'url' => 'usuarios.php'],
    ['label' => 'Novo']
];

include '../../views/layouts/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        
        <!-- Cabeçalho -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Novo Usuário</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Adicione um novo usuário ao sistema</p>
        </div>

        <!-- Alertas -->
        <?php if (isset($_SESSION['erro'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                <span class="block sm:inline"><?= $_SESSION['erro'] ?></span>
            </div>
            <?php unset($_SESSION['erro']); ?>
        <?php endif; ?>

        <!-- Formulário -->
        <form action="usuario_salvar.php" method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            
            <!-- Informações Básicas -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Informações Básicas</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nome de Usuário <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                               required autofocus
                               placeholder="Ex: joao.silva">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            E-mail <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Telefone
                        </label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               placeholder="(00) 00000-0000">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Perfil <span class="text-red-500">*</span>
                        </label>
                        <select name="perfil" 
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                                required>
                            <option value="">Selecione...</option>
                            <option value="gestor" <?= ($_POST['perfil'] ?? '') == 'gestor' ? 'selected' : '' ?>>
                                Gestor
                            </option>
                            <option value="vendedor" <?= ($_POST['perfil'] ?? '') == 'vendedor' ? 'selected' : '' ?>>
                                Vendedor
                            </option>
                            <option value="producao" <?= ($_POST['perfil'] ?? '') == 'producao' ? 'selected' : '' ?>>
                                Produção
                            </option>
                            <option value="arte_finalista" <?= ($_POST['perfil'] ?? '') == 'arte_finalista' ? 'selected' : '' ?>>
                                Arte-Finalista
                            </option>
                            <option value="financeiro" <?= ($_POST['perfil'] ?? '') == 'financeiro' ? 'selected' : '' ?>>
                                Financeiro
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Segurança -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Segurança</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Senha <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="senha" id="senha"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               minlength="6"
                               placeholder="Mínimo 6 caracteres"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Confirmar Senha <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="confirmar_senha" id="confirmar_senha"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               minlength="6"
                               placeholder="Digite a senha novamente"
                               required>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Status da Conta</h3>
                
                <div class="flex items-center space-x-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="ativo" value="1" 
                               class="form-radio text-green-600" 
                               checked>
                        <span class="ml-2 text-gray-700 dark:text-gray-300">Ativo</span>
                    </label>
                    
                    <label class="inline-flex items-center">
                        <input type="radio" name="ativo" value="0" 
                               class="form-radio text-red-600">
                        <span class="ml-2 text-gray-700 dark:text-gray-300">Inativo</span>
                    </label>
                </div>
            </div>

            <!-- Observações -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Observações
                </label>
                <textarea name="observacoes" rows="3"
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                          placeholder="Observações sobre o usuário (opcional)"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
            </div>

            <!-- Opções adicionais -->
            <div class="mb-6 bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="forcar_troca_senha" value="1" 
                           class="form-checkbox text-green-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        Forçar troca de senha no primeiro acesso
                    </span>
                </label>
            </div>

            <!-- Botões -->
            <div class="flex justify-between items-center">
                <a href="usuarios.php" 
                   class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-medium px-6 py-2 rounded-lg transition">
                    Cancelar
                </a>
                
                <button type="submit" 
                        class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-2 rounded-lg transition">
                    Criar Usuário
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Validação de senha
document.querySelector('form').addEventListener('submit', function(e) {
    const senha = document.getElementById('senha').value;
    const confirmarSenha = document.getElementById('confirmar_senha').value;
    
    if (senha !== confirmarSenha) {
        e.preventDefault();
        alert('As senhas não coincidem!');
        return false;
    }
    
    if (senha.length < 6) {
        e.preventDefault();
        alert('A senha deve ter no mínimo 6 caracteres!');
        return false;
    }
});
</script>

<?php include '../../views/layouts/_footer.php'; ?>