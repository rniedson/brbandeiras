<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['producao', 'gestor']);

// Buscar produtos para seleção
$produtos = $pdo->query("
    SELECT id, codigo, nome, quantidade_atual 
    FROM produtos_estoque 
    ORDER BY nome
")->fetchAll();

// Buscar pedidos em produção (para vincular saídas)
$pedidos = $pdo->query("
    SELECT p.id, p.numero, c.nome as cliente_nome 
    FROM pedidos p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    WHERE p.status IN ('producao', 'pronto', 'entregue') 
    ORDER BY p.created_at DESC 
    LIMIT 100
")->fetchAll();

$titulo = 'Nova Movimentação';
$breadcrumb = [
    ['label' => 'Estoque', 'url' => 'estoque.php'],
    ['label' => 'Nova Movimentação']
];
include '../../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Nova Movimentação de Estoque</h1>
        <p class="text-gray-600 mt-2">Registre entradas, saídas ou ajustes de estoque</p>
    </div>
    
    <form method="POST" action="movimentacao_salvar.php" onsubmit="return validarMovimentacao(this)"
          x-data="{
              tipo: '',
              produtos: [{ produto_id: '', quantidade: 1 }],
              getProdutoInfo(produtoId) {
                  const select = document.querySelector(`select[value='${produtoId}']`);
                  if (select && select.selectedOptions[0]) {
                      return {
                          estoque: select.selectedOptions[0].dataset.estoque || 0,
                          unidade: select.selectedOptions[0].dataset.unidade || ''
                      };
                  }
                  return { estoque: 0, unidade: '' };
              }
          }">
        
        <!-- Tipo de Movimentação -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Tipo de Movimentação</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50"
                       :class="tipo === 'entrada' ? 'border-green-500 bg-green-50' : 'border-gray-200'">
                    <input type="radio" name="tipo" value="entrada" x-model="tipo" required class="mr-3">
                    <div>
                        <div class="font-semibold text-green-600">Entrada</div>
                        <div class="text-sm text-gray-600">Compra ou devolução</div>
                    </div>
                </label>
                
                <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50"
                       :class="tipo === 'saida' ? 'border-red-500 bg-red-50' : 'border-gray-200'">
                    <input type="radio" name="tipo" value="saida" x-model="tipo" required class="mr-3">
                    <div>
                        <div class="font-semibold text-red-600">Saída</div>
                        <div class="text-sm text-gray-600">Consumo ou venda</div>
                    </div>
                </label>
                
                <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50"
                       :class="tipo === 'ajuste' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                    <input type="radio" name="tipo" value="ajuste" x-model="tipo" required class="mr-3">
                    <div>
                        <div class="font-semibold text-blue-600">Ajuste</div>
                        <div class="text-sm text-gray-600">Correção de inventário</div>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Produtos -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6" x-show="tipo">
            <h2 class="text-lg font-semibold mb-4">Produtos</h2>
            
            <template x-for="(item, index) in produtos" :key="index">
                <div class="grid grid-cols-12 gap-3 mb-3">
                    <div class="col-span-6">
                        <select x-model="item.produto_id" 
                                :name="'produtos['+index+'][produto_id]'" 
                                required
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                            <option value="">Selecione o produto...</option>
                            <?php foreach ($produtos as $produto): ?>
                            <option value="<?= $produto['id'] ?>" 
                                    data-estoque="<?= $produto['quantidade_atual'] ?? 0 ?>"
                                    data-unidade="UN">
                                <?= htmlspecialchars($produto['codigo'] ?? '') ?> - <?= htmlspecialchars($produto['nome'] ?? '') ?>
                                (Estoque: <?= number_format($produto['quantidade_atual'] ?? 0, 2, ',', '.') ?> UN)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-span-3">
                        <input type="number" 
                               x-model="item.quantidade" 
                               :name="'produtos['+index+'][quantidade]'"
                               step="0.01" 
                               min="0.01" 
                               required
                               placeholder="Quantidade"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    
                    <div class="col-span-2 flex items-center">
                        <span class="text-sm text-gray-600" x-text="getProdutoInfo(item.produto_id).unidade"></span>
                    </div>
                    
                    <div class="col-span-1">
                        <button type="button" 
                                @click="produtos.splice(index, 1)" 
                                x-show="produtos.length > 1"
                                class="w-full px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                            ✕
                        </button>
                    </div>
                </div>
            </template>
            
            <button type="button" 
                    @click="produtos.push({produto_id: '', quantidade: 1})" 
                    class="mt-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                + Adicionar Produto
            </button>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6" x-show="tipo">
            <h2 class="text-lg font-semibold mb-4">Informações Adicionais</h2>
            
            <!-- Vincular a Pedido (apenas para saídas) -->
            <div class="mb-4" x-show="tipo === 'saida'">
                <label class="block text-sm font-medium text-gray-700 mb-2">Vincular a Pedido</label>
                <select name="pedido_id" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Não vincular</option>
                    <?php foreach ($pedidos as $pedido): ?>
                    <option value="<?= $pedido['id'] ?>">
                        #<?= $pedido['numero'] ?> - <?= htmlspecialchars($pedido['cliente_nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Data da Movimentação -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Data da Movimentação
                </label>
                <input type="datetime-local" 
                       name="data_movimentacao" 
                       value="<?= date('Y-m-d\TH:i') ?>"
                       max="<?= date('Y-m-d\TH:i') ?>"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <!-- Documento de Referência -->
            <div class="mb-4" x-show="tipo === 'entrada'">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Documento de Referência
                </label>
                <input type="text" 
                       name="documento_referencia"
                       placeholder="Ex: NF 12345, Ordem de Compra 456"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <!-- Observações -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Observações <span class="text-red-500">*</span>
                </label>
                <textarea name="observacoes" 
                          rows="3" 
                          required
                          placeholder="Descreva o motivo da movimentação..."
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
            </div>
        </div>
        
        <!-- Botões -->
        <div class="flex justify-between">
            <a href="estoque.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" 
                    x-show="tipo"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Confirmar Movimentação
            </button>
        </div>
    </form>
</div>

<script>
function validarMovimentacao(form) {
    const tipo = form.tipo.value;
    const produtos = form.querySelectorAll('select[name*="produto_id"]');
    
    // Validar produtos únicos
    const produtosSelecionados = [];
    for (let select of produtos) {
        if (select.value) {
            if (produtosSelecionados.includes(select.value)) {
                alert('Produto duplicado na lista!');
                return false;
            }
            produtosSelecionados.push(select.value);
        }
    }
    
    // Validar estoque disponível para saídas
    if (tipo === 'saida') {
        for (let i = 0; i < produtos.length; i++) {
            const select = produtos[i];
            if (select.value) {
                const estoqueAtual = parseFloat(select.selectedOptions[0].dataset.estoque);
                const quantidade = parseFloat(form.querySelector(`input[name="produtos[${i}][quantidade]"]`).value);
                
                if (quantidade > estoqueAtual) {
                    alert(`Quantidade indisponível! Estoque atual: ${estoqueAtual}`);
                    return false;
                }
            }
        }
    }
    
    return confirm('Confirmar movimentação de estoque?') && showLoading(form);
}
</script>

<?php include '../../views/layouts/_footer.php'; ?>
