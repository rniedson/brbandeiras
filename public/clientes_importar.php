<?php
// clientes_importar.php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor']);

$titulo = 'Importar Clientes';
$breadcrumb = [
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'Clientes', 'url' => 'clientes.php'],
    ['label' => 'Importar']
];

// Buscar últimas importações
$stmt = $pdo->query("
    SELECT i.*, u.nome as usuario_nome
    FROM importacoes_clientes i
    LEFT JOIN usuarios u ON i.usuario_id = u.id
    ORDER BY i.created_at DESC
    LIMIT 10
");
$importacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../views/_header.php';
?>

<div class="container mx-auto px-4 py-8" x-data="importacaoClientes()">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Importar Clientes</h1>
        <p class="text-gray-600 mt-2">Importe clientes em massa através de arquivo CSV</p>
    </div>

    <!-- Card de Upload -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Upload de Arquivo CSV</h2>
        
        <!-- Instruções -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Formato do arquivo CSV</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>O arquivo deve conter as seguintes colunas (separadas por vírgula):</p>
                        <ul class="list-disc list-inside mt-2">
                            <li>Código Sistema</li>
                            <li>Nome/Razão Social</li>
                            <li>Apelido/Nome fantasia</li>
                            <li>Tipo (Lista de Preços)</li>
                            <li>Sexo (M ou F)</li>
                            <li>CPF</li>
                            <li>RG</li>
                            <li>Expedição RG</li>
                            <li>UF do RG</li>
                            <li>Indicador IE Destinatário</li>
                            <li>CNPJ</li>
                            <li>IE</li>
                            <li>Telefone</li>
                            <li>Celular</li>
                            <li>Fax</li>
                            <li>Email</li>
                            <li>Site</li>
                            <li>Endereço</li>
                            <li>Número</li>
                            <li>Complemento</li>
                            <li>Bairro</li>
                            <li>Cidade</li>
                            <li>Estado</li>
                            <li>CEP</li>
                            <li>Data de nascimento</li>
                        </ul>
                        <p class="mt-2 font-semibold">⚠️ Clientes existentes serão atualizados baseado no Código Sistema ou CPF/CNPJ</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário de Upload -->
        <form id="formImportacao" action="clientes_processar_importacao.php" method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Arquivo CSV
                </label>
                <input type="file" 
                       name="arquivo_csv" 
                       accept=".csv"
                       required
                       @change="validarArquivo"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <p class="text-xs text-gray-500 mt-1">Máximo 10MB - Formato CSV</p>
            </div>

            <!-- Opções de Importação -->
            <div class="mb-4 space-y-3">
                <label class="flex items-center">
                    <input type="checkbox" name="atualizar_existentes" value="1" checked
                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">Atualizar dados de clientes existentes</span>
                </label>
                
                <label class="flex items-center">
                    <input type="checkbox" name="ignorar_erros" value="1"
                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">Continuar importação mesmo com erros</span>
                </label>

                <label class="flex items-center">
                    <input type="checkbox" name="modo_teste" value="1"
                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">Modo teste (simular importação sem salvar)</span>
                </label>
            </div>

            <!-- Botões -->
            <div class="flex gap-4">
                <button type="submit" 
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Importar Clientes
                </button>
                <a href="clientes.php" 
                   class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

    <!-- Histórico de Importações -->
    <?php if (!empty($importacoes)): ?>
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-bold mb-4">Histórico de Importações</h2>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arquivo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inseridos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Atualizados</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Erros</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($importacoes as $imp): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= formatarDataHora($imp['created_at']) ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?= htmlspecialchars($imp['arquivo_nome']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= $imp['total_registros'] ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <?= $imp['registros_inseridos'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?= $imp['registros_atualizados'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($imp['registros_erro'] > 0): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"
                                  x-data="{ showErrors: false }">
                                <button @click="showErrors = !showErrors" class="focus:outline-none">
                                    <?= $imp['registros_erro'] ?> ⚠️
                                </button>
                                <?php if ($imp['erros_detalhes']): ?>
                                <div x-show="showErrors" class="absolute z-10 bg-white p-2 shadow-lg rounded mt-1 text-left max-w-xs">
                                    <pre class="text-xs"><?= htmlspecialchars($imp['erros_detalhes']) ?></pre>
                                </div>
                                <?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-green-600">✓</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($imp['usuario_nome']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function importacaoClientes() {
    return {
        validarArquivo(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Validar tamanho (10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('Arquivo muito grande! Máximo permitido: 10MB');
                event.target.value = '';
                return;
            }
            
            // Validar extensão
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Por favor, selecione um arquivo CSV');
                event.target.value = '';
                return;
            }
        }
    }
}

// Adicionar loading no submit
document.getElementById('formImportacao').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = 'Processando... <svg class="animate-spin inline-block w-4 h-4 ml-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
});
</script>

<?php include '../views/_footer.php'; ?>