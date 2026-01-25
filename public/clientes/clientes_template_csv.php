<?php
// clientes_template_csv.php
// Gera um template CSV para download com exemplos

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="template_importacao_clientes.csv"');

// Abrir output
$output = fopen('php://output', 'w');

// Adicionar BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho
fputcsv($output, [
    'Código Sistema',
    'Nome/Razão Social',
    'Apelido/Nome fantasia',
    'Tipo (Lista de Preços)',
    'Sexo (M ou F)',
    'CPF',
    'RG',
    'Expedição RG',
    'UF do RG',
    'Indicador IE Destinatário',
    'CNPJ',
    'IE',
    'Telefone',
    'Celular',
    'Fax',
    'Email',
    'Site',
    'Endereço',
    'Número',
    'Complemento',
    'Bairro',
    'Cidade',
    'Estado',
    'CEP',
    'Data de nascimento'
]);

// Exemplos
$exemplos = [
    [
        '1001',
        'EMPRESA EXEMPLO LTDA',
        'Empresa Exemplo',
        'Padrão',
        '',
        '',
        '',
        '',
        '',
        'Contribuinte do ICMS',
        '12.345.678/0001-90',
        '123.456.789.012',
        '(11) 3333-4444',
        '(11) 99999-8888',
        '',
        'contato@exemplo.com.br',
        'www.exemplo.com.br',
        'Rua das Flores',
        '100',
        'Sala 201',
        'Centro',
        'São Paulo',
        'SP',
        '01234-567',
        ''
    ],
    [
        '1002',
        'João da Silva',
        'João',
        'Padrão',
        'M',
        '123.456.789-00',
        '12.345.678-9',
        'SSP',
        'SP',
        'Não Contribuinte',
        '',
        '',
        '(11) 2222-3333',
        '(11) 98888-7777',
        '',
        'joao@email.com',
        '',
        'Av. Paulista',
        '1000',
        'Apto 501',
        'Bela Vista',
        'São Paulo',
        'SP',
        '01310-100',
        '15/03/1980'
    ]
];

foreach ($exemplos as $exemplo) {
    fputcsv($output, $exemplo);
}

fclose($output);
exit;

// ===============================================
// cliente_detalhes_completo.php
// Visualização completa do cliente com novos campos
// ===============================================
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

$cliente_id = $_GET['id'] ?? 0;

