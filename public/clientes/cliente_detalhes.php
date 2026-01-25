<?php
// cliente_detalhes.php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

$cliente_id = $_GET['id'] ?? 0;

// Buscar cliente com todos os campos
$stmt = $pdo->prepare("
    SELECT c.*,
           COALESCE(c.nome_fantasia, c.nome) as nome_exibicao,
           COUNT(DISTINCT p.id) as total_pedidos,
           COALESCE(SUM(p.valor_final), 0) as valor_total_pedidos,
           MAX(p.created_at) as ultimo_pedido,
           MIN(p.created_at) as primeiro_pedido
    FROM clientes c
    LEFT JOIN pedidos p ON p.cliente_id = c.id
    WHERE c.id = ?
    GROUP BY c.id
");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    $_SESSION['erro'] = 'Cliente não encontrado';
    header('Location: clientes.php');
    exit;
}

// Buscar últimos pedidos
$stmt = $pdo->prepare("
    SELECT p.*, u.nome as vendedor_nome,
           COUNT(pi.id) as total_itens
    FROM pedidos p
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
    WHERE p.cliente_id = ?
    GROUP BY p.id, u.nome
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$cliente_id]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas de pedidos
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'orcamento' THEN 1 END) as orcamentos,
        COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as aprovados,
        COUNT(CASE WHEN status = 'producao' THEN 1 END) as producao,
        COUNT(CASE WHEN status = 'pronto' THEN 1 END) as prontos,
        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as entregues,
        COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as cancelados
    FROM pedidos
    WHERE cliente_id = ?
");
$stmt->execute([$cliente_id]);
$stats_pedidos = $stmt->fetch(PDO::FETCH_ASSOC);

$titulo = 'Detalhes do Cliente';
$breadcrumb = [
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'Clientes', 'url' => 'clientes.php'],
    ['label' => $cliente['nome']]
];

