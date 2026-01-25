<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

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

// Buscar imagens adicionais
$stmt = $pdo->prepare("SELECT * FROM produtos_imagens WHERE produto_id = ? ORDER BY ordem");
$stmt->execute([$produto_id]);
$imagens = $stmt->fetchAll();

// Buscar categorias
$categorias = $pdo->query("SELECT * FROM categorias_produtos WHERE ativo = true ORDER BY nome")->fetchAll();

// Decodificar especificações
$especificacoes = json_decode($produto['especificacoes'], true) ?: [];

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
include '../views/_header.php';
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
                    <select name="categoria_id" required 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $produto['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
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
</script>

<?php include '../views/_footer.php'; ?>