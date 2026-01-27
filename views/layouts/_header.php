<?php
// Importar funções de autenticação se ainda não foram importadas
if (!function_exists('isVerComoAtivo')) {
    if (file_exists(__DIR__ . '/../app/auth.php')) {
        require_once __DIR__ . '/../app/auth.php';
    }
}

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Obter informações do usuário para display (compatível com modo "Ver Como")
if (function_exists('getInfoUsuarioDisplay')) {
    $infoUsuario = getInfoUsuarioDisplay();
    $perfilAtual = getPerfilEfetivo();
    $nomeAtual = getNomeEfetivo();
} else {
    $infoUsuario = [
        'nome' => $_SESSION['user_nome'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'perfil' => $_SESSION['user_perfil'] ?? '',
        'modo_ver_como' => false
    ];
    $perfilAtual = $_SESSION['user_perfil'] ?? '';
    $nomeAtual = $_SESSION['user_nome'] ?? '';
}

// Estrutura reorganizada de menus com até 3 níveis
$menuCompleto = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'url' => 'dashboard/dashboard.php',
        'perfis' => ['todos']
    ],
    'pedidos' => [
        'label' => 'Pedidos',
        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'perfis' => ['vendedor', 'gestor', 'producao'],
        'submenu' => [
            [
                'label' => 'Gestão',
                'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                'perfis' => ['vendedor', 'gestor'], // Produção não tem acesso
                'itens' => [
                    ['label' => 'Lista de Pedidos', 'url' => 'pedidos/pedidos.php', 'badge' => 'pedidos_novos'],
                    ['label' => 'Novo Pedido', 'url' => 'pedidos/pedido_novo.php'],
                    ['label' => 'Orçamentos', 'url' => 'orcamentos/orcamentos.php', 'badge' => 'orcamentos_pendentes'],
                    ['label' => 'Aprovações Pendentes', 'url' => 'aprovacoes.php', 'badge' => 'aprovacoes']
                ]
            ],
            [
                'label' => 'Produção',
                'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                'perfis' => ['producao', 'gestor'], // Apenas produção e gestor
                'itens' => [
                    ['label' => 'Dashboard Produção', 'url' => 'dashboard/dashboard_producao.php'],
                    ['label' => 'Kanban Produção', 'url' => 'producao/producao.php'],
                    ['label' => 'Fila de Impressão', 'url' => 'impressao.php', 'badge' => 'fila_impressao'],
                    ['label' => 'Ordem de Serviço', 'url' => 'ordem_servico.php'],
                    ['label' => 'Expedição', 'url' => 'expedicao.php']
                ]
            ]
        ]
    ],
    'clientes' => [
        'label' => 'Clientes',
        'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'perfis' => ['vendedor', 'gestor'],
        'submenu' => [
            [
                'label' => 'Cadastros',
                'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
                'itens' => [
                    ['label' => 'Lista de Clientes', 'url' => 'clientes/clientes.php'],
                    ['label' => 'Novo Cliente', 'url' => 'clientes/cliente_novo.php'],
                    ['label' => 'Grupos de Clientes', 'url' => 'cliente_grupos.php']
                ]
            ],
            [
                'label' => 'Análise',
                'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'itens' => [
                    ['label' => 'Histórico de Compras', 'url' => 'cliente_historico.php']
                ]
            ]
        ]
    ],
    'produtos' => [
        'label' => 'Produtos',
        'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'perfis' => ['vendedor', 'gestor', 'producao'],
        'submenu' => [
            [
                'label' => 'Catálogo',
                'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'perfis' => ['vendedor', 'gestor', 'producao'],
                'itens' => [
                    ['label' => 'Lista de Produtos', 'url' => 'produtos/catalogo.php'],
                    ['label' => 'Novo Produto', 'url' => 'produtos/catalogo_produto_novo.php', 'perfis' => ['vendedor', 'gestor']],
                    ['label' => 'Importar Produtos', 'url' => 'produtos/catalogo_importar.php', 'perfis' => ['vendedor', 'gestor']],
                    ['label' => 'Atualização de Preços', 'url' => 'produtos/catalogo_precos.php', 'perfis' => ['vendedor', 'gestor']],
                    ['label' => 'Categorias', 'url' => 'produtos/categorias_produtos.php', 'perfis' => ['vendedor', 'gestor']]
                ]
            ],
            [
                'label' => 'Estoque',
                'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
                'perfis' => ['vendedor', 'gestor', 'producao'],
                'itens' => [
                    ['label' => 'Posição de Estoque', 'url' => 'estoque/estoque.php'],
                    ['label' => 'Movimentações', 'url' => 'estoque/movimentacao_nova.php', 'perfis' => ['gestor']] // Apenas gestor pode fazer movimentações
                ]
            ],
            [
                'label' => 'Fornecedores',
                'icon' => 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
                'perfis' => ['vendedor', 'gestor'], // Produção não precisa de fornecedores
                'itens' => [
                    ['label' => 'Lista de Fornecedores', 'url' => 'fornecedores.php'],
                    ['label' => 'Novo Fornecedor', 'url' => 'fornecedor_novo.php'],
                    ['label' => 'Cotações', 'url' => 'cotacoes.php']
                ]
            ]
        ]
    ],
    'financeiro' => [
        'label' => 'Financeiro',
        'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'perfis' => ['gestor', 'vendedor'],
        'submenu' => [
            [
                'label' => 'Contas',
                'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                'perfis' => ['gestor'], // Apenas gestor
                'itens' => [
                    ['label' => 'Dashboard Financeiro', 'url' => 'financeiro_dashboard.php'],
                    ['label' => 'Contas a Receber', 'url' => 'contas_receber.php', 'badge' => 'receber_vencidas']
                ]
            ],
            [
                'label' => 'Vendas',
                'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
                'perfis' => ['gestor', 'vendedor'],
                'itens' => [
                    ['label' => 'Comissões', 'url' => 'comissoes.php', 'perfis' => ['gestor']], // Gestor vê todas
                    ['label' => 'Minhas Comissões', 'url' => 'comissoes_vendedor.php', 'perfis' => ['vendedor']], // Vendedor vê só suas
                    ['label' => 'Metas de Vendas', 'url' => 'metas.php', 'perfis' => ['gestor']]
                ]
            ],
            [
                'label' => 'Relatórios',
                'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'perfis' => ['gestor'], // Apenas gestor
                'itens' => [
                    ['label' => 'Vendas', 'url' => 'relatorio_vendas.php'],
                    ['label' => 'Financeiro', 'url' => 'relatorio_financeiro.php'],
                    ['label' => 'Artes', 'url' => 'relatorio_artes.php']
                ]
            ]
        ]
    ],
    'configuracoes' => [
        'label' => 'Configurações',
        'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
        'perfis' => ['gestor', 'administrador'],
        'submenu' => [
            [
                'label' => 'Sistema',
                'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
                'itens' => [
                    ['label' => 'Configurações do Sistema', 'url' => 'configuracoes_sistema.php'],
                    ['label' => 'Usuários', 'url' => 'usuarios/usuarios.php'],
                    ['label' => 'Auditoria', 'url' => 'auditoria/auditoria.php', 'perfis' => ['gestor', 'administrador']],
                    ['label' => 'Relatórios de Auditoria', 'url' => 'auditoria/relatorio.php', 'perfis' => ['gestor', 'administrador']]
                ]
            ],
            [
                'label' => 'Empresa',
                'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                'itens' => [
                    ['label' => 'Dados da Empresa', 'url' => 'empresa.php'],
                    ['label' => 'Filiais', 'url' => 'filiais.php'],
                    ['label' => 'Documentos', 'url' => 'documentos.php']
                ]
            ]
        ]
    ]
];


