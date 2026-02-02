<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

$produto_id = $_GET['id'] ?? null;

if (!$produto_id) {
    header('Location: catalogo.php');
    exit;
}

// Buscar produto
$stmt = $pdo->prepare("
    SELECT p.*, c.nome as categoria_nome
    FROM produtos_catalogo p
    LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$produto_id]);
$produto = $stmt->fetch();

if (!$produto) {
    $_SESSION['erro'] = 'Produto não encontrado';
    header('Location: catalogo.php');
    exit;
}

// Buscar categorias
$categorias = $pdo->query("SELECT * FROM categorias_produtos WHERE ativo = true ORDER BY nome")->fetchAll();

// Valores padrão para colunas que não existem na tabela
$produto['especificacoes'] = $produto['especificacoes'] ?? null;
$produto['unidade_venda'] = $produto['unidade_venda'] ?? 'UN';
$produto['preco_promocional'] = $produto['preco_promocional'] ?? null;
$produto['tempo_producao'] = $produto['tempo_producao'] ?? 1;
$produto['imagem_principal'] = $produto['imagem_principal'] ?? null;
$produto['estoque_disponivel'] = $produto['estoque_disponivel'] ?? true;
$produto['tags'] = $produto['tags'] ?? null;
$produto['popularidade'] = $produto['popularidade'] ?? 0;

// Decodificar especificações (se existir)
$especificacoes = [];
if (!empty($produto['especificacoes'])) {
    $especificacoes = json_decode($produto['especificacoes'], true) ?: [];
}

// Buscar imagens adicionais (se a tabela existir)
$imagens = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM produtos_imagens WHERE produto_id = ? ORDER BY ordem");
    $stmt->execute([$produto_id]);
    $imagens = $stmt->fetchAll();
} catch (Exception $e) {
    // Tabela pode não existir
}

// Recuperar dados do formulário em caso de erro
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
    
    // Sobrescrever dados do produto com dados do formulário
    foreach ($form_data as $key => $value) {
        if ($key !== 'especificacoes') {
            $produto[$key] = $value;
        }
    }
    
    // Processar especificações do formulário
    if (isset($form_data['especificacoes'])) {
        $especificacoes = [];
        foreach ($form_data['especificacoes'] as $spec) {
            if (!empty($spec['nome']) && !empty($spec['valor'])) {
                $especificacoes[] = $spec;
            }
        }
    }
}

