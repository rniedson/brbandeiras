<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['vendedor', 'gestor']);

// Buscar dados necessários
$cliente_id_param = $_GET['cliente_id'] ?? null;

$clientes = $pdo->query("
    SELECT 
        id, nome, nome_fantasia, cpf_cnpj, telefone, celular, whatsapp, email, codigo_sistema,
        COALESCE(celular, whatsapp, telefone) as telefone_principal
    FROM clientes 
    WHERE ativo = true 
    ORDER BY nome
")->fetchAll();

$cliente_selecionado = null;
if ($cliente_id_param) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND ativo = true");
    $stmt->execute([$cliente_id_param]);
    $cliente_selecionado = $stmt->fetch();
}

$produtos = $pdo->query("
    SELECT p.*, c.nome as categoria_nome 
    FROM produtos_catalogo p
    LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
    WHERE p.ativo = true AND p.estoque_disponivel = true
    ORDER BY c.nome, p.nome
")->fetchAll();

$produtosPorCategoria = [];
foreach ($produtos as $produto) {
    $categoria = $produto['categoria_nome'] ?: 'Sem Categoria';
    if (!isset($produtosPorCategoria[$categoria])) {
        $produtosPorCategoria[$categoria] = [];
    }
    $produtosPorCategoria[$categoria][] = $produto;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Pedido - BR Bandeiras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Prevenir FOUC (Flash of Unstyled Content) */
        [x-cloak] { display: none !important; }
        
        /* Loading Screen */
        .modal-loading {
            position: absolute;
            inset: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
        }
        
        /* Garantir que elementos com x-show=false sejam escondidos */
        [x-show="false"] {
            display: none !important;
        }
        
        /* Prevenir que o body fique travado */
        body.modal-open {
            overflow: hidden;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(34, 197, 94, 0.2);
            border-top-color: #22c55e;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Estilos do modal */
        .modal-backdrop {
            backdrop-filter: blur(5px);
        }
        
        /* Autocomplete */
        .autocomplete-container {
            position: relative;
        }

        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 9999;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            transition: background-color 0.15s;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background-color: #f0fdf4;
        }

        .autocomplete-highlight {
            background-color: #fef3c7;
            font-weight: 600;
        }

        /* Editor */
        #editor-observacoes {
            min-height: 100px;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.5;
        }

        #editor-observacoes:empty:before {
            content: attr(placeholder);
            color: #9ca3af;
            pointer-events: none;
        }

        /* Animações */
        .slide-enter {
            animation: slideEnter 0.3s ease-out;
        }

        @keyframes slideEnter {
            from {
                transform: translateX(50px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Scrollbar personalizada */
        .modal-content::-webkit-scrollbar {
            width: 6px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        /* Correção de overflow para permitir dropdown */
        .modal-content {
            overflow-y: auto;
            overflow-x: visible;
        }
        
        .items-container {
            position: relative;
        }

        /* Drag & Drop */
        .drag-over {
            background-color: #f0fdf4 !important;
            border-color: #22c55e !important;
        }

        /* Items Grid Mobile */
        @media (max-width: 640px) {
            .item-grid {
                grid-template-columns: 1fr !important;
            }
        }
        
        /* Categoria badge */
        .categoria-badge {
            display: inline-block;
            padding: 2px 8px;
            background-color: #e5e7eb;
            color: #4b5563;
            font-size: 11px;
            border-radius: 4px;
            margin-right: 4px;
        }
        
        /* Progress Bar */
        .upload-progress-bar {
            transition: width 0.3s ease;
        }
    </style>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body>
    <!-- Modal Container -->
    <div x-data="pedidoModal()" 
         x-show="open" 
         x-cloak
         x-init="init()"
         class="fixed inset-0 z-50 overflow-hidden"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop" 
             @click="confirmarFechar()"></div>
        
        <!-- Modal -->
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col relative"
                 @click.stop
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100">
                
                <!-- Loading do Modal -->
                <div x-show="loading" class="modal-loading">
                    <div class="loading-spinner"></div>
                </div>
                
                <!-- Header -->
                <div class="px-6 py-4 border-b bg-gradient-to-r from-green-600 to-green-700 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-white">Novo Pedido</h2>
                            <p class="text-sm text-white/80 mt-1">
                                <span x-text="currentStep === 1 ? 'Passo 1 de 2: Cliente e Itens' : 'Passo 2 de 2: Finalização'"></span>
                            </p>
                        </div>
                        <button @click="confirmarFechar()" 
                                class="text-white hover:text-gray-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-3 h-2 bg-white/30 rounded-full overflow-hidden">
                        <div class="h-full bg-white transition-all duration-500"
                             :style="`width: ${currentStep * 50}%`"></div>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="flex-1 overflow-y-auto modal-content">
                    <form id="formPedido" @submit.prevent="salvarPedido()">
                        
                        <!-- Step 1: Cliente + Itens -->
                        <div x-show="currentStep === 1" x-cloak class="p-6 space-y-6 slide-enter">
                            
                            <!-- Seção Cliente -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="text-lg font-semibold mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Cliente
                                </h3>
                                
                                <div class="mb-3">
                                    <label class="flex items-center cursor-pointer hover:bg-white p-2 rounded">
                                        <input type="checkbox" x-model="formData.cliente_novo" @change="limparBuscaCliente()" class="mr-3 h-4 w-4 text-green-600">
                                        <span class="text-sm font-medium text-gray-700">Cadastrar novo cliente</span>
                                    </label>
                                </div>
                                
                                <!-- Cliente Existente -->
                                <div x-show="!formData.cliente_novo" x-transition>
                                    <div class="autocomplete-container">
                                        <input type="text" 
                                               x-model="cliente_busca"
                                               @input="buscarClientes()"
                                               @keydown.down.prevent="navegarSugestoes('down')"
                                               @keydown.up.prevent="navegarSugestoes('up')"
                                               @keydown.enter.prevent="selecionarSugestao()"
                                               @keydown.escape="fecharSugestoes()"
                                               placeholder="Digite nome, CPF/CNPJ, telefone..."
                                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                        
                                        <div x-show="mostrar_sugestoes && sugestoes.length > 0" 
                                             x-transition
                                             class="autocomplete-results">
                                            <template x-for="(cliente, index) in sugestoes" :key="cliente.id">
                                                <div @click="selecionarCliente(cliente)"
                                                     :class="{'active': index === sugestao_ativa}"
                                                     class="autocomplete-item">
                                                    <div class="font-medium" x-html="destacarTexto(cliente.nome, cliente_busca)"></div>
                                                    <div class="text-xs text-gray-600">
                                                        <span x-show="cliente.cpf_cnpj" x-text="formatarDocumento(cliente.cpf_cnpj)"></span>
                                                        <span x-show="cliente.telefone_principal"> • <span x-text="cliente.telefone_principal"></span></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <div x-show="cliente_selecionado" x-transition class="mt-3 p-3 bg-green-50 rounded-lg flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-green-800" x-text="cliente_selecionado?.nome"></p>
                                            <p class="text-sm text-green-600" x-text="cliente_selecionado?.telefone_principal"></p>
                                        </div>
                                        <button type="button" @click="limparClienteSelecionado()" class="text-red-600 hover:text-red-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Novo Cliente -->
                                <div x-show="formData.cliente_novo" x-transition class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input type="text" 
                                           x-model="formData.cliente_nome"
                                           placeholder="Nome completo *"
                                           class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                    
                                    <input type="text" 
                                           x-model="formData.cliente_telefone"
                                           @input="formatarTelefone($event)"
                                           placeholder="Telefone *"
                                           maxlength="15"
                                           class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                    
                                    <input type="email" 
                                           x-model="formData.cliente_email"
                                           placeholder="E-mail (opcional)"
                                           class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                    
                                    <input type="text" 
                                           x-model="formData.cliente_cpf_cnpj"
                                           @input="formatarCpfCnpj($event)"
                                           placeholder="CPF/CNPJ (opcional)"
                                           maxlength="18"
                                           class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                </div>
                            </div>
                            
                            <!-- Seção Itens -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold flex items-center">
                                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        Itens do Pedido
                                    </h3>
                                    <a href="catalogo.php" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
                                        Ver Catálogo
                                    </a>
                                </div>
                                
                                <div class="space-y-3 items-container">
                                    <template x-for="(item, index) in formData.items" :key="item.id">
                                        <div class="bg-white p-3 rounded-lg border item-grid grid grid-cols-12 gap-3 relative">
                                            <div class="col-span-12 md:col-span-7">
                                                <div class="autocomplete-container">
                                                    <input type="text"
                                                           x-model="item.produto_busca"
                                                           @input="buscarProdutos(index)"
                                                           @focus="item.mostrar_produtos = true"
                                                           @keydown.down.prevent="navegarProdutos(index, 'down')"
                                                           @keydown.up.prevent="navegarProdutos(index, 'up')"
                                                           @keydown.enter.prevent="selecionarProdutoSugestao(index)"
                                                           @keydown.escape="item.mostrar_produtos = false"
                                                           placeholder="Digite código, nome do produto ou use personalizado..."
                                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500 text-sm">
                                                    
                                                    <div x-show="item.mostrar_produtos && item.produtos_filtrados?.length > 0" 
                                                         x-transition
                                                         @click.away="item.mostrar_produtos = false"
                                                         class="autocomplete-results">
                                                        <template x-for="(produto, pIndex) in item.produtos_filtrados" :key="produto.id">
                                                            <div @click="selecionarProduto(index, produto)"
                                                                 :class="{'active': pIndex === item.produto_ativo}"
                                                                 class="autocomplete-item">
                                                                <div>
                                                                    <span class="categoria-badge" x-text="produto.categoria_nome || 'Sem Categoria'"></span>
                                                                    <span class="font-medium" x-html="destacarTexto(produto.codigo + ' - ' + produto.nome, item.produto_busca)"></span>
                                                                </div>
                                                                <div class="text-xs text-green-600 mt-1">
                                                                    R$ <span x-text="parseFloat(produto.preco).toFixed(2).replace('.', ',')"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-span-4 md:col-span-2">
                                                <input type="number" 
                                                       x-model.number="item.quantidade" 
                                                       min="1" 
                                                       @input="calcularTotal()"
                                                       placeholder="Qtd"
                                                       class="w-full px-2 py-2 border rounded-lg focus:outline-none focus:border-green-500 text-sm text-center">
                                            </div>
                                            
                                            <div class="col-span-7 md:col-span-2">
                                                <div class="relative">
                                                    <span class="absolute left-2 top-2 text-gray-500 text-xs">R$</span>
                                                    <input type="number" 
                                                           x-model.number="item.valor_unitario" 
                                                           step="0.01" 
                                                           min="0" 
                                                           @input="calcularTotal()"
                                                           :readonly="item.produto_id ? true : false"
                                                           class="w-full pl-8 pr-2 py-2 border rounded-lg focus:outline-none focus:border-green-500 text-sm text-right">
                                                </div>
                                            </div>
                                            
                                            <div class="col-span-1">
                                                <button type="button" 
                                                        @click="removerItem(index)" 
                                                        x-show="formData.items.length > 1"
                                                        class="w-full h-full bg-red-500 text-white rounded-lg hover:bg-red-600 flex items-center justify-center">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <div class="mt-4 flex items-center justify-between pt-4 border-t">
                                    <button type="button" 
                                            @click="adicionarItem()" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Adicionar Item
                                    </button>
                                    
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">Subtotal</p>
                                        <p class="text-2xl font-bold text-gray-800" x-text="'R$ ' + subtotal.toFixed(2).replace('.', ',')"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Finalização -->
                        <div x-show="currentStep === 2" x-cloak class="p-6 space-y-6 slide-enter">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Coluna Esquerda -->
                                <div class="space-y-4">
                                    <!-- Resumo do Pedido -->
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <h3 class="font-semibold mb-3 text-gray-700">Resumo do Pedido</h3>
                                        
                                        <!-- Cliente -->
                                        <div class="mb-3 pb-3 border-b">
                                            <p class="text-sm text-gray-600">Cliente:</p>
                                            <p class="font-medium" x-text="getClienteNome()"></p>
                                        </div>
                                        
                                        <!-- Itens -->
                                        <div class="space-y-2 mb-3 pb-3 border-b">
                                            <p class="text-sm text-gray-600">Itens:</p>
                                            <template x-for="item in formData.items" :key="item.id">
                                                <div class="flex justify-between text-sm">
                                                    <span>
                                                        <span x-text="item.quantidade"></span>x 
                                                        <span x-text="item.descricao || item.produto_busca || 'Produto personalizado'"></span>
                                                    </span>
                                                    <span x-text="'R$ ' + (item.quantidade * item.valor_unitario).toFixed(2).replace('.', ',')"></span>
                                                </div>
                                            </template>
                                        </div>
                                        
                                        <!-- Valores -->
                                        <div class="space-y-2">
                                            <div class="flex justify-between text-sm">
                                                <span>Subtotal:</span>
                                                <span x-text="'R$ ' + subtotal.toFixed(2).replace('.', ',')"></span>
                                            </div>
                                            <div x-show="formData.desconto > 0" class="flex justify-between text-sm text-red-600">
                                                <span>Desconto:</span>
                                                <span x-text="'- R$ ' + calcularDesconto().toFixed(2).replace('.', ',')"></span>
                                            </div>
                                            <div class="flex justify-between font-bold text-lg pt-2 border-t">
                                                <span>Total:</span>
                                                <span class="text-green-600" x-text="'R$ ' + valorFinal().toFixed(2).replace('.', ',')"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Desconto -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Aplicar Desconto</label>
                                        <div class="flex gap-2">
                                            <select x-model="formData.tipo_desconto" 
                                                    @change="calcularTotal()"
                                                    class="px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                                <option value="valor">R$</option>
                                                <option value="porcentagem">%</option>
                                            </select>
                                            
                                            <input type="number" 
                                                   x-model.number="formData.desconto"
                                                   @input="calcularTotal()"
                                                   step="0.01" 
                                                   min="0"
                                                   placeholder="0,00"
                                                   class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Coluna Direita -->
                                <div class="space-y-4">
                                    <!-- Prazo -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Prazo de Entrega <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" 
                                               x-model="formData.prazo_entrega"
                                               min="<?= date('Y-m-d') ?>"
                                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                                    </div>
                                    
                                    <!-- Observações -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                                        <div class="border rounded-lg">
                                            <div class="bg-gray-50 border-b p-2 flex gap-1">
                                                <button type="button" 
                                                        @click="formatText('bold')" 
                                                        title="Negrito"
                                                        class="p-1 hover:bg-gray-200 rounded">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M13.5,15.5H10V12.5H13.5A1.5,1.5 0 0,1 15,14A1.5,1.5 0 0,1 13.5,15.5M10,6.5H13A1.5,1.5 0 0,1 14.5,8A1.5,1.5 0 0,1 13,9.5H10M15.6,10.79C16.57,10.11 17.25,9 17.25,8C17.25,5.74 15.5,4 13.25,4H7V18H14.04C16.14,18 17.75,16.3 17.75,14.21C17.75,12.69 16.89,11.39 15.6,10.79Z"/>
                                                    </svg>
                                                </button>
                                                <button type="button" 
                                                        @click="formatText('insertUnorderedList')" 
                                                        title="Lista"
                                                        class="p-1 hover:bg-gray-200 rounded">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M7,5H21V7H7V5M7,13V11H21V13H7M4,4.5A1.5,1.5 0 0,1 5.5,6A1.5,1.5 0 0,1 4,7.5A1.5,1.5 0 0,1 2.5,6A1.5,1.5 0 0,1 4,4.5M4,10.5A1.5,1.5 0 0,1 5.5,12A1.5,1.5 0 0,1 4,13.5A1.5,1.5 0 0,1 2.5,12A1.5,1.5 0 0,1 4,10.5M7,19V17H21V19H7M4,16.5A1.5,1.5 0 0,1 5.5,18A1.5,1.5 0 0,1 4,19.5A1.5,1.5 0 0,1 2.5,18A1.5,1.5 0 0,1 4,16.5Z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div id="editor-observacoes"
                                                 contenteditable="true"
                                                 class="p-3 focus:outline-none"
                                                 placeholder="Digite observações sobre o pedido..."
                                                 @input="syncObservacoes()">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Upload -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Arquivos de Arte</label>
                                        <div @dragover.prevent="dragover = true"
                                             @dragleave.prevent="dragover = false"
                                             @drop.prevent="handleDrop($event)"
                                             :class="{'drag-over': dragover}"
                                             class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-all">
                                            
                                            <input type="file" 
                                                   id="fileInput"
                                                   multiple 
                                                   accept=".pdf,.jpg,.jpeg,.png,.ai,.cdr,.psd"
                                                   class="hidden"
                                                   @change="handleFiles($event.target.files)">
                                            
                                            <label for="fileInput" class="cursor-pointer">
                                                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                </svg>
                                                <p class="mt-1 text-xs text-gray-600">Clique ou arraste arquivos</p>
                                            </label>
                                            
                                            <!-- Lista de arquivos -->
                                            <div x-show="files.length > 0" class="mt-3 text-left">
                                                <template x-for="(file, index) in files" :key="index">
                                                    <div class="flex items-center justify-between py-1 text-sm">
                                                        <span class="truncate flex-1" x-text="file.name"></span>
                                                        <button type="button" @click="removeFile(index)" class="ml-2 text-red-600 hover:text-red-800">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            
                                            <!-- Progresso de Upload -->
                                            <div x-show="uploadingFiles.length > 0" class="mt-3 space-y-2">
                                                <template x-for="filename in uploadingFiles" :key="filename">
                                                    <div class="bg-gray-100 rounded p-2">
                                                        <div class="flex justify-between text-sm mb-1">
                                                            <span x-text="filename"></span>
                                                            <span x-text="(uploadProgress[filename] || 0) + '%'"></span>
                                                        </div>
                                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                                            <div class="bg-green-600 h-2 rounded-full upload-progress-bar"
                                                                 :style="`width: ${uploadProgress[filename] || 0}%`"></div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Urgente -->
                                    <label class="flex items-center cursor-pointer p-3 bg-red-50 rounded-lg hover:bg-red-100">
                                        <input type="checkbox" x-model="formData.urgente" class="mr-3 h-4 w-4 text-red-600">
                                        <span class="text-sm font-semibold text-red-600 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                            </svg>
                                            Marcar como Pedido Urgente
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                    </form>
                </div>
                
                <!-- Footer -->
                <div class="px-6 py-4 border-t bg-gray-50 rounded-b-xl">
                    <div class="flex justify-between items-center">
                        <div class="flex gap-3">
                            <button type="button"
                                    @click="currentStep = 1"
                                    x-show="currentStep === 2"
                                    x-cloak
                                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">
                                <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                Voltar
                            </button>
                            
                            <button type="button"
                                    @click="confirmarFechar()"
                                    class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                Cancelar
                            </button>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="button"
                                    @click="salvarRascunho()"
                                    class="px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-50">
                                <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                Salvar Rascunho
                            </button>
                            
                            <button type="button"
                                    @click="proximoStep()"
                                    x-show="currentStep === 1"
                                    x-cloak
                                    :disabled="!podeAvancar(false)"
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                Próximo
                                <svg class="w-5 h-5 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            
                            <button type="button"
                                    @click="salvarPedido()"
                                    x-show="currentStep === 2"
                                    x-cloak
                                    :disabled="salvando || !podeAvancar(false)"
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="!salvando" class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <svg x-show="salvando" x-cloak class="animate-spin h-5 w-5 inline mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="salvando ? 'Salvando...' : 'Criar Pedido'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-[60] space-y-2"></div>
    
    <script>
    // Dados iniciais
    const clientesData = <?= json_encode($clientes) ?>;
    const clientePreSelecionado = <?= json_encode($cliente_selecionado) ?>;
    const produtosPorCategoria = <?= json_encode($produtosPorCategoria) ?>;
    const todosOsProdutos = Object.values(produtosPorCategoria).flat();
    
    // Função para normalizar strings (remover acentos)
    function normalizeString(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, "").toLowerCase();
    }
    
    function pedidoModal() {
        return {
            open: true,
            loading: true,
            currentStep: 1,
            salvando: false,
            dragover: false,
            
            // Upload assíncrono
            uploadQueue: [],
            uploadingFiles: [],
            uploadProgress: {},
            
            formData: {
                cliente_novo: false,
                cliente_id: clientePreSelecionado?.id || null,
                cliente_nome: '',
                cliente_telefone: '',
                cliente_email: '',
                cliente_cpf_cnpj: '',
                items: [{
                    id: Date.now(),
                    produto_id: '',
                    descricao: '',
                    quantidade: 1,
                    valor_unitario: 0,
                    produto_busca: '',
                    produtos_filtrados: [],
                    mostrar_produtos: false,
                    produto_ativo: -1
                }],
                desconto: 0,
                tipo_desconto: 'valor',
                prazo_entrega: '',
                observacoes: '',
                urgente: false
            },
            
            // Autocomplete
            cliente_busca: clientePreSelecionado?.nome || '',
            cliente_selecionado: clientePreSelecionado,
            sugestoes: [],
            mostrar_sugestoes: false,
            sugestao_ativa: -1,
            busca_timer: null,
            
            // Cálculos
            subtotal: 0,
            
            // Arquivos
            files: [],
            
            init() {
                // Adicionar classe ao body
                document.body.classList.add('modal-open');
                
                // Prazo padrão: amanhã
                const amanha = new Date();
                amanha.setDate(amanha.getDate() + 1);
                this.formData.prazo_entrega = amanha.toISOString().split('T')[0];
                
                // Recuperar rascunho
                const rascunho = localStorage.getItem('pedido_modal_rascunho_v2');
                if (rascunho) {
                    if (confirm('Há um rascunho salvo. Deseja recuperá-lo?')) {
                        const dados = JSON.parse(rascunho);
                        Object.assign(this.formData, dados);
                        this.calcularTotal();
                    }
                }
                
                // Atalhos de teclado
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && !this.salvando) {
                        this.confirmarFechar();
                    }
                    if (e.ctrlKey && e.key === 's') {
                        e.preventDefault();
                        this.salvarRascunho();
                    }
                });
                
                // Remover loading após inicialização
                setTimeout(() => {
                    this.loading = false;
                }, 500);
            },
            
            // Navegação
            proximoStep() {
                if (this.podeAvancar(true)) {
                    this.currentStep = 2;
                    this.$nextTick(() => {
                        document.querySelector('.modal-content').scrollTop = 0;
                    });
                }
            },
            
            podeAvancar(mostrarNotificacao = true) {
                if (this.currentStep === 1) {
                    // Validar cliente
                    const clienteOk = this.formData.cliente_novo ? 
                        (this.formData.cliente_nome && this.formData.cliente_telefone) : 
                        this.formData.cliente_id;
                    
                    // Validar itens
                    const itensOk = this.formData.items.length > 0 && 
                        this.formData.items.every(item => 
                            (item.produto_id || item.descricao || item.produto_busca) && 
                            item.quantidade > 0 && 
                            item.valor_unitario > 0
                        );
                    
                    if (!clienteOk && mostrarNotificacao) {
                        this.showNotification('Selecione ou cadastre um cliente', 'error');
                        return false;
                    }
                    
                    if (!itensOk && mostrarNotificacao) {
                        this.showNotification('Adicione pelo menos um item válido', 'error');
                        return false;
                    }
                    
                    return clienteOk && itensOk;
                } else {
                    return this.formData.prazo_entrega;
                }
            },
            
            // Cliente
            buscarClientes() {
                // Não buscar se estiver cadastrando novo cliente
                if (this.formData.cliente_novo) return;
                
                // Limpar timer anterior
                clearTimeout(this.busca_timer);
                
                if (this.cliente_busca.length < 2) {
                    this.fecharSugestoes();
                    return;
                }
                
                // Debounce de 300ms
                this.busca_timer = setTimeout(() => {
                    const termo = normalizeString(this.cliente_busca);
                    const termoNum = this.cliente_busca.replace(/\D/g, '');
                    
                    this.sugestoes = clientesData.filter(cliente => {
                        const nome = normalizeString(cliente.nome || '');
                        const nomeFantasia = normalizeString(cliente.nome_fantasia || '');
                        const cpf = (cliente.cpf_cnpj || '').replace(/\D/g, '');
                        const tel = (cliente.telefone_principal || '').replace(/\D/g, '');
                        
                        // Busca por palavras individuais
                        const palavras = termo.split(' ').filter(p => p.length > 0);
                        const nomeMatch = palavras.every(palavra => nome.includes(palavra) || nomeFantasia.includes(palavra));
                        
                        return nomeMatch || 
                               (termoNum && (cpf.includes(termoNum) || tel.includes(termoNum)));
                    }).slice(0, 5);
                    
                    this.mostrar_sugestoes = this.sugestoes.length > 0;
                }, 300);
            },
            
            limparBuscaCliente() {
                this.cliente_busca = '';
                this.cliente_selecionado = null;
                this.formData.cliente_id = null;
                this.fecharSugestoes();
            },
            
            selecionarCliente(cliente) {
                this.formData.cliente_id = cliente.id;
                this.cliente_busca = cliente.nome;
                this.cliente_selecionado = cliente;
                this.fecharSugestoes();
            },
            
            limparClienteSelecionado() {
                this.formData.cliente_id = null;
                this.cliente_busca = '';
                this.cliente_selecionado = null;
            },
            
            fecharSugestoes() {
                this.mostrar_sugestoes = false;
                this.sugestao_ativa = -1;
            },
            
            navegarSugestoes(direcao) {
                if (!this.mostrar_sugestoes) return;
                
                if (direcao === 'down') {
                    this.sugestao_ativa = Math.min(this.sugestao_ativa + 1, this.sugestoes.length - 1);
                } else {
                    this.sugestao_ativa = Math.max(this.sugestao_ativa - 1, -1);
                }
            },
            
            selecionarSugestao() {
                if (this.sugestao_ativa >= 0) {
                    this.selecionarCliente(this.sugestoes[this.sugestao_ativa]);
                }
            },
            
            destacarTexto(texto, busca) {
                if (!busca || busca.length < 2) return texto;
                const regex = new RegExp(`(${busca})`, 'gi');
                return texto.replace(regex, '<span class="autocomplete-highlight">$1</span>');
            },
            
            getClienteNome() {
                if (this.formData.cliente_novo) {
                    return this.formData.cliente_nome + ' (Novo)';
                }
                return this.cliente_selecionado?.nome || 'Não selecionado';
            },
            
            // Produtos
            buscarProdutos(index) {
                const item = this.formData.items[index];
                const busca = normalizeString(item.produto_busca);
                
                if (busca.length < 2) {
                    item.produtos_filtrados = [];
                    item.mostrar_produtos = false;
                    return;
                }
                
                item.produtos_filtrados = todosOsProdutos.filter(produto => {
                    const codigo = normalizeString(produto.codigo || '');
                    const nome = normalizeString(produto.nome || '');
                    const categoria = normalizeString(produto.categoria_nome || '');
                    
                    // Busca flexível
                    const palavras = busca.split(' ').filter(p => p.length > 0);
                    return palavras.every(palavra => 
                        codigo.includes(palavra) || 
                        nome.includes(palavra) || 
                        categoria.includes(palavra)
                    );
                }).slice(0, 10);
                
                item.mostrar_produtos = item.produtos_filtrados.length > 0;
                item.produto_ativo = -1;
            },
            
            navegarProdutos(index, direcao) {
                const item = this.formData.items[index];
                if (!item.mostrar_produtos) return;
                
                if (direcao === 'down') {
                    item.produto_ativo = Math.min(item.produto_ativo + 1, item.produtos_filtrados.length - 1);
                } else {
                    item.produto_ativo = Math.max(item.produto_ativo - 1, -1);
                }
            },
            
            selecionarProdutoSugestao(index) {
                const item = this.formData.items[index];
                if (item.produto_ativo >= 0) {
                    this.selecionarProduto(index, item.produtos_filtrados[item.produto_ativo]);
                }
            },
            
            selecionarProduto(index, produto) {
                const item = this.formData.items[index];
                
                if (produto) {
                    item.produto_id = produto.id;
                    item.descricao = `${produto.codigo} - ${produto.nome}`;
                    item.produto_busca = item.descricao;
                    item.valor_unitario = parseFloat(produto.preco);
                } else {
                    // Produto personalizado
                    item.produto_id = '';
                    item.descricao = item.produto_busca;
                    // Valor unitário editável
                }
                
                item.mostrar_produtos = false;
                this.calcularTotal();
            },
            
            adicionarItem() {
                this.formData.items.push({
                    id: Date.now(),
                    produto_id: '',
                    descricao: '',
                    quantidade: 1,
                    valor_unitario: 0,
                    produto_busca: '',
                    produtos_filtrados: [],
                    mostrar_produtos: false,
                    produto_ativo: -1
                });
            },
            
            removerItem(index) {
                if (this.formData.items.length > 1) {
                    this.formData.items.splice(index, 1);
                    this.calcularTotal();
                }
            },
            
            // Cálculos
            calcularTotal() {
                this.subtotal = this.formData.items.reduce((total, item) => {
                    return total + (item.quantidade * item.valor_unitario);
                }, 0);
            },
            
            calcularDesconto() {
                if (this.formData.tipo_desconto === 'porcentagem') {
                    return this.subtotal * this.formData.desconto / 100;
                }
                return this.formData.desconto;
            },
            
            valorFinal() {
                return Math.max(0, this.subtotal - this.calcularDesconto());
            },
            
            // Arquivos
            handleFiles(fileList) {
                for (let file of fileList) {
                    if (file.size > 25 * 1024 * 1024) {
                        this.showNotification(`${file.name} excede 25MB`, 'error');
                        continue;
                    }
                    this.files.push(file);
                }
                this.dragover = false;
            },
            
            handleDrop(e) {
                this.dragover = false;
                this.handleFiles(e.dataTransfer.files);
            },
            
            removeFile(index) {
                this.files.splice(index, 1);
            },
            
            // Upload assíncrono
            async processarUploads(pedido_id) {
                for (let file of this.files) {
                    this.uploadingFiles.push(file.name);
                    this.uploadProgress[file.name] = 0;
                    
                    const formData = new FormData();
                    formData.append('arquivo', file);
                    formData.append('pedido_id', pedido_id);
                    
                    try {
                        const xhr = new XMLHttpRequest();
                        
                        // Monitorar progresso
                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                this.uploadProgress[file.name] = Math.round((e.loaded / e.total) * 100);
                            }
                        });
                        
                        // Promise para aguardar conclusão
                        await new Promise((resolve, reject) => {
                            xhr.onload = () => {
                                if (xhr.status === 200) {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        resolve();
                                    } else {
                                        reject(new Error(response.message));
                                    }
                                } else {
                                    reject(new Error('Erro no upload'));
                                }
                            };
                            
                            xhr.onerror = () => reject(new Error('Erro de rede'));
                            
                            xhr.open('POST', 'pedido_upload_ajax.php');
                            xhr.send(formData);
                        });
                        
                        // Remover da lista de uploading
                        this.uploadingFiles = this.uploadingFiles.filter(f => f !== file.name);
                        
                    } catch (error) {
                        console.error(`Erro ao enviar ${file.name}:`, error);
                        this.showNotification(`Erro ao enviar ${file.name}`, 'error');
                    }
                }
            },
            
            // Formatações
            formatarTelefone(event) {
                let value = event.target.value.replace(/\D/g, '');
                if (value.length > 10) {
                    value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                } else if (value.length > 5) {
                    value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
                } else if (value.length > 2) {
                    value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
                }
                event.target.value = value;
                this.formData.cliente_telefone = value;
            },
            
            formatarCpfCnpj(event) {
                let value = event.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
                } else {
                    value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
                }
                event.target.value = value;
                this.formData.cliente_cpf_cnpj = value;
            },
            
            formatarDocumento(doc) {
                if (!doc) return '';
                doc = doc.replace(/\D/g, '');
                if (doc.length <= 11) {
                    return doc.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                }
                return doc.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            },
            
            // Salvamento
            salvarRascunho() {
                localStorage.setItem('pedido_modal_rascunho_v2', JSON.stringify(this.formData));
                this.showNotification('Rascunho salvo!', 'success');
            },
            
            async salvarPedido() {
                if (!this.podeAvancar(true)) return;
                
                this.salvando = true;
                
                try {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    
                    // Dados do formulário (sem arquivos)
                    Object.keys(this.formData).forEach(key => {
                        if (key === 'items') {
                            this.formData.items.forEach((item, index) => {
                                formData.append(`items[${index}][produto_id]`, item.produto_id || '');
                                formData.append(`items[${index}][descricao]`, item.descricao || item.produto_busca);
                                formData.append(`items[${index}][quantidade]`, item.quantidade);
                                formData.append(`items[${index}][valor_unitario]`, item.valor_unitario);
                            });
                        } else {
                            formData.append(key, this.formData[key] || '');
                        }
                    });
                    
                    // Primeiro salvar o pedido SEM os arquivos
                    const response = await fetch('pedido_salvar.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    // Verificar se a resposta é JSON
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        throw new Error("Servidor retornou uma resposta inválida");
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Agora fazer upload dos arquivos assincronamente
                        if (this.files.length > 0) {
                            this.showNotification('Enviando arquivos...', 'info');
                            await this.processarUploads(result.data.pedido_id);
                        }
                        
                        localStorage.removeItem('pedido_modal_rascunho_v2');

						this.showNotification('Pedido criado com sucesso!', 'success');
                        
                        setTimeout(() => {
                            window.location.href = `pedido_detalhes.php?id=${result.data.pedido_id}`;
                        }, 1000);
                    } else {
                        throw new Error(result.message || 'Erro ao salvar pedido');
                    }
                    
                } catch (error) {
                    console.error('Erro:', error);
                    this.showNotification(error.message || 'Erro ao salvar pedido', 'error');
                } finally {
                    this.salvando = false;
                }
            },
            
            // Helpers
            confirmarFechar() {
                if (this.temAlteracoes()) {
                    if (confirm('Há alterações não salvas. Deseja realmente sair?')) {
                        this.fecharComAnimacao();
                    }
                } else {
                    this.fecharComAnimacao();
                }
            },
            
            fecharComAnimacao() {
                // Fechar o modal com animação
                this.open = false;
                
                // Remover classe do body
                document.body.classList.remove('modal-open');
                
                // Aguardar a animação terminar antes de redirecionar
                setTimeout(() => {
                    if (window.opener) {
                        window.close();
                    } else {
                        window.location.href = 'dashboard_gestor.php';
                    }
                }, 300);
            },
            
            temAlteracoes() {
                return this.formData.items.some(item => item.produto_id || item.descricao || item.produto_busca) ||
                       this.formData.cliente_id ||
                       this.formData.cliente_nome;
            },
            
            showNotification(message, type = 'info') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `px-4 py-3 rounded-lg shadow-lg text-white ${
                    type === 'error' ? 'bg-red-500' : 
                    type === 'success' ? 'bg-green-500' : 
                    'bg-blue-500'
                } transform transition-all duration-300 translate-x-full`;
                toast.textContent = message;
                
                container.appendChild(toast);
                
                // Animar entrada
                setTimeout(() => {
                    toast.classList.remove('translate-x-full');
                }, 10);
                
                // Remover após 3 segundos
                setTimeout(() => {
                    toast.classList.add('translate-x-full', 'opacity-0');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }
        }
    }
    
    // Funções do editor
    function formatText(command) {
        document.execCommand(command, false, null);
        document.getElementById('editor-observacoes').focus();
    }
    
    function syncObservacoes() {
        const editor = document.getElementById('editor-observacoes');
        const alpineData = Alpine.$data(document.querySelector('[x-data]'));
        if (alpineData) {
            alpineData.formData.observacoes = editor.innerHTML;
        }
    }
    
    // Inicialização
    document.addEventListener('alpine:init', () => {
        // Qualquer inicialização adicional necessária
    });
    </script>
</body>
</html>