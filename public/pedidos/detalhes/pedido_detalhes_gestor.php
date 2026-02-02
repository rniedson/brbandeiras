<?php
require_once '../../../app/config.php';
require_once '../../../app/auth.php';
require_once '../../../app/functions.php';




requireLogin();
requireRole(['gestor']);

// Validar ID do pedido
$pedido_id = validarPedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID de pedido inválido';
    redirect('../../dashboard/dashboard.php');
}

// Buscar TODAS as informações do pedido para o gestor
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.nome as cliente_nome,
            c.tipo_pessoa,
            c.cpf_cnpj,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            c.endereco,
            c.numero as cliente_numero,
            c.complemento,
            c.bairro,
            c.cidade,
            c.estado,
            c.cep,
            v.nome as vendedor_nome,
            v.email as vendedor_email,
            v.telefone as vendedor_telefone,
            pa.arte_finalista_id,
            af.nome as arte_finalista_nome,
            (SELECT COUNT(*) FROM arte_versoes WHERE pedido_id = p.id) as total_versoes_arte,
            (SELECT MAX(versao) FROM arte_versoes WHERE pedido_id = p.id) as ultima_versao_arte,
            (SELECT COUNT(*) FROM pedidos WHERE cliente_id = p.cliente_id) as total_pedidos_cliente,
            (SELECT SUM(valor_final) FROM pedidos WHERE cliente_id = p.cliente_id AND status = 'entregue') as total_vendido_cliente
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios v ON p.vendedor_id = v.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios af ON pa.arte_finalista_id = af.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar pedido: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao carregar dados do pedido';
    redirect('../../dashboard/dashboard.php');
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
    redirect('../../dashboard/dashboard.php');
}

// Buscar todos os itens
try {
    $stmt = $pdo->prepare("
        SELECT pi.*, pc.id as produto_codigo, pc.nome as produto_nome
        FROM pedido_itens pi
        LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar itens do pedido: " . $e->getMessage());
    $itens = [];
}

// Buscar todas as versões de arte (uma única query)
try {
    $stmt = $pdo->prepare("
        SELECT av.*, u.nome as usuario_nome, u.perfil as usuario_perfil
        FROM arte_versoes av
        LEFT JOIN usuarios u ON av.usuario_id = u.id
        WHERE av.pedido_id = ?
        ORDER BY av.versao DESC
    ");
    $stmt->execute([$pedido_id]);
    $versoes_arte = $stmt->fetchAll();
    
    // Filtrar versões aprovadas do mesmo array
    $versoes_aprovadas = array_filter($versoes_arte, function($v) {
        return (isset($v['aprovada']) && $v['aprovada'] == true) ||
               (isset($v['status']) && $v['status'] === 'aprovada') ||
               (isset($v['aprovado']) && $v['aprovado'] == true);
    });
} catch (PDOException $e) {
    error_log("Erro ao buscar versões de arte: " . $e->getMessage());
    $versoes_arte = [];
    $versoes_aprovadas = [];
}

// Buscar arquivos originais (recebidos do cliente)
// Verificar se a coluna usuario_id existe na tabela pedido_arquivos usando função auxiliar
$hasUserIdColumn = verificarColunaExiste('pedido_arquivos', 'usuario_id');

try {
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare("
            SELECT pa.*, u.nome as usuario_nome
            FROM pedido_arquivos pa
            LEFT JOIN usuarios u ON pa.usuario_id = u.id
            WHERE pa.pedido_id = ?
            ORDER BY pa.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT pa.*, NULL as usuario_nome
            FROM pedido_arquivos pa
            WHERE pa.pedido_id = ?
            ORDER BY pa.created_at DESC
        ");
    }
    $stmt->execute([$pedido_id]);
    $arquivos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar arquivos: " . $e->getMessage());
    $arquivos = [];
}

// Buscar histórico completo
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, u.nome as usuario_nome, u.perfil as usuario_perfil
        FROM producao_status ps
        LEFT JOIN usuarios u ON ps.usuario_id = u.id
        WHERE ps.pedido_id = ?
        ORDER BY ps.created_at DESC
    ");
    $stmt->execute([$pedido_id]);
    $historico = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar histórico: " . $e->getMessage());
    $historico = [];
}

// Buscar logs do sistema relacionados
try {
    // Sanitizar número do pedido para uso em LIKE
    $numero_pedido = htmlspecialchars($pedido['numero'], ENT_QUOTES, 'UTF-8');
    $termo_busca = "%Pedido #{$numero_pedido}%";
    
    $stmt = $pdo->prepare("
        SELECT l.*, u.nome as usuario_nome
        FROM logs_sistema l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        WHERE l.detalhes LIKE ?
        ORDER BY l.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$termo_busca]);
    $logs_sistema = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar logs do sistema: " . $e->getMessage());
    $logs_sistema = [];
}

