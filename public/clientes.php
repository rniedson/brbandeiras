<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['vendedor', 'gestor']);

// Função para preparar número para WhatsApp
function prepararWhatsApp($numero) {
    if (!$numero) return null;
    
    // Remove tudo que não for número
    $numero = preg_replace('/\D/', '', $numero);
    
    // Se tem menos de 10 dígitos, não é válido
    if (strlen($numero) < 10) return null;
    
    // Se tem 10 ou 11 dígitos, assume que é brasileiro
    if (strlen($numero) == 10 || strlen($numero) == 11) {
        // Se não tem código do país, adiciona 55
        if (!str_starts_with($numero, '55')) {
            $numero = '55' . $numero;
        }
    }
    
    // Verifica se é celular (9º dígito ou números específicos)
    if (strlen($numero) == 13) { // 55 + DDD(2) + 9 dígitos
        return $numero;
    } elseif (strlen($numero) == 12) { // 55 + DDD(2) + 8 dígitos
        // Adiciona o 9 após o DDD para celulares antigos
        $ddd = substr($numero, 2, 2);
        $resto = substr($numero, 4);
        // Verifica se começa com 6, 7, 8 ou 9 (celular)
        if (in_array($resto[0], ['6', '7', '8', '9'])) {
            return '55' . $ddd . '9' . $resto;
        }
    }
    
    return $numero;
}

// Mapa de regiões do Brasil
$regioes_estados = [
    'AC' => 'NO', 'AL' => 'NE', 'AP' => 'NO', 'AM' => 'NO', 'BA' => 'NE', 
    'CE' => 'NE', 'DF' => 'CO', 'ES' => 'SE', 'GO' => 'CO', 'MA' => 'NE',
    'MT' => 'CO', 'MS' => 'CO', 'MG' => 'SE', 'PA' => 'NO', 'PB' => 'NE',
    'PR' => 'SU', 'PE' => 'NE', 'PI' => 'NE', 'RJ' => 'SE', 'RN' => 'NE',
    'RS' => 'SU', 'RO' => 'NO', 'RR' => 'NO', 'SC' => 'SU', 'SP' => 'SE',
    'SE' => 'NE', 'TO' => 'NO'
];

$nomes_regioes = [
    'NE' => 'Nordeste',
    'CO' => 'Centro-Oeste', 
    'SE' => 'Sudeste',
    'SU' => 'Sul',
    'NO' => 'Norte'
];

// Filtros
$busca = $_GET['busca'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$estado = $_GET['estado'] ?? '';
$cidade = $_GET['cidade'] ?? '';
$excluir_top = $_GET['excluir_top'] ?? 0; // Para filtro dinâmico
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = $_GET['limite'] ?? 25;
$offset = ($pagina - 1) * $limite;

// Query base
$where = ["c.ativo = true"];
$params = [];

if ($busca) {
    $where[] = "(
        c.nome ILIKE ? OR 
        c.nome_fantasia ILIKE ? OR
        c.cpf_cnpj LIKE ? OR 
        c.telefone LIKE ? OR 
        c.celular LIKE ? OR
        c.email ILIKE ? OR
        c.codigo_sistema LIKE ?
    )";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam, $buscaParam, $buscaParam, $buscaParam, $buscaParam]);
}

if ($tipo) {
    $where[] = "c.tipo_pessoa = ?";
    $params[] = $tipo;
}

if ($estado) {
    $where[] = "c.estado = ?";
    $params[] = $estado;
}

if ($cidade) {
    $where[] = "c.cidade ILIKE ?";
    $params[] = "%$cidade%";
}

$whereClause = implode(' AND ', $where);

// Contar total de registros
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes c WHERE $whereClause");
$stmt->execute($params);
$totalRegistros = $stmt->fetchColumn();
$totalPaginas = ceil($totalRegistros / $limite);

// Buscar clientes com mais campos
$sql = "SELECT 
        c.*,
        COALESCE(c.nome_fantasia, c.nome) as nome_exibicao,
        COALESCE(c.celular, c.whatsapp, c.telefone) as telefone_principal,
        COUNT(DISTINCT p.id) as total_pedidos,
        COALESCE(SUM(p.valor_final), 0) as valor_total,
        MAX(p.created_at) as ultimo_pedido
    FROM clientes c
    LEFT JOIN pedidos p ON p.cliente_id = c.id
    WHERE $whereClause
    GROUP BY c.id
    ORDER BY c.nome
    LIMIT ? OFFSET ?";

