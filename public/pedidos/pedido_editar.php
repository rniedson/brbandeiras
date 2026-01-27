<?php
/**
 * PEDIDO_EDITAR.PHP - VERSÃO CORRIGIDA COM SELEÇÃO DE CATÁLOGO
 * Sistema completo de edição com modal de produtos
 */

// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

// Validar ID do pedido
$pedido_id = validarPedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID do pedido inválido';
    redirect('pedidos.php');
}

try {
    // 1. VERIFICAR COLUNAS EXISTENTES usando função auxiliar
    $tem_forma_pagamento = verificarColunaExiste('pedidos', 'forma_pagamento');
    $tem_condicoes_pagamento = verificarColunaExiste('pedidos', 'condicoes_pagamento');
    
    // 2. BUSCAR PEDIDO
    $stmt = $pdo->prepare("
        SELECT p.*, 
               c.nome as cliente_nome, 
               c.telefone as cliente_telefone,
               c.email as cliente_email,
               c.cpf_cnpj as cliente_cpf_cnpj,
               v.nome as vendedor_nome
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios v ON p.vendedor_id = v.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        $_SESSION['erro'] = 'Pedido não encontrado';
        redirect('pedidos.php');
    }
    
    // 3. VERIFICAR PERMISSÕES
    $pode_editar = false;
    if ($_SESSION['user_perfil'] === 'gestor') {
        $pode_editar = true;
    } elseif ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] == $_SESSION['user_id']) {
        if (in_array($pedido['status'], ['orcamento', 'aprovado'])) {
            $pode_editar = true;
        }
    }
    
    // Buscar desconto máximo para vendedor
    $descontoMaximoVendedor = getDescontoMaximoVendedor();
    $isVendedor = $_SESSION['user_perfil'] === 'vendedor' && $_SESSION['user_perfil'] !== 'gestor';
    $isGestor = $_SESSION['user_perfil'] === 'gestor';
    
    if (!$pode_editar) {
        $_SESSION['erro'] = 'Você não tem permissão para editar este pedido';
        redirect("pedido_detalhes.php?id={$pedido_id}");
    }
    
    // 4. BUSCAR ITENS DO PEDIDO
    $stmt = $pdo->prepare("
        SELECT pi.*, pc.id as produto_codigo, pc.nome as produto_nome
        FROM pedido_itens pi
        LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. BUSCAR ARQUIVOS
    $checkArquivosColumns = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'pedido_arquivos' 
        AND column_name IN ('caminho', 'caminho_arquivo')
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $caminhoColumn = in_array('caminho_arquivo', $checkArquivosColumns) ? 'caminho_arquivo' : 'caminho';
    
    $stmt = $pdo->prepare("
        SELECT pa.*, u.nome as usuario_nome,
               pa.{$caminhoColumn} as caminho_arquivo
        FROM pedido_arquivos pa
        LEFT JOIN usuarios u ON pa.usuario_id = u.id
        WHERE pa.pedido_id = ?
        ORDER BY pa.created_at DESC
    ");
    $stmt->execute([$pedido_id]);
    $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. BUSCAR CLIENTES ATIVOS
    $clientes = $pdo->query("
        SELECT id, nome, telefone, cpf_cnpj 
        FROM clientes 
        WHERE ativo = true 
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. BUSCAR PRODUTOS DO CATÁLOGO (IMPORTANTE!)
    $produtos = $pdo->query("
        SELECT p.*, c.nome as categoria_nome
        FROM produtos_catalogo p
        LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
        WHERE p.ativo = true
        ORDER BY c.nome, p.nome
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar produtos por categoria para o modal
    $produtosPorCategoria = [];
    foreach ($produtos as $produto) {
        $categoria = $produto['categoria_nome'] ?: 'Sem Categoria';
        if (!isset($produtosPorCategoria[$categoria])) {
            $produtosPorCategoria[$categoria] = [];
        }
        $produtosPorCategoria[$categoria][] = $produto;
    }
    
    // 8. PREPARAR ITENS PARA JAVASCRIPT
    $itensJson = [];
    foreach ($itens as $item) {
        $itensJson[] = [
            'id' => $item['id'],
            'produto_id' => $item['produto_id'] ?? null,
            'descricao' => $item['descricao'],
            'quantidade' => floatval($item['quantidade']),
            'valor_unitario' => floatval($item['valor_unitario']),
            'valor_total' => floatval($item['valor_total']),
            'is_saved' => true
        ];
    }
    
} catch (PDOException $e) {
    error_log("Erro em pedido_editar.php: " . $e->getMessage());
    die("<h1>Erro no Banco de Dados</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

$titulo = 'Editar Pedido #' . $pedido['numero'];
$breadcrumb = [
    ['label' => 'Pedidos', 'url' => 'pedidos.php'],
    ['label' => 'Pedido #' . $pedido['numero'], 'url' => 'pedido_detalhes.php?id=' . $pedido_id],
    ['label' => 'Editar']
];

include '../../views/layouts/_header.php';
?>

<div class="max-w-6xl mx-auto" x-data="editarPedidoForm()">
    <!-- Cabeçalho -->
    <div class="bg-white rounded-lg shadow-lg mb-6 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6">
            <div class="flex justify-between items-start">
                <div class="text-white">
                    <h1 class="text-3xl font-bold mb-2">
                        Editar Pedido #<?= htmlspecialchars($pedido['numero']) ?>
                    </h1>
                    <p class="text-blue-100">
                        Status atual: <span class="font-bold"><?= ucfirst($pedido['status']) ?></span>
                    </p>
                </div>
                <div class="text-right text-white">
                    <p class="text-sm text-blue-100">Criado em</p>
                    <p class="font-semibold"><?= formatarDataHora($pedido['created_at']) ?></p>
                </div>
            </div>
        </div>
        
        <?php if ($pedido['status'] !== 'orcamento'): ?>
        <div class="bg-yellow-50 border-b border-yellow-200 px-6 py-3">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <p class="text-yellow-800">
                    <strong>Atenção:</strong> Este pedido está com status "<?= ucfirst($pedido['status']) ?>". 
                    Algumas alterações podem ter impacto na produção.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Formulário de Edição -->
    <form id="formEditarPedido" method="POST" action="pedido_atualizar.php" enctype="multipart/form-data">
        <?= CSRF::getField() ?>
        <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
        <input type="hidden" name="status_atual" value="<?= $pedido['status'] ?>">
        
        <!-- Card: Dados do Cliente -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Cliente
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                    <select name="cliente_id" id="cliente_id" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            @change="clienteAlterado()">
                        <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= $cliente['id'] ?>" 
                                <?= $cliente['id'] == $pedido['cliente_id'] ? 'selected' : '' ?>
                                data-telefone="<?= htmlspecialchars($cliente['telefone']) ?>">
                            <?= htmlspecialchars($cliente['nome']) ?>
                            <?php if (!empty($cliente['cpf_cnpj'])): ?>
                                (<?= htmlspecialchars($cliente['cpf_cnpj']) ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                    <input type="text" id="cliente_telefone" 
                           class="w-full px-4 py-2 bg-gray-100 border rounded-lg" 
                           value="<?= htmlspecialchars($pedido['cliente_telefone']) ?>" readonly>
                </div>
            </div>
        </div>
        
        <!-- Card: Itens do Pedido -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    Itens do Pedido
                </h2>
                
                <button type="button" @click="showModalProdutos = true"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Adicionar do Catálogo
                </button>
            </div>
            
            <!-- Tabela de Itens -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qtd</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Unit.</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="(item, index) in items.filter(i => !i.is_removed)" :key="'item-' + index">
                            <tr :class="item.is_new ? 'bg-green-50' : (item.is_modified ? 'bg-yellow-50' : '')">
                                <td class="px-4 py-3 text-sm">
                                    <span x-text="index + 1"></span>
                                    <span x-show="item.is_new" class="ml-2 px-2 py-1 bg-green-600 text-white text-xs rounded">NOVO</span>
                                    <span x-show="item.is_modified && !item.is_new" class="ml-2 px-2 py-1 bg-yellow-600 text-white text-xs rounded">ALTERADO</span>
                                </td>
                                <td class="px-4 py-3">
                                    <textarea x-model="item.descricao" 
                                              @input="itemModificado(index)"
                                              :name="'items[' + index + '][descricao]'"
                                              class="w-full px-2 py-1 border rounded resize-none" 
                                              rows="2" required></textarea>
                                    <input type="hidden" :name="'items[' + index + '][id]'" :value="item.id || ''">
                                    <input type="hidden" :name="'items[' + index + '][produto_id]'" :value="item.produto_id || ''">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" x-model.number="item.quantidade" 
                                           @input="calcularTotalItem(index); itemModificado(index)"
                                           :name="'items[' + index + '][quantidade]'"
                                           class="w-20 px-2 py-1 border rounded text-center" 
                                           min="1" step="1" required>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input type="number" x-model.number="item.valor_unitario" 
                                           @input="calcularTotalItem(index); itemModificado(index)"
                                           :name="'items[' + index + '][valor_unitario]'"
                                           class="w-32 px-2 py-1 border rounded text-right" 
                                           step="0.01" min="0" required>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    <span x-text="formatarMoeda(item.valor_total)"></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" @click="removerItem(index)"
                                            class="text-red-600 hover:text-red-800">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="items.filter(i => !i.is_removed).length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                Nenhum item no pedido. Clique em "Adicionar do Catálogo" para começar.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right font-semibold">Subtotal:</td>
                            <td class="px-4 py-3 text-right font-bold">
                                <span x-text="formatarMoeda(valorTotal)"></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Itens removidos (hidden inputs) -->
            <template x-for="item in itemsRemovidos" :key="'removed-' + item.id">
                <input type="hidden" name="items_removidos[]" :value="item.id">
            </template>
        </div>
        
        <!-- Card: Valores e Prazo -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Valores e Prazo
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Desconto (R$)
                        <?php if ($isVendedor): ?>
                            <span class="text-xs text-gray-500">(Máx: <?= number_format($descontoMaximoVendedor, 1, ',', '.') ?>%)</span>
                        <?php endif; ?>
                    </label>
                    <input type="number" name="desconto" x-model.number="desconto" 
                           @input="calcularValorFinal(); validarDesconto()"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                           :class="descontoExcedido ? 'border-red-500' : ''"
                           step="0.01" min="0" :max="valorTotal">
                    <p class="text-xs text-gray-500 mt-1">
                        <span x-text="descontoPercentual"></span>% do subtotal
                    </p>
                    <?php if ($isVendedor): ?>
                    <p x-show="descontoExcedido" class="text-xs text-red-600 mt-1">
                        <i class="fas fa-exclamation-triangle"></i>
                        Desconto máximo permitido: <?= number_format($descontoMaximoVendedor, 1, ',', '.') ?>%
                    </p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor Final</label>
                    <div class="text-2xl font-bold text-green-600" x-text="formatarMoeda(valorFinal)"></div>
                    <input type="hidden" name="valor_total" :value="valorTotal">
                    <input type="hidden" name="valor_final" :value="valorFinal">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prazo de Entrega *</label>
                    <input type="date" name="prazo_entrega" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                           value="<?= htmlspecialchars($pedido['prazo_entrega']) ?>" required>
                </div>
            </div>
            
            <?php if ($tem_forma_pagamento): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forma de Pagamento</label>
                    <select name="forma_pagamento" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        <option value="">Selecione...</option>
                        <option value="Dinheiro" <?= ($pedido['forma_pagamento'] ?? '') == 'Dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                        <option value="PIX" <?= ($pedido['forma_pagamento'] ?? '') == 'PIX' ? 'selected' : '' ?>>PIX</option>
                        <option value="Cartão Débito" <?= ($pedido['forma_pagamento'] ?? '') == 'Cartão Débito' ? 'selected' : '' ?>>Cartão Débito</option>
                        <option value="Cartão Crédito" <?= ($pedido['forma_pagamento'] ?? '') == 'Cartão Crédito' ? 'selected' : '' ?>>Cartão Crédito</option>
                        <option value="Boleto" <?= ($pedido['forma_pagamento'] ?? '') == 'Boleto' ? 'selected' : '' ?>>Boleto</option>
                    </select>
                </div>
                
                <?php if ($tem_condicoes_pagamento): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Condições de Pagamento</label>
                    <input type="text" name="condicoes_pagamento" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                           placeholder="Ex: 50% entrada + 50% na entrega"
                           value="<?= htmlspecialchars($pedido['condicoes_pagamento'] ?? '') ?>">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="urgente" value="1" 
                           class="mr-2 w-4 h-4 text-red-600 rounded focus:ring-red-500"
                           <?= $pedido['urgente'] ? 'checked' : '' ?>>
                    <span class="text-sm font-medium text-gray-700">
                        Marcar como <span class="text-red-600 font-bold">URGENTE</span>
                    </span>
                </label>
            </div>
        </div>
        
        <!-- Card: Observações -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                </svg>
                Observações
            </h2>
            
            <textarea name="observacoes" rows="4"
                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                      placeholder="Observações gerais sobre o pedido..."><?= htmlspecialchars($pedido['observacoes'] ?? '') ?></textarea>
        </div>
        
        <!-- Botões de Ação -->
        <div class="flex justify-between items-center">
            <a href="pedido_detalhes.php?id=<?= $pedido_id ?>" 
               class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Cancelar
            </a>
            
            <button type="submit" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Alterações
            </button>
        </div>
    </form>
    
    <!-- MODAL: Adicionar Produtos do Catálogo -->
    <div x-show="showModalProdutos" x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] flex flex-col">
            <!-- Header do Modal -->
            <div class="bg-gradient-to-r from-green-600 to-green-700 text-white p-4 rounded-t-lg flex justify-between items-center">
                <h3 class="text-xl font-bold">Selecionar Produto do Catálogo</h3>
                <button @click="showModalProdutos = false" class="text-white hover:text-gray-200 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Busca -->
            <div class="p-4 border-b">
                <input type="text" 
                       x-model="buscaProduto" 
                       @input="filtrarProdutos()"
                       placeholder="Buscar produto por nome ou código..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            
            <!-- Lista de Produtos -->
            <div class="flex-1 overflow-y-auto p-4">
                <?php foreach ($produtosPorCategoria as $categoria => $produtosCategoria): ?>
                <div class="mb-6" x-show="produtosFiltrados['<?= htmlspecialchars($categoria, ENT_QUOTES) ?>']?.length > 0">
                    <h4 class="font-bold text-gray-700 mb-3 pb-2 border-b">
                        <?= htmlspecialchars($categoria) ?>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($produtosCategoria as $produto): ?>
                        <div class="border rounded-lg p-3 hover:bg-green-50 cursor-pointer transition"
                             x-show="produtoVisivel(<?= htmlspecialchars(json_encode([
                                 'id' => $produto['id'],
                                 'codigo' => $produto['codigo'],
                                 'nome' => $produto['nome'],
                                 'categoria' => $categoria
                             ])) ?>)"
                             @click="adicionarProdutoCatalogo(<?= htmlspecialchars(json_encode([
                                 'id' => $produto['id'],
                                 'codigo' => $produto['codigo'],
                                 'nome' => $produto['nome'],
                                 'descricao' => $produto['descricao'] ?? '',
                                 'preco' => floatval($produto['preco'])
                             ])) ?>)">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-1 bg-gray-200 text-xs rounded font-mono">
                                            <?= htmlspecialchars($produto['codigo']) ?>
                                        </span>
                                        <p class="font-semibold text-gray-900">
                                            <?= htmlspecialchars($produto['nome']) ?>
                                        </p>
                                    </div>
                                    <?php if (!empty($produto['descricao'])): ?>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars(substr($produto['descricao'], 0, 100)) ?>...
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <p class="font-bold text-green-600 ml-3">
                                    <?= formatarMoeda($produto['preco']) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Opção de Item Personalizado -->
                <div class="mt-6 p-4 bg-gray-100 rounded-lg">
                    <h4 class="font-bold text-gray-700 mb-3">Adicionar Item Personalizado</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <input type="text" 
                                   x-model="itemPersonalizado.descricao" 
                                   placeholder="Descrição do item personalizado"
                                   class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <input type="number" 
                                   x-model.number="itemPersonalizado.valor" 
                                   placeholder="Valor unitário"
                                   step="0.01" min="0"
                                   class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>
                    <button @click="adicionarItemPersonalizado()" 
                            class="mt-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        Adicionar Item Personalizado
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Alpine.js -->
<script>
function editarPedidoForm() {
    return {
        // Dados do formulário
        items: <?= json_encode($itensJson) ?>,
        itemsRemovidos: [],
        valorTotal: 0,
        desconto: <?= floatval($pedido['desconto'] ?? 0) ?>,
        valorFinal: 0,
        descontoPercentual: 0,
        
        // Configurações de desconto
        descontoMaximoVendedor: <?= $isVendedor ? $descontoMaximoVendedor : '999999' ?>,
        isVendedor: <?= $isVendedor ? 'true' : 'false' ?>,
        descontoExcedido: false,
        
        // Controle do Modal
        showModalProdutos: false,
        buscaProduto: '',
        produtosFiltrados: {},
        
        // Item personalizado
        itemPersonalizado: {
            descricao: '',
            valor: 0
        },
        
        // Produtos do catálogo para busca
        produtosCatalogo: <?= json_encode($produtos) ?>,
        
        init() {
            this.calcularTotais();
            this.produtosFiltrados = <?= json_encode($produtosPorCategoria) ?>;
        },
        
        // Cálculos
        calcularTotalItem(index) {
            const item = this.items[index];
            if (item) {
                item.valor_total = (item.quantidade || 0) * (item.valor_unitario || 0);
                this.calcularTotais();
            }
        },
        
        calcularTotais() {
            this.valorTotal = this.items
                .filter(item => !item.is_removed)
                .reduce((sum, item) => sum + (item.valor_total || 0), 0);
            this.calcularValorFinal();
        },
        
        calcularValorFinal() {
            this.valorFinal = Math.max(0, this.valorTotal - this.desconto);
            this.descontoPercentual = this.valorTotal > 0
                ? ((this.desconto / this.valorTotal) * 100).toFixed(1) 
                : 0;
            this.validarDesconto();
        },
        
        validarDesconto() {
            if (!this.isVendedor) {
                this.descontoExcedido = false;
                return;
            }
            
            // Calcular percentual do desconto atual
            const percentualDesconto = this.valorTotal > 0 
                ? (this.desconto / this.valorTotal) * 100 
                : 0;
            this.descontoExcedido = percentualDesconto > this.descontoMaximoVendedor;
        },
        
        // Gestão de Itens
        itemModificado(index) {
            const item = this.items[index];
            if (item && item.is_saved && !item.is_new) {
                item.is_modified = true;
            }
        },
        
        adicionarProdutoCatalogo(produto) {
            const novoItem = {
                produto_id: produto.id,
                descricao: `${produto.codigo} - ${produto.nome}\n${produto.descricao || ''}`,
                quantidade: 1,
                valor_unitario: produto.preco,
                valor_total: produto.preco,
                is_new: true,
                is_saved: false
            };
            this.items.push(novoItem);
            this.calcularTotais();
            this.showModalProdutos = false;
            
            // Limpar busca
            this.buscaProduto = '';
            
            // Toast de sucesso
            this.showToast('Produto adicionado com sucesso!');
        },
        
        adicionarItemPersonalizado() {
            if (!this.itemPersonalizado.descricao || this.itemPersonalizado.valor <= 0) {
                alert('Por favor, preencha a descrição e o valor do item personalizado');
                return;
            }
            
            const novoItem = {
                produto_id: null,
                descricao: this.itemPersonalizado.descricao,
                quantidade: 1,
                valor_unitario: this.itemPersonalizado.valor,
                valor_total: this.itemPersonalizado.valor,
                is_new: true,
                is_saved: false
            };
            
            this.items.push(novoItem);
            this.calcularTotais();
            
            // Limpar campos
            this.itemPersonalizado.descricao = '';
            this.itemPersonalizado.valor = 0;
            this.showModalProdutos = false;
            
            this.showToast('Item personalizado adicionado!');
        },
        
        removerItem(index) {
            const item = this.items[index];
            
            if (!item) return;
            
            if (item.is_saved && item.id) {
                if (confirm('Tem certeza que deseja remover este item?')) {
                    this.itemsRemovidos.push(item);
                    item.is_removed = true;
                    this.calcularTotais();
                }
            } else {
                this.items.splice(index, 1);
                this.calcularTotais();
            }
        },
        
        // Busca e Filtros
        filtrarProdutos() {
            const busca = this.buscaProduto.toLowerCase();
            
            if (!busca) {
                this.produtosFiltrados = <?= json_encode($produtosPorCategoria) ?>;
                return;
            }
            
            // Filtrar produtos por nome ou código
            this.produtosFiltrados = {};
            this.produtosCatalogo.forEach(produto => {
                const categoria = produto.categoria_nome || 'Sem Categoria';
                
                if (produto.nome.toLowerCase().includes(busca) || 
                    produto.codigo.toLowerCase().includes(busca)) {
                    
                    if (!this.produtosFiltrados[categoria]) {
                        this.produtosFiltrados[categoria] = [];
                    }
                    this.produtosFiltrados[categoria].push(produto);
                }
            });
        },
        
        produtoVisivel(produto) {
            if (!this.buscaProduto) return true;
            
            const busca = this.buscaProduto.toLowerCase();
            return produto.nome.toLowerCase().includes(busca) || 
                   produto.codigo.toLowerCase().includes(busca);
        },
        
        // Outros
        clienteAlterado() {
            const select = document.getElementById('cliente_id');
            const option = select.options[select.selectedIndex];
            document.getElementById('cliente_telefone').value = option.dataset.telefone || '';
        },
        
        formatarMoeda(valor) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor || 0);
        },
        
        showToast(message) {
            // Implementar toast notification se desejar
            console.log(message);
        }
    }
}
</script>

<?php include '../../views/layouts/_footer.php'; ?>