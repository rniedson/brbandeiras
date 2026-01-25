<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();
requireRole(['producao', 'gestor']);

try {
    // Buscar dados do kanban usando a view otimizada
    $stmt = $pdo->prepare("
        SELECT * FROM view_kanban_producao 
        ORDER BY 
            CASE 
                WHEN status = 'aprovado' THEN 1 
                WHEN status = 'producao' THEN 2 
                WHEN status = 'finalizado' THEN 3 
                ELSE 4 
            END,
            urgente DESC,
            prazo_entrega ASC
    ");
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar por status
    $kanban = [
        'aprovado' => [],
        'producao' => [],
        'finalizado' => []
    ];
    
    foreach ($pedidos as $pedido) {
        $status = $pedido['status'];
        if (isset($kanban[$status])) {
            $kanban[$status][] = $pedido;
        }
    }
    
    // Buscar operadores de produção
    $stmt = $pdo->prepare("
        SELECT id, nome 
        FROM usuarios 
        WHERE perfil IN ('producao', 'gestor') AND ativo = true
        ORDER BY nome
    ");
    $stmt->execute();
    $operadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas rápidas
    $stats = [
        'total' => count($pedidos),
        'aprovado' => count($kanban['aprovado']),
        'producao' => count($kanban['producao']),
        'finalizado' => count($kanban['finalizado']),
        'urgentes' => count(array_filter($pedidos, function($p) { 
            return $p['urgente'] && $p['status'] !== 'finalizado'; 
        })),
        'atrasados' => count(array_filter($pedidos, function($p) { 
            return $p['dias_ate_prazo'] < 0 && $p['status'] !== 'finalizado'; 
        }))
    ];
    
} catch (PDOException $e) {
    error_log("Erro no Kanban: " . $e->getMessage());
    $kanban = ['aprovado' => [], 'producao' => [], 'finalizado' => []];
    $operadores = [];
    $stats = ['total' => 0, 'aprovado' => 0, 'producao' => 0, 'finalizado' => 0, 'urgentes' => 0, 'atrasados' => 0];
}

$titulo = 'Produção - Kanban';
$breadcrumb = [
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'Produção']
];

include '../../views/layouts/_header.php';
?>

<div class="min-h-screen bg-gray-100 flex flex-col" x-data="kanbanBoard()">
    <!-- Header com Estatísticas -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Kanban de Produção</h1>
                    <p class="text-gray-600">Arraste e solte para mover os pedidos entre as etapas</p>
                </div>
                
                <!-- Relógio Digital -->
                <div class="text-right">
                    <div class="text-2xl font-mono font-bold text-gray-900" id="relogio">
                        --:--:--
                    </div>
                    <div class="text-sm text-gray-600" id="data">
                        --/--/----
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= $stats['total'] ?></div>
                    <div class="text-sm text-blue-800">Total OSs</div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600"><?= $stats['aprovado'] ?></div>
                    <div class="text-sm text-yellow-800">Na Fila</div>
                </div>
                <div class="bg-orange-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-orange-600"><?= $stats['producao'] ?></div>
                    <div class="text-sm text-orange-800">Em Produção</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600"><?= $stats['finalizado'] ?></div>
                    <div class="text-sm text-green-800">Finalizados</div>
                </div>
                <div class="bg-red-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-red-600"><?= $stats['urgentes'] ?></div>
                    <div class="text-sm text-red-800">Urgentes</div>
                </div>
                <div class="bg-purple-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600"><?= $stats['atrasados'] ?></div>
                    <div class="text-sm text-purple-800">Atrasados</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="flex justify-between items-center mt-4">
                <div class="flex gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" x-model="filtroUrgente" class="mr-2">
                        <span class="text-sm text-gray-700">Apenas urgentes</span>
                    </label>
                    <select x-model="filtroOperador" class="text-sm border rounded px-3 py-1">
                        <option value="">Todos os operadores</option>
                        <?php foreach ($operadores as $op): ?>
                        <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Legenda -->
                <div class="flex gap-4 text-sm">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                        <span>Urgente</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                        <span>Próximo do prazo</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-purple-500 rounded-full mr-2"></span>
                        <span>Atrasado</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kanban Board -->
    <div class="flex-1 overflow-x-auto bg-gray-50 p-4">
        <div class="flex gap-6 min-w-max h-full">
            
            <!-- Coluna Aprovado / Fila -->
            <div class="w-80 bg-white rounded-lg shadow-sm border-t-4 border-yellow-500">
                <div class="p-4 bg-gradient-to-r from-yellow-600 to-yellow-700 text-white rounded-t-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <h3 class="font-bold text-lg">Fila de Produção</h3>
                        </div>
                        <span class="bg-yellow-800 px-2 py-1 rounded text-sm font-bold">
                            <?= $stats['aprovado'] ?>
                        </span>
                    </div>
                    <p class="text-sm opacity-90 mt-1">Pedidos aprovados aguardando produção</p>
                </div>
                
                <div class="p-2 overflow-y-auto"
                     @drop="drop($event, 'aprovado')" 
                     @dragover.prevent
                     @dragenter.prevent
                     style="max-height: calc(100vh - 300px);">
                     
                    <template x-for="pedido in filtrarPedidos(pedidosAprovado)" :key="pedido.id">
                        <div class="mb-3" 
                             draggable="true"
                             @dragstart="dragStart($event, pedido)"
                             @click="abrirDetalhes(pedido)">
                            <!-- Card será renderizado por template -->
                            <div class="bg-white border rounded-lg p-3 cursor-pointer hover:shadow-md transition-shadow"
                                 :class="{'border-red-300 bg-red-50': pedido.urgente, 'border-purple-300 bg-purple-50': pedido.dias_ate_prazo < 0}">
                                
                                <!-- Cabeçalho do card -->
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-bold text-sm text-gray-800" x-text="`OS #${pedido.numero}`"></span>
                                    <div class="flex gap-1">
                                        <span x-show="pedido.urgente" class="text-red-500">⚡</span>
                                        <span x-show="pedido.dias_ate_prazo < 0" class="text-purple-500">⏰</span>
                                    </div>
                                </div>
                                
                                <!-- Cliente -->
                                <p class="text-sm text-gray-700 mb-2 font-medium" x-text="pedido.cliente_nome"></p>
                                
                                <!-- Produtos -->
                                <p class="text-xs text-gray-600 mb-3" x-text="pedido.produtos"></p>
                                
                                <!-- Prazo -->
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-500">Prazo:</span>
                                    <span x-text="formatarData(pedido.prazo_entrega)"
                                          :class="pedido.dias_ate_prazo < 0 ? 'text-red-600 font-bold' : pedido.dias_ate_prazo <= 2 ? 'text-yellow-600 font-bold' : 'text-gray-700'"></span>
                                </div>
                                
                                <!-- Observações se houver -->
                                <div x-show="pedido.observacoes" class="mt-2 pt-2 border-t border-gray-200">
                                    <p class="text-xs text-gray-600" x-text="pedido.observacoes"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <!-- Coluna Em Produção -->
            <div class="w-80 bg-white rounded-lg shadow-sm border-t-4 border-orange-500">
                <div class="p-4 bg-gradient-to-r from-orange-600 to-orange-700 text-white rounded-t-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                            </svg>
                            <h3 class="font-bold text-lg">Em Produção</h3>
                        </div>
                        <span class="bg-orange-800 px-2 py-1 rounded text-sm font-bold">
                            <?= $stats['producao'] ?>
                        </span>
                    </div>
                    <p class="text-sm opacity-90 mt-1">Pedidos sendo produzidos atualmente</p>
                </div>
                
                <div class="p-2 overflow-y-auto"
                     @drop="drop($event, 'producao')" 
                     @dragover.prevent
                     @dragenter.prevent
                     style="max-height: calc(100vh - 300px);">
                     
                    <template x-for="pedido in filtrarPedidos(pedidosProducao)" :key="pedido.id">
                        <div class="mb-3" 
                             draggable="true"
                             @dragstart="dragStart($event, pedido)"
                             @click="abrirDetalhes(pedido)">
                            
                            <div class="bg-white border rounded-lg p-3 cursor-pointer hover:shadow-md transition-shadow"
                                 :class="{'border-red-300 bg-red-50': pedido.urgente}">
                                
                                <!-- Cabeçalho do card -->
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-bold text-sm text-gray-800" x-text="`OS #${pedido.numero}`"></span>
                                    <div class="flex gap-1">
                                        <span x-show="pedido.urgente" class="text-red-500">⚡</span>
                                    </div>
                                </div>
                                
                                <!-- Cliente -->
                                <p class="text-sm text-gray-700 mb-2 font-medium" x-text="pedido.cliente_nome"></p>
                                
                                <!-- Produtos -->
                                <p class="text-xs text-gray-600 mb-2" x-text="pedido.produtos"></p>
                                
                                <!-- Responsável -->
                                <div x-show="pedido.responsavel_nome" class="flex items-center gap-2 mb-2">
                                    <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                    </svg>
                                    <span class="text-xs text-gray-600" x-text="pedido.responsavel_nome"></span>
                                </div>
                                
                                <!-- Tempo de produção -->
                                <div x-show="pedido.iniciado_producao_em" class="flex items-center gap-2 mb-3">
                                    <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                                    </svg>
                                    <span class="text-xs text-orange-600 font-medium" 
                                          x-text="calcularTempoProducao(pedido.iniciado_producao_em)"></span>
                                </div>
                                
                                <!-- Progress do Checklist -->
                                <div class="mb-2">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span>Progresso</span>
                                        <span x-text="`${pedido.progresso_checklist}/4`"></span>
                                    </div>
                                    <div class="bg-gray-200 rounded-full h-2 overflow-hidden">
                                        <div class="bg-gradient-to-r from-orange-400 to-green-500 h-full transition-all duration-300"
                                             :style="`width: ${(pedido.progresso_checklist / 4) * 100}%`"></div>
                                    </div>
                                </div>
                                
                                <!-- Prazo -->
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-500">Prazo:</span>
                                    <span x-text="formatarData(pedido.prazo_entrega)"
                                          :class="pedido.dias_ate_prazo < 0 ? 'text-red-600 font-bold' : pedido.dias_ate_prazo <= 2 ? 'text-yellow-600 font-bold' : 'text-gray-700'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <!-- Coluna Finalizado -->
            <div class="w-80 bg-white rounded-lg shadow-sm border-t-4 border-green-500">
                <div class="p-4 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-t-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                            </svg>
                            <h3 class="font-bold text-lg">Finalizados</h3>
                        </div>
                        <span class="bg-green-800 px-2 py-1 rounded text-sm font-bold">
                            <?= $stats['finalizado'] ?>
                        </span>
                    </div>
                    <p class="text-sm opacity-90 mt-1">Pedidos concluídos e prontos</p>
                </div>
                
                <div class="p-2 overflow-y-auto"
                     @drop="drop($event, 'finalizado')" 
                     @dragover.prevent
                     @dragenter.prevent
                     style="max-height: calc(100vh - 300px);">
                     
                    <template x-for="pedido in filtrarPedidos(pedidosFinalizado)" :key="pedido.id">
                        <div class="mb-3" @click="abrirDetalhes(pedido)">
                            <div class="bg-white border border-green-200 rounded-lg p-3 cursor-pointer hover:shadow-md transition-shadow bg-green-50">
                                
                                <!-- Cabeçalho do card -->
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-bold text-sm text-gray-800" x-text="`OS #${pedido.numero}`"></span>
                                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                                    </svg>
                                </div>
                                
                                <!-- Cliente -->
                                <p class="text-sm text-gray-700 mb-2 font-medium" x-text="pedido.cliente_nome"></p>
                                
                                <!-- Produtos -->
                                <p class="text-xs text-gray-600 mb-2" x-text="pedido.produtos"></p>
                                
                                <!-- Responsável e tempo -->
                                <div class="grid grid-cols-1 gap-1 text-xs">
                                    <div x-show="pedido.responsavel_nome" class="flex items-center gap-1">
                                        <span class="text-gray-500">Por:</span>
                                        <span class="text-gray-700" x-text="pedido.responsavel_nome"></span>
                                    </div>
                                    <div x-show="pedido.tempo_producao_minutos" class="flex items-center gap-1">
                                        <span class="text-gray-500">Tempo:</span>
                                        <span class="text-gray-700" x-text="formatarTempo(pedido.tempo_producao_minutos)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes da OS -->
    <div x-show="modalDetalhes" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="modalDetalhes = false">
        <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden"
             @click.stop>
            
            <!-- Header do Modal -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-bold" x-text="`OS #${pedidoSelecionado?.numero || ''}`"></h3>
                        <p class="opacity-90" x-text="pedidoSelecionado?.cliente_nome || ''"></p>
                    </div>
                    <button @click="modalDetalhes = false" 
                            class="text-white hover:text-gray-300">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Corpo do Modal -->
            <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                <template x-if="pedidoSelecionado">
                    <div class="space-y-6">
                        
                        <!-- Informações Gerais -->
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Produtos</label>
                                    <p class="text-gray-900" x-text="pedidoSelecionado.produtos"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Vendedor</label>
                                    <p class="text-gray-900" x-text="pedidoSelecionado.vendedor_nome"></p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Prazo de Entrega</label>
                                    <p class="font-medium" 
                                       x-text="formatarData(pedidoSelecionado.prazo_entrega)"
                                       :class="pedidoSelecionado.dias_ate_prazo < 0 ? 'text-red-600' : pedidoSelecionado.dias_ate_prazo <= 2 ? 'text-yellow-600' : 'text-green-600'">
                                    </p>
                                </div>
                                <div x-show="pedidoSelecionado.status === 'producao'">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Responsável</label>
                                    <select x-model="pedidoSelecionado.responsavel_producao_id"
                                            @change="atualizarResponsavel(pedidoSelecionado.id, $event.target.value)"
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="">Selecionar operador</option>
                                        <?php foreach ($operadores as $op): ?>
                                        <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Checklist de Produção -->
                        <div x-show="pedidoSelecionado.status === 'producao'" class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-3">Checklist de Produção</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           :checked="pedidoSelecionado.corte"
                                           @change="atualizarChecklist(pedidoSelecionado.id, 'corte')"
                                           class="mr-3 h-4 w-4">
                                    <span>Corte</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           :checked="pedidoSelecionado.costura"
                                           @change="atualizarChecklist(pedidoSelecionado.id, 'costura')"
                                           class="mr-3 h-4 w-4">
                                    <span>Costura</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           :checked="pedidoSelecionado.acabamento"
                                           @change="atualizarChecklist(pedidoSelecionado.id, 'acabamento')"
                                           class="mr-3 h-4 w-4">
                                    <span>Acabamento</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           :checked="pedidoSelecionado.qualidade"
                                           @change="atualizarChecklist(pedidoSelecionado.id, 'qualidade')"
                                           class="mr-3 h-4 w-4">
                                    <span>Controle de Qualidade</span>
                                </label>
                            </div>
                            
                            <!-- Barra de Progresso -->
                            <div class="mt-4">
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Progresso</span>
                                    <span x-text="`${pedidoSelecionado.progresso_checklist}/4 itens`"></span>
                                </div>
                                <div class="bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="bg-gradient-to-r from-orange-400 to-green-500 h-full transition-all duration-300"
                                         :style="`width: ${(pedidoSelecionado.progresso_checklist / 4) * 100}%`"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observações -->
                        <div x-show="pedidoSelecionado.observacoes">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                            <p class="bg-gray-50 p-3 rounded border text-gray-900" x-text="pedidoSelecionado.observacoes"></p>
                        </div>
                        
                        <!-- Observações da Produção -->
                        <div x-show="pedidoSelecionado.status === 'producao'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Observações da Produção</label>
                            <textarea x-model="pedidoSelecionado.observacoes_producao"
                                      @blur="atualizarObservacoesProducao(pedidoSelecionado.id, $event.target.value)"
                                      rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                      placeholder="Adicione observações sobre a produção..."></textarea>
                        </div>
                        
                        <!-- Tempo de Produção -->
                        <div x-show="pedidoSelecionado.tempo_producao_minutos" class="text-center">
                            <div class="inline-flex items-center gap-2 bg-blue-100 px-4 py-2 rounded-lg">
                                <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                                </svg>
                                <span class="text-blue-800 font-medium">
                                    Tempo total de produção: 
                                    <span x-text="formatarTempo(pedidoSelecionado.tempo_producao_minutos)"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div x-show="loading" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center gap-3">
            <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-700">Processando...</span>
        </div>
    </div>
</div>

<script>
function kanbanBoard() {
    return {
        // Estado
        pedidosAprovado: <?= json_encode($kanban['aprovado']) ?>,
        pedidosProducao: <?= json_encode($kanban['producao']) ?>,
        pedidosFinalizado: <?= json_encode($kanban['finalizado']) ?>,
        pedidoSelecionado: null,
        modalDetalhes: false,
        loading: false,
        
        // Filtros
        filtroUrgente: false,
        filtroOperador: '',
        
        // Drag and Drop
        pedidoArrastado: null,
        
        init() {
            // Atualizar relógio
            this.atualizarRelogio();
            setInterval(() => this.atualizarRelogio(), 1000);
        },
        
        atualizarRelogio() {
            const now = new Date();
            
            document.getElementById('relogio').textContent = 
                now.toLocaleTimeString('pt-BR', { 
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
            document.getElementById('data').textContent = 
                now.toLocaleDateString('pt-BR', { 
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
        },
        
        filtrarPedidos(pedidos) {
            return pedidos.filter(pedido => {
                if (this.filtroUrgente && !pedido.urgente) return false;
                if (this.filtroOperador && pedido.responsavel_producao_id != this.filtroOperador) return false;
                return true;
            });
        },
        
        dragStart(event, pedido) {
            this.pedidoArrastado = pedido;
            event.dataTransfer.effectAllowed = 'move';
        },
        
        async drop(event, novoStatus) {
            event.preventDefault();
            if (!this.pedidoArrastado || this.pedidoArrastado.status === novoStatus) return;
            
            // Validações de transição
            const statusAtual = this.pedidoArrastado.status;
            
            if (statusAtual === 'aprovado' && novoStatus === 'finalizado') {
                alert('Não é possível mover diretamente da fila para finalizado. Passe pela produção primeiro.');
                return;
            }
            
            if (statusAtual === 'producao' && novoStatus === 'finalizado') {
                // Verificar checklist
                if (this.pedidoArrastado.progresso_checklist < 4) {
                    alert('Complete todo o checklist antes de finalizar a produção!');
                    return;
                }
            }
            
            this.loading = true;
            
            try {
                const response = await fetch('producao_atualizar_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pedido_id: this.pedidoArrastado.id,
                        novo_status: novoStatus,
                        status_anterior: statusAtual
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Atualizar dados localmente
                    this.moverPedido(this.pedidoArrastado.id, statusAtual, novoStatus, result.data);
                    
                    // Mostrar feedback
                    this.mostrarNotificacao('Status atualizado com sucesso!', 'success');
                } else {
                    alert(result.message || 'Erro ao atualizar status');
                }
                
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão. Tente novamente.');
            } finally {
                this.loading = false;
                this.pedidoArrastado = null;
            }
        },
        
        moverPedido(pedidoId, statusAnterior, novoStatus, dadosAtualizados) {
            // Encontrar e remover do status anterior
            const arrays = {
                'aprovado': this.pedidosAprovado,
                'producao': this.pedidosProducao,
                'finalizado': this.pedidosFinalizado
            };
            
            const arrayAnterior = arrays[statusAnterior];
            const arrayNovo = arrays[novoStatus];
            
            const index = arrayAnterior.findIndex(p => p.id == pedidoId);
            if (index >= 0) {
                const pedido = arrayAnterior.splice(index, 1)[0];
                
                // Atualizar dados do pedido
                Object.assign(pedido, dadosAtualizados);
                pedido.status = novoStatus;
                
                // Adicionar ao novo array
                arrayNovo.unshift(pedido);
            }
        },
        
        abrirDetalhes(pedido) {
            this.pedidoSelecionado = {...pedido};
            this.modalDetalhes = true;
        },
        
        async atualizarChecklist(pedidoId, campo) {
            this.loading = true;
            
            try {
                const response = await fetch('producao_atualizar_checklist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pedido_id: pedidoId,
                        campo: campo
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Atualizar dados local
                    this.pedidoSelecionado[campo] = !this.pedidoSelecionado[campo];
                    this.pedidoSelecionado.progresso_checklist = result.progresso;
                    
                    // Atualizar também no array de produção
                    const pedido = this.pedidosProducao.find(p => p.id == pedidoId);
                    if (pedido) {
                        pedido[campo] = this.pedidoSelecionado[campo];
                        pedido.progresso_checklist = result.progresso;
                    }
                } else {
                    alert(result.message || 'Erro ao atualizar checklist');
                }
                
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão. Tente novamente.');
            } finally {
                this.loading = false;
            }
        },
        
        async atualizarResponsavel(pedidoId, responsavelId) {
            this.loading = true;
            
            try {
                const response = await fetch('producao_atualizar_responsavel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pedido_id: pedidoId,
                        responsavel_id: responsavelId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Atualizar dados local
                    this.pedidoSelecionado.responsavel_nome = result.responsavel_nome;
                    
                    // Atualizar também no array de produção
                    const pedido = this.pedidosProducao.find(p => p.id == pedidoId);
                    if (pedido) {
                        pedido.responsavel_producao_id = responsavelId;
                        pedido.responsavel_nome = result.responsavel_nome;
                    }
                } else {
                    alert(result.message || 'Erro ao atualizar responsável');
                }
                
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão. Tente novamente.');
            } finally {
                this.loading = false;
            }
        },
        
        async atualizarObservacoesProducao(pedidoId, observacoes) {
            try {
                const response = await fetch('producao_atualizar_observacoes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pedido_id: pedidoId,
                        observacoes: observacoes
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    alert(result.message || 'Erro ao salvar observações');
                }
                
            } catch (error) {
                console.error('Erro:', error);
            }
        },
        
        formatarData(data) {
            if (!data) return '';
            return new Date(data).toLocaleDateString('pt-BR');
        },
        
        formatarTempo(minutos) {
            if (!minutos) return '';
            const horas = Math.floor(minutos / 60);
            const min = Math.round(minutos % 60);
            return `${horas}h${min.toString().padStart(2, '0')}min`;
        },
        
        calcularTempoProducao(iniciadoEm) {
            if (!iniciadoEm) return '';
            
            const inicio = new Date(iniciadoEm);
            const agora = new Date();
            const diff = agora - inicio;
            
            const horas = Math.floor(diff / (1000 * 60 * 60));
            const minutos = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            return `${horas}h${minutos.toString().padStart(2, '0')}min`;
        },
        
        mostrarNotificacao(mensagem, tipo = 'info') {
            // Implementar sistema de notificações se necessário
            console.log(`${tipo.toUpperCase()}: ${mensagem}`);
        }
    }
}
</script>

<style>
[x-cloak] { 
    display: none !important; 
}

/* Estilos para drag and drop */
.kanban-column {
    min-height: 200px;
    transition: background-color 0.2s ease;
}

.kanban-column.drag-over {
    background-color: rgba(59, 130, 246, 0.1);
}

.kanban-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kanban-card:hover {
    transform: translateY(-2px);
}

.kanban-card.dragging {
    opacity: 0.5;
    transform: rotate(5deg);
}

/* Scrollbar personalizada */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>

<?php include '../../views/layouts/_footer.php'; ?>