$params[] = $limite;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar estatísticas gerais
$stats = [];

// Total por tipo
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN tipo_pessoa = 'F' THEN 1 END) as pessoa_fisica,
        COUNT(CASE WHEN tipo_pessoa = 'J' THEN 1 END) as pessoa_juridica
    FROM clientes 
    WHERE ativo = true
");
$stats['tipos'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Estatísticas de localização - X municípios em Y UFs
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT cidade) as total_municipios,
        COUNT(DISTINCT estado) as total_estados
    FROM clientes
    WHERE ativo = true 
    AND cidade IS NOT NULL AND cidade != ''
    AND estado IS NOT NULL AND estado != ''
");
$stats['localizacao'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Top estados com opção de excluir tops
$excluir_top_int = intval($excluir_top);
$sql_estados = "
    WITH ranked_estados AS (
        SELECT 
            estado, 
            COUNT(*) as total,
            ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) as rank
        FROM clientes
        WHERE ativo = true AND estado IS NOT NULL AND estado != ''
        GROUP BY estado
    )
    SELECT estado, total
    FROM ranked_estados
    WHERE rank > ?
    ORDER BY total DESC
    LIMIT 5
";
$stmt = $pdo->prepare($sql_estados);
$stmt->execute([$excluir_top_int]);
$stats['estados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top cidades com opção de excluir tops
$sql_cidades = "
    WITH ranked_cidades AS (
        SELECT 
            cidade, 
            estado, 
            COUNT(*) as total,
            ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) as rank
        FROM clientes
        WHERE ativo = true AND cidade IS NOT NULL AND cidade != ''
        GROUP BY cidade, estado
    )
    SELECT cidade, estado, total
    FROM ranked_cidades
    WHERE rank > ?
    ORDER BY total DESC
    LIMIT 5
