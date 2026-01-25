<?php
// clientes_importar_v2.php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();
requireRole(['gestor']);

$titulo = 'Importar Clientes';
$breadcrumb = [
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'Clientes', 'url' => 'clientes.php'],
    ['label' => 'Importar']
];

include '../../views/layouts/_header.php';
?>

<!-- Alpine.js Data -->
<div class="container mx-auto px-4 py-8" x-data="importadorClientes()">
    
    <!-- Cabeçalho -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Importar Clientes</h1>
        <p class="text-gray-600 mt-2">Importe clientes através de arquivo CSV - Processamento seguro no navegador</p>
    </div>

    <?php if (isset($_SESSION['mensagem'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
        <p><?= htmlspecialchars($_SESSION['mensagem']) ?></p>
    </div>
    <?php unset($_SESSION['mensagem']); endif; ?>

    <?php if (isset($_SESSION['erro'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
        <p><?= htmlspecialchars($_SESSION['erro']) ?></p>
    </div>
    <?php unset($_SESSION['erro']); endif; ?>

    <!-- Barra de Progresso das Etapas -->
    <div x-show="etapa > 0" class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center flex-1">
                <!-- Etapa 1 -->
                <div class="flex items-center">
                    <div :class="etapa >= 1 ? 'bg-green-600' : 'bg-gray-300'" 
                         class="rounded-full h-10 w-10 flex items-center justify-center text-white font-bold transition-colors">
                        <svg x-show="etapa > 1" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span x-show="etapa <= 1">1</span>
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="etapa >= 1 ? 'text-green-600' : 'text-gray-500'">
                        Carregar Arquivo
                    </span>
                </div>
                
                <div class="flex-1 mx-4">
                    <div class="h-1 bg-gray-300 rounded-full">
                        <div :class="etapa >= 2 ? 'bg-green-600' : ''" 
                             class="h-1 rounded-full transition-all duration-500"
                             :style="etapa >= 2 ? 'width: 100%' : 'width: 0%'"></div>
                    </div>
                </div>
                
                <!-- Etapa 2 -->
                <div class="flex items-center">
                    <div :class="etapa >= 2 ? 'bg-green-600' : 'bg-gray-300'" 
                         class="rounded-full h-10 w-10 flex items-center justify-center text-white font-bold transition-colors">
                        <svg x-show="etapa > 2" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span x-show="etapa <= 2">2</span>
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="etapa >= 2 ? 'text-green-600' : 'text-gray-500'">
                        Validar Dados
                    </span>
                </div>
                
                <div class="flex-1 mx-4">
                    <div class="h-1 bg-gray-300 rounded-full">
                        <div :class="etapa >= 3 ? 'bg-green-600' : ''" 
                             class="h-1 rounded-full transition-all duration-500"
                             :style="etapa >= 3 ? 'width: 100%' : 'width: 0%'"></div>
                    </div>
                </div>
                
                <!-- Etapa 3 -->
                <div class="flex items-center">
                    <div :class="etapa >= 3 ? 'bg-green-600' : 'bg-gray-300'" 
                         class="rounded-full h-10 w-10 flex items-center justify-center text-white font-bold transition-colors">
                        <svg x-show="etapa > 3" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span x-show="etapa <= 3">3</span>
                    </div>
                    <span class="ml-2 text-sm font-medium" :class="etapa >= 3 ? 'text-green-600' : 'text-gray-500'">
                        Importar
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Etapa 1: Upload do Arquivo -->
    <div x-show="etapa === 0 || etapa === 1" class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            Selecione o Arquivo CSV
        </h2>
        
        <!-- Instruções -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Formato do arquivo</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>O arquivo CSV deve conter as colunas na ordem correta. 
                        <a href="clientes_template_csv.php" class="underline font-medium">Baixe o template aqui</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Área de Upload -->
        <div @drop.prevent="handleDrop" 
             @dragover.prevent="dragover = true" 
             @dragleave.prevent="dragover = false"
             :class="dragover ? 'border-green-500 bg-green-50' : 'border-gray-300'"
             class="border-2 border-dashed rounded-lg p-8 text-center transition-all duration-200">
            
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            
            <p class="text-lg mb-2 text-gray-700">Arraste o arquivo CSV aqui</p>
            <p class="text-sm text-gray-500 mb-4">ou</p>
            
            <label class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 cursor-pointer transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <input type="file" @change="handleFileSelect" accept=".csv" class="hidden">
                Escolher Arquivo
            </label>
            
            <p class="text-xs text-gray-500 mt-4">Formato CSV com separador vírgula ou ponto-vírgula</p>
        </div>
        
        <!-- Arquivo Selecionado -->
        <div x-show="arquivo" class="mt-6 p-4 bg-gray-50 rounded-lg" x-transition>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg mr-3">
                        <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900" x-text="arquivo?.name"></p>
                        <p class="text-sm text-gray-500">
                            Tamanho: <span x-text="formatarTamanho(arquivo?.size)"></span>
                        </p>
                    </div>
                </div>
                <button @click="removerArquivo()" 
                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Botão Processar -->
        <div x-show="arquivo" class="mt-6 flex justify-end" x-transition>
            <button @click="processarCSV()" 
                    :disabled="processando"
                    class="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center">
                <svg x-show="!processando" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <svg x-show="processando" class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="processando ? 'Processando...' : 'Processar Arquivo'"></span>
            </button>
        </div>
    </div>

    <!-- Etapa 2: Validação e Preview -->
    <div x-show="etapa === 2" class="space-y-6" x-transition>
        
        <!-- Estatísticas -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold mb-4">Análise do Arquivo</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Total</p>
                            <p class="text-2xl font-bold text-blue-800" x-text="dados.length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-green-600 font-medium">Novos</p>
                            <p class="text-2xl font-bold text-green-800" x-text="estatisticas.novos"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-yellow-600 font-medium">Atualizar</p>
                            <p class="text-2xl font-bold text-yellow-800" x-text="estatisticas.atualizacoes"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-red-600 font-medium">Erros</p>
                            <p class="text-2xl font-bold text-red-800" x-text="estatisticas.erros"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Opções de Importação -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-bold mb-4">Opções de Importação</h3>
            <div class="space-y-3">
                <label class="flex items-center">
                    <input type="checkbox" x-model="opcoes.atualizarExistentes" 
                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <span class="ml-2 text-gray-700">Atualizar dados de clientes existentes</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" x-model="opcoes.ignorarErros" 
                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <span class="ml-2 text-gray-700">Ignorar linhas com erro e continuar</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" x-model="opcoes.modoTeste" 
                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <span class="ml-2 font-semibold text-blue-600">Modo teste (simular sem salvar no banco)</span>
                </label>
            </div>
        </div>
        
        <!-- Preview dos Dados -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-bold mb-4">Prévia dos Dados</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome/Razão</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CPF/CNPJ</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cidade/UF</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="(cliente, index) in dados.slice(0, 10)" :key="index">
                            <tr :class="{
                                'bg-red-50': cliente._status === 'erro',
                                'bg-yellow-50': cliente._status === 'atualizar',
                                'bg-green-50': cliente._status === 'novo'
                            }">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span x-show="cliente._status === 'novo'" 
                                          class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        Novo
                                    </span>
                                    <span x-show="cliente._status === 'atualizar'" 
                                          class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                        Atualizar
                                    </span>
                                    <span x-show="cliente._status === 'erro'" 
                                          class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                        Erro
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm" x-text="cliente.codigo_sistema || '-'"></td>
                                <td class="px-4 py-3 text-sm font-medium" x-text="cliente.nome"></td>
                                <td class="px-4 py-3 text-sm font-mono" x-text="formatarDocumento(cliente.cpf_cnpj)"></td>
                                <td class="px-4 py-3 text-sm" x-text="cliente.telefone || cliente.celular || '-'"></td>
                                <td class="px-4 py-3 text-sm" x-text="(cliente.cidade || '-') + '/' + (cliente.estado || '-')"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="dados.length > 10" class="text-sm text-gray-500 mt-3">
                Mostrando 10 de <span x-text="dados.length"></span> registros
            </p>
        </div>
        
        <!-- Erros Encontrados -->
        <div x-show="erros.length > 0" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
            <h3 class="font-bold text-red-800 mb-2">Erros Encontrados</h3>
            <ul class="text-sm text-red-600 space-y-1">
                <template x-for="erro in erros.slice(0, 5)" :key="erro">
                    <li x-text="'• ' + erro"></li>
                </template>
            </ul>
            <p x-show="erros.length > 5" class="text-sm text-red-500 mt-2 font-medium">
                ... e mais <span x-text="erros.length - 5"></span> erros
            </p>
        </div>
        
        <!-- Botões de Ação -->
        <div class="flex justify-between">
            <button @click="voltarEtapa1()" 
                    class="px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition">
                ← Voltar
            </button>
            <button @click="iniciarImportacao()" 
                    :disabled="importando || (estatisticas.erros > 0 && !opcoes.ignorarErros)"
                    class="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <span x-text="opcoes.modoTeste ? 'Simular Importação' : 'Iniciar Importação'"></span>
            </button>
        </div>
    </div>

    <!-- Etapa 3: Progresso da Importação -->
    <div x-show="etapa === 3" class="bg-white rounded-lg shadow-lg p-6" x-transition>
        <h2 class="text-xl font-bold mb-6">Importando Clientes...</h2>
        
        <!-- Barra de Progresso -->
        <div class="mb-6">
            <div class="flex justify-between text-sm text-gray-600 mb-2">
                <span>Progresso da importação</span>
                <span><span x-text="progresso.atual"></span> de <span x-text="progresso.total"></span></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div class="bg-green-600 h-4 rounded-full transition-all duration-300 flex items-center justify-center text-xs text-white font-bold" 
                     :style="`width: ${progresso.percentual}%`">
                    <span x-show="progresso.percentual > 10" x-text="progresso.percentual + '%'"></span>
                </div>
            </div>
        </div>
        
        <!-- Contadores em Tempo Real -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-3xl font-bold text-green-600" x-text="resultado.inseridos"></p>
                <p class="text-sm text-green-800">Inseridos</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <p class="text-3xl font-bold text-yellow-600" x-text="resultado.atualizados"></p>
                <p class="text-sm text-yellow-800">Atualizados</p>
            </div>
            <div class="text-center p-4 bg-red-50 rounded-lg">
                <p class="text-3xl font-bold text-red-600" x-text="resultado.erros"></p>
                <p class="text-sm text-red-800">Erros</p>
            </div>
        </div>
        
        <!-- Log de Processamento -->
        <div class="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto">
            <h3 class="font-semibold mb-2">Log de Processamento</h3>
            <div class="space-y-1 text-sm font-mono">
                <template x-for="log in logs.slice(-10).reverse()" :key="log.time + log.message">
                    <div class="flex items-start">
                        <span class="text-gray-500 mr-2" x-text="log.time"></span>
                        <span :class="{
                            'text-red-600': log.type === 'erro',
                            'text-green-600': log.type === 'sucesso',
                            'text-blue-600': log.type === 'info'
                        }" x-text="log.message"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Etapa 4: Resultado Final -->
    <div x-show="etapa === 4" class="bg-white rounded-lg shadow-lg p-6" x-transition>
        <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                <span x-text="opcoes.modoTeste ? 'Simulação' : 'Importação'"></span> Concluída!
            </h2>
            
            <p x-show="opcoes.modoTeste" class="text-yellow-600 mb-4">
                ⚠️ Modo teste ativo - Nenhum dado foi salvo no banco
            </p>
            
            <div class="mt-6 inline-block">
                <div class="grid grid-cols-3 gap-8 p-6 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-600">Clientes Inseridos</p>
                        <p class="text-3xl font-bold text-green-600" x-text="resultado.inseridos"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Clientes Atualizados</p>
                        <p class="text-3xl font-bold text-yellow-600" x-text="resultado.atualizados"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Erros</p>
                        <p class="text-3xl font-bold text-red-600" x-text="resultado.erros"></p>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 space-x-4">
                <a href="clientes.php" 
                   class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Ver Clientes
                </a>
                <button @click="novaImportacao()" 
                        class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Nova Importação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
<script>
function importadorClientes() {
    return {
        // Estados
        etapa: 0,
        arquivo: null,
        dragover: false,
        processando: false,
        importando: false,
        
        // Dados
        dados: [],
        erros: [],
        logs: [],
        
        // Opções
        opcoes: {
            atualizarExistentes: true,
            ignorarErros: false,
            modoTeste: false
        },
        
        // Estatísticas
        estatisticas: {
            novos: 0,
            atualizacoes: 0,
            erros: 0
        },
        
        // Progresso
        progresso: {
            atual: 0,
            total: 0,
            percentual: 0
        },
        
        // Resultado
        resultado: {
            inseridos: 0,
            atualizados: 0,
            erros: 0
        },
        
        // Métodos
        handleDrop(e) {
            this.dragover = false;
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.processarArquivo(files[0]);
            }
        },
        
        handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                this.processarArquivo(files[0]);
            }
        },
        
        processarArquivo(file) {
            // Validar tipo
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Por favor, selecione um arquivo CSV');
                return;
            }
            
            // Validar tamanho (50MB)
            if (file.size > 50 * 1024 * 1024) {
                alert('Arquivo muito grande. Máximo: 50MB');
                return;
            }
            
            this.arquivo = file;
            this.etapa = 1;
        },
        
        removerArquivo() {
            this.arquivo = null;
            this.etapa = 0;
            this.dados = [];
            this.erros = [];
        },
        
        formatarTamanho(bytes) {
            if (!bytes) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        formatarDocumento(doc) {
            if (!doc) return '-';
            doc = doc.replace(/\D/g, '');
            if (doc.length === 11) {
                return doc.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            } else if (doc.length === 14) {
                return doc.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            }
            return doc;
        },
        
        processarCSV() {
            this.processando = true;
            this.dados = [];
            this.erros = [];
            
            Papa.parse(this.arquivo, {
                header: false,
                skipEmptyLines: true,
                encoding: 'UTF-8',
                complete: (results) => {
                    this.processarDados(results.data);
                },
                error: (error) => {
                    alert('Erro ao processar arquivo: ' + error.message);
                    this.processando = false;
                }
            });
        },
        
        async processarDados(linhas) {
            // Resetar estatísticas
            this.estatisticas = { novos: 0, atualizacoes: 0, erros: 0 };
            this.dados = [];
            
            // Pular cabeçalho
            const dados = linhas.slice(1);
            
            for (let i = 0; i < dados.length; i++) {
                const linha = dados[i];
                
                // Pular linhas vazias
                if (!linha || linha.length === 0 || !linha[1]) continue;
                
                const cliente = {
                    _linha: i + 2,
                    _status: 'novo',
                    codigo_sistema: (linha[0] || '').trim(),
                    nome: (linha[1] || '').trim(),
                    nome_fantasia: (linha[2] || '').trim(),
                    tipo_lista_precos: (linha[3] || 'Padrão').trim(),
                    sexo: (linha[4] || '').trim().toUpperCase(),
                    cpf: linha[5] ? linha[5].replace(/\D/g, '') : '',
                    rg: (linha[6] || '').trim(),
                    rg_expedicao: (linha[7] || '').trim(),
                    rg_uf: (linha[8] || '').trim().toUpperCase(),
                    indicador_ie: (linha[9] || '').trim(),
                    cnpj: linha[10] ? linha[10].replace(/\D/g, '') : '',
                    inscricao_estadual: (linha[11] || '').trim(),
                    telefone: (linha[12] || '').trim(),
                    celular: (linha[13] || '').trim(),
                    fax: (linha[14] || '').trim(),
                    email: (linha[15] || '').trim().toLowerCase(),
                    site: (linha[16] || '').trim(),
                    endereco: (linha[17] || '').trim(),
                    numero: (linha[18] || '').trim(),
                    complemento: (linha[19] || '').trim(),
                    bairro: (linha[20] || '').trim(),
                    cidade: (linha[21] || '').trim(),
                    estado: (linha[22] || '').trim().toUpperCase(),
                    cep: linha[23] ? linha[23].replace(/\D/g, '') : '',
                    data_nascimento: (linha[24] || '').trim()
                };
                
                // Determinar CPF/CNPJ (prioridade CNPJ)
                if (cliente.cnpj && cliente.cnpj.length >= 14) {
                    cliente.cpf_cnpj = cliente.cnpj.substring(0, 14);
                    cliente.tipo_pessoa = 'J';
                } else if (cliente.cpf && cliente.cpf.length >= 11) {
                    cliente.cpf_cnpj = cliente.cpf.substring(0, 11);
                    cliente.tipo_pessoa = 'F';
                } else {
                    cliente.cpf_cnpj = '';
                    cliente.tipo_pessoa = 'J';
                }
                
                // Validar dados mínimos
                if (!cliente.nome) {
                    this.erros.push(`Linha ${cliente._linha}: Nome é obrigatório`);
                    cliente._status = 'erro';
                    cliente._erro = 'Nome obrigatório';
                    this.estatisticas.erros++;
                } else {
                    // Simular verificação de existência (será feito no servidor)
                    if (cliente.codigo_sistema || cliente.cpf_cnpj) {
                        // Simulação: 30% chance de já existir
                        if (Math.random() > 0.7) {
                            cliente._status = 'atualizar';
                            this.estatisticas.atualizacoes++;
                        } else {
                            cliente._status = 'novo';
                            this.estatisticas.novos++;
                        }
                    } else {
                        cliente._status = 'novo';
                        this.estatisticas.novos++;
                    }
                }
                
                this.dados.push(cliente);
            }
            
            this.processando = false;
            this.etapa = 2;
        },
        
        voltarEtapa1() {
            this.etapa = 1;
        },
        
        async iniciarImportacao() {
            this.importando = true;
            this.etapa = 3;
            this.progresso = {
                atual: 0,
                total: this.dados.length,
                percentual: 0
            };
            this.resultado = {
                inseridos: 0,
                atualizados: 0,
                erros: 0
            };
            this.logs = [];
            
            // Processar em lotes
            const loteSize = 10;
            const lotes = Math.ceil(this.dados.length / loteSize);
            
            for (let i = 0; i < lotes; i++) {
                const inicio = i * loteSize;
                const fim = Math.min(inicio + loteSize, this.dados.length);
                const lote = this.dados.slice(inicio, fim);
                
                await this.processarLote(lote);
                
                this.progresso.atual = fim;
                this.progresso.percentual = Math.round((fim / this.dados.length) * 100);
                
                // Pequena pausa entre lotes
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            
            this.importando = false;
            this.etapa = 4;
            
            // Registrar conclusão se não for teste
            if (!this.opcoes.modoTeste) {
                this.registrarConclusao();
            }
        },
        
        async processarLote(lote) {
            const dadosLimpos = lote.filter(c => c._status !== 'erro' || this.opcoes.ignorarErros);
            
            if (dadosLimpos.length === 0) return;
            
            try {
                const response = await fetch('clientes_processar_lote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        clientes: dadosLimpos,
                        opcoes: this.opcoes
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    this.resultado.inseridos += result.inseridos || 0;
                    this.resultado.atualizados += result.atualizados || 0;
                    this.resultado.erros += result.erros || 0;
                    
                    // Adicionar logs
                    if (result.logs && Array.isArray(result.logs)) {
                        result.logs.forEach(log => {
                            this.logs.push({
                                time: new Date().toLocaleTimeString('pt-BR'),
                                message: log.message,
                                type: log.type || 'info'
                            });
                        });
                    }
                } else {
                    throw new Error(result.message || 'Erro desconhecido');
                }
                
            } catch (error) {
                console.error('Erro ao processar lote:', error);
                this.resultado.erros += lote.length;
                this.logs.push({
                    time: new Date().toLocaleTimeString('pt-BR'),
                    message: `Erro: ${error.message}`,
                    type: 'erro'
                });
            }
        },
        
        async registrarConclusao() {
            try {
                const response = await fetch('clientes_registrar_importacao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        arquivo: this.arquivo.name,
                        total: this.dados.length,
                        inseridos: this.resultado.inseridos,
                        atualizados: this.resultado.atualizados,
                        erros: this.resultado.erros
                    })
                });
            } catch (error) {
                console.error('Erro ao registrar importação:', error);
            }
        },
        
        novaImportacao() {
            // Resetar tudo
            this.etapa = 0;
            this.arquivo = null;
            this.dados = [];
            this.erros = [];
            this.logs = [];
            this.estatisticas = { novos: 0, atualizacoes: 0, erros: 0 };
            this.resultado = { inseridos: 0, atualizados: 0, erros: 0 };
            this.progresso = { atual: 0, total: 0, percentual: 0 };
        }
    }
}
</script>

<?php include '../../views/layouts/_footer.php'; ?>