// Calcular métricas e prazos
$prazo = new DateTime($pedido['prazo_entrega']);
$hoje = new DateTime();
$diff = $hoje->diff($prazo);
$dias_restantes = $diff->invert ? -$diff->days : $diff->days;

// Determinar status e cores usando função centralizada
$status_info = getStatusInfo();
$status_atual = getStatusInfo($pedido['status']);

// Calcular progresso
$status_ordem = ['orcamento', 'aprovado', 'pagamento_50', 'producao', 'pagamento_100', 'pronto', 'entregue'];
$posicao_atual = array_search($pedido['status'], $status_ordem);
$progresso = $posicao_atual !== false ? (($posicao_atual + 1) / count($status_ordem)) * 100 : 0;

// Preparar dados para componentes
$versoes = $versoes_arte;

// Garantir que cada versão tenha os campos esperados para evitar warnings
foreach ($versoes as &$versao) {
    if (!isset($versao['comentarios'])) $versao['comentarios'] = '';
    if (!isset($versao['status'])) $versao['status'] = 'pendente';
    if (!isset($versao['observacoes'])) $versao['observacoes'] = '';
}
unset($versao); // Limpar referência

$pode_interagir = true;
$perfil_usuario = 'gestor';
$exibir_filtros = true;
$compacto = false;

$titulo = 'Pedido #' . $pedido['numero'];
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Pedido #' . $pedido['numero']]
];
include '../../../views/layouts/_header.php';
?>
</main>
<main class="flex-grow bg-gray-50 dark:bg-gray-900">