$titulo = 'Editar Produto - ' . $produto['nome'];
$breadcrumb = [
    ['label' => 'Catálogo', 'url' => 'catalogo.php'],
    ['label' => 'Editar Produto']
];
include '../../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Editar Produto</h1>
        <p class="text-gray-600 mt-2">Atualize as informações do produto</p>
    </div>
    
    <form method="POST" action="catalogo_produto_atualizar.php" enctype="multipart/form-data" 
          onsubmit="return validarFormulario(this)">
        
        <input type="hidden" name="produto_id" value="<?= $produto_id ?>">
        
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
                           value="<?= htmlspecialchars($produto['codigo']) ?>"
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
                           value="<?= htmlspecialchars($produto['nome']) ?>"
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
                            <option value="<?= $cat['id'] ?>" <?= $produto['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
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
                        <?php
                        $unidades = [
                            'UN' => 'Unidade (UN)',
                            'M' => 'Metro (M)',
                            'M2' => 'Metro Quadrado (M²)',
                            'ML' => 'Metro Linear (ML)',
                            'KG' => 'Quilograma (KG)',
                            'CX' => 'Caixa (CX)',
                            'PC' => 'Pacote (PC)'
                        ];
                        foreach ($unidades as $valor => $label):
                        ?>
                        <option value="<?= $valor ?>" <?= $produto['unidade_venda'] == $valor ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Descrição -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <textarea name="descricao" rows="4"
                              placeholder="Descrição detalhada do produto..."
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
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
                           value="<?= $produto['preco'] ?>"
                           placeholder="0,00"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Preço Promocional -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Preço Promocional (R$)
                    </label>
                    <input type="number" name="preco_promocional" step="0.01" min="0"
                           value="<?= $produto['preco_promocional'] ?>"
                           placeholder="0,00"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-1">Deixe vazio se não houver promoção</p>
                </div>
                
                <!-- Tempo de Produção -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tempo de Produção (dias) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="tempo_producao" min="1" required
                           value="<?= $produto['tempo_producao'] ?>"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
            </div>
        </div>
        
        <!-- Especificações Técnicas -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Especificações Técnicas</h2>
            
            <div id="especificacoes" class="space-y-3">
                <?php if (empty($especificacoes)): ?>
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
                <?php else: ?>
                <?php foreach ($especificacoes as $index => $spec): ?>
                <div class="grid grid-cols-5 gap-2">
                    <div class="col-span-2">
                        <input type="text" name="especificacoes[<?= $index ?>][nome]" 
                               value="<?= htmlspecialchars($spec['nome']) ?>"
                               placeholder="Nome da especificação"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    <div class="col-span-2">
                        <input type="text" name="especificacoes[<?= $index ?>][valor]" 
                               value="<?= htmlspecialchars($spec['valor']) ?>"
                               placeholder="Valor"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    <div class="col-span-1">
                        <?php if ($index > 0): ?>
                        <button type="button" onclick="this.parentElement.parentElement.remove()" 
                                class="w-full px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                            ✕
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" onclick="adicionarEspecificacao()" 
                    class="mt-3 text-sm text-blue-600 hover:text-blue-800">
                + Adicionar especificação
            </button>
        </div>
        
        <!-- Imagens -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Imagens</h2>
            
            <!-- Imagem Principal Atual -->
            <?php if ($produto['imagem_principal']): ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Imagem Principal Atual</label>
                <div class="flex items-center gap-4">
                    <img src="../<?= htmlspecialchars($produto['imagem_principal']) ?>" 
                         alt="Imagem atual" 
                         class="w-32 h-32 object-cover rounded">
                    <div>
                        <p class="text-sm text-gray-600 mb-2">
                            Selecione uma nova imagem abaixo para substituir
                        </p>
                        <label class="flex items-center">
                            <input type="checkbox" name="remover_imagem_principal" value="1" class="mr-2">
                            <span class="text-sm text-red-600">Remover imagem principal</span>
                        </label>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <?= $produto['imagem_principal'] ? 'Nova Imagem Principal' : 'Imagem Principal' ?>
                </label>
                <input type="file" name="imagem_principal" accept="image/*"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <p class="text-xs text-gray-500 mt-1">Formatos aceitos: JPG, PNG, GIF, WebP (máx. 5MB)</p>
            </div>
            
            <!-- Imagens Adicionais Atuais -->
            <?php if (!empty($imagens)): ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Imagens Adicionais Atuais</label>
                <div class="grid grid-cols-4 gap-2">
                    <?php foreach ($imagens as $img): ?>
                    <div class="relative group">
                        <img src="<?= htmlspecialchars($img['caminho']) ?>" 
                             alt="Imagem adicional" 
                             class="w-full h-24 object-cover rounded">
                        <button type="button" 
                                onclick="removerImagemAdicional(<?= $img['id'] ?>, this)"
                                class="absolute top-1 right-1 bg-red-500 text-white p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        <input type="hidden" name="imagens_remover[]" value="" disabled>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Adicionar Novas Imagens</label>
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
                    <input type="checkbox" name="estoque_disponivel" value="1" 
                           <?= $produto['estoque_disponivel'] ? 'checked' : '' ?> class="mr-2">
                    <span class="text-sm text-gray-700">Produto disponível em estoque</span>
                </label>
                
                <label class="flex items-center">
                    <input type="checkbox" name="ativo" value="1" 
                           <?= $produto['ativo'] ? 'checked' : '' ?> class="mr-2">
                    <span class="text-sm text-gray-700">Produto ativo no catálogo</span>
                </label>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tags (separadas por vírgula)</label>
                <input type="text" name="tags"
                       value="<?= htmlspecialchars($produto['tags'] ?? '') ?>"
                       placeholder="Ex: promocao, lancamento, mais-vendido"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
        </div>
        
        <!-- Informações de Registro -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-600">
            <p>Criado em: <?= formatarDataHora($produto['created_at']) ?></p>
            <p>Última atualização: <?= formatarDataHora($produto['updated_at']) ?></p>
            <p>Visualizações: <?= number_format($produto['popularidade']) ?></p>
        </div>
        
        <!-- Botões -->
        <div class="flex justify-between">
            <a href="catalogo_produto_detalhes.php?id=<?= $produto_id ?>" 
               class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Salvar Alterações
            </button>
        </div>
    </form>
</div>

<script>
let especIndex = <?= count($especificacoes) ?>;

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
    return true;
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

function removerImagemAdicional(imagemId, botao) {
    if (confirm('Deseja realmente remover esta imagem?')) {
        // Marcar para remoção
        const container = botao.parentElement;
        const input = container.querySelector('input[name="imagens_remover[]"]');
        input.value = imagemId;
        input.disabled = false;
        
        // Ocultar visualmente
        container.style.opacity = '0.5';
        botao.style.display = 'none';
    }
}

function validarFormulario(form) {
    const codigo = form.codigo.value.trim();
    const nome = form.nome.value.trim();
    const preco = parseFloat(form.preco.value);
    
    if (!codigo || !nome) {
        alert('Por favor, preencha todos os campos obrigatórios');
        return false;
    }
    
    if (preco <= 0) {
        alert('O preço deve ser maior que zero');
        return false;
    }
    
    // Validar preço promocional
    const precoPromo = parseFloat(form.preco_promocional.value || 0);
    if (precoPromo > 0 && precoPromo >= preco) {
        alert('O preço promocional deve ser menor que o preço normal');
        return false;
    }
    
    return confirm('Confirmar alterações no produto?') && showLoading(form);
}

// Funções do Modal de Categoria
function abrirModalNovaCategoria() {
    document.getElementById('formNovaCategoria').reset();
    document.getElementById('categoriaCriadaSucesso').classList.add('hidden');
    document.getElementById('modalNovaCategoria').classList.remove('hidden');
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
            throw new Error('Resposta inválida do servidor');
        }
    })
    .then(data => {
        if (data.success) {
            document.getElementById('categoriaCriadaSucesso').classList.remove('hidden');
            
            const select = document.getElementById('categoria_id');
            const option = document.createElement('option');
            option.value = data.id;
            option.textContent = formData.get('nome');
            option.selected = true;
            
            const options = Array.from(select.options).slice(1);
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
            
            setTimeout(() => {
                fecharModalCategoria();
            }, 1000);
            
        } else {
            alert('Erro: ' + (data.message || 'Erro ao criar categoria'));
        }
    })
    .catch(error => {
        alert('Erro ao criar categoria: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = btnTexto;
    });
}

document.getElementById('modalNovaCategoria').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalCategoria();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalCategoria();
    }
});
</script>

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

<?php include '../../views/layouts/_footer.php'; ?>