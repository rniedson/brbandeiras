<?php
require_once '../../../app/config.php';
require_once '../../../app/auth.php';
require_once '../../../app/functions.php';

requireLogin();
requireRole(['vendedor', 'gestor']);

// Validar ID do pedido
$pedido_id = validarPedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID de pedido inválido';
    redirect('../pedidos.php');
}

// Buscar dados completos do pedido para informações fiscais
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
            c.numero,
            c.complemento,
            c.bairro,
            c.cidade,
            c.estado,
            c.cep,
            v.nome as vendedor_nome,
            v.email as vendedor_email,
            (SELECT COUNT(*) FROM pedidos WHERE cliente_id = p.cliente_id) as total_pedidos_cliente,
            (SELECT SUM(valor_final) FROM pedidos WHERE cliente_id = p.cliente_id AND status = 'entregue') as total_vendido
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios v ON p.vendedor_id = v.id
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
    redirect('../pedidos.php');
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
    redirect('../pedidos.php');
}

// Verificar permissão (vendedor só vê seus próprios pedidos)
if ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] != $_SESSION['user_id']) {
    $_SESSION['erro'] = 'Você não tem permissão para visualizar este pedido';
    redirect('../pedidos.php');
}

// Buscar itens do pedido
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

// Determinar status do pagamento
$status_pagamento = [
    'orcamento' => ['texto' => 'Aguardando Aprovação', 'cor' => 'bg-gray-600'],
    'aprovado' => ['texto' => 'Aguardando Entrada (50%)', 'cor' => 'bg-yellow-600'],
    'pagamento_50' => ['texto' => 'Entrada Recebida (50%)', 'cor' => 'bg-blue-600'],
    'producao' => ['texto' => 'Em Produção - 50% Pago', 'cor' => 'bg-orange-600'],
    'pagamento_100' => ['texto' => 'Pagamento Total Recebido', 'cor' => 'bg-green-600'],
    'pronto' => ['texto' => 'Pago - Pronto para Entrega', 'cor' => 'bg-green-700'],
    'entregue' => ['texto' => 'Entregue e Pago', 'cor' => 'bg-green-800'],
    'cancelado' => ['texto' => 'Cancelado', 'cor' => 'bg-red-600']
];

$pagamento_info = $status_pagamento[$pedido['status']] ?? ['texto' => 'Status Desconhecido', 'cor' => 'bg-gray-500'];

$titulo = 'Dados Fiscais - Pedido #' . $pedido['numero'];
$breadcrumb = [
    ['label' => 'Vendas', 'url' => 'pedidos.php'],
    ['label' => 'Pedido #' . $pedido['numero']]
];
include '../../../views/layouts/_header.php';
?>