<!-- Estilos para impressão e layout -->
<style>
    /* Garantir altura fluída para as abas */
    .tab-content {
        width: 100%;
        min-height: auto;
        height: auto;
    }
    
    /* Altura fluída para conteúdo das abas */
    [x-show] {
        min-height: 0;
        transition: all 0.3s ease;
    }
    
    /* Para telas grandes, manter scroll apenas onde necessário */
    @media (min-width: 1024px) {
        .overflow-y-auto {
            max-height: 600px;
        }
    }
    
    /* Animação para tabs */
    .tab-button {
        position: relative;
        transition: all 0.3s ease;
    }
    
    .tab-button::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, #10b981, #059669);
        transition: width 0.3s ease;
    }
    
    .tab-button.active::after {
        width: 100%;
    }
    
    /* Efeito glassmorphism nas cards */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    /* Grid de imagens */
    .image-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .image-card {
        position: relative;
        padding-bottom: 100%;
        background: #f3f4f6;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    .image-card img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* Aspect ratio para miniaturas */
    .aspect-square {
        aspect-ratio: 1 / 1;
    }
    
    /* Remover scrollbar horizontal desnecessário */
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    
    /* Remover scroll horizontal em todas as situações */
    .overflow-x-auto {
        overflow-x: visible;
    }
    
    @media (max-width: 768px) {
        .overflow-x-auto {
            overflow-x: auto;
        }
    }
    
    /* Melhorias para impressão */
    @media print {
        /* Ocultar elementos não imprimíveis */
        header, nav, .no-print, 
        #header-principal, #footer-principal,
        .fixed, .breadcrumb,
        button, .btn-acoes {
            display: none !important;
        }
        
        /* Resetar alturas fixas na impressão */
        .max-h-96,
        .overflow-y-auto,
        .overflow-x-auto {
            max-height: none !important;
            overflow: visible !important;
        }
        
        /* Mostrar conteúdo completo */
        .tab-content {
            height: auto !important;
            min-height: auto !important;
        }
        
        /* Garantir que imagens sejam impressas */
        img {
            max-width: 100%;
            page-break-inside: avoid;
        }
        
        /* Evitar quebras de página em elementos importantes */
        .glass-card,
        table,
        .grid > div {
            page-break-inside: avoid;
        }
        
        /* Ajustar tamanho de fonte para impressão */
        body {
            font-size: 11pt;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
    }
</style>

<div class="max-w-7xl mx-auto p-4 lg:p-6 print-container">
    <!-- Header Compacto com Título e Ações -->
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
            Pedido #<?= htmlspecialchars($pedido['numero']) ?>
            <?php if ($pedido['urgente']): ?>
                <span class="px-2 py-1 bg-red-500 text-white text-xs rounded-full animate-pulse">URGENTE</span>
            <?php endif; ?>
            <span class="<?= $status_atual['cor'] ?> text-white px-3 py-1 rounded-full text-sm">
                <?= $status_atual['texto'] ?>
            </span>
        </h1>
        
        <div class="flex gap-2">
            <button onclick="imprimirAbaAtiva()" 
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Imprimir
            </button>
            <button onclick="abrirModalStatus()" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
                Alterar Status
            </button>
        </div>
    </div>

    <!-- Container Principal com Tabs Modernizadas -->
    <div class="bg-white rounded-xl shadow-xl" x-data="{ 
        activeTab: 'resumo',
        tabs: [
            { id: 'resumo', label: 'Visão Geral', icon: '📋' },
            { id: 'fiscal', label: 'Orçamento', icon: '💰' },
            { id: 'timeline', label: 'Arte', icon: '🎨', count: <?= count($versoes_arte) ?> },
            { id: 'producao', label: 'Produção', icon: '🏭' },
            { id: 'historico', label: 'Histórico & Logs', icon: '📊', count: <?= count($historico) + count($logs_sistema) ?> }
        ]
    }">
        <!-- Tabs Navigation Modernizada -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 border-b">
            <nav class="flex overflow-x-auto no-scrollbar">
                <template x-for="tab in tabs" :key="tab.id">
                    <button @click="activeTab = tab.id" 
                            :class="activeTab === tab.id ? 'bg-white text-green-600 shadow-sm border-b-3 border-green-500' : 'text-gray-600 hover:text-gray-800 hover:bg-white/50'"
                            class="tab-button px-6 py-4 font-medium text-sm transition-all duration-300 whitespace-nowrap flex items-center gap-2 relative">
                        <span x-text="tab.icon" class="text-lg"></span>
                        <span x-text="tab.label"></span>
                        <template x-if="tab.count">
                            <span class="ml-1 px-2 py-0.5 bg-gray-200 text-gray-700 text-xs rounded-full" 
                                  :class="activeTab === tab.id ? 'bg-green-100 text-green-700' : ''"
                                  x-text="tab.count"></span>
                        </template>
                    </button>
                </template>
            </nav>
        </div>
        
        <!-- Conteúdo das Tabs -->
        <div class="p-6">
            <!-- Tab: Visão Geral (Resumo + Arquivos) -->
            <div x-show="activeTab === 'resumo'" class="tab-content" :class="activeTab === 'resumo' ? 'active-print' : ''">
                <!-- Cards de Informações Principais -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
                    <div class="glass-card rounded-lg p-4 shadow-sm">
                        <p class="text-xs text-gray-500 uppercase mb-1">Cliente</p>
                        <p class="font-bold text-gray-900"><?= htmlspecialchars($pedido['cliente_nome'] ?? 'Não informado') ?></p>
                        <p class="text-xs text-gray-600"><?= formatarCpfCnpj($pedido['cpf_cnpj'] ?? '') ?></p>
                    </div>
                    
                    <div class="glass-card rounded-lg p-4 shadow-sm">
                        <p class="text-xs text-gray-500 uppercase mb-1">Vendedor</p>
                        <p class="font-bold text-gray-900"><?= htmlspecialchars($pedido['vendedor_nome'] ?? 'Não informado') ?></p>
                        <p class="text-xs text-gray-600"><?= htmlspecialchars($pedido['vendedor_telefone'] ?? '') ?></p>
                    </div>
                    
                    <div class="glass-card rounded-lg p-4 shadow-sm">
                        <p class="text-xs text-gray-500 uppercase mb-1">Prazo de Entrega</p>
                        <p class="font-bold text-gray-900"><?= formatarData($pedido['prazo_entrega']) ?></p>
                        <p class="text-xs <?= $dias_restantes < 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= $dias_restantes >= 0 ? "$dias_restantes dias restantes" : abs($dias_restantes) . " dias atrasado" ?>
                        </p>
                    </div>
                    
                    <div class="glass-card rounded-lg p-4 shadow-sm">
                        <p class="text-xs text-gray-500 uppercase mb-1">Valor Total</p>
                        <p class="font-bold text-gray-900 text-xl"><?= formatarMoeda($pedido['valor_final']) ?></p>
                        <div class="mt-2">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-green-400 to-green-600 h-2 rounded-full" 
                                     style="width: <?= $progresso ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-600 mt-1"><?= round($progresso) ?>% concluído</p>
                        </div>
                    </div>
                </div>
                
                <!-- Itens do Pedido (MOVIDO PARA CIMA) -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Itens do Pedido
                    </h3>
                    <div class="bg-gray-50 rounded-lg overflow-hidden">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                                <tr class="text-xs text-gray-600 uppercase">
                                    <th class="text-left p-3">Item</th>
                                    <th class="text-center p-3">Qtd</th>
                                    <th class="text-right p-3">Unit.</th>
                                    <th class="text-right p-3">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($itens as $item): ?>
                                <?php 
                                // Limpar descrição de possíveis "undefined" ou valores nulos
                                $descricao = $item['descricao'] ?? '';
                                $descricao = preg_replace('/^undefined\s*-\s*/i', '', $descricao);
                                $descricao = trim($descricao);
                                ?>
                                <tr class="hover:bg-white transition">
                                    <td class="p-3 text-sm">
                                        <div><?= htmlspecialchars($descricao) ?></div>
                                        <?php if (!empty($item['produto_codigo'])): ?>
                                            <div class="text-xs text-gray-500">Cód: <?= htmlspecialchars($item['produto_codigo']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['produto_nome'])): ?>
                                            <div class="text-xs text-gray-500">Produto: <?= htmlspecialchars($item['produto_nome']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-center text-sm font-medium"><?= number_format($item['quantidade'], 0) ?></td>
                                    <td class="p-3 text-right text-sm"><?= formatarMoeda($item['valor_unitario']) ?></td>
                                    <td class="p-3 text-right font-bold text-sm"><?= formatarMoeda($item['valor_total']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-100">
                                <tr>
                                    <td colspan="3" class="p-3 text-right font-bold">Total:</td>
                                    <td class="p-3 text-right font-bold text-lg text-green-600"><?= formatarMoeda($pedido['valor_final']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <?php if (!empty($pedido['observacoes'])): ?>
                    <?php $observacoes = processarObservacoesHTML($pedido['observacoes']); ?>
                    <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm font-semibold text-yellow-800 mb-1">Observações do Pedido:</p>
                        <div class="text-sm text-gray-700"><?= $observacoes ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Comparação de Arquivos: Recebidos vs Aprovados (AGORA EMBAIXO) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Arquivos Recebidos com Miniaturas -->
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Arquivos Recebidos do Cliente
                        </h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <?php if (empty($arquivos)): ?>
                                <div class="text-center py-8">
                                    <svg class="mx-auto w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="text-gray-500 text-sm">Nenhum arquivo recebido</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                // Separar arquivos de imagem, áudio e outros usando função auxiliar
                                $arquivos_separados = separarArquivosPorTipo($arquivos);
                                $arquivos_imagem = $arquivos_separados['imagens'];
                                $arquivos_audio = $arquivos_separados['audios'] ?? [];
                                $arquivos_outros = $arquivos_separados['outros'];
                                ?>
                                
                                <?php if (!empty($arquivos_imagem)): ?>
                                    <!-- Grid de Imagens com Miniaturas -->
                                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mb-4">
                                        <?php foreach ($arquivos_imagem as $arquivo): ?>
                                            <div class="group relative">
                                                <div class="aspect-square bg-gray-200 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition">
                                                    <img src="../../<?= htmlspecialchars($arquivo['caminho'] ?? '') ?>" 
                                                         alt="<?= htmlspecialchars($arquivo['nome_original'] ?? $arquivo['nome_arquivo'] ?? '') ?>"
                                                         class="w-full h-full object-cover group-hover:scale-105 transition cursor-pointer"
                                                         onclick="abrirModalImagem('../../<?= htmlspecialchars($arquivo['caminho'] ?? '') ?>', '<?= htmlspecialchars($arquivo['nome_original'] ?? $arquivo['nome_arquivo'] ?? '') ?>')"
                                                         onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gray-100\'><svg class=\'w-8 h-8 text-gray-400\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'></path></svg></div>'">
                                                </div>
                                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition rounded-lg flex items-center justify-center">
                                                    <a href="../../utils/download.php?tipo=pedido&id=<?= $arquivo['id'] ?>" 
                                                       class="opacity-0 group-hover:opacity-100 transition bg-white rounded-full p-2 hover:bg-gray-100 no-print"
                                                       onclick="event.stopPropagation()">
                                                        <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                                        </svg>
                                                    </a>
                                                </div>
                                                <!-- Tooltip com nome do arquivo -->
                                                <div class="absolute bottom-0 left-0 right-0 p-1 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition">
                                                    <p class="text-white text-xs truncate px-1"><?= htmlspecialchars($arquivo['nome_arquivo']) ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($arquivos_audio)): ?>
                                    <!-- Áudios do WhatsApp -->
                                    <div class="mb-4">
                                        <p class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/>
                                            </svg>
                                            Áudios (<?= count($arquivos_audio) ?>)
                                        </p>
                                        <div class="space-y-3">
                                            <?php foreach ($arquivos_audio as $arquivo): ?>
                                                <div class="bg-white rounded-lg p-3 shadow-sm border border-gray-100">
                                                    <div class="flex items-center gap-3 mb-2">
                                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.91-3c-.49 0-.9.36-.98.85C16.52 14.2 14.47 16 12 16s-4.52-1.8-4.93-4.15c-.08-.49-.49-.85-.98-.85-.61 0-1.09.54-1 1.14.49 3 2.89 5.35 5.91 5.78V20c0 .55.45 1 1 1s1-.45 1-1v-2.08c3.02-.43 5.42-2.78 5.91-5.78.1-.6-.39-1.14-1-1.14z"/>
                                                            </svg>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($arquivo['nome_original'] ?? $arquivo['nome_arquivo']) ?></p>
                                                            <p class="text-xs text-gray-500"><?= formatarDataHora($arquivo['created_at'] ?? $arquivo['uploaded_at'] ?? '') ?></p>
                                                        </div>
                                                        <a href="../../utils/download.php?tipo=pedido&id=<?= $arquivo['id'] ?>" 
                                                           class="text-green-600 hover:text-green-800 no-print p-1"
                                                           title="Baixar áudio">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                                            </svg>
                                                        </a>
                                                    </div>
                                                    <audio controls class="w-full h-10" preload="metadata">
                                                        <source src="../../<?= htmlspecialchars($arquivo['caminho'] ?? '') ?>" type="<?= htmlspecialchars($arquivo['tipo'] ?? 'audio/mpeg') ?>">
                                                        Seu navegador não suporta o elemento de áudio.
                                                    </audio>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($arquivos_outros)): ?>
                                    <!-- Lista de outros arquivos -->
                                    <div class="space-y-2">
                                        <p class="text-sm font-medium text-gray-700 mb-2">Outros arquivos:</p>
                                        <?php foreach ($arquivos_outros as $arquivo): ?>
                                            <div class="flex items-center justify-between p-3 bg-white rounded-lg hover:shadow-sm transition">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 bg-blue-100 rounded flex items-center justify-center">
                                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-sm text-gray-900"><?= htmlspecialchars($arquivo['nome_arquivo']) ?></p>
                                                        <p class="text-xs text-gray-500"><?= formatarDataHora($arquivo['created_at']) ?></p>
                                                    </div>
                                                </div>
                                                <a href="../../utils/download.php?tipo=pedido&id=<?= $arquivo['id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-800 no-print">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Artes Aprovadas com Miniaturas -->
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Artes Finais Aprovadas
                        </h3>
                        <div class="bg-green-50 rounded-lg p-4">
                            <?php if (empty($versoes_aprovadas)): ?>
                                <div class="text-center py-8">
                                    <svg class="mx-auto w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="text-gray-500 text-sm">Nenhuma arte aprovada ainda</p>
                                </div>
                            <?php else: ?>
                                <!-- Grid de artes aprovadas com miniaturas -->
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <?php foreach ($versoes_aprovadas as $versao): ?>
                                        <?php 
                                        $extensao = strtolower(pathinfo($versao['caminho_arquivo'] ?? '', PATHINFO_EXTENSION));
                                        $is_image = in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
                                        ?>
                                        
                                        <?php if ($is_image && !empty($versao['caminho_arquivo'])): ?>
                                            <div class="group relative">
                                                <div class="aspect-square bg-green-100 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition">
                                                    <img src="../../<?= htmlspecialchars($versao['caminho_arquivo']) ?>" 
                                                         alt="Versão <?= $versao['versao'] ?>"
                                                         class="w-full h-full object-cover group-hover:scale-105 transition cursor-pointer"
                                                         onclick="abrirModalImagem('../../<?= htmlspecialchars($versao['caminho_arquivo']) ?>', 'Versão <?= $versao['versao'] ?> - Aprovada')">
                                                </div>
                                                <div class="absolute top-2 left-2">
                                                    <span class="bg-green-600 text-white text-xs px-2 py-1 rounded-full font-bold">
                                                        V<?= $versao['versao'] ?>
                                                    </span>
                                                </div>
                                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition rounded-lg flex items-center justify-center">
                                                    <a href="../../utils/download.php?tipo=arte&id=<?= $versao['id'] ?>" 
                                                       class="opacity-0 group-hover:opacity-100 transition bg-white rounded-full p-2 hover:bg-gray-100 no-print"
                                                       onclick="event.stopPropagation()">
                                                        <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Versão sem imagem ou arquivo não é imagem -->
                                            <div class="p-3 bg-white rounded-lg border border-green-200">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="bg-green-600 text-white text-xs px-2 py-1 rounded-full font-bold">
                                                        V<?= $versao['versao'] ?>
                                                    </span>
                                                    <span class="text-xs text-green-700">Aprovada</span>
                                                </div>
                                                <p class="text-xs text-gray-600">Por <?= htmlspecialchars($versao['usuario_nome']) ?></p>
                                                <p class="text-xs text-gray-500"><?= formatarDataHora($versao['created_at']) ?></p>
                                                <?php if (!empty($versao['caminho_arquivo'])): ?>
                                                    <a href="../../utils/download.php?tipo=arte&id=<?= $versao['id'] ?>" 
                                                       class="text-green-600 hover:text-green-800 text-xs no-print">
                                                        Download
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Orçamento (Segunda aba) -->
            <div x-show="activeTab === 'fiscal'" class="tab-content" :class="activeTab === 'fiscal' ? 'active-print' : ''" x-cloak>
                <?php require_once '../../orcamentos/orcamento.php'; ?>
            </div>
            
            <!-- Tab: Timeline Arte (Terceira aba) -->
            <div x-show="activeTab === 'timeline'" class="tab-content" :class="activeTab === 'timeline' ? 'active-print' : ''" x-cloak>
                <?php require_once '../../../views/partials/_arte_timeline.php'; ?>
            </div>
            
            <!-- Tab: Produção (Quarta aba) -->
            <div x-show="activeTab === 'producao'" class="tab-content" :class="activeTab === 'producao' ? 'active-print' : ''" x-cloak>
                <?php require_once 'pedido_detalhes_producao.php'; ?>
            </div>
            
            <!-- Tab: Histórico & Logs (Combinados) -->
            <div x-show="activeTab === 'historico'" class="tab-content" :class="activeTab === 'historico' ? 'active-print' : ''" x-cloak>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Histórico de Status -->
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Histórico de Status
                        </h3>
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <?php foreach ($historico as $evento): ?>
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 text-sm">
                                        Status: <span class="font-bold"><?= ucfirst($evento['status']) ?></span>
                                    </p>
                                    <?php if ($evento['observacoes']): ?>
                                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($evento['observacoes']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= htmlspecialchars($evento['usuario_nome']) ?> • <?= formatarDataHora($evento['created_at']) ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Logs do Sistema -->
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Logs do Sistema
                        </h3>
                        <div class="bg-gray-50 rounded-lg overflow-hidden">
                            <div class="max-h-96 overflow-y-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gray-100 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($logs_sistema as $log): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-xs text-gray-900">
                                                <?= htmlspecialchars($log['acao']) ?>
                                                <?php if ($log['detalhes']): ?>
                                                    <div class="text-gray-500 truncate max-w-xs" title="<?= htmlspecialchars($log['detalhes']) ?>">
                                                        <?= htmlspecialchars($log['detalhes']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($log['usuario_nome']) ?></td>
                                            <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap"><?= formatarDataHora($log['created_at']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Alteração de Status -->
<div id="modalStatus" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 z-50 flex items-center justify-center no-print">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Alterar Status do Pedido</h3>
        <select id="novoStatus" class="w-full px-4 py-2 border rounded-lg mb-4">
            <option value="">Selecione o novo status</option>
            <?php foreach ($status_info as $key => $info): ?>
                <option value="<?= $key ?>" <?= $key === $pedido['status'] ? 'disabled' : '' ?>>
                    <?= $info['texto'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        <textarea id="observacaoStatus" 
                  class="w-full px-4 py-2 border rounded-lg mb-4" 
                  rows="3" 
                  placeholder="Observação (opcional)"></textarea>
        <div class="flex justify-end gap-2">
            <button onclick="fecharModalStatus()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                Cancelar
            </button>
            <button onclick="confirmarAlteracaoStatus()" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Confirmar
            </button>
        </div>
    </div>
</div>

<!-- Modal para visualizar imagem ampliada -->
<div id="modalImagem" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center no-print" onclick="fecharModalImagem()">
    <div class="relative max-w-6xl max-h-screen p-4">
        <button onclick="fecharModalImagem()" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <img id="imagemAmpliada" src="" alt="" class="max-w-full max-h-full object-contain">
        <p id="tituloImagem" class="text-white text-center mt-2"></p>
    </div>
</div>

<script>
// Função de impressão corrigida
function imprimirAbaAtiva() {
    // Preparar para impressão
    const body = document.body;
    const container = document.querySelector('.print-container');
    
    // Criar elemento temporário para impressão
    const printContent = document.createElement('div');
    printContent.className = 'print-wrapper';
    
    // Capturar título do pedido
    const titulo = 'Pedido #<?= htmlspecialchars($pedido['numero']) ?>';
    
    // Determinar qual aba está ativa
    let abaAtiva = 'resumo'; // default
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(btn => {
        if (btn.classList.contains('active') || 
            btn.classList.contains('bg-white') || 
            btn.classList.contains('text-green-600')) {
            // Extrair o nome da aba do evento onclick
            const onclickAttr = btn.getAttribute('@click');
            if (onclickAttr) {
                const match = onclickAttr.match(/activeTab = '([^']+)'/);
                if (match) {
                    abaAtiva = match[1];
                }
            }
        }
    });
    
    // Mapear títulos das abas
    const titulosAbas = {
        'resumo': 'Visão Geral',
        'fiscal': 'Orçamento', 
        'timeline': 'Arte',
        'producao': 'Produção',
        'historico': 'Histórico & Logs'
    };
    
    // Criar cabeçalho para impressão
    let htmlPrint = `
        <div style="padding: 20px;">
            <h1 style="font-size: 24px; font-weight: bold; margin-bottom: 10px;">
                ${titulo}
            </h1>
            <h2 style="font-size: 18px; color: #666; margin-bottom: 20px;">
                ${titulosAbas[abaAtiva] || 'Detalhes'}
            </h2>
            <hr style="margin-bottom: 20px;">
    `;
    
    // Capturar conteúdo da aba ativa
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        const xShow = content.getAttribute('x-show');
        if (xShow && xShow.includes(`activeTab === '${abaAtiva}'`)) {
            // Clonar o conteúdo para não afetar o original
            const clonedContent = content.cloneNode(true);
            
            // Remover elementos que não devem ser impressos
            clonedContent.querySelectorAll('.no-print').forEach(el => el.remove());
            clonedContent.querySelectorAll('button').forEach(el => el.remove());
            
            // Adicionar o conteúdo ao HTML de impressão
            htmlPrint += clonedContent.innerHTML;
        }
    });
    
    htmlPrint += '</div>';
    
    // Criar iframe oculto para impressão
    const printFrame = document.createElement('iframe');
    printFrame.style.position = 'absolute';
    printFrame.style.top = '-10000px';
    printFrame.style.left = '-10000px';
    document.body.appendChild(printFrame);
    
    // Escrever conteúdo no iframe
    const printDoc = printFrame.contentWindow.document;
    printDoc.open();
    printDoc.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${titulo}</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 1200px;
                    margin: 0 auto;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 20px 0;
                }
                th, td { 
                    padding: 8px; 
                    text-align: left; 
                    border-bottom: 1px solid #ddd;
                }
                th { 
                    background-color: #f5f5f5;
                    font-weight: bold;
                }
                .grid { 
                    display: grid; 
                    gap: 20px;
                }
                .grid-cols-2 {
                    grid-template-columns: repeat(2, 1fr);
                }
                .grid-cols-3 {
                    grid-template-columns: repeat(3, 1fr);
                }
                .grid-cols-4 {
                    grid-template-columns: repeat(4, 1fr);
                }
                .bg-gray-50 {
                    background-color: #f9f9f9;
                    padding: 15px;
                    border-radius: 8px;
                }
                .rounded-lg {
                    border-radius: 8px;
                }
                .shadow-sm {
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .font-bold {
                    font-weight: bold;
                }
                .text-sm {
                    font-size: 0.875rem;
                }
                .text-xs {
                    font-size: 0.75rem;
                }
                .mb-4 {
                    margin-bottom: 1rem;
                }
                .p-4 {
                    padding: 1rem;
                }
                img {
                    max-width: 200px;
                    height: auto;
                }
                @media print {
                    body { 
                        print-color-adjust: exact;
                        -webkit-print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            ${htmlPrint}
        </body>
        </html>
    `);
    printDoc.close();
    
    // Aguardar carregamento e imprimir
    setTimeout(() => {
        printFrame.contentWindow.focus();
        printFrame.contentWindow.print();
        
        // Remover iframe após impressão
        setTimeout(() => {
            document.body.removeChild(printFrame);
        }, 1000);
    }, 500);
}

// Funções para modal de status
function abrirModalStatus() {
    document.getElementById('modalStatus').classList.remove('hidden');
}

function fecharModalStatus() {
    document.getElementById('modalStatus').classList.add('hidden');
}

function confirmarAlteracaoStatus() {
    const novoStatus = document.getElementById('novoStatus').value;
    const observacao = document.getElementById('observacaoStatus').value;
    
    if (!novoStatus) {
        alert('Selecione um status');
        return;
    }
    
    if (confirm('Confirma a alteração de status?')) {
        window.location.href = `pedido_status.php?id=<?= $pedido_id ?>&status=${novoStatus}&obs=${encodeURIComponent(observacao)}`;
    }
}

// Funções para modal de imagem
function abrirModalImagem(src, titulo) {
    document.getElementById('imagemAmpliada').src = src;
    document.getElementById('tituloImagem').textContent = titulo;
    document.getElementById('modalImagem').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function fecharModalImagem() {
    document.getElementById('modalImagem').classList.add('hidden');
    document.body.style.overflow = '';
}

// Fechar modais com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalImagem();
        fecharModalStatus();
    }
});
</script>
</main> <!-- Fecha o main customizado -->
<main class="hidden"> <!-- Abre um main vazio para o footer fechar -->
<?php include '../../../views/layouts/_footer.php'; ?>