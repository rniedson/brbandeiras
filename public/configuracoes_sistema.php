<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

$mensagem = null;
$erro = null;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $descontoMaximo = floatval($_POST['desconto_maximo_vendedor'] ?? 2.0);
        
        if ($descontoMaximo < 0 || $descontoMaximo > 100) {
            throw new Exception('Desconto máximo deve estar entre 0% e 100%');
        }
        
        if (setConfig('desconto_maximo_vendedor', $descontoMaximo, 'decimal', 'Desconto máximo permitido para vendedores (%)')) {
            $mensagem = 'Configuração salva com sucesso!';
            registrarLog('configuracao_atualizada', "Desconto máximo vendedor alterado para {$descontoMaximo}%");
        } else {
            throw new Exception('Erro ao salvar configuração');
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Buscar configuração atual
$descontoMaximoAtual = getDescontoMaximoVendedor();

$titulo = 'Configurações do Sistema';
include '../views/layouts/_header.php';
?>

<div class="flex-1 bg-gray-50">
    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">
                <i class="fas fa-cog mr-2"></i>
                Configurações do Sistema
            </h1>
            
            <?php if ($mensagem): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($mensagem) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($erro): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <!-- Configuração de Desconto Máximo para Vendedores -->
                <div class="border border-gray-200 rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-percent mr-2 text-blue-600"></i>
                        Limite de Desconto para Vendedores
                    </h2>
                    
                    <p class="text-sm text-gray-600 mb-4">
                        Defina o percentual máximo de desconto que os vendedores podem aplicar nos pedidos. 
                        Descontos acima deste limite só podem ser aplicados por gestores.
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Desconto Máximo (%)
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       name="desconto_maximo_vendedor" 
                                       value="<?= number_format($descontoMaximoAtual, 2, '.', '') ?>"
                                       step="0.1"
                                       min="0"
                                       max="100"
                                       required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <span class="absolute right-3 top-2 text-gray-500">%</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Valor atual: <strong><?= number_format($descontoMaximoAtual, 2, ',', '.') ?>%</strong>
                            </p>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Salvar Configuração
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Importante:</strong> Esta configuração afeta todos os vendedores do sistema. 
                            Ao tentar aplicar um desconto maior que o limite, o vendedor receberá uma mensagem de erro 
                            e precisará solicitar aprovação do gestor.
                        </p>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle mr-2 text-gray-600"></i>
                Informações sobre Permissões
            </h2>
            
            <div class="space-y-4 text-sm text-gray-700">
                <div class="border-l-4 border-blue-500 pl-4">
                    <h3 class="font-semibold mb-2">Vendedores</h3>
                    <ul class="list-disc list-inside space-y-1 text-gray-600">
                        <li>Podem aplicar desconto até o limite configurado</li>
                        <li>Veem apenas seus próprios pedidos e comissões</li>
                        <li>Não têm acesso a valores de outros vendedores</li>
                    </ul>
                </div>
                
                <div class="border-l-4 border-green-500 pl-4">
                    <h3 class="font-semibold mb-2">Gestores</h3>
                    <ul class="list-disc list-inside space-y-1 text-gray-600">
                        <li>Podem aplicar qualquer desconto (sem limite)</li>
                        <li>Têm acesso a todos os pedidos e valores</li>
                        <li>Podem configurar limites de desconto</li>
                    </ul>
                </div>
                
                <div class="border-l-4 border-orange-500 pl-4">
                    <h3 class="font-semibold mb-2">Produção e Arte</h3>
                    <ul class="list-disc list-inside space-y-1 text-gray-600">
                        <li>Não veem valores financeiros dos pedidos</li>
                        <li>Focam apenas nas informações operacionais</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../views/layouts/_footer.php'; ?>