<div class="max-w-6xl mx-auto p-4 lg:p-6">
    <!-- Cabeçalho -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg mb-6 p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="text-white">
                <h1 class="text-2xl lg:text-3xl font-bold mb-2">
                    Dados Fiscais - Pedido #<?= htmlspecialchars($pedido['numero']) ?>
                </h1>
                <div class="flex flex-wrap gap-4 text-blue-100 text-sm">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Criado em: <?= formatarData($pedido['created_at']) ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Prazo: <?= formatarData($pedido['prazo_entrega']) ?>
                    </span>
                </div>
            </div>
            <div>
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold <?= $pagamento_info['cor'] ?> text-white">
                    <?= $pagamento_info['texto'] ?>
                </span>
            </div>
        </div>
    </div>

    <?php if (empty($pedido['cpf_cnpj']) || empty($pedido['cliente_nome'])): ?>
    <!-- Mensagem de Vazio -->
    <div class="bg-white rounded-lg shadow-lg p-12 text-center mb-6">
        <svg class="w-24 h-24 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="text-xl font-semibold text-gray-800 mb-2">Informações fiscais ainda não estão disponíveis</h3>
        <p class="text-gray-500 mb-6">Os dados fiscais serão exibidos após o cadastro completo do cliente.</p>
        <a href="cliente_editar.php?id=<?= $pedido['cliente_id'] ?>" 
           class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Completar Cadastro do Cliente
        </a>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Dados do Cliente para NF -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Dados do Cliente para Emissão de NF
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Coluna 1 -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">
                                <?= $pedido['tipo_pessoa'] === 'J' ? 'Razão Social' : 'Nome Completo' ?>
                            </label>
                            <p class="font-semibold text-gray-900 text-lg">
                                <?= htmlspecialchars($pedido['cliente_nome'] ?: 'Não informado') ?>
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">
                                <?= $pedido['tipo_pessoa'] === 'J' ? 'CNPJ' : 'CPF' ?>
                            </label>
                            <p class="font-mono text-gray-900 text-lg">
                                <?= htmlspecialchars($pedido['cpf_cnpj'] ?: 'Não informado') ?>
                            </p>
                            <?php if ($pedido['cpf_cnpj']): ?>
                            <button onclick="copiarTexto('<?= $pedido['cpf_cnpj'] ?>')" 
                                    class="mt-1 text-xs text-blue-600 hover:text-blue-800">
                                📋 Copiar
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">Telefone</label>
                            <p class="font-semibold text-gray-900">
                                <?= htmlspecialchars($pedido['cliente_telefone'] ?: 'Não informado') ?>
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">E-mail</label>
                            <p class="text-gray-900">
                                <?= htmlspecialchars($pedido['cliente_email'] ?: 'Não informado') ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Coluna 2 -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">Endereço</label>
                            <p class="text-gray-900">
                                <?php if ($pedido['endereco']): ?>
                                    <?= htmlspecialchars($pedido['endereco']) ?>
                                    <?php if ($pedido['numero']): ?>, <?= htmlspecialchars($pedido['numero']) ?><?php endif; ?>
                                    <?php if ($pedido['complemento']): ?> - <?= htmlspecialchars($pedido['complemento']) ?><?php endif; ?>
                                <?php else: ?>
                                    Não informado
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">Bairro</label>
                            <p class="text-gray-900">
                                <?= htmlspecialchars($pedido['bairro'] ?: 'Não informado') ?>
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">Cidade/UF</label>
                            <p class="text-gray-900">
                                <?php if ($pedido['cidade'] && $pedido['estado']): ?>
                                    <?= htmlspecialchars($pedido['cidade']) ?>/<?= htmlspecialchars($pedido['estado']) ?>
                                <?php else: ?>
                                    Não informado
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">CEP</label>
                            <p class="font-mono text-gray-900">
                                <?= htmlspecialchars($pedido['cep'] ?: 'Não informado') ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Histórico do Cliente -->
                <div class="mt-6 pt-6 border-t">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total de Pedidos</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $pedido['total_pedidos_cliente'] ?></p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Vendido</p>
                            <p class="text-2xl font-bold text-green-600"><?= formatarMoeda($pedido['total_vendido'] ?: 0) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dados do Pedido -->
        <div class="space-y-6">
            <!-- Resumo Financeiro -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Resumo Financeiro
                </h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-semibold text-gray-900"><?= formatarMoeda($pedido['valor_total']) ?></span>
                    </div>
                    
                    <?php if ($pedido['desconto'] > 0): ?>
                    <div class="flex justify-between items-center text-red-600">
                        <span>Desconto:</span>
                        <span class="font-semibold">- <?= formatarMoeda($pedido['desconto']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="pt-3 border-t">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-800">Total:</span>
                            <span class="text-2xl font-bold text-green-600"><?= formatarMoeda($pedido['valor_final']) ?></span>
                        </div>
                    </div>
                    
                    <!-- Status de Pagamento Detalhado -->
                    <div class="mt-4 space-y-2">
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-sm text-gray-600">Entrada (50%):</span>
                            <span class="font-semibold <?= in_array($pedido['status'], ['pagamento_50', 'producao', 'pagamento_100', 'pronto', 'entregue']) ? 'text-green-600' : 'text-gray-400' ?>">
                                <?= formatarMoeda($pedido['valor_final'] / 2) ?>
                                <?php if (in_array($pedido['status'], ['pagamento_50', 'producao', 'pagamento_100', 'pronto', 'entregue'])): ?>
                                    ✓
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-sm text-gray-600">Saldo (50%):</span>
                            <span class="font-semibold <?= in_array($pedido['status'], ['pagamento_100', 'pronto', 'entregue']) ? 'text-green-600' : 'text-gray-400' ?>">
                                <?= formatarMoeda($pedido['valor_final'] / 2) ?>
                                <?php if (in_array($pedido['status'], ['pagamento_100', 'pronto', 'entregue'])): ?>
                                    ✓
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ações -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Ações Rápidas</h2>
                
                <div class="space-y-2">
                    <a href="orcamento_pdf.php?id=<?= $pedido_id ?>" target="_blank"
                       class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Gerar PDF
                    </a>
                    
                    <button onclick="copiarDadosNF()" 
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        Copiar Dados NF
                    </button>
                    
                    <a href="pedido_imprimir.php?id=<?= $pedido_id ?>&tipo=fiscal" target="_blank"
                       class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Imprimir
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detalhamento dos Itens -->
    <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Detalhamento dos Itens
        </h2>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qtd</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($itens as $index => $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900"><?= $index + 1 ?></td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">
                            <?= htmlspecialchars($item['produto_codigo'] ?: '-') ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <?= htmlspecialchars($item['descricao']) ?>
                            <?php if ($item['observacoes']): ?>
                                <span class="block text-xs text-gray-500 mt-1"><?= htmlspecialchars($item['observacoes']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-center text-gray-900">
                            <?= number_format($item['quantidade'], 0) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900">
                            <?= formatarMoeda($item['valor_unitario']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">
                            <?= formatarMoeda($item['valor_total']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-100">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right font-bold text-gray-800">Total Geral:</td>
                        <td class="px-4 py-3 text-right font-bold text-lg text-green-600">
                            <?= formatarMoeda($pedido['valor_final']) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Toast de notificação -->
<div id="toast" class="hidden fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all">
    <span id="toastMessage">Copiado!</span>
</div>

<script>
function copiarTexto(texto) {
    navigator.clipboard.writeText(texto).then(function() {
        mostrarToast('CPF/CNPJ copiado!');
    });
}

function copiarDadosNF() {
    const dados = `
DADOS PARA NF - PEDIDO #<?= $pedido['numero'] ?>
========================================
CLIENTE: <?= $pedido['cliente_nome'] ?>

<?= $pedido['tipo_pessoa'] === 'J' ? 'CNPJ' : 'CPF' ?>: <?= $pedido['cpf_cnpj'] ?>

ENDEREÇO: <?= $pedido['endereco'] ?><?= $pedido['numero'] ? ', ' . $pedido['numero'] : '' ?><?= $pedido['complemento'] ? ' - ' . $pedido['complemento'] : '' ?>

BAIRRO: <?= $pedido['bairro'] ?>

CIDADE/UF: <?= $pedido['cidade'] ?>/<?= $pedido['estado'] ?>

CEP: <?= $pedido['cep'] ?>

VALOR TOTAL: <?= formatarMoeda($pedido['valor_final']) ?>
========================================
    `.trim();
    
    navigator.clipboard.writeText(dados).then(function() {
        mostrarToast('Dados copiados para área de transferência!');
    });
}

function mostrarToast(mensagem) {
    const toast = document.getElementById('toast');
    document.getElementById('toastMessage').textContent = mensagem;
    toast.classList.remove('hidden');
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}
</script>

<?php include '../../../views/layouts/_footer.php'; ?>