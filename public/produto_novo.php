<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['vendedor', 'gestor']);

// Buscar clientes para o select
$clientes = getCachedQuery($pdo, 'clientes_ativos_lista', "SELECT id, nome FROM clientes WHERE ativo = true ORDER BY nome", [], 300);

$titulo = 'Novo Pedido';
$breadcrumb = [
    ['label' => 'Pedidos', 'url' => 'pedidos/pedidos.php'],
    ['label' => 'Novo Pedido']
];
include '../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Novo Pedido</h2>
        
        <form id="formPedido" method="POST" action="pedido_salvar.php" enctype="multipart/form-data"
      x-data="formPedido()" x-init="init()">
            
            <!-- Dados do Cliente -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Dados do Cliente</h3>
                
                <div class="mb-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" x-model="cliente_novo" class="mr-2">
                        <span class="text-sm text-gray-700">Cadastrar novo cliente</span>
                    </label>
                </div>
                
                <!-- Cliente Existente -->
                <div x-show="!cliente_novo" x-transition class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Selecione o Cliente <span class="text-red-500">*</span>
                    </label>
                    <select name="cliente_id" 
                            x-bind:required="!cliente_novo"
                            x-bind:disabled="cliente_novo"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Selecione o cliente...</option>
                        <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Novo Cliente -->
                <div x-show="cliente_novo" x-transition class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Nome do Cliente <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="cliente_nome" 
                                   x-bind:required="cliente_novo"
                                   x-bind:disabled="!cliente_novo"
                                   placeholder="Nome completo ou razão social"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Telefone <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="cliente_telefone" 
                                   x-bind:required="cliente_novo"
                                   x-bind:disabled="!cliente_novo"
                                   placeholder="(00) 00000-0000"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
                            <input type="email" name="cliente_email"
                                   x-bind:disabled="!cliente_novo"
                                   placeholder="email@exemplo.com"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">CPF/CNPJ</label>
                            <input type="text" name="cliente_cpf_cnpj"
                                   x-bind:disabled="!cliente_novo"
                                   placeholder="000.000.000-00"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Itens do Pedido -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Itens do Pedido</h3>
                
                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-6">
                                <input type="text" x-model="item.descricao" :name="'items['+index+'][descricao]'" 
                                       placeholder="Descrição do produto" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                            </div>
                            <div class="col-span-2">
                                <input type="number" x-model.number="item.quantidade" :name="'items['+index+'][quantidade]'" 
                                       min="1" required @change="calcularTotal()"
                                       placeholder="Qtd"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                            </div>
                            <div class="col-span-3">
                                <input type="number" x-model.number="item.valor_unitario" :name="'items['+index+'][valor_unitario]'" 
                                       step="0.01" min="0" required @change="calcularTotal()"
                                       placeholder="Valor unitário"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                            </div>
                            <div class="col-span-1">
                                <button type="button" @click="items.splice(index, 1); calcularTotal()" 
                                        x-show="items.length > 1"
                                        class="w-full px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
                
                <button type="button" @click="items.push({descricao: '', quantidade: 1, valor_unitario: 0})" 
                        class="mt-3 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Adicionar Item
                </button>
            </div>
            
            <!-- Informações Adicionais -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">Informações Adicionais</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Valor Total</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">R$</span>
                            <input type="number" id="valor_total" name="valor_total" step="0.01" readonly
                                   class="w-full pl-10 pr-4 py-2 bg-gray-100 border rounded-lg font-semibold">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Desconto</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">R$</span>
                            <input type="number" name="desconto" step="0.01" min="0" value="0"
                                   class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Prazo de Entrega <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="prazo_entrega" id="prazo_entrega" required
                               min="<?= date('Y-m-d') ?>"
                               value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="urgente" value="1" class="mr-2">
                        <span class="text-sm font-semibold text-red-600 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                            </svg>
                            Pedido Urgente
                        </span>
                    </label>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                    <textarea name="observacoes" rows="3" 
                              placeholder="Informações importantes sobre o pedido..."
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Arquivos de Arte</label>
                    <input type="file" name="arquivos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-1">
                        Formatos aceitos: PDF, JPG, PNG, DOC, DOCX (máx. 10MB cada)
                    </p>
                </div>
            </div>
            
            <!-- Botões -->
            <div class="flex justify-between">
                <a href="pedidos/pedidos.php" 
                   class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Cancelar
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Salvar Pedido
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function formPedido() {
        return {
            items: [{ descricao: '', quantidade: 1, valor_unitario: 0 }],
            cliente_novo: false,
            cep: '',
            buscandoCep: false,

            init() {
                // Definir prazo de entrega padrão: +7 dias
                const prazoEntrega = new Date();
                prazoEntrega.setDate(prazoEntrega.getDate() + 7);
                const campoPrazo = document.getElementById('prazo_entrega');
                if (campoPrazo && !campoPrazo.value) {
                    campoPrazo.value = prazoEntrega.toISOString().split('T')[0];
                }
                
                // Garantir que os campos estejam corretos ao carregar
                this.$watch('cliente_novo', value => {
                    if (value) {
                        // Limpa o select de clientes existentes
                        const selectCliente = document.querySelector('select[name=cliente_id]');
                        if (selectCliente) selectCliente.value = '';
                    } else {
                        // Limpa campos de cliente novo
                        const campos = ['cliente_nome', 'cliente_telefone', 'cliente_email', 'cliente_cpf_cnpj'];
                        campos.forEach(campo => {
                            const input = document.querySelector(`input[name="${campo}"]`);
                            if (input) input.value = '';
                        });
                    }
                });
            },

            calcularTotal() {
                let total = 0;
                this.items.forEach(item => {
                    total += (item.quantidade * item.valor_unitario);
                });
                const campoTotal = document.getElementById('valor_total');
                if (campoTotal) campoTotal.value = total.toFixed(2);
            }
        }
    }
</script>

<script>
// Validação adicional do formulário
document.getElementById('formPedido').addEventListener('submit', function(e) {
    const valorTotal = parseFloat(document.getElementById('valor_total').value);
    
    if (valorTotal <= 0) {
        e.preventDefault();
        alert('Por favor, adicione pelo menos um item ao pedido!');
        return false;
    }
    
    // Mostrar loading
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <svg class="animate-spin h-5 w-5 mr-2 inline" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Salvando...
    `;
});

// Formatação de telefone
document.addEventListener('input', function(e) {
    if (e.target.name === 'cliente_telefone') {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            if (value.length > 6) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
            }
        }
        e.target.value = value;
    }
    
    // Formatação de CPF/CNPJ
    if (e.target.name === 'cliente_cpf_cnpj') {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            // CPF
            value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
        } else if (value.length <= 14) {
            // CNPJ
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
        }
        e.target.value = value;
    }
});
</script>

<?php include '../views/layouts/_footer.php'; ?>