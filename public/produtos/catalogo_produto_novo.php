<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

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
                    <div class="flex gap-2">
                        <select name="categoria_id" id="categoria_id" required 
                                class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="abrirModalNovaCategoria()" 
                                class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center"
                                title="Criar nova categoria">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Não encontrou a categoria? Clique no <span class="text-blue-600 font-medium">+</span> para criar</p>
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

<!-- Modal de Nova Categoria -->
<div id="modalNovaCategoria" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                <svg class="w-5 h-5 inline mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                Nova Categoria
            </h3>
            <button type="button" onclick="fecharModalCategoria()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="formNovaCategoria" onsubmit="salvarNovaCategoria(event)">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Nome da Categoria <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       id="nova_categoria_nome"
                       name="nome" 
                       required
                       placeholder="Ex: Bandeiras Especiais"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Descrição <span class="text-gray-400 text-xs">(opcional)</span>
                </label>
                <textarea id="nova_categoria_descricao"
                          name="descricao" 
                          rows="3"
                          placeholder="Breve descrição da categoria..."
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" 
                        onclick="fecharModalCategoria()"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Cancelar
                </button>
                <button type="submit"
                        id="btnSalvarCategoria"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Criar Categoria
                </button>
            </div>
        </form>
        
        <div id="categoriaCriadaSucesso" class="hidden mt-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Categoria criada com sucesso!</span>
            </div>
        </div>
    </div>
</div>

<script>
let especIndex = 1;

// Função de loading para o formulário
function showLoading(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg class="animate-spin inline w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Salvando...
        `;
    }
    return true; // Permite o envio do formulário
}

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

// Funções do Modal de Categoria
function abrirModalNovaCategoria() {
    document.getElementById('formNovaCategoria').reset();
    document.getElementById('categoriaCriadaSucesso').classList.add('hidden');
    document.getElementById('modalNovaCategoria').classList.remove('hidden');
    // Focar no campo de nome
    setTimeout(() => {
        document.getElementById('nova_categoria_nome').focus();
    }, 100);
}

function fecharModalCategoria() {
    document.getElementById('modalNovaCategoria').classList.add('hidden');
}

function salvarNovaCategoria(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const submitBtn = document.getElementById('btnSalvarCategoria');
    const btnTexto = submitBtn.innerHTML;
    
    // Mostrar loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Criando...
    `;
    
    fetch('categoria_produto_criar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Resposta recebida:', text);
            throw new Error('Resposta inválida do servidor');
        }
    })
    .then(data => {
        if (data.success) {
            // Mostrar sucesso
            document.getElementById('categoriaCriadaSucesso').classList.remove('hidden');
            
            // Adicionar nova categoria ao select
            const select = document.getElementById('categoria_id');
            const option = document.createElement('option');
            option.value = data.id;
            option.textContent = formData.get('nome');
            option.selected = true;
            
            // Inserir em ordem alfabética
            const options = Array.from(select.options).slice(1); // Pular "Selecione..."
            let inserted = false;
            
            for (let i = 0; i < options.length; i++) {
                if (options[i].textContent.toLowerCase() > formData.get('nome').toLowerCase()) {
                    select.insertBefore(option, options[i]);
                    inserted = true;
                    break;
                }
            }
            
            if (!inserted) {
                select.appendChild(option);
            }
            
            // Fechar modal após 1 segundo
            setTimeout(() => {
                fecharModalCategoria();
            }, 1000);
            
        } else {
            alert('Erro: ' + (data.message || 'Erro ao criar categoria'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar categoria: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = btnTexto;
    });
}

// Fechar modal ao clicar fora
document.getElementById('modalNovaCategoria').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalCategoria();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalCategoria();
    }
});
</script>

<?php include '../../views/layouts/_footer.php'; ?>