";
$stmt = $pdo->prepare($sql_cidades);
$stmt->execute([$excluir_top_int]);
$stats['cidades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas por região
$stmt = $pdo->query("
    SELECT estado, COUNT(*) as total
    FROM clientes
    WHERE ativo = true AND estado IS NOT NULL AND estado != ''
    GROUP BY estado
");
$clientes_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats['regioes'] = [];
foreach ($nomes_regioes as $sigla => $nome) {
    $stats['regioes'][$sigla] = [
        'nome' => $nome,
        'total' => 0,
        'estados' => []
    ];
}

foreach ($clientes_por_estado as $item) {
    $estado = $item['estado'];
    if (isset($regioes_estados[$estado])) {
        $regiao = $regioes_estados[$estado];
        $stats['regioes'][$regiao]['total'] += $item['total'];
        $stats['regioes'][$regiao]['estados'][] = $estado;
    }
}

// Ordenar regiões por total
uasort($stats['regioes'], function($a, $b) {
    return $b['total'] - $a['total'];
});

// Lista de estados para filtro
$stmt = $pdo->query("
    SELECT DISTINCT estado
    FROM clientes
    WHERE ativo = true AND estado IS NOT NULL AND estado != ''
    ORDER BY estado
");
$estados = $stmt->fetchAll(PDO::FETCH_COLUMN);

$titulo = 'Clientes';
$breadcrumb = [
    ['label' => 'Clientes']
];

// Adicionar CSS customizado para scroll antes do header
ob_start();
?>
<style>
    /* Garantir scroll funcionando */
    html, body {
        height: auto !important;
        min-height: 100vh;
        overflow-x: hidden;
        overflow-y: auto !important;
    }
    
    /* Remover flex do body para permitir scroll natural */
    body {
        display: block !important;
    }
    
    /* Animação suave para hover dos ícones */
    .whatsapp-hover:hover {
        transform: scale(1.1);
        transition: transform 0.2s;
    }
</style>
<?php
$custom_css = ob_get_clean();

// Incluir header padrão
include '../views/_header.php';

// Injetar CSS customizado
echo $custom_css;
?>

<div class="mb-6" x-data="{ filterTop: <?= $excluir_top ?> }">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Clientes</h1>
            <p class="text-gray-600 mt-2">Gerencie sua base de clientes</p>
        </div>
        <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
        <div class="flex gap-2">
            <a href="clientes_importar_v2.php" 
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Importar CSV
            </a>
            <a href="cliente_novo.php" 
               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Novo Cliente
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- KPIs Principais com novo card de localização -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <!-- Total de Clientes -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Total de Clientes</p>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($totalRegistros, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>

    <!-- Pessoa Física -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Pessoa Física</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?= number_format($stats['tipos']['pessoa_fisica'], 0, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400">
                    <?= round(($stats['tipos']['pessoa_fisica'] / max(1, $stats['tipos']['pessoa_fisica'] + $stats['tipos']['pessoa_juridica'])) * 100) ?>%
                </p>
            </div>
        </div>
    </div>

    <!-- Pessoa Jurídica -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Pessoa Jurídica</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?= number_format($stats['tipos']['pessoa_juridica'], 0, ',', '.') ?>
                </p>
                <p class="text-xs text-gray-400">
                    <?= round(($stats['tipos']['pessoa_juridica'] / max(1, $stats['tipos']['pessoa_fisica'] + $stats['tipos']['pessoa_juridica'])) * 100) ?>%
                </p>
            </div>
        </div>
    </div>

    <!-- NOVO KPI: Localização -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-orange-100 rounded-lg">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Abrangência</p>
                <p class="text-lg font-bold text-gray-800">
                    <?= number_format($stats['localizacao']['total_municipios'], 0, ',', '.') ?> cidades
                </p>
                <p class="text-xs text-gray-400">
                    em <?= $stats['localizacao']['total_estados'] ?> estados
                </p>
            </div>
        </div>
    </div>

    <!-- Novos este Mês -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Novos este Mês</p>
                <?php
                $stmt = $pdo->query("
                    SELECT COUNT(*) 
                    FROM clientes 
                    WHERE ativo = true 
                    AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)
                ");
                $novosMes = $stmt->fetchColumn();
                ?>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($novosMes, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard de Localização com Filtro Dinâmico e Regiões -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <!-- Card de Regiões -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-700 mb-3">Clientes por Região</h3>
        <div class="space-y-2">
            <?php 
            $max_regiao = max(array_column($stats['regioes'], 'total'));
            foreach ($stats['regioes'] as $sigla => $regiao): 
                if ($regiao['total'] > 0):
            ?>
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm font-medium text-gray-700"><?= $regiao['nome'] ?></span>
                    <span class="text-xs text-gray-400 ml-1">(<?= $sigla ?>)</span>
                </div>
                <div class="flex items-center">
                    <div class="w-20 bg-gray-200 rounded-full h-2 mr-2">
                        <div class="bg-indigo-600 h-2 rounded-full transition-all" 
                             style="width: <?= $max_regiao > 0 ? ($regiao['total'] / $max_regiao) * 100 : 0 ?>%"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-700 w-12 text-right"><?= $regiao['total'] ?></span>
                </div>
            </div>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
    </div>

    <!-- Top Estados com Filtro -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-700">Top 5 Estados</h3>
            <select x-model="filterTop" 
                    @change="window.location.href='?excluir_top=' + filterTop + '&<?= http_build_query(array_diff_key($_GET, ['excluir_top' => ''])) ?>'"
                    class="text-xs px-2 py-1 border rounded">
                <option value="0">Todos</option>
                <option value="1" <?= $excluir_top == 1 ? 'selected' : '' ?>>Excluir 1º</option>
                <option value="2" <?= $excluir_top == 2 ? 'selected' : '' ?>>Excluir 1º e 2º</option>
                <option value="3" <?= $excluir_top == 3 ? 'selected' : '' ?>>Excluir Top 3</option>
            </select>
        </div>
        <div class="space-y-2">
            <?php 
            $max_estado = $stats['estados'] ? max(array_column($stats['estados'], 'total')) : 0;
            foreach ($stats['estados'] as $index => $est): 
            ?>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">
                    <?= ($index + 1 + $excluir_top_int) ?>º <?= htmlspecialchars($est['estado']) ?>
                </span>
                <div class="flex items-center">
                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                        <div class="bg-green-600 h-2 rounded-full transition-all" 
                             style="width: <?= $max_estado > 0 ? ($est['total'] / $max_estado) * 100 : 0 ?>%"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-700 w-12 text-right"><?= $est['total'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Top Cidades -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-700 mb-3">
            Top 5 Cidades 
            <?php if ($excluir_top > 0): ?>
            <span class="text-xs text-gray-400">(excluindo top <?= $excluir_top ?>)</span>
            <?php endif; ?>
        </h3>
        <div class="space-y-2">
            <?php 
            $max_cidade = $stats['cidades'] ? max(array_column($stats['cidades'], 'total')) : 0;
            foreach ($stats['cidades'] as $index => $cid): 
            ?>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 truncate max-w-[150px]" title="<?= htmlspecialchars($cid['cidade']) ?>/<?= htmlspecialchars($cid['estado']) ?>">
                    <?= ($index + 1 + $excluir_top_int) ?>º <?= htmlspecialchars($cid['cidade']) ?>/<?= htmlspecialchars($cid['estado']) ?>
                </span>
                <div class="flex items-center">
                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all" 
                             style="width: <?= $max_cidade > 0 ? ($cid['total'] / $max_cidade) * 100 : 0 ?>%"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-700 w-12 text-right"><?= $cid['total'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Filtros Avançados -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="space-y-4">
            <!-- Linha 1: Busca -->
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                           placeholder="Nome, CPF/CNPJ, telefone, e-mail, código..."
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
            </div>
            
            <!-- Linha 2: Filtros -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="tipo" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Todos</option>
                        <option value="F" <?= $tipo === 'F' ? 'selected' : '' ?>>Pessoa Física</option>
                        <option value="J" <?= $tipo === 'J' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="estado" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $uf): ?>
                        <option value="<?= $uf ?>" <?= $estado === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                    <input type="text" name="cidade" value="<?= htmlspecialchars($cidade) ?>"
                           placeholder="Digite a cidade..."
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Registros por página</label>
                    <select name="limite" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="25" <?= $limite == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limite == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
            </div>
            
            <!-- Manter filtro de top ao submeter -->
            <input type="hidden" name="excluir_top" value="<?= htmlspecialchars($excluir_top) ?>">
            
            <!-- Botões -->
            <div class="flex justify-between">
                <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Filtrar
                </button>
                <a href="clientes.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Limpar Filtros
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Clientes -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if ($clientes): ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Código/Tipo
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Cliente
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Contato
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Localização
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Pedidos
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Total
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Ações
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($clientes as $cliente): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-xs">
                            <?php if ($cliente['codigo_sistema']): ?>
                            <div class="font-mono text-gray-600">#<?= htmlspecialchars($cliente['codigo_sistema']) ?></div>
                            <?php endif; ?>
                            <div class="mt-1">
                                <?php if ($cliente['tipo_pessoa'] === 'J'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">PJ</span>
                                <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">PF</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($cliente['nome']) ?>
                            </div>
                            <?php if ($cliente['nome_fantasia'] && $cliente['nome_fantasia'] != $cliente['nome']): ?>
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($cliente['nome_fantasia']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($cliente['cpf_cnpj']): ?>
                            <div class="text-xs text-gray-400 font-mono mt-1">
                                <?= formatarCpfCnpj($cliente['cpf_cnpj']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 space-y-1">
                            <?php 
                            // Priorizar celular/whatsapp
                            $celular = $cliente['celular'] ?: $cliente['whatsapp'];
                            $telefone = $cliente['telefone'];
                            
                            if ($celular): 
                                $numeroWhatsApp = prepararWhatsApp($celular);
                            ?>
                            <div class="flex items-center">
                                <?php if ($numeroWhatsApp): ?>
                                <a href="https://wa.me/<?= $numeroWhatsApp ?>" 
                                   target="_blank"
                                   class="flex items-center hover:text-green-600 transition-colors whatsapp-hover"
                                   title="Abrir no WhatsApp">
                                    <svg class="w-4 h-4 mr-1 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                        <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2.546 20.2A1.01 1.01 0 0 0 3.8 21.454l3.032-.892A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                                    </svg>
                                    <?= htmlspecialchars($celular) ?>
                                </a>
                                <?php else: ?>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                                    </svg>
                                    <?= htmlspecialchars($celular) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($telefone && $telefone != $celular): ?>
                            <div class="flex items-center">
                                <?php 
                                $telefoneWhatsApp = prepararWhatsApp($telefone);
                                if ($telefoneWhatsApp && strlen(preg_replace('/\D/', '', $telefone)) >= 10): 
                                ?>
                                <a href="https://wa.me/<?= $telefoneWhatsApp ?>" 
                                   target="_blank"
                                   class="flex items-center hover:text-green-600 transition-colors whatsapp-hover"
                                   title="Abrir no WhatsApp">
                                    <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                        <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2.546 20.2A1.01 1.01 0 0 0 3.8 21.454l3.032-.892A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                                    </svg>
                                    <?= htmlspecialchars($telefone) ?>
                                </a>
                                <?php else: ?>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    <?= htmlspecialchars($telefone) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($cliente['email']): ?>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <a href="mailto:<?= htmlspecialchars($cliente['email']) ?>" 
                                   class="truncate max-w-xs hover:text-blue-600 transition-colors">
                                    <?= htmlspecialchars($cliente['email']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?php if ($cliente['cidade'] || $cliente['estado']): ?>
                            <div class="font-medium">
                                <?= htmlspecialchars($cliente['cidade'] ?: '-') ?>/<?= htmlspecialchars($cliente['estado'] ?: '-') ?>
                                <?php if (isset($regioes_estados[$cliente['estado']])): ?>
                                <span class="text-xs text-gray-400 ml-1">(<?= $regioes_estados[$cliente['estado']] ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($cliente['bairro']): ?>
                            <div class="text-xs text-gray-500">
                                <?= htmlspecialchars($cliente['bairro']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($cliente['total_pedidos'] > 0): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <?= $cliente['total_pedidos'] ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php if ($cliente['valor_total'] > 0): ?>
                        <span class="text-sm font-medium text-gray-900">
                            <?= formatarMoeda($cliente['valor_total']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-center text-sm font-medium">
                        <div class="flex justify-center space-x-2">
                            <a href="cliente_detalhes.php?id=<?= $cliente['id'] ?>" 
                               class="text-blue-600 hover:text-blue-900" title="Ver detalhes">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            <a href="cliente_editar.php?id=<?= $cliente['id'] ?>" 
                               class="text-green-600 hover:text-green-900" title="Editar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <a href="pedido_novo.php?cliente_id=<?= $cliente['id'] ?>" 
                               class="text-purple-600 hover:text-purple-900" title="Novo pedido">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação Melhorada -->
    <?php if ($totalPaginas > 1): ?>
    <div class="bg-gray-50 px-6 py-3 flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden">
            <?php if ($pagina > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>" 
               class="px-4 py-2 border rounded-md bg-white hover:bg-gray-100">
                Anterior
            </a>
            <?php endif; ?>
            
            <?php if ($pagina < $totalPaginas): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>" 
               class="ml-3 px-4 py-2 border rounded-md bg-white hover:bg-gray-100">
                Próxima
            </a>
            <?php endif; ?>
        </div>
        
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Mostrando <span class="font-medium"><?= $offset + 1 ?></span> a 
                    <span class="font-medium"><?= min($offset + $limite, $totalRegistros) ?></span> de 
                    <span class="font-medium"><?= number_format($totalRegistros, 0, ',', '.') ?></span> clientes
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                    <?php
                    $inicio = max(1, $pagina - 2);
                    $fim = min($totalPaginas, $pagina + 2);
                    
                    if ($pagina > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($inicio > 1): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                    <?php endif; ?>
                    
                    <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>" 
                       class="relative inline-flex items-center px-4 py-2 border <?= $i === $pagina ? 'z-10 bg-green-50 border-green-500 text-green-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> text-sm font-medium">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($fim < $totalPaginas): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($pagina < $totalPaginas): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $totalPaginas])) ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum cliente encontrado</h3>
        <p class="mt-1 text-sm text-gray-500">
            <?php if ($busca || $tipo || $estado || $cidade): ?>
            Tente ajustar os filtros de busca.
            <?php else: ?>
            Comece cadastrando um novo cliente.
            <?php endif; ?>
        </p>
        <div class="mt-6">
            <a href="cliente_novo.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Novo Cliente
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../views/_footer.php'; ?>