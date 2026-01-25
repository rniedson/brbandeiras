<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['producao', 'gestor']);

$titulo = 'Novo Fornecedor';
$breadcrumb = [
    ['label' => 'Fornecedores', 'url' => 'fornecedores.php'],
    ['label' => 'Novo Fornecedor']
];
include '../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Novo Fornecedor</h1>
        <p class="text-gray-600 mt-2">Cadastre um novo fornecedor no sistema</p>
    </div>
    
    <form method="POST" action="fornecedor_salvar.php" onsubmit="return showLoading(this)"
          x-data="{ 
              tipoPessoa: 'J',
              cep: '',
              buscandoCep: false,
              formatarTelefone(e) {
                  let value = e.target.value.replace(/\D/g, '');
                  if (value.length <= 11) {
                      value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                  }
                  e.target.value = value;
              },
              formatarDocumento(e) {
                  let value = e.target.value.replace(/\D/g, '');
                  if (this.tipoPessoa === 'F') {
                      value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
                  } else {
                      value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
                  }
                  e.target.value = value;
              },
              async buscarCep() {
                  if (this.cep.length !== 9) return;
                  
                  this.buscandoCep = true;
                  const cepLimpo = this.cep.replace(/\D/g, '');
                  
                  try {
                      const response = await fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`);
                      const data = await response.json();
                      
                      if (!data.erro) {
                          document.getElementById('endereco').value = data.logradouro;
                          document.getElementById('bairro').value = data.bairro;
                          document.getElementById('cidade').value = data.localidade;
                          document.getElementById('estado').value = data.uf;
                      } else {
                          alert('CEP não encontrado');
                      }
                  } catch (error) {
                      alert('Erro ao buscar CEP');
                  }
                  
                  this.buscandoCep = false;
              }
          }">
        
        <!-- Dados Básicos -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Dados Básicos</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Tipo de Pessoa -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Pessoa</label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="radio" name="tipo_pessoa" value="J" x-model="tipoPessoa" 
                                   class="mr-2" required>
                            <span>Pessoa Jurídica</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="tipo_pessoa" value="F" x-model="tipoPessoa" 
                                   class="mr-2">
                            <span>Pessoa Física</span>
                        </label>
                    </div>
                </div>
                
                <!-- Nome/Razão Social -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span x-text="tipoPessoa === 'J' ? 'Razão Social' : 'Nome Completo'"></span>
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nome" required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Nome Fantasia -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- CPF/CNPJ -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span x-text="tipoPessoa === 'J' ? 'CNPJ' : 'CPF'"></span>
                    </label>
                    <input type="text" name="cpf_cnpj" 
                           @input="formatarDocumento"
                           :placeholder="tipoPessoa === 'J' ? '00.000.000/0000-00' : '000.000.000-00'"
                           :maxlength="tipoPessoa === 'J' ? 18 : 14"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Telefone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Telefone <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="telefone" required
                           @input="formatarTelefone"
                           placeholder="(00) 00000-0000"
                           maxlength="15"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Celular -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Celular</label>
                    <input type="text" name="celular"
                           @input="formatarTelefone"
                           placeholder="(00) 00000-0000"
                           maxlength="15"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- E-mail -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
                    <input type="email" name="email"
                           placeholder="fornecedor@email.com"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- WhatsApp -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp</label>
                    <input type="text" name="whatsapp"
                           @input="formatarTelefone"
                           placeholder="(00) 00000-0000"
                           maxlength="15"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
            </div>
        </div>
        
        <!-- Endereço -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Endereço</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- CEP -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CEP</label>
                    <div class="flex">
                        <input type="text" name="cep" x-model="cep"
                               @input="e => e.target.value = e.target.value.replace(/\D/g, '').replace(/^(\d{5})(\d)/, '$1-$2')"
                               placeholder="00000-000"
                               maxlength="9"
                               class="flex-1 px-4 py-2 border rounded-l-lg focus:outline-none focus:border-green-500">
                        <button type="button" @click="buscarCep" :disabled="buscandoCep"
                                class="px-4 py-2 bg-gray-600 text-white rounded-r-lg hover:bg-gray-700 disabled:opacity-50">
                            <span x-show="!buscandoCep">Buscar</span>
                            <span x-show="buscandoCep">...</span>
                        </button>
                    </div>
                </div>
                
                <!-- Endereço -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Endereço</label>
                    <input type="text" name="endereco" id="endereco"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Número -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Número</label>
                    <input type="text" name="numero"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Complemento -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Complemento</label>
                    <input type="text" name="complemento"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Bairro -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bairro</label>
                    <input type="text" name="bairro" id="bairro"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Cidade -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cidade</label>
                    <input type="text" name="cidade" id="cidade"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Estado -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                    <select name="estado" id="estado" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Selecione...</option>
                        <?php
                        $estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                        foreach ($estados as $uf): ?>
                        <option value="<?= $uf ?>"><?= $uf ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Informações Adicionais</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Contato Principal -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contato Principal</label>
                    <input type="text" name="contato_principal"
                           placeholder="Nome do responsável"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <!-- Site -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Site</label>
                    <input type="url" name="site"
                           placeholder="https://www.exemplo.com.br"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="4"
                          placeholder="Informações importantes sobre o fornecedor..."
                          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
            </div>
        </div>
        
        <!-- Botões -->
        <div class="flex justify-between">
            <a href="fornecedores.php" 
               class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Salvar Fornecedor
            </button>
        </div>
    </form>
</div>

<?php include '../views/layouts/_footer.php'; ?>