include '../../views/layouts/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Cabeçalho -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex justify-between items-start">
            <div class="flex-1">
                <div class="flex items-center gap-4 mb-2">
                    <h1 class="text-3xl font-bold text-gray-800">
                        <?= htmlspecialchars($cliente['nome']) ?>
                    </h1>
                    <?php if ($cliente['ativo']): ?>
                    <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">Ativo</span>
                    <?php else: ?>
                    <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">Inativo</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($cliente['nome_fantasia'] && $cliente['nome_fantasia'] != $cliente['nome']): ?>
                <p class="text-lg text-gray-600 mb-2">
                    <?= htmlspecialchars($cliente['nome_fantasia']) ?>
                </p>
                <?php endif; ?>
                
                <div class="flex flex-wrap gap-2 mt-3">
                    <?php if ($cliente['codigo_sistema']): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                        </svg>
                        Código: <?= htmlspecialchars($cliente['codigo_sistema']) ?>
                    </span>
                    <?php endif; ?>
                    
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <?= $cliente['tipo_pessoa'] === 'J' ? 'Pessoa Jurídica' : 'Pessoa Física' ?>
                    </span>
                    
                    <?php if ($cliente['tipo_lista_precos']): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        Lista: <?= htmlspecialchars($cliente['tipo_lista_precos']) ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($cliente['origem_dados']): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        Origem: <?= ucfirst(htmlspecialchars($cliente['origem_dados'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex gap-2">
                <a href="cliente_editar.php?id=<?= $cliente_id ?>" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                </a>
                <a href="<?= $baseUrl ?>pedidos/pedido_novo.php?cliente_id=<?= $cliente_id ?>" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Novo Pedido
                </a>
            </div>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Total Pedidos</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $cliente['total_pedidos'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Valor Total</p>
                    <p class="text-2xl font-bold text-gray-800"><?= formatarMoeda($cliente['valor_total_pedidos']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Último Pedido</p>
                    <p class="text-lg font-bold text-gray-800">
                        <?= $cliente['ultimo_pedido'] ? formatarData($cliente['ultimo_pedido']) : 'Nunca' ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Ticket Médio</p>
                    <p class="text-2xl font-bold text-gray-800">
                        <?= $cliente['total_pedidos'] > 0 ? formatarMoeda($cliente['valor_total_pedidos'] / $cliente['total_pedidos']) : 'R$ 0,00' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Coluna Principal -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Informações de Contato -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    Informações de Contato
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if ($cliente['telefone']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Telefone</label>
                        <p class="font-medium"><?= htmlspecialchars($cliente['telefone']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['celular']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Celular</label>
                        <p class="font-medium flex items-center">
                            <?= htmlspecialchars($cliente['celular']) ?>
                            <?php 
                            $celularLimpo = preg_replace('/\D/', '', $cliente['celular']);
                            if (strlen($celularLimpo) >= 10): 
                            ?>
                            <a href="https://wa.me/55<?= $celularLimpo ?>" target="_blank" class="ml-2 text-green-600 hover:text-green-700">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                    <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2.546 20.2A1.01 1.01 0 0 0 3.8 21.454l3.032-.892A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                                </svg>
                            </a>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['whatsapp']): ?>
                    <div>
                        <label class="text-sm text-gray-500">WhatsApp</label>
                        <p class="font-medium"><?= htmlspecialchars($cliente['whatsapp']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['fax']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Fax</label>
                        <p class="font-medium"><?= htmlspecialchars($cliente['fax']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['email']): ?>
                    <div>
                        <label class="text-sm text-gray-500">E-mail</label>
                        <p class="font-medium">
                            <a href="mailto:<?= $cliente['email'] ?>" class="text-blue-600 hover:underline">
                                <?= htmlspecialchars($cliente['email']) ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['site']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Site</label>
                        <p class="font-medium">
                            <a href="<?= strpos($cliente['site'], 'http') === 0 ? $cliente['site'] : 'http://' . $cliente['site'] ?>" 
                               target="_blank" class="text-blue-600 hover:underline">
                                <?= htmlspecialchars($cliente['site']) ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documentos -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Documentos
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if ($cliente['tipo_pessoa'] === 'J'): ?>
                        <?php if ($cliente['cpf_cnpj']): ?>
                        <div>
                            <label class="text-sm text-gray-500">CNPJ</label>
                            <p class="font-mono font-medium"><?= formatarCNPJ($cliente['cpf_cnpj']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['inscricao_estadual']): ?>
                        <div>
                            <label class="text-sm text-gray-500">Inscrição Estadual</label>
                            <p class="font-medium"><?= htmlspecialchars($cliente['inscricao_estadual']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['indicador_ie']): ?>
                        <div>
                            <label class="text-sm text-gray-500">Indicador IE</label>
                            <p class="font-medium"><?= htmlspecialchars($cliente['indicador_ie']) ?></p>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($cliente['cpf_cnpj']): ?>
                        <div>
                            <label class="text-sm text-gray-500">CPF</label>
                            <p class="font-mono font-medium"><?= formatarCPF($cliente['cpf_cnpj']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['rg']): ?>
                        <div>
                            <label class="text-sm text-gray-500">RG</label>
                            <p class="font-medium">
                                <?= htmlspecialchars($cliente['rg']) ?>
                                <?php if ($cliente['rg_expedicao']): ?>
                                    - <?= htmlspecialchars($cliente['rg_expedicao']) ?>
                                <?php endif; ?>
                                <?php if ($cliente['rg_uf']): ?>
                                    / <?= htmlspecialchars($cliente['rg_uf']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['data_nascimento']): ?>
                        <div>
                            <label class="text-sm text-gray-500">Data de Nascimento</label>
                            <p class="font-medium"><?= formatarData($cliente['data_nascimento']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['sexo']): ?>
                        <div>
                            <label class="text-sm text-gray-500">Sexo</label>
                            <p class="font-medium"><?= $cliente['sexo'] === 'M' ? 'Masculino' : 'Feminino' ?></p>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Endereço -->
            <?php if ($cliente['endereco'] || $cliente['cep']): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Endereço
                </h2>
                
                <div class="space-y-2">
                    <?php if ($cliente['endereco']): ?>
                    <p class="text-gray-700">
                        <?= htmlspecialchars($cliente['endereco']) ?>
                        <?php if ($cliente['numero']): ?>
                            , <?= htmlspecialchars($cliente['numero']) ?>
                        <?php endif; ?>
                        <?php if ($cliente['complemento']): ?>
                            - <?= htmlspecialchars($cliente['complemento']) ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($cliente['bairro']): ?>
                    <p class="text-gray-700"><?= htmlspecialchars($cliente['bairro']) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($cliente['cidade'] || $cliente['estado']): ?>
                    <p class="text-gray-700">
                        <?= htmlspecialchars($cliente['cidade']) ?>
                        <?php if ($cliente['estado']): ?>
                            / <?= htmlspecialchars($cliente['estado']) ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($cliente['cep']): ?>
                    <p class="text-gray-700">CEP: <?= formatarCEP($cliente['cep']) ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if ($cliente['endereco'] && $cliente['cidade']): ?>
                <div class="mt-4">
                    <a href="https://www.google.com/maps/search/<?= urlencode($cliente['endereco'] . ', ' . $cliente['numero'] . ', ' . $cliente['cidade'] . ', ' . $cliente['estado']) ?>" 
                       target="_blank"
                       class="inline-flex items-center text-blue-600 hover:text-blue-800">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Ver no Google Maps
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Observações -->
            <?php if ($cliente['observacoes']): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                    Observações
                </h2>
                <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($cliente['observacoes']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status dos Pedidos -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Status dos Pedidos</h2>
                <div class="space-y-3">
                    <?php foreach ($stats_pedidos as $status => $count): 
                        if ($count > 0):
                            $cores = [
                                'orcamentos' => 'bg-gray-100 text-gray-800',
                                'aprovados' => 'bg-blue-100 text-blue-800',
                                'producao' => 'bg-yellow-100 text-yellow-800',
                                'prontos' => 'bg-green-100 text-green-800',
                                'entregues' => 'bg-purple-100 text-purple-800',
                                'cancelados' => 'bg-red-100 text-red-800'
                            ];
                            $labels = [
                                'orcamentos' => 'Orçamentos',
                                'aprovados' => 'Aprovados',
                                'producao' => 'Em Produção',
                                'prontos' => 'Prontos',
                                'entregues' => 'Entregues',
                                'cancelados' => 'Cancelados'
                            ];
                    ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600"><?= $labels[$status] ?></span>
                        <span class="px-2 py-1 text-xs rounded-full <?= $cores[$status] ?>">
                            <?= $count ?>
                        </span>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <!-- Informações do Sistema -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Informações do Sistema</h2>
                <div class="space-y-3 text-sm">
                    <div>
                        <label class="text-gray-500">Cadastrado em</label>
                        <p class="font-medium"><?= formatarDataHora($cliente['created_at']) ?></p>
                    </div>
                    
                    <?php if ($cliente['updated_at']): ?>
                    <div>
                        <label class="text-gray-500">Última atualização</label>
                        <p class="font-medium"><?= formatarDataHora($cliente['updated_at']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['primeiro_pedido']): ?>
                    <div>
                        <label class="text-gray-500">Cliente desde</label>
                        <p class="font-medium"><?= formatarData($cliente['primeiro_pedido']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['data_ultima_importacao']): ?>
                    <div>
                        <label class="text-gray-500">Última importação</label>
                        <p class="font-medium"><?= formatarDataHora($cliente['data_ultima_importacao']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ações Rápidas -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Ações Rápidas</h2>
                <div class="space-y-2">
                    <a href="<?= $baseUrl ?>pedidos/pedido_novo.php?cliente_id=<?= $cliente_id ?>" 
                       class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Novo Pedido
                    </a>
                    
                    <a href="cliente_editar.php?id=<?= $cliente_id ?>" 
                       class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar Cliente
                    </a>
                    
                    <?php if ($cliente['email']): ?>
                    <a href="mailto:<?= $cliente['email'] ?>" 
                       class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Enviar E-mail
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    $whatsappNum = $cliente['celular'] ?: $cliente['whatsapp'];
                    if ($whatsappNum): 
                        $whatsappLimpo = preg_replace('/\D/', '', $whatsappNum);
                    ?>
                    <a href="https://wa.me/55<?= $whatsappLimpo ?>" target="_blank"
                       class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2.546 20.2A1.01 1.01 0 0 0 3.8 21.454l3.032-.892A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                        </svg>
                        WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico de Pedidos -->
    <?php if (!empty($pedidos)): ?>
    <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
        <h2 class="text-lg font-bold mb-4">Histórico de Pedidos</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Itens</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($pedidos as $pedido): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #<?= htmlspecialchars($pedido['numero']) ?>
                            <?php if ($pedido['urgente']): ?>
                            <span class="ml-2 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Urgente</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= formatarData($pedido['created_at']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($pedido['vendedor_nome']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= $pedido['total_itens'] ?> <?= $pedido['total_itens'] == 1 ? 'item' : 'itens' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $status_classes = [
                                'orcamento' => 'bg-gray-100 text-gray-800',
                                'aprovado' => 'bg-blue-100 text-blue-800',
                                'producao' => 'bg-yellow-100 text-yellow-800',
                                'pronto' => 'bg-green-100 text-green-800',
                                'entregue' => 'bg-purple-100 text-purple-800',
                                'cancelado' => 'bg-red-100 text-red-800'
                            ];
                            $class = $status_classes[$pedido['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $class ?>">
                                <?= ucfirst($pedido['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            <?= formatarMoeda($pedido['valor_final']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" 
                               class="text-blue-600 hover:text-blue-900">
                                Ver Detalhes
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($cliente['total_pedidos'] > 10): ?>
        <div class="mt-4 text-center">
            <a href="<?= $baseUrl ?>pedidos/pedidos.php?cliente_id=<?= $cliente_id ?>" 
               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                Ver todos os <?= $cliente['total_pedidos'] ?> pedidos →
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../../views/layouts/_footer.php'; ?>