// Função auxiliar para verificar permissão
function temPermissao($item, $perfilUsuario) {
    if (!isset($item['perfis'])) {
        // Se não tem perfis definidos, herda do menu pai ou permite todos
        return true;
    }
    
    // Normalizar perfil para minúsculas para comparação case-insensitive
    $perfilNormalizado = strtolower(trim($perfilUsuario ?? ''));
    $perfisNormalizados = array_map(function($p) { 
        return strtolower(trim($p)); 
    }, $item['perfis']);
    
    // Verificar se permite todos ou se o perfil está na lista
    if (in_array('todos', $perfisNormalizados)) {
        return true;
    }
    
    return in_array($perfilNormalizado, $perfisNormalizados);
}

// Função para contar badges
function contarBadge($tipo) {
    $badges = [
        'pedidos_novos' => "SELECT COUNT(*) FROM pedidos WHERE status = 'novo'",
        'orcamentos_pendentes' => "SELECT COUNT(*) FROM pedidos WHERE status = 'orcamento'",
        'aprovacoes' => "SELECT COUNT(*) FROM pedidos WHERE status = 'orcamento'",
        'fila_impressao' => "SELECT COUNT(*) FROM pedidos WHERE status = 'impressao'",
        'receber_vencidas' => "SELECT COUNT(*) FROM contas_receber WHERE vencimento < CURRENT_DATE AND status = 'aberto'",
        'pagar_vencidas' => "SELECT COUNT(*) FROM contas_pagar WHERE vencimento < CURRENT_DATE AND status = 'aberto'",
        'itens_minimo' => "SELECT COUNT(*) FROM produtos WHERE estoque_atual <= estoque_minimo"
    ];
    
    if (isset($badges[$tipo])) {
        try {
            $db = getDb();
            return $db->query($badges[$tipo])->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    return 0;
}

// Detectar página atual
$paginaAtual = basename($_SERVER['PHP_SELF']);

// Verificar se está no modo "Ver Como"
$modoVerComo = function_exists('isVerComoAtivo') ? isVerComoAtivo() : false;

// Calcular base URL para links (usar caminhos absolutos baseados no DocumentRoot)
// Obter caminho do script atual
$scriptPath = $_SERVER['SCRIPT_NAME']; // Ex: /brbandeiras/public/dashboard/dashboard.php ou /public/dashboard/dashboard.php

// Normalizar o caminho
$scriptPath = str_replace('//', '/', $scriptPath);

// Encontrar onde está 'public' no caminho para determinar o prefixo base
$publicPos = strpos($scriptPath, '/public/');
if ($publicPos !== false) {
    // Extrair tudo até '/public/' incluindo
    $basePath = substr($scriptPath, 0, $publicPos + 7); // 7 = strlen('/public/')
    $baseUrl = $basePath; // Ex: /brbandeiras/public/ ou /public/
} else {
    // Fallback: assumir que estamos em /brbandeiras/public/
    // Ou tentar detectar do DocumentRoot
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
    
    // Tentar encontrar 'public' no caminho do arquivo
    if (strpos($scriptFile, '/public/') !== false) {
        $parts = explode('/public/', $scriptFile);
        $beforePublic = $parts[0];
        // Calcular caminho relativo ao DocumentRoot
        $relativeToDocRoot = str_replace($documentRoot, '', $beforePublic);
        $baseUrl = $relativeToDocRoot . '/public/';
    } else {
        // Último fallback: usar caminho padrão
        $baseUrl = '/brbandeiras/public/';
    }
}

// Garantir que termina com /
$baseUrl = rtrim($baseUrl, '/') . '/';
?>

<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BR Bandeiras - Sistema de Gestão de Fábrica de Bandeiras</title>
    
    <!-- Preconnect para CDNs críticos (reduz latência de conexão) -->
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="dns-prefetch" href="https://unpkg.com">
    
    <!-- Preload Alpine.js para reduzir latência na cadeia crítica -->
    <link rel="preload" href="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" as="script">
    <link rel="preload" href="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js" as="script">
    
    <!-- Tailwind CSS - Build local -->
    <link rel="stylesheet" href="/public/css/tailwind.min.css">
    
    <!-- Preload da fonte crítica para otimizar cadeia de dependências -->
    <link rel="preload" href="/public/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    
    <!-- Font Awesome para ícones - Hospedado localmente -->
    <link rel="preload" href="/public/css/font-awesome/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="/public/css/font-awesome/all.min.css"></noscript>
    <style>
    /* Otimização Font Awesome: font-display swap para evitar FOIT */
    /* Sobrescrever font-display do Font Awesome para usar swap */
    @font-face {
        font-family: 'Font Awesome 6 Free';
        font-display: swap !important;
    }
    @font-face {
        font-family: 'Font Awesome 6 Brands';
        font-display: swap !important;
    }
    @font-face {
        font-family: 'Font Awesome 6 Pro';
        font-display: swap !important;
    }
    /* Aplicar font-display: swap especificamente para fa-solid-900 */
    @font-face {
        font-family: 'Font Awesome 6 Free';
        src: url('/public/webfonts/fa-solid-900.woff2') format('woff2');
        font-display: swap !important;
        font-weight: 900;
        font-style: normal;
    }
    </style>
    <!-- Alpine Collapse Plugin ANTES do Alpine Core -->
    <script src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js" defer></script>
    <!-- Alpine Core -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        /* Animações customizadas */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-slideDown {
            animation: slideDown 0.2s ease-out;
        }
        
        @keyframes pulse-border {
            0%, 100% { border-color: rgba(147, 51, 234, 0.5); }
            50% { border-color: rgba(147, 51, 234, 1); }
        }
        .pulse-border { animation: pulse-border 2s infinite; }
        
        /* Scrollbar customizada - cores mais contrastantes no dark mode */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #1f2937;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 4px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        
        /* Dark mode scrollbar com mais contraste */
        .dark .custom-scrollbar::-webkit-scrollbar-track {
            background: #0f172a;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #64748b;
            border: 1px solid #334155;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Hover com transição suave */
        .menu-item-hover {
            transition: all 0.2s ease;
        }
        
        /* Badge pulse animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .badge-pulse {
            animation: pulse 2s infinite;
        }
        
        /* Transição suave entre temas */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        /* Animação para ícone de tema */
        .theme-icon {
            transition: transform 0.3s ease;
        }
        
        button:hover .theme-icon {
            transform: scale(1.1);
        }
        
        button:active .theme-icon {
            transform: scale(1.1) rotate(180deg);
        }
        
        /* CORES DARK MODE COM MAIOR CONTRASTE */
        .dark-mode {
            background-color: #0a0a0a;
            color: #ffffff;
        }
        
        .dark-mode main {
            background-color: #0a0a0a;
        }
        
        /* Classes para elementos específicos no modo escuro - CONTRASTE AUMENTADO */
        .dark .bg-gray-50 {
            background-color: #0f172a !important;
        }
        
        .dark .bg-white {
            background-color: #1e293b !important;
            color: #f8fafc !important;
        }
        
        .dark .bg-gray-100 {
            background-color: #334155 !important;
        }
        
        .dark .bg-gray-200 {
            background-color: #475569 !important;
        }
        
        .dark .bg-gray-800 {
            background-color: #0c1621 !important;
        }
        
        .dark .bg-gray-900 {
            background-color: #020617 !important;
        }
        
        .dark .bg-gray-950 {
            background-color: #000000 !important;
        }
        
        /* Texto com contraste muito maior */
        .dark .text-gray-700 {
            color: #e2e8f0 !important;
        }
        
        .dark .text-gray-900 {
            color: #ffffff !important;
        }
        
        .dark .text-gray-600 {
            color: #cbd5e1 !important;
        }
        
        .dark .text-gray-500 {
            color: #94a3b8 !important;
        }
        
        .dark .text-gray-400 {
            color: #cbd5e1 !important;
        }
        
        .dark .text-gray-300 {
            color: #f1f5f9 !important;
        }
        
        /* Bordas mais visíveis */
        .dark .border-gray-200 {
            border-color: #475569 !important;
        }
        
        .dark .border-gray-300 {
            border-color: #64748b !important;
        }
        
        .dark .border-gray-700 {
            border-color: #334155 !important;
        }
        
        .dark .border-gray-800 {
            border-color: #1e293b !important;
        }
        
        /* Hovers com contraste aumentado */
        .dark .hover\:bg-gray-100:hover {
            background-color: #334155 !important;
        }
        
        .dark .hover\:bg-gray-700:hover {
            background-color: #1e293b !important;
        }
        
        .dark .hover\:bg-gray-800:hover {
            background-color: #0f172a !important;
        }
        
        .dark .hover\:bg-gray-900:hover {
            background-color: #0c1017 !important;
        }
        
        /* Menu específico com cores ultra contrastantes */
        .dark nav {
            background-color: #000000 !important;
            border-color: #1e293b !important;
        }
        
        .dark nav .text-gray-300 {
            color: #f8fafc !important;
        }
        
        .dark nav .hover\:text-white:hover {
            color: #ffffff !important;
        }
        
        .dark nav .hover\:bg-gray-800:hover {
            background-color: #1e293b !important;
        }
        
        /* Dropdowns com fundo muito escuro e texto claro */
        .dark nav .bg-gray-800 {
            background-color: #0f1419 !important;
            border: 1px solid #334155;
        }
        
        /* Mobile menu com contraste máximo */
        .dark .fixed.bg-gray-900 {
            background-color: #000000 !important;
        }
        
        .dark .bg-gray-600 {
            background-color: #1e293b !important;
        }
        
        /* Para páginas com fundo preto específico como dashboard */
        body.dashboard-page.dark-mode {
            background-color: #000 !important;
        }
        
        body.dashboard-page.dark-mode main {
            background-color: #000 !important;
        }
        
        /* Cards e containers em dark mode */
        .dark .shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.1);
        }
        
        .dark .shadow-xl {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        
        /* Input fields com contraste */
        .dark input[type="text"],
        .dark input[type="email"],
        .dark input[type="password"],
        .dark textarea,
        .dark select {
            background-color: #1e293b !important;
            border-color: #475569 !important;
            color: #f8fafc !important;
        }
        
        .dark input[type="text"]:focus,
        .dark input[type="email"]:focus,
        .dark input[type="password"]:focus,
        .dark textarea:focus,
        .dark select:focus {
            border-color: #0ea5e9 !important;
            background-color: #0f172a !important;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300 <?= $modoVerComo ? 'pt-12' : '' ?>">
    
    <?php if ($modoVerComo): ?>
    <!-- Banner do Modo "Ver Como" -->
    <div class="fixed top-0 left-0 right-0 z-50 bg-gradient-to-r from-purple-600 to-purple-700 text-white px-4 py-3 shadow-lg border-b-2 border-purple-800 pulse-border">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-white bg-opacity-20 p-2 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-sm">
                        Você está visualizando como: 
                        <span class="font-bold text-yellow-300"><?= htmlspecialchars($infoUsuario['nome']) ?></span>
                        <span class="text-purple-200">(<?= ucfirst(str_replace('_', ' ', $infoUsuario['perfil'])) ?>)</span>
                    </p>
                    <p class="text-xs text-purple-200">
                        Modo de validação ativo - Ações de alteração estão desabilitadas
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden md:inline-block text-xs text-purple-200">
                    Sua conta: <?= htmlspecialchars($infoUsuario['gestor_nome']) ?>
                </span>
                <a href="<?= $baseUrl ?>utils/ver_como_desativar.php" 
                   class="bg-white text-purple-700 px-4 py-2 rounded-lg font-semibold text-sm hover:bg-purple-50 transition flex items-center gap-2 shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                    </svg>
                    Voltar para minha conta
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Container principal -->
    <div class="h-full flex flex-col" x-data="{ 
        mobileMenuOpen: false, 
        menuAberto: null,
        darkMode: localStorage.getItem('darkMode') === 'true' || 
                  (localStorage.getItem('darkMode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches) || 
                  false,
        toggleTheme() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
            document.documentElement.classList.toggle('dark');
            if (this.darkMode) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        },
        init() {
            if (this.darkMode) {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark-mode');
            }
        }
    }">
        
        <!-- Header principal com gradiente verde bonito -->
        <header
          class="bg-gradient-to-r from-green-900 to-green-800 dark:from-green-950 dark:to-green-900 border-b-4 border-yellow-500 dark:border-yellow-400 px-6 lg:px-8 py-6 shadow-lg"
          x-data="{ userMenuOpen: false }"
        >
          <div class="flex items-center justify-between">
            <!-- Marca -->
            <div class="flex items-center gap-3">
              <div class="w-3 h-12 bg-yellow-500 dark:bg-yellow-400"></div>
              <div class="leading-tight">
                <h1 class="text-4xl font-extrabold text-white tracking-tight">BR BANDEIRAS</h1>
                <p class="text-xs text-green-200 dark:text-green-100 -mt-1">Sistema de Gestão de Fábrica de Bandeiras</p>
              </div>

              <!-- Espaço para Título da Página -->
              <?php if (isset($titulo) && !empty($titulo)): ?>
                <div class="ml-6 pl-6 border-l border-green-700 dark:border-green-600">
                  <span class="text-xl text-yellow-400 dark:text-yellow-300 font-semibold">
                    <?= htmlspecialchars($titulo) ?>
                  </span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Direita: Tema + Notificações + Perfil -->
            <div class="flex items-center gap-4">
              <!-- Toggle de Tema -->
              <button
                @click="toggleTheme()"
                class="p-2 text-green-200 dark:text-green-100 hover:text-white hover:bg-green-700 dark:hover:bg-green-800 rounded-lg transition-colors relative group"
                :aria-label="darkMode ? 'Mudar para tema claro' : 'Mudar para tema escuro'"
                title=""
                :title="darkMode ? 'Mudar para tema claro' : 'Mudar para tema escuro'"
              >
                <!-- Tooltip -->
                <span class="absolute -bottom-10 left-1/2 transform -translate-x-1/2 px-2 py-1 bg-gray-900 dark:bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                  <span x-text="darkMode ? 'Tema Claro' : 'Tema Escuro'"></span>
                </span>
                <svg x-show="!darkMode" class="w-6 h-6 theme-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 rotate-180"
                     x-transition:enter-end="opacity-100 rotate-0">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                  </path>
                </svg>
                <svg x-show="darkMode" class="w-6 h-6 theme-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 rotate-180"
                     x-transition:enter-end="opacity-100 rotate-0">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                  </path>
                </svg>
              </button>

              <!-- Notificações -->
              <button
                class="relative p-2 text-green-200 dark:text-green-100 hover:text-white hover:bg-green-700 dark:hover:bg-green-800 rounded-lg transition-colors"
                aria-label="Notificações"
                <?= $modoVerComo ? 'disabled' : '' ?>
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <?php if ($notificacoes = contarBadge('notificacoes')): ?>
                  <span class="absolute top-0 right-0 block h-2 w-2 bg-red-500 dark:bg-red-400 rounded-full badge-pulse"></span>
                <?php endif; ?>
              </button>

              <!-- Perfil -->
              <div class="relative">
                <button
                  @click="userMenuOpen = !userMenuOpen"
                  class="flex items-center space-x-3 text-sm p-2 rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition-colors"
                >
                  <div class="w-8 h-8 bg-yellow-500 dark:bg-yellow-400 rounded-full flex items-center justify-center">
                    <span class="text-green-900 dark:text-green-900 font-bold">
                      <?= strtoupper(substr($nomeAtual, 0, 1)) ?>
                    </span>
                  </div>
                  <div class="hidden sm:block text-left">
                    <p class="text-white font-medium">
                        <?= htmlspecialchars($nomeAtual) ?>
                        <?php if ($modoVerComo): ?>
                            <span class="text-purple-200 text-xs">(Ver Como)</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-green-200 dark:text-green-100"><?= ucfirst($perfilAtual) ?></p>
                  </div>
                  <svg class="w-4 h-4 text-green-200 dark:text-green-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                  </svg>
                </button>

                <!-- Dropdown -->
                <div
                  x-show="userMenuOpen"
                  x-transition:enter="transition ease-out duration-100"
                  x-transition:enter-start="transform opacity-0 scale-95"
                  x-transition:enter-end="transform opacity-100 scale-100"
                  x-transition:leave="transition ease-in duration-75"
                  x-transition:leave-start="transform opacity-100 scale-100"
                  x-transition:leave-end="transform opacity-0 scale-95"
                  @click.away="userMenuOpen = false"
                  class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-1 z-50 border border-gray-200 dark:border-gray-600"
                >
                  <?php if ($modoVerComo): ?>
                    <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                        <p class="text-xs text-purple-600 font-semibold">Modo "Ver Como" Ativo</p>
                        <p class="text-xs text-gray-500">Perfil: <?= ucfirst(str_replace('_', ' ', $infoUsuario['perfil'])) ?></p>
                    </div>
                    <a href="<?= $baseUrl ?>utils/ver_como_desativar.php" class="block px-4 py-2 text-sm text-purple-700 hover:bg-purple-50 dark:hover:bg-purple-900/20">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                        </svg>
                        Voltar para minha conta
                    </a>
                  <?php else: ?>
                    <a href="<?= $baseUrl ?>usuarios/perfil.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white">Meu Perfil</a>
                    <a href="<?= $baseUrl ?>usuarios/configuracoes_usuario.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white">Configurações</a>
                  <?php endif; ?>
                  <hr class="my-1 border-gray-200 dark:border-gray-600">
                  <a href="<?= $baseUrl ?>logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-800 dark:hover:text-red-300">Sair</a>
                </div>
              </div>
            </div>
          </div>
        </header>

        <!-- Botão menu mobile (no canto superior esquerdo da barra de navegação) -->
        <button @click="mobileMenuOpen = true" 
                class="lg:hidden fixed left-4 top-[104px] z-40 p-2 bg-gray-800 dark:bg-black text-white rounded-md shadow-lg border border-gray-600 dark:border-gray-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        
        <!-- Barra de navegação principal -->
        <nav class="bg-gray-900 dark:bg-black shadow-sm border-b border-gray-700 dark:border-gray-600">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-12">
                    <!-- Menu desktop -->
                    <div class="hidden lg:flex lg:space-x-1">
                        <?php foreach ($menuCompleto as $key => $menu): ?>
                            <?php if (!temPermissao($menu, $perfilAtual)) continue; ?>
                            
                            <?php if (isset($menu['submenu'])): ?>
                            <!-- Menu com submenu -->
                            <div class="relative"
                                 @mouseenter="menuAberto = '<?= $key ?>'"
                                 @mouseleave="menuAberto = null">
                                <button class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 dark:text-gray-100 hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 menu-item-hover<?= (isset($menu['url']) && $paginaAtual == $menu['url']) ? ' bg-gray-800 dark:bg-gray-800 text-white border-b-2 border-yellow-500 dark:border-yellow-400' : '' ?>">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $menu['icon'] ?>"></path>
                                    </svg>
                                    <?= $menu['label'] ?>
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                
                                <!-- Dropdown de submenu -->
                                <div x-show="menuAberto === '<?= $key ?>'"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     class="absolute left-0 mt-0 w-64 bg-gray-800 dark:bg-gray-900 rounded-b-lg shadow-xl z-50 border border-gray-700 dark:border-gray-600">
                                    <?php foreach ($menu['submenu'] as $grupo): ?>
                                        <?php if (is_array($grupo) && isset($grupo['label'])): ?>
                                        <?php 
                                        // Verificar se o grupo tem acesso permitido
                                        $grupoTemAcesso = true;
                                        if (isset($grupo['perfis']) && !empty($grupo['perfis'])) {
                                            $grupoTemAcesso = temPermissao($grupo, $perfilAtual);
                                        }
                                        
                                        if (!$grupoTemAcesso) {
                                            continue; // Pular este grupo se não tiver acesso
                                        }
                                        
                                        // Coletar itens com acesso para verificar se o grupo não está vazio
                                        $itensComAcesso = [];
                                        foreach ($grupo['itens'] as $item) {
                                            $temAcesso = false;
                                            if (isset($item['perfis']) && !empty($item['perfis'])) {
                                                $temAcesso = temPermissao($item, $perfilAtual);
                                            } elseif (isset($grupo['perfis']) && !empty($grupo['perfis'])) {
                                                $temAcesso = temPermissao($grupo, $perfilAtual);
                                            } else {
                                                $temAcesso = temPermissao($menu, $perfilAtual);
                                            }
                                            if ($temAcesso) {
                                                $itensComAcesso[] = $item;
                                            }
                                        }
                                        
                                        // Se não houver itens com acesso, pular o grupo
                                        if (empty($itensComAcesso)) {
                                            continue;
                                        }
                                        ?>
                                        <div class="px-4 py-2">
                                            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-300 uppercase tracking-wider flex items-center">
                                                <?php if (isset($grupo['icon'])): ?>
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $grupo['icon'] ?>"></path>
                                                </svg>
                                                <?php endif; ?>
                                                <?= $grupo['label'] ?>
                                            </h3>
                                            <div class="mt-2 space-y-1">
                                                <?php foreach ($itensComAcesso as $item): ?>
                                                    <a href="<?= isset($item['url']) ? $baseUrl . $item['url'] : '#' ?>" 
                                                       class="group flex items-center justify-between px-3 py-2 text-sm text-gray-300 dark:text-gray-200 rounded hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white">
                                                        <span><?= $item['label'] ?></span>
                                                        <?php if (isset($item['badge']) && $badge = contarBadge($item['badge'])): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-500 dark:bg-yellow-400 text-gray-900">
                                                            <?= $badge ?>
                                                        </span>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php if ($grupo !== end($menu['submenu'])): ?>
                                        <div class="my-2 border-t border-gray-700 dark:border-gray-600"></div>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Menu sem submenu -->
                            <a href="<?= isset($menu['url']) ? $baseUrl . $menu['url'] : '#' ?>"
                               class="flex items-center px-4 py-3 text-sm font-medium text-gray-300 dark:text-gray-100 hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 menu-item-hover<?= (isset($menu['url']) && $paginaAtual == $menu['url']) ? ' bg-gray-800 dark:bg-gray-800 text-white border-b-2 border-yellow-500 dark:border-yellow-400' : '' ?>">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $menu['icon'] ?>"></path>
                                </svg>
                                <?= $menu['label'] ?>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Ações rápidas (desktop) -->
                    <div class="hidden lg:flex lg:items-center lg:space-x-2">
                    
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Menu mobile -->
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="lg:hidden fixed inset-0 z-50">
            
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75 dark:bg-black dark:bg-opacity-80" @click="mobileMenuOpen = false"></div>
            
            <!-- Menu lateral -->
            <div x-show="mobileMenuOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="-translate-x-full"
                 class="fixed inset-y-0 left-0 flex flex-col w-full max-w-xs bg-gray-900 dark:bg-black shadow-xl border-r border-gray-700 dark:border-gray-600">
                
                <!-- Header do menu mobile -->
                <div class="flex items-center justify-between px-4 py-5 bg-gradient-to-r from-green-900 to-green-800 dark:from-green-950 dark:to-green-900 border-b border-gray-700 dark:border-gray-600">
                    <div class="flex items-center">
                        <div class="w-1 h-8 bg-yellow-500 dark:bg-yellow-400 mr-3"></div>
                        <div>
                            <h2 class="text-lg font-bold text-white">BR Bandeiras</h2>
                            <p class="text-xs text-green-200 dark:text-green-100">
                                <?= htmlspecialchars($nomeAtual) ?>
                                <?php if ($modoVerComo): ?>
                                    <span class="text-purple-200">(Ver Como)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <button @click="mobileMenuOpen = false" class="p-2 rounded-md text-green-200 dark:text-green-100 hover:text-white hover:bg-green-700 dark:hover:bg-green-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Conteúdo do menu mobile -->
                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    <nav class="px-2 py-4 space-y-1">
                        <?php foreach ($menuCompleto as $key => $menu): ?>
                            <?php if (!temPermissao($menu, $perfilAtual)) continue; ?>
                            
                            <?php if (isset($menu['submenu'])): ?>
                            <!-- Menu com submenu no mobile -->
                            <div x-data="{ submenuOpen: false }">
                                <button @click="submenuOpen = !submenuOpen"
                                        class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-300 dark:text-gray-100 hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded-md">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $menu['icon'] ?>"></path>
                                        </svg>
                                        <?= $menu['label'] ?>
                                    </div>
                                    <svg class="w-4 h-4 transition-transform" 
                                         :class="submenuOpen ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                
                                <div x-show="submenuOpen" 
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 transform scale-95"
                                     x-transition:enter-end="opacity-100 transform scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 transform scale-100"
                                     x-transition:leave-end="opacity-0 transform scale-95"
                                     class="mt-1">
                                    <?php foreach ($menu['submenu'] as $grupo): ?>
                                        <?php if (is_array($grupo) && isset($grupo['label'])): ?>
                                        <?php 
                                        // Verificar se o grupo tem acesso permitido (mobile)
                                        $grupoTemAcessoMobile = true;
                                        if (isset($grupo['perfis']) && !empty($grupo['perfis'])) {
                                            $grupoTemAcessoMobile = temPermissao($grupo, $perfilAtual);
                                        }
                                        
                                        if (!$grupoTemAcessoMobile) {
                                            continue; // Pular este grupo se não tiver acesso
                                        }
                                        
                                        // Coletar itens com acesso para verificar se o grupo não está vazio (mobile)
                                        $itensComAcessoMobile = [];
                                        foreach ($grupo['itens'] as $item) {
                                            $temAcesso = false;
                                            if (isset($item['perfis']) && !empty($item['perfis'])) {
                                                $temAcesso = temPermissao($item, $perfilAtual);
                                            } elseif (isset($grupo['perfis']) && !empty($grupo['perfis'])) {
                                                $temAcesso = temPermissao($grupo, $perfilAtual);
                                            } else {
                                                $temAcesso = temPermissao($menu, $perfilAtual);
                                            }
                                            if ($temAcesso) {
                                                $itensComAcessoMobile[] = $item;
                                            }
                                        }
                                        
                                        // Se não houver itens com acesso, pular o grupo
                                        if (empty($itensComAcessoMobile)) {
                                            continue;
                                        }
                                        ?>
                                        <div class="ml-8 mt-2">
                                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                <?= $grupo['label'] ?>
                                            </h4>
                                            <?php foreach ($itensComAcessoMobile as $item): ?>
                                                <a href="<?= isset($item['url']) ? $baseUrl . $item['url'] : '#' ?>" 
                                                   class="flex items-center justify-between px-3 py-2 text-sm text-gray-400 dark:text-gray-300 hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded">
                                                    <span><?= $item['label'] ?></span>
                                                    <?php if (isset($item['badge']) && $badge = contarBadge($item['badge'])): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-500 dark:bg-yellow-400 text-gray-900">
                                                        <?= $badge ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Menu sem submenu no mobile -->
                            <a href="<?= isset($menu['url']) ? $baseUrl . $menu['url'] : '#' ?>"
                               class="flex items-center px-3 py-2 text-sm font-medium text-gray-300 dark:text-gray-100 hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded-md">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $menu['icon'] ?>"></path>
                                </svg>
                                <?= $menu['label'] ?>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                </div>
                
                <!-- Footer do menu mobile -->
                <div class="border-t border-gray-700 dark:border-gray-600 px-4 py-4 space-y-3">
                    <!-- Toggle de Tema no Mobile -->
                    <button @click="toggleTheme()" 
                            class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-300 dark:text-gray-100 hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded-md">
                        <div class="flex items-center">
                            <svg x-show="!darkMode" class="w-5 h-5 mr-3 theme-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                            <svg x-show="darkMode" class="w-5 h-5 mr-3 theme-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                                </path>
                            </svg>
                            <span x-text="darkMode ? 'Tema Escuro' : 'Tema Claro'"></span>
                        </div>
                        <svg x-show="darkMode" class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                        <svg x-show="!darkMode" class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                    
                    <?php if ($modoVerComo): ?>
                    <a href="<?= $baseUrl ?>utils/ver_como_desativar.php" 
                       class="flex items-center px-3 py-2 text-sm font-medium text-purple-400 hover:text-purple-300 hover:bg-gray-800 dark:hover:bg-gray-800 rounded-md">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                        </svg>
                        Voltar para minha conta
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?= $baseUrl ?>logout.php" 
                       class="flex items-center px-3 py-2 text-sm font-medium text-red-400 hover:text-red-300 hover:bg-gray-800 dark:hover:bg-gray-800 rounded-md">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Sair do Sistema
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Conteúdo principal -->
        <main class="flex-1 overflow-y-auto flex flex-col bg-gray-50 dark:bg-gray-900">
            <!-- O conteúdo das páginas será inserido aqui -->