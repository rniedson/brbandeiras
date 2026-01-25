<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor']); // Apenas gestores podem editar usuários

$id = $_GET['id'] ?? null;

if (!$id) {
    $_SESSION['erro'] = 'Usuário não encontrado';
    header('Location: usuarios.php');
    exit;
}

// Buscar dados do usuário
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(p.id) as total_pedidos,
           COUNT(CASE WHEN p.status = 'orcamento' THEN 1 END) as pedidos_pendentes
    FROM usuarios u
    LEFT JOIN pedidos p ON p.vendedor_id = u.id
    WHERE u.id = ?
    GROUP BY u.id, u.nome, u.email, u.senha, u.perfil, u.ativo, u.telefone, u.created_at, u.updated_at
");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    $_SESSION['erro'] = 'Usuário não encontrado';
    header('Location: usuarios.php');
    exit;
}

// Corrigido: usar $_SESSION['user_id'] ao invés de $_SESSION['usuario_id']
$editando_proprio_perfil = ($usuario['id'] == $_SESSION['user_id']);

$titulo = 'Editar Usuário';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Usuários', 'url' => 'usuarios.php'],
    ['label' => 'Editar']
];

include '../views/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        
        <!-- Cabeçalho -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Editar Usuário</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Atualize os dados do usuário</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Usuário desde</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200"><?= formatarData($usuario['created_at']) ?></p>
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

        <!-- Estatísticas do usuário -->
        <?php if ($usuario['perfil'] == 'vendedor'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <div class="text-blue-600 dark:text-blue-400 text-sm font-medium">Total de Pedidos</div>
                <div class="text-2xl font-bold text-blue-800 dark:text-blue-300"><?= $usuario['total_pedidos'] ?></div>
            </div>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                <div class="text-yellow-600 dark:text-yellow-400 text-sm font-medium">Orçamentos Pendentes</div>
                <div class="text-2xl font-bold text-yellow-800 dark:text-yellow-300"><?= $usuario['pedidos_pendentes'] ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form action="usuario_salvar.php" method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
            
            <!-- Informações Básicas -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Informações Básicas</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nome Completo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            E-mail <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Telefone
                        </label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               placeholder="(00) 00000-0000">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Perfil <span class="text-red-500">*</span>
                        </label>
                        <?php if ($editando_proprio_perfil): ?>
                            <input type="hidden" name="perfil" value="<?= $usuario['perfil'] ?>">
                            <select disabled
                                    class="w-full px-4 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600 cursor-not-allowed">
                                <option><?= ucfirst(str_replace('_', ' ', $usuario['perfil'])) ?></option>
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Você não pode alterar seu próprio perfil
                            </p>
                        <?php else: ?>
                            <select name="perfil" 
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" 
                                    required>
                                <option value="gestor" <?= $usuario['perfil'] == 'gestor' ? 'selected' : '' ?>>
                                    Gestor
                                </option>
                                <option value="vendedor" <?= $usuario['perfil'] == 'vendedor' ? 'selected' : '' ?>>
                                    Vendedor
                                </option>
                                <option value="producao" <?= $usuario['perfil'] == 'producao' ? 'selected' : '' ?>>
                                    Produção
                                </option>
                                <option value="arte_finalista" <?= $usuario['perfil'] == 'arte_finalista' ? 'selected' : '' ?>>
                                    Arte-Finalista
                                </option>
                                <option value="financeiro" <?= $usuario['perfil'] == 'financeiro' ? 'selected' : '' ?>>
                                    Financeiro
                                </option>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Segurança -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Segurança</h3>
                
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Deixe os campos de senha em branco para manter a senha atual
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nova Senha
                        </label>
                        <input type="password" name="senha" id="senha"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               minlength="6"
                               placeholder="Mínimo 6 caracteres">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Confirmar Nova Senha
                        </label>
                        <input type="password" name="confirmar_senha" id="confirmar_senha"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               minlength="6"
                               placeholder="Digite a senha novamente">
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Status da Conta</h3>
                
                <?php if ($editando_proprio_perfil): ?>
                    <input type="hidden" name="ativo" value="1">
                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4">
                        <span class="text-green-600 dark:text-green-400 font-medium">✓ Conta Ativa</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Você não pode desativar sua própria conta
                        </p>
                    </div>
                <?php else: ?>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="ativo" value="1" 
                                   class="form-radio text-green-600" 
                                   <?= $usuario['ativo'] ? 'checked' : '' ?>>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Ativo</span>
                        </label>
                        
                        <label class="inline-flex items-center">
                            <input type="radio" name="ativo" value="0" 
                                   class="form-radio text-red-600" 
                                   <?= !$usuario['ativo'] ? 'checked' : '' ?>>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Inativo</span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Observações -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Observações
                </label>
                <textarea name="observacoes" rows="3"
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                          placeholder="Observações sobre o usuário (opcional)"><?= htmlspecialchars($usuario['observacoes'] ?? '') ?></textarea>
            </div>

            <!-- Informações do Sistema -->
            <div class="mb-6 bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Informações do Sistema</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <div>
                        <span class="font-medium">Criado em:</span><br>
                        <?= formatarDataHora($usuario['created_at']) ?>
                    </div>
                    <div>
                        <span class="font-medium">Última atualização:</span><br>
                        <?= $usuario['updated_at'] ? formatarDataHora($usuario['updated_at']) : 'Nunca' ?>
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="flex justify-between items-center">
                <a href="usuarios.php" 
                   class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-medium px-6 py-2 rounded-lg transition">
                    Cancelar
                </a>
                
                <div class="space-x-2">
                    <?php if (!$editando_proprio_perfil && $usuario['total_pedidos'] == 0): ?>
                    <button type="button"
                            onclick="confirmarExclusao(<?= $usuario['id'] ?>)"
                            class="bg-red-600 hover:bg-red-700 text-white font-medium px-6 py-2 rounded-lg transition">
                        Excluir
                    </button>
                    <?php endif; ?>
                    
                    <button type="submit" 
                            class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-2 rounded-lg transition">
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Validação de senha
document.querySelector('form').addEventListener('submit', function(e) {
    const senha = document.getElementById('senha').value;
    const confirmarSenha = document.getElementById('confirmar_senha').value;
    
    if (senha || confirmarSenha) {
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
    }
});

function confirmarExclusao(id) {
    if (confirm('Tem certeza que deseja excluir este usuário?\n\nEsta ação não pode ser desfeita!')) {
        fetch('usuario_excluir.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'usuarios.php';
            } else {
                alert(data.message || 'Erro ao excluir usuário');
            }
        })
        .catch(error => {
            alert('Erro ao processar solicitação');
            console.error('Error:', error);
        });
    }
}
</script>

<?php include '../views/_footer.php'; ?>