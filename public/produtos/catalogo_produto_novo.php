<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireRole(['gestor']);

// Buscar categorias
$categorias = $pdo->query("SELECT * FROM categorias_produtos WHERE ativo = true ORDER BY nome")->fetchAll();

$titulo = 'Novo Produto - Catálogo';
$breadcrumb = [
    ['label' => 'Catálogo', 'url' => 'catalogo.php'],
    ['label' => 'Novo Produto']
];
include '../../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Novo Produto</h1>
        <p class="text-gray-600 mt-2">Adicione um novo produto ao catálogo</p>
    </div>
    
    <form method="POST" action="catalogo_produto_salvar.php" enctype="multipart/form-data" 
          onsubmit="return showLoading(this)">
        
        <!-- Informações Básicas -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Informações Básicas</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Código -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Código <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="codigo" required
                           placeholder="Ex: BAND-001"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-1">Código único do produto</p>
                </div>
                
                <!-- Nome -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome do Produto <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nome" required
                           placeholder="Ex: Bandeira do Brasil 1,5x1m"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Categoria -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Categoria <span class="text-red-500">*</span>
                    </label>
                    <select name="categoria_id" required 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Unidade de Venda -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Unidade de Venda <span class="text-red-500">*</span>
                    </label>
                    <select name="unidade_venda" required 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="UN">Unidade (UN)</option>
                        <option value="M">Metro (M)</option>
                        <option value="M2">Metro Quadrado (M²)</option>
                        <option value="ML">Metro Linear (ML)</option>
                        <option value="KG">Quilograma (KG)</option>
                        <option value="CX">Caixa (CX)</option>
                        <option value="PC">Pacote (PC)</option>
                    </select>
                </div>
                
                <!-- Descrição -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <textarea name="descricao" rows="4"
                              placeholder="Descrição detalhada do produto..."
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Preços e Produção -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Preços e Produção</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Preço Normal -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Preço (R$) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="preco" step="0.01" min="0" required
                           placeholder="0,00"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Preço Promocional -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Preço Promocional (R$)
                    </label>
                    <input type="number" name="preco_promocional" step="0.01" min="0"
                           placeholder="0,00"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-1">Deixe vazio se não houver promoção</p>
                </div>
                
                <!-- Tempo de Produção -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tempo de Produção (dias) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="tempo_producao" min="1" value="1" required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
            </div>
        </div>
        
        <!-- Especificações Técnicas -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Especificações Técnicas</h2>
            
            <div id="especificacoes" class="space-y-3">
                <div class="grid grid-cols-5 gap-2">
                    <div class="col-span-2">
                        <input type="text" name="especificacoes[0][nome]" 
                               placeholder="Ex: Material"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    <div class="col-span-3">
                        <input type="text" name="especificacoes[0][valor]" 
                               placeholder="Ex: Poliéster 110g/m²"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                </div>
            </div>
            
            <button type="button" onclick="adicionarEspecificacao()" 
                    class="mt-3 text-sm text-blue-600 hover:text-blue-800">
                + Adicionar especificação
            </button>
        </div>
        
        <!-- Imagens -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Imagens</h2>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Imagem Principal</label>
                <input type="file" name="imagem_principal" accept="image/*"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <p class="text-xs text-gray-500 mt-1">Formatos aceitos: JPG, PNG, GIF (máx. 5MB)</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Imagens Adicionais</label>
                <input type="file" name="imagens_adicionais[]" accept="image/*" multiple
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <p class="text-xs text-gray-500 mt-1">Você pode selecionar múltiplas imagens</p>
            </div>
        </div>
        
        <!-- Configurações -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Configurações</h2>
            
            <div class="space-y-3">
                <label class="flex items-center">
                    <input type="checkbox" name="estoque_disponivel" value="1" checked class="mr-2">
                    <span class="text-sm text-gray-700">Produto disponível em estoque</span>
                </label>
                
                <label class="flex items-center">
                    <input type="checkbox" name="ativo" value="1" checked class="mr-2">
                    <span class="text-sm text-gray-700">Produto ativo no catálogo</span>
                </label>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tags (separadas por vírgula)</label>
                <input type="text" name="tags"
                       placeholder="Ex: promocao, lancamento, mais-vendido"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
        </div>
        
        <!-- Botões -->
        <div class="flex justify-between">
            <a href="catalogo.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Cadastrar Produto
            </button>
        </div>
    </form>
</div>

<script>
let especIndex = 1;

function adicionarEspecificacao() {
    const container = document.getElementById('especificacoes');
    const html = `
        <div class="grid grid-cols-5 gap-2">
            <div class="col-span-2">
                <input type="text" name="especificacoes[${especIndex}][nome]" 
                       placeholder="Nome da especificação"
                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            <div class="col-span-2">
                <input type="text" name="especificacoes[${especIndex}][valor]" 
                       placeholder="Valor"
                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            <div class="col-span-1">
                <button type="button" onclick="this.parentElement.parentElement.remove()" 
                        class="w-full px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                    ✕
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    especIndex++;
}
</script>

<?php include '../../views/layouts/_footer.php'; ?>