// Buscar cliente com todos os campos
$stmt = $pdo->prepare("
    SELECT c.*,
           COUNT(DISTINCT p.id) as total_pedidos,
           COALESCE(SUM(p.valor_final), 0) as valor_total_pedidos,
           MAX(p.created_at) as ultimo_pedido
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
    SELECT p.*, u.nome as vendedor_nome
    FROM pedidos p
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    WHERE p.cliente_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$cliente_id]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    <?= htmlspecialchars($cliente['nome']) ?>
                </h1>
                <?php if ($cliente['nome_fantasia']): ?>
                <p class="text-lg text-gray-600 mt-1">
                    <?= htmlspecialchars($cliente['nome_fantasia']) ?>
                </p>
                <?php endif; ?>
                
                <div class="flex gap-4 mt-3">
                    <?php if ($cliente['codigo_sistema']): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
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
                    
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $cliente['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= $cliente['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </div>
            </div>
            
            <div class="flex gap-2">
                <a href="cliente_editar.php?id=<?= $cliente_id ?>" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Editar
                </a>
                <a href="<?= $baseUrl ?>pedidos/pedido_novo.php?cliente_id=<?= $cliente_id ?>" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Novo Pedido
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Informações Principais -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Documentos -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Documentos</h2>
                <div class="grid grid-cols-2 gap-4">
                    <?php if ($cliente['tipo_pessoa'] === 'J'): ?>
                        <?php if ($cliente['cpf_cnpj']): ?>
                        <div>
                            <label class="text-sm text-gray-500">CNPJ</label>
                            <p class="font-mono"><?= formatarCNPJ($cliente['cpf_cnpj']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['inscricao_estadual']): ?>
                        <div>
                            <label class="text-sm text-gray-500">Inscrição Estadual</label>
                            <p><?= htmlspecialchars($cliente['inscricao_estadual']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['indicador_ie']): ?>
                        <div>
                            <label class="text-sm text-gray-500">Indicador IE</label>
                            <p><?= htmlspecialchars($cliente['indicador_ie']) ?></p>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($cliente['cpf_cnpj']): ?>
                        <div>
                            <label class="text-sm text-gray-500">CPF</label>
                            <p class="font-mono"><?= formatarCPF($cliente['cpf_cnpj']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['rg']): ?>
                        <div>
                            <label class="text-sm text-gray-500">RG</label>
                            <p><?= htmlspecialchars($cliente['rg']) ?>
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
                            <p><?= formatarData($cliente['data_nascimento']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['sexo']): ?>
                        <div>
                            <label class="text-sm text-gray-500">Sexo</label>
                            <p><?= $cliente['sexo'] === 'M' ? 'Masculino' : 'Feminino' ?></p>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contatos -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Contatos</h2>
                <div class="grid grid-cols-2 gap-4">
                    <?php if ($cliente['telefone']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Telefone</label>
                        <p><?= htmlspecialchars($cliente['telefone']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['celular']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Celular</label>
                        <p><?= htmlspecialchars($cliente['celular']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['whatsapp']): ?>
                    <div>
                        <label class="text-sm text-gray-500">WhatsApp</label>
                        <p><?= htmlspecialchars($cliente['whatsapp']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['fax']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Fax</label>
                        <p><?= htmlspecialchars($cliente['fax']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['email']): ?>
                    <div>
                        <label class="text-sm text-gray-500">E-mail</label>
                        <p><a href="mailto:<?= $cliente['email'] ?>" class="text-blue-600 hover:underline">
                            <?= htmlspecialchars($cliente['email']) ?>
                        </a></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cliente['site']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Site</label>
                        <p><a href="<?= strpos($cliente['site'], 'http') === 0 ? $cliente['site'] : 'http://' . $cliente['site'] ?>" 
                              target="_blank" class="text-blue-600 hover:underline">
                            <?= htmlspecialchars($cliente['site']) ?>
                        </a></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Endereço -->
            <?php if ($cliente['endereco'] || $cliente['cep']): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Endereço</h2>
                <div class="space-y-2">
                    <?php if ($cliente['endereco']): ?>
                    <p>
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
                    <p><?= htmlspecialchars($cliente['bairro']) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($cliente['cidade'] || $cliente['estado']): ?>
                    <p>
                        <?= htmlspecialchars($cliente['cidade']) ?>
                        <?php if ($cliente['estado']): ?>
                            / <?= htmlspecialchars($cliente['estado']) ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($cliente['cep']): ?>
                    <p>CEP: <?= formatarCEP($cliente['cep']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Estatísticas -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Estatísticas</h2>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Total de Pedidos</label>
                        <p class="text-2xl font-bold text-blue-600"><?= $cliente['total_pedidos'] ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Valor Total</label>
                        <p class="text-2xl font-bold text-green-600"><?= formatarMoeda($cliente['valor_total_pedidos']) ?></p>
                    </div>
                    <?php if ($cliente['ultimo_pedido']): ?>
                    <div>
                        <label class="text-sm text-gray-500">Último Pedido</label>
                        <p><?= formatarDataHora($cliente['ultimo_pedido']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informações do Sistema -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Sistema</h2>
                <div class="space-y-3 text-sm">
                    <div>
                        <label class="text-gray-500">Cadastrado em</label>
                        <p><?= formatarDataHora($cliente['created_at']) ?></p>
                    </div>
                    <?php if ($cliente['updated_at']): ?>
                    <div>
                        <label class="text-gray-500">Última atualização</label>
                        <p><?= formatarDataHora($cliente['updated_at']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($cliente['origem_dados']): ?>
                    <div>
                        <label class="text-gray-500">Origem</label>
                        <p class="capitalize"><?= htmlspecialchars($cliente['origem_dados']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($cliente['data_ultima_importacao']): ?>
                    <div>
                        <label class="text-gray-500">Última importação</label>
                        <p><?= formatarDataHora($cliente['data_ultima_importacao']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos Pedidos -->
    <?php if (!empty($pedidos)): ?>
    <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
        <h2 class="text-lg font-bold mb-4">Últimos Pedidos</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #<?= htmlspecialchars($pedido['numero']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= formatarData($pedido['created_at']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($pedido['vendedor_nome']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $status_classes = [
                                'orcamento' => 'bg-gray-100 text-gray-800',
                                'aprovado' => 'bg-blue-100 text-blue-800',
                                'producao' => 'bg-yellow-100 text-yellow-800',
                                'pronto' => 'bg-green-100 text-green-800',
                                'entregue' => 'bg-purple-100 text-purple-800'
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
                                Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../views/layouts/_footer.php'; ?>