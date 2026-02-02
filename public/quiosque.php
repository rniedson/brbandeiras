<?php
/**
 * Quiosque - Visualização pública para TV
 * Layout moderno com identidade BR Bandeiras
 * Versão: 2.0 - Redesign Visual
 * Não requer autenticação
 */

require_once '../app/config.php';

// Buscar próximas entregas - Incluir todos os pedidos ativos
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.numero,
            p.prazo_entrega,
            c.nome as cliente_nome,
            COALESCE(c.celular, c.whatsapp, c.telefone) as cliente_telefone,
            p.urgente,
            p.status,
            p.created_at,
            p.updated_at,
            u.nome as vendedor_nome,
            ua.nome as arte_finalista_nome,
            (
                SELECT pc.nome 
                FROM pedido_itens pi 
                LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id 
                WHERE pi.pedido_id = p.id 
                ORDER BY pi.id 
                LIMIT 1
            ) as primeiro_produto,
            (
                SELECT pa.caminho 
                FROM pedido_arquivos pa 
                WHERE pa.pedido_id = p.id 
                AND LOWER(pa.nome_arquivo) ~ '\\.(jpg|jpeg|png|gif|webp)$'
                ORDER BY pa.created_at DESC
                LIMIT 1
            ) as imagem_caminho,
            GREATEST(
                p.updated_at,
                COALESCE((
                    SELECT MAX(l.created_at) 
                    FROM logs_sistema l 
                    WHERE l.detalhes LIKE '%Pedido #' || p.numero || '%'
                ), p.updated_at)
            ) as ultima_atualizacao
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios ua ON pa.arte_finalista_id = ua.id
        WHERE p.status NOT IN ('entregue', 'cancelado')
        ORDER BY 
            ultima_atualizacao DESC
        LIMIT 15
    ");
    $proximas_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar próximas entregas: " . $e->getMessage());
    $proximas_entregas = [];
}

// Função para formatar tempo relativo
function formatarTempoRelativo($dataHora) {
    if (!$dataHora) return '—';
    $agora = new DateTime();
    $data = new DateTime($dataHora);
    $diff = $agora->diff($data);
    
    if ($diff->days > 0) {
        return $diff->days . 'd';
    } elseif ($diff->h > 0) {
        return $diff->h . 'h';
    } else {
        return max(1, $diff->i) . 'min';
    }
}

// Função para formatar identificação do cliente (primeiro nome + 4 últimos dígitos)
function formatarClienteQuiosque($nome, $telefone) {
    // Pegar primeiro nome
    $primeiroNome = $nome ? explode(' ', trim($nome))[0] : 'Cliente';
    
    // Pegar últimos 4 dígitos do telefone
    $telefoneNumeros = preg_replace('/\D/', '', $telefone ?? '');
    $ultimos4 = strlen($telefoneNumeros) >= 4 ? substr($telefoneNumeros, -4) : '****';
    
    return $primeiroNome . ' - ' . $ultimos4;
}

$empresa_nome = defined('NOME_EMPRESA') ? NOME_EMPRESA : 'BR Bandeiras';

// ============================================
// ARRAYS PARA TELAS ESPECIAIS DO CARROSSEL
// ============================================

// Array de curiosidades sobre bandeiras (reutilizado da tela de login)
$curiosidades = [
    "Você sabia que a bandeira branca só virou regra internacional de trégua na Convenção de Haia de 1899?",
    "Sabia que a bandeira do México mostra a lenda mexica da águia e da serpente sobre um nopal?",
    "Você sabia que a bandeira do Paraguai tem frente e verso diferentes?",
    "Sabia que a bandeira do Nepal é a única nacional que não é retangular?",
    "Você sabia que as bandeiras da Suíça e do Vaticano são oficialmente quadradas?",
    "Sabia que a bandeira da Líbia já foi apenas um retângulo verde sólido?",
    "Você sabia que a bandeira de Bangladesh desloca o disco para parecer centrado ao vento?",
    "Sabia que a bandeira de Palau também desloca o disco para o mastro?",
    "Você sabia que a bandeira da Arábia Saudita não vai a meio-mastro por trazer o credo islâmico?",
    "Sabia que a bandeira de Belize é uma das poucas bandeiras nacionais que incluem pessoas?",
    "Você sabia que a bandeira de Dominica usa roxo no papagaio sisserou?",
    "Sabia que a bandeira da Guatemala exibe rifles cruzados e o pássaro quetzal?",
    "Você sabia que a bandeira da República Dominicana traz uma Bíblia aberta no brasão?",
    "Sabia que a bandeira do Camboja estampa Angkor Wat em destaque?",
    "Você sabia que a bandeira do Chade é quase idêntica à bandeira da Romênia?",
    "Sabia que a bandeira de Mônaco quase se confunde com a bandeira da Indonésia?",
    "Você sabia que a bandeira da Jamaica não usa vermelho, branco ou azul?",
    "Sabia que a bandeira de Moçambique inclui um fuzil AK-47?",
    "Você sabia que a bandeira das Filipinas vira de guerra quando o vermelho fica em cima?",
    "Sabia que a bandeira da Dinamarca é a mais antiga em uso contínuo?",
    "Você sabia que a bandeira de Gales com o dragão não aparece na bandeira do Reino Unido?",
    "Sabia que a bandeira do Laos simboliza a lua cheia sobre o rio Mekong com o disco branco?",
    "Você sabia que a bandeira do Brasil mostra o céu do Rio em 15/11/1889 com constelações espelhadas?",
    "Sabia que a bandeira do Brasil dá a cada estrela um estado e o Distrito Federal?",
    "Você sabia que a bandeira da Etiópia inspirou as cores pan-africanas verde amarelo e vermelho?",
    "Sabia que as cores pan-árabes em muitas bandeiras vêm da Revolta Árabe de 1916?",
    "Você sabia que a bandeira do Afeganistão mudou muitas vezes no último século?",
    "Sabia que a bandeira do Japão só teve medidas do sol padronizadas por lei em 1999?",
    "Você sabia que a bandeira do Qatar tem proporção incomum de 11:28?",
    "Sabia que a cor vinho da bandeira do Qatar surgiu de pigmentos que escureciam no sol?",
    "Você sabia que a bandeira do Chipre traz o mapa da ilha com ramos de oliveira?",
    "Sabia que a bandeira do Alasca foi criada por um estudante de 13 anos em 1927?",
    "Você sabia que a bandeira do Brasil exibe o lema positivista 'Ordem e Progresso'?",
    "Sabia que muitas bandeiras com texto sagrado são confeccionadas em dupla face para evitar escrita espelhada?"
];

// Array de produtos da BR Bandeiras
$produtos = [
    ["nome" => "Bandeiras Oficiais", "descricao" => "Bandeiras do Brasil, estados e municípios com qualidade premium", "icone" => "🇧🇷"],
    ["nome" => "Bandeiras Personalizadas", "descricao" => "Sua marca estampada em bandeiras de alta durabilidade", "icone" => "🎨"],
    ["nome" => "Wind Banners", "descricao" => "Comunicação visual que se destaca em eventos e pontos de venda", "icone" => "🚩"],
    ["nome" => "Faixas e Banners", "descricao" => "Impressão digital em lona com acabamento profissional", "icone" => "📢"],
    ["nome" => "Mastros e Suportes", "descricao" => "Estruturas robustas para fixação de bandeiras", "icone" => "🏛️"],
    ["nome" => "Toalhas de Mesa", "descricao" => "Sublimação total para eventos e feiras", "icone" => "🎪"],
    ["nome" => "Galhardetes", "descricao" => "Perfeitos para decoração e brindes corporativos", "icone" => "🏆"],
    ["nome" => "Flâmulas", "descricao" => "Ideais para troféus, medalhas e reconhecimentos", "icone" => "🎖️"],
    ["nome" => "Bandeiras de Mesa", "descricao" => "Elegância para seu escritório e sala de reuniões", "icone" => "💼"],
    ["nome" => "Uniformes Sublimados", "descricao" => "Camisetas e uniformes com impressão de alta definição", "icone" => "👕"]
];

// Array de branding BR Bandeiras
$branding = [
    ["slogan" => "Qualidade que Tremula", "mensagem" => "Há mais de 20 anos levando sua marca mais longe"],
    ["slogan" => "Cores que Comunicam", "mensagem" => "Impressão digital de alta definição para resultados impactantes"],
    ["slogan" => "Tradição e Inovação", "mensagem" => "Combinando técnicas tradicionais com tecnologia de ponta"],
    ["slogan" => "Sua Marca, Nossa Missão", "mensagem" => "Comprometidos com a excelência em cada projeto"],
    ["slogan" => "Do Projeto à Entrega", "mensagem" => "Acompanhamento completo do seu pedido"],
    ["slogan" => "Feito para Durar", "mensagem" => "Materiais premium que resistem ao tempo e às intempéries"]
];

// Converter arrays para JSON para uso no JavaScript
$curiosidadesJson = json_encode($curiosidades, JSON_UNESCAPED_UNICODE);
$produtosJson = json_encode($produtos, JSON_UNESCAPED_UNICODE);
$brandingJson = json_encode($branding, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Quiosque - <?= htmlspecialchars($empresa_nome) ?></title>
    
    <!-- Google Fonts - Inter + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <style>
        /* ============================================
           VARIÁVEIS CSS - SINGLE SOURCE OF TRUTH
           Paleta baseada na identidade BR Bandeiras
           ============================================ */
        :root {
            /* Cores primárias */
            --color-brand-yellow: #f5b800;
            --color-brand-green: #22c55e;
            --color-brand-green-dark: #0d3d1a;
            
            /* Backgrounds - tons de verde escuro */
            --color-bg-primary: #031a08;
            --color-bg-secondary: rgba(13, 61, 26, 0.4);
            --color-bg-elevated: rgba(13, 61, 26, 0.6);
            --color-bg-card: rgba(10, 40, 18, 0.7);
            --color-bg-hover: rgba(245, 184, 0, 0.1);
            
            /* Texto */
            --color-text-primary: #ffffff;
            --color-text-secondary: rgba(255, 255, 255, 0.8);
            --color-text-muted: rgba(255, 255, 255, 0.6);
            
            /* Status */
            --color-status-arte: #f5b800;
            --color-status-producao: #22c55e;
            --color-status-pronto: #4ade80;
            --color-status-urgente: #ef4444;
            --color-status-hoje: #f59e0b;
            
            /* Bordas */
            --color-border-subtle: rgba(245, 184, 0, 0.15);
            --color-border-medium: rgba(245, 184, 0, 0.25);
            --color-border-accent: rgba(245, 184, 0, 0.4);
            
            /* Espaçamento (escala de 8px) */
            --spacing-xs: 0.5rem;
            --spacing-sm: 0.75rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            
            /* Tipografia */
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', 'Consolas', 'Monaco', monospace;
            
            /* Tamanhos de fonte (otimizado para visualização de longe - aumentado 50%) */
            --text-xs: 1.875rem;
            --text-sm: 2.25rem;
            --text-base: 2.625rem;
            --text-lg: 3rem;
            --text-xl: 3.75rem;
            --text-2xl: 4.5rem;
            --text-3xl: 6.75rem;
            
            /* Transições */
            --transition-fast: 0.15s ease;
            --transition-normal: 0.3s ease;
        }

        /* ============================================
           RESET E BASE
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: linear-gradient(135deg, #021a08 0%, #0d3d1a 25%, #0a2d12 50%, #0d3d1a 75%, #021a08 100%);
            background-attachment: fixed;
            color: var(--color-text-primary);
            min-height: 100vh;
            overflow: hidden;
            line-height: 1.5;
        }

        /* ============================================
           LAYOUT PRINCIPAL
           ============================================ */
        .quiosque-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: var(--spacing-md);
        }

        /* ============================================
           HEADER COM NOME DA EMPRESA E TÍTULOS
           ============================================ */
        .quiosque-header {
            background: var(--color-bg-secondary);
            border: 1px solid var(--color-border-subtle);
            border-radius: 8px 8px 0 0;
            backdrop-filter: blur(10px);
            flex-shrink: 0;
        }

        /* Linha superior - Hora, Nome da empresa e Status */
        .header-brand {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-sm) var(--spacing-lg);
            border-bottom: 1px solid var(--color-border-subtle);
        }

        .brand-left,
        .brand-right {
            flex: 1;
            display: flex;
            align-items: center;
        }

        .brand-left {
            justify-content: flex-start;
        }

        .brand-right {
            justify-content: flex-end;
        }

        .brand-name {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--color-text-primary);
            text-transform: uppercase;
            letter-spacing: 0.15em;
        }

        .brand-highlight {
            color: var(--color-brand-yellow);
        }

        .current-time {
            font-size: var(--text-lg);
            font-weight: 600;
            color: var(--color-text-primary);
            font-family: var(--font-mono);
        }

        .update-indicator {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: var(--text-sm);
            color: var(--color-text-secondary);
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            background: var(--color-brand-green);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--color-brand-green);
        }

        /* Animação do pulse dot */
        @media (prefers-reduced-motion: no-preference) {
            .pulse-dot {
                animation: pulse 2s ease-in-out infinite;
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.85); }
        }

        /* Linha inferior - Títulos das colunas */
        .header-columns {
            display: table;
            table-layout: fixed;
            width: 100%;
        }

        .header-col {
            display: table-cell;
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--color-brand-yellow);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: var(--spacing-md) var(--spacing-lg);
            vertical-align: middle;
        }
        
        .header-icon {
            width: 1.75rem;
            height: 1.75rem;
            stroke-width: 2;
            display: inline-block;
            vertical-align: middle;
        }
        
        @media (min-width: 1920px) {
            .header-icon {
                width: 2.25rem;
                height: 2.25rem;
            }
        }
        
        @media (min-width: 2560px) {
            .header-icon {
                width: 2.75rem;
                height: 2.75rem;
            }
        }
        
        /* As larguras são herdadas das classes .col-* definidas abaixo */

        .header-special-title {
            text-align: center;
            width: 100%;
        }

        .header-special-title #filterTitle {
            font-size: var(--text-xl);
            font-weight: 600;
            color: var(--color-text-primary);
            transition: opacity 0.2s ease;
        }

        /* ============================================
           SEÇÃO DE ENTREGAS - TABELA
           ============================================ */
        .entregas-section {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Indicadores de navegação (agora no footer) */
        .filter-indicators {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 8px 20px;
            background: var(--color-bg-card);
            border-radius: 25px;
            border: 1px solid var(--color-border-subtle);
        }

        .filter-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--color-text-muted);
            opacity: 0.4;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .filter-dot:hover {
            opacity: 0.7;
            transform: scale(1.1);
        }

        .filter-dot.active {
            opacity: 1;
            transform: scale(1.2);
            box-shadow: 0 0 8px currentColor;
        }

        .filter-dot[data-filter="todos"] { background: var(--color-text-primary); }
        .filter-dot[data-filter="todos"].active { box-shadow: 0 0 8px var(--color-text-primary); }
        
        .filter-dot[data-filter="arte"] { background: var(--color-status-arte); }
        .filter-dot[data-filter="arte"].active { box-shadow: 0 0 8px var(--color-status-arte); }
        
        .filter-dot[data-filter="orcamento"] { background: #3b82f6; }
        .filter-dot[data-filter="orcamento"].active { box-shadow: 0 0 8px #3b82f6; }
        
        .filter-dot[data-filter="producao"] { background: var(--color-status-producao); }
        .filter-dot[data-filter="producao"].active { box-shadow: 0 0 8px var(--color-status-producao); }

        .filter-separator {
            width: 1px;
            height: 16px;
            background: var(--color-border-medium);
            margin: 0 4px;
        }
        
        .filter-dot[data-filter="curiosidade"] { background: #a855f7; }
        .filter-dot[data-filter="curiosidade"].active { box-shadow: 0 0 8px #a855f7; }
        
        .filter-dot[data-filter="produto"] { background: #ec4899; }
        .filter-dot[data-filter="produto"].active { box-shadow: 0 0 8px #ec4899; }
        
        .filter-dot[data-filter="branding"] { background: var(--color-brand-yellow); }
        .filter-dot[data-filter="branding"].active { box-shadow: 0 0 8px var(--color-brand-yellow); }

        /* Animação de transição da tabela */
        .entregas-table-container {
            transition: opacity 0.4s ease;
        }

        .entregas-table-container.transitioning {
            opacity: 0.3;
        }

        /* ============================================
           TELAS ESPECIAIS DO CARROSSEL
           ============================================ */
        .tela-especial {
            display: none;
            flex: 1;
            background: var(--color-bg-card);
            border-radius: 12px;
            border: 1px solid var(--color-border-subtle);
            backdrop-filter: blur(10px);
            padding: var(--spacing-xl);
            animation: fadeInScale 0.6s ease-out;
        }

        .tela-especial.active {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeInScale {
            from { 
                opacity: 0; 
                transform: scale(0.95);
            }
            to { 
                opacity: 1; 
                transform: scale(1);
            }
        }

        /* Tela de Curiosidade */
        .tela-curiosidade {
            text-align: center;
        }

        .curiosidade-icone {
            font-size: 5rem;
            margin-bottom: var(--spacing-lg);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .curiosidade-label {
            font-size: var(--text-sm);
            color: var(--color-brand-yellow);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: var(--spacing-md);
            font-weight: 600;
        }

        .curiosidade-texto {
            font-size: var(--text-xl);
            color: var(--color-text-primary);
            line-height: 1.6;
            max-width: 90%;
            font-weight: 500;
        }

        /* Tela de Produto */
        .tela-produto {
            text-align: center;
        }

        .produto-icone {
            font-size: 6rem;
            margin-bottom: var(--spacing-lg);
            filter: drop-shadow(0 0 20px rgba(245, 184, 0, 0.3));
        }

        .produto-nome {
            font-size: var(--text-2xl);
            color: var(--color-brand-yellow);
            font-weight: 700;
            margin-bottom: var(--spacing-md);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .produto-descricao {
            font-size: var(--text-lg);
            color: var(--color-text-secondary);
            max-width: 600px;
            line-height: 1.5;
        }

        .produto-cta {
            margin-top: var(--spacing-xl);
            padding: var(--spacing-md) var(--spacing-xl);
            background: linear-gradient(135deg, var(--color-brand-yellow), #e6a800);
            color: var(--color-bg-primary);
            font-weight: 700;
            font-size: var(--text-base);
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* Tela de Branding */
        .tela-branding {
            text-align: center;
            background: linear-gradient(135deg, rgba(13, 61, 26, 0.8), rgba(3, 26, 8, 0.9));
        }

        .branding-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
            opacity: 0;
            transform: scale(0.8);
        }
        
        .tela-branding.active .branding-logo {
            animation: brandingLogoIn 1s ease-out 0.2s forwards;
        }
        
        @keyframes brandingLogoIn {
            0% { 
                opacity: 0; 
                transform: scale(0.8);
            }
            100% { 
                opacity: 1; 
                transform: scale(1);
            }
        }

        .branding-logo-text {
            font-size: var(--text-3xl);
            font-weight: 700;
            color: var(--color-text-primary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .branding-logo-text span {
            color: var(--color-brand-yellow);
        }

        .branding-logo-bar {
            width: 60px;
            height: 30px;
            background: var(--color-brand-yellow);
            border-radius: 4px;
        }
        
        .tela-branding.active .branding-logo-bar {
            animation: brandingBarPulse 2s ease-in-out 2s infinite;
        }
        
        @keyframes brandingBarPulse {
            0%, 100% { 
                transform: scaleX(1);
                box-shadow: 0 0 0 rgba(245, 184, 0, 0);
            }
            50% { 
                transform: scaleX(1.1);
                box-shadow: 0 0 30px rgba(245, 184, 0, 0.5);
            }
        }

        .branding-slogan {
            font-size: var(--text-2xl);
            color: var(--color-brand-yellow);
            font-weight: 600;
            margin-bottom: var(--spacing-lg);
            font-style: italic;
            opacity: 0;
            transform: translateY(30px);
        }
        
        .tela-branding.active .branding-slogan {
            animation: brandingSlideUp 0.8s ease-out 1s forwards;
        }
        
        @keyframes brandingSlideUp {
            0% { 
                opacity: 0; 
                transform: translateY(30px);
            }
            100% { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        .branding-mensagem {
            font-size: var(--text-lg);
            color: var(--color-text-secondary);
            opacity: 0;
            transform: translateY(20px);
        }
        
        .tela-branding.active .branding-mensagem {
            animation: brandingSlideUp 0.8s ease-out 1.5s forwards;
        }
            max-width: 700px;
            line-height: 1.6;
        }

        .branding-contato {
            margin-top: var(--spacing-xl);
            display: flex;
            gap: var(--spacing-lg);
            color: var(--color-text-muted);
            font-size: var(--text-sm);
        }

        .branding-contato span {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        /* Ocultar tabela quando tela especial estiver ativa */
        .entregas-table-container.hidden {
            display: none;
        }

        /* Container da tabela */
        .entregas-table-container {
            flex: 1;
            background: var(--color-bg-card);
            border-radius: 12px;
            border: 1px solid var(--color-border-subtle);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        /* Tabela */
        .entregas-table {
            width: 100%;
            border-collapse: collapse;
        }

        .entregas-table thead {
            background: var(--color-bg-elevated);
            border-bottom: 2px solid var(--color-brand-yellow);
        }

        .entregas-table th {
            padding: var(--spacing-md) var(--spacing-lg);
            text-align: left;
            font-size: var(--text-xs);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-brand-yellow);
        }

        .entregas-table {
            table-layout: fixed;
            width: 100%;
        }

        .entregas-table thead,
        .entregas-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .entregas-table tbody {
            display: block;
            overflow-y: auto;
            max-height: calc(100vh - 400px);
        }

        .entregas-table th,
        .entregas-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            word-wrap: break-word;
        }

        /* Larguras das colunas - usadas tanto no header quanto na tabela */
        .col-imagem { width: 10%; text-align: center; }
        .col-produto { width: 30%; }
        .col-vendedor { width: 18%; }
        .col-arte { width: 18%; }
        .col-status { width: 14%; text-align: center; }
        .col-atualizado { width: 10%; text-align: center; }

        .entregas-table tbody tr {
            transition: background var(--transition-fast);
        }

        .entregas-table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.02);
        }

        .entregas-table tbody tr:hover {
            background: var(--color-bg-hover);
        }

        .entregas-table tbody tr.urgente {
            border-left: 4px solid var(--color-status-urgente);
        }

        /* Animação de entrada das linhas */
        @media (prefers-reduced-motion: no-preference) {
            .entregas-table tbody tr {
                animation: rowFadeIn 0.3s ease-out backwards;
            }
        }

        @keyframes rowFadeIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Células da tabela */
        .entregas-table td {
            padding: var(--spacing-md) var(--spacing-lg);
            font-size: var(--text-sm);
            color: var(--color-text-primary);
            border-bottom: 1px solid var(--color-border-subtle);
            vertical-align: middle;
        }

        .miniatura-container {
            width: 128px;
            height: 128px;
            border-radius: 10px;
            overflow: hidden;
            background: var(--color-bg-elevated);
            border: 1px solid var(--color-border-subtle);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .miniatura-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .miniatura-placeholder {
            color: var(--color-text-muted);
            font-size: 48px;
        }

        .pedido-numero {
            font-family: var(--font-mono);
            font-weight: 700;
            color: var(--color-brand-yellow);
            font-size: var(--text-base);
        }

        .cliente-nome,
        .vendedor-nome,
        .arte-finalista-nome,
        .produto-nome {
            display: block;
            overflow: hidden;
            white-space: normal;
            word-wrap: break-word;
            max-width: 100%;
            line-height: 1.3;
        }

        .cliente-nome {
            color: var(--color-text-primary);
            font-weight: 500;
        }

        .vendedor-nome {
            color: var(--color-text-secondary);
            font-weight: 500;
        }

        .arte-finalista-nome {
            color: var(--color-status-arte);
            font-weight: 500;
        }

        .produto-nome {
            color: var(--color-text-secondary);
            font-size: var(--text-xs);
        }

        .tempo-atualizado {
            font-family: var(--font-mono);
            color: var(--color-text-muted);
            font-size: var(--text-sm);
            text-align: center;
            display: block;
        }

        /* ============================================
           STATUS BADGES
           ============================================ */
        .status-badge {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: var(--text-xs);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-arte {
            background: rgba(245, 184, 0, 0.2);
            color: var(--color-status-arte);
        }

        .status-producao {
            background: rgba(34, 197, 94, 0.2);
            color: var(--color-status-producao);
        }

        .status-pronto {
            background: rgba(74, 222, 128, 0.2);
            color: var(--color-status-pronto);
        }

        .status-orcamento {
            background: rgba(245, 184, 0, 0.15);
            color: var(--color-brand-yellow);
        }

        /* ============================================
           ESTADO VAZIO
           ============================================ */
        .no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-xl) var(--spacing-xl);
            color: var(--color-text-muted);
            font-size: var(--text-lg);
        }

        .no-data svg {
            width: 64px;
            height: 64px;
            margin-bottom: var(--spacing-md);
            opacity: 0.4;
        }

        /* ============================================
           FOOTER COM INDICADORES DE NAVEGAÇÃO
           ============================================ */
        .quiosque-footer {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: var(--spacing-sm) var(--spacing-lg);
            background: var(--color-bg-secondary);
            border-top: 1px solid var(--color-border-subtle);
            flex-shrink: 0;
        }

        /* ============================================
           SCROLLBAR ESTILIZADA
           ============================================ */
        .entregas-table tbody::-webkit-scrollbar {
            width: 6px;
        }

        .entregas-table tbody::-webkit-scrollbar-track {
            background: transparent;
        }

        .entregas-table tbody::-webkit-scrollbar-thumb {
            background: rgba(245, 184, 0, 0.3);
            border-radius: 3px;
        }

        .entregas-table tbody::-webkit-scrollbar-thumb:hover {
            background: rgba(245, 184, 0, 0.5);
        }

        /* ============================================
           RESPONSIVIDADE PARA TV MAIORES
           ============================================ */
        @media (min-width: 1920px) {
            :root {
                --text-xs: 2.25rem;
                --text-sm: 2.625rem;
                --text-base: 3rem;
                --text-lg: 3.75rem;
                --text-xl: 4.5rem;
                --text-2xl: 5.25rem;
                --text-3xl: 7.5rem;
            }

            .quiosque-header {
                padding: var(--spacing-md) var(--spacing-xl);
            }

            .header-col {
                font-size: var(--text-base);
            }

            .entregas-table td {
                padding: var(--spacing-lg) var(--spacing-xl);
            }
            
            .miniatura-container {
                width: 144px;
                height: 144px;
            }
        }

        /* 4K */
        @media (min-width: 2560px) {
            :root {
                --text-xs: 2.625rem;
                --text-sm: 3rem;
                --text-base: 3.375rem;
                --text-lg: 4.125rem;
                --text-xl: 5.25rem;
                --text-2xl: 6rem;
                --text-3xl: 9rem;
            }

            .header-col {
                font-size: var(--text-lg);
            }
            
            .miniatura-container {
                width: 192px;
                height: 192px;
            }
        }
    </style>
</head>
<body>
    <div class="quiosque-container">
        <!-- Header com nome da empresa e títulos das colunas -->
        <header class="quiosque-header">
            <!-- Linha superior com hora, nome da empresa e status -->
            <div class="header-brand">
                <div class="brand-left">
                    <span class="current-time" id="currentTime"></span>
                </div>
                <span class="brand-name"><span class="brand-highlight">BR</span> BANDEIRAS</span>
                <div class="brand-right">
                    <div class="update-indicator">
                        <div class="pulse-dot"></div>
                        <span id="updateStatus">Ao vivo</span>
                    </div>
                </div>
            </div>
            <!-- Linha inferior com títulos das colunas -->
            <div class="header-columns" id="headerColumns">
                <span class="header-col col-imagem"><i data-lucide="image" class="header-icon"></i></span>
                <span class="header-col col-produto"><i data-lucide="package" class="header-icon"></i></span>
                <span class="header-col col-vendedor"><i data-lucide="headset" class="header-icon"></i></span>
                <span class="header-col col-arte"><i data-lucide="palette" class="header-icon"></i></span>
                <span class="header-col col-status"><i data-lucide="activity" class="header-icon"></i></span>
                <span class="header-col col-atualizado"><i data-lucide="clock" class="header-icon"></i></span>
            </div>
            <!-- Título para telas especiais -->
            <div class="header-special-title" id="headerSpecialTitle" style="display: none;">
                <span id="filterTitle">Todos os Pedidos</span>
            </div>
        </header>

        <!-- Seção de Entregas -->
        <div class="entregas-section">
            <div class="entregas-table-container">
                <table class="entregas-table">
                    <tbody id="entregasTableBody">
                        <?php if (!empty($proximas_entregas)): ?>
                            <?php foreach ($proximas_entregas as $index => $entrega): ?>
                            <tr class="<?= $entrega['urgente'] ? 'urgente' : '' ?>" data-numero="<?= htmlspecialchars($entrega['numero']) ?>" style="animation-delay: <?= $index * 0.03 ?>s">
                                <td class="col-imagem">
                                    <div class="miniatura-container">
                                        <?php if (!empty($entrega['imagem_caminho'])): ?>
                                            <img src="<?= htmlspecialchars($entrega['imagem_caminho']) ?>" 
                                                 alt="Miniatura" 
                                                 loading="lazy"
                                                 onerror="this.parentElement.innerHTML='<span class=\'miniatura-placeholder\'>📋</span>'">
                                        <?php else: ?>
                                            <span class="miniatura-placeholder">📋</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="col-produto">
                                    <span class="produto-nome"><?= htmlspecialchars($entrega['primeiro_produto'] ?? 'Sem produto') ?></span>
                                </td>
                                <td class="col-vendedor">
                                    <span class="vendedor-nome"><?= htmlspecialchars($entrega['vendedor_nome'] ?? '—') ?></span>
                                </td>
                                <td class="col-arte">
                                    <span class="arte-finalista-nome"><?= htmlspecialchars($entrega['arte_finalista_nome'] ?? '—') ?></span>
                                </td>
                                <td class="col-status">
                                    <span class="status-badge status-<?= htmlspecialchars($entrega['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($entrega['status'])) ?>
                                    </span>
                                </td>
                                <td class="col-atualizado">
                                    <span class="tempo-atualizado"><?= formatarTempoRelativo($entrega['ultima_atualizacao']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-data">
                                        <svg viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>Nenhum pedido em andamento</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tela de Curiosidade -->
            <div id="telaCuriosidade" class="tela-especial tela-curiosidade">
                <div class="curiosidade-label">Você sabia?</div>
                <div id="curiosidadeTexto" class="curiosidade-texto"></div>
            </div>

            <!-- Tela de Produto -->
            <div id="telaProduto" class="tela-especial tela-produto">
                <div id="produtoIcone" class="produto-icone"></div>
                <div id="produtoNome" class="produto-nome"></div>
                <div id="produtoDescricao" class="produto-descricao"></div>
                <div class="produto-cta">Solicite um orçamento</div>
            </div>

            <!-- Tela de Branding -->
            <div id="telaBranding" class="tela-especial tela-branding">
                <div class="branding-logo">
                    <div class="branding-logo-text"><span>BR</span> BANDEIRAS</div>
                    <div class="branding-logo-bar"></div>
                </div>
                <div id="brandingSlogan" class="branding-slogan"></div>
                <div id="brandingMensagem" class="branding-mensagem"></div>
    
            </div>
        </div>

        <!-- Footer com indicadores de navegação e status -->
        <footer class="quiosque-footer">
            <div class="footer-left"></div>
            <div class="filter-indicators">
                <span class="filter-dot active" data-filter="todos" title="Todos"></span>
                <!-- DESATIVADAS TEMPORARIAMENTE - Remover style="display:none" para reativar: -->
                <span class="filter-dot" data-filter="arte" title="Arte" style="display:none"></span>
                <span class="filter-dot" data-filter="orcamento" title="Comercial" style="display:none"></span>
                <span class="filter-dot" data-filter="producao" title="Produção" style="display:none"></span>
                <span class="filter-separator" style="display:none"></span>
                <!-- FIM DAS DESATIVADAS -->
                <span class="filter-dot" data-filter="curiosidade" title="Curiosidade"></span>
                <span class="filter-dot" data-filter="produto" title="Produtos" style="display:none"></span>
                <span class="filter-dot" data-filter="branding" title="BR Bandeiras"></span>
            </div>
        </footer>
    </div>

    <script>
        // ============================================
        // DADOS DAS TELAS ESPECIAIS
        // ============================================
        const CURIOSIDADES = <?= $curiosidadesJson ?>;
        const PRODUTOS = <?= $produtosJson ?>;
        const BRANDING = <?= $brandingJson ?>;

        // ============================================
        // CONFIGURAÇÃO DE ROTAÇÃO DE TELAS
        // ============================================
        // ============================================
        // TELAS DO CARROSSEL
        // Para reativar arte/comercial/produção, descomente as linhas abaixo
        // ============================================
        const TELAS = [
            { id: 'todos', label: 'Todos os Pedidos', tipo: 'tabela', status: null },
            // DESATIVADAS TEMPORARIAMENTE - Descomentar para reativar:
            // { id: 'arte', label: 'Em Arte', tipo: 'tabela', status: 'arte' },
            // { id: 'orcamento', label: 'Comercial', tipo: 'tabela', status: 'orcamento' },
            // { id: 'producao', label: 'Em Produção', tipo: 'tabela', status: 'producao' },
            { id: 'curiosidade', label: 'Curiosidade', tipo: 'especial' },
            /* { id: 'produto', label: 'Nossos Produtos', tipo: 'especial' }, */
            { id: 'branding', label: '', tipo: 'especial' }
        ];
        
        let telaAtualIndex = 0;
        let todosOsDados = []; // Armazena todos os dados para filtrar localmente
        let rotacaoTimeout = null;
        const TEMPO_ROTACAO = 10000; // 10 segundos (padrão)
        const TEMPO_BRANDING = 20000; // 20 segundos para tela de branding
        
        // Índices para telas especiais (não repetir)
        let curiosidadeIndex = 0;
        let brandingIndex = 0;

        // ============================================
        // ATUALIZAR HORA
        // ============================================
        function updateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('pt-BR', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            document.getElementById('currentTime').textContent = timeStr;
        }

        // Inicializar ícones Lucide
        lucide.createIcons();
        
        updateTime();
        setInterval(updateTime, 1000);

        // ============================================
        // BUSCAR DADOS VIA AJAX
        // ============================================
        async function atualizarDados() {
            try {
                const statusEl = document.getElementById('updateStatus');
                if (statusEl) statusEl.textContent = 'Atualizando...';

                const response = await fetch('api/quiosque_data.php?t=' + Date.now());
                
                if (!response.ok) throw new Error('Erro na requisição');

                const data = await response.json();

                if (data.success) {
                    todosOsDados = data.entregas; // Armazenar dados completos
                    // Só atualizar se estiver em uma tela de tabela
                    const telaAtual = TELAS[telaAtualIndex];
                    if (telaAtual.tipo === 'tabela') {
                        exibirTela();
                    }
                    if (statusEl) statusEl.textContent = 'Ao vivo';
                }
            } catch (error) {
                console.error('Erro ao atualizar dados:', error);
                const statusEl = document.getElementById('updateStatus');
                if (statusEl) statusEl.textContent = 'Erro';
            }
        }

        // ============================================
        // EXIBIR TELA ATUAL
        // ============================================
        function exibirTela() {
            const tela = TELAS[telaAtualIndex];
            
            // Elementos
            const tableContainer = document.querySelector('.entregas-table-container');
            const telaCuriosidade = document.getElementById('telaCuriosidade');
            const telaProduto = document.getElementById('telaProduto');
            const telaBranding = document.getElementById('telaBranding');
            const headerColumns = document.getElementById('headerColumns');
            const headerSpecialTitle = document.getElementById('headerSpecialTitle');
            const titleEl = document.getElementById('filterTitle');

            // Esconder todas as telas especiais
            telaCuriosidade.classList.remove('active');
            telaProduto.classList.remove('active');
            telaBranding.classList.remove('active');

            // Atualizar indicadores visuais
            document.querySelectorAll('.filter-dot').forEach(dot => {
                dot.classList.remove('active');
                if (dot.dataset.filter === tela.id) {
                    dot.classList.add('active');
                }
            });

            if (tela.tipo === 'tabela') {
                // Mostrar títulos das colunas no header
                headerColumns.style.display = 'flex';
                headerSpecialTitle.style.display = 'none';
                
                // Mostrar tabela
                tableContainer.classList.remove('hidden');
                
                // Filtrar dados
                let dadosFiltrados = todosOsDados;
                if (tela.status) {
                    dadosFiltrados = todosOsDados.filter(e => e.status === tela.status);
                }

                // Atualizar tabela com transição
                tableContainer.classList.add('transitioning');
                setTimeout(() => {
                    atualizarTabela(dadosFiltrados);
                    tableContainer.classList.remove('transitioning');
                }, 400);

            } else {
                // Mostrar título especial no header
                headerColumns.style.display = 'none';
                headerSpecialTitle.style.display = 'block';
                
                // Atualizar título com transição
                if (titleEl) {
                    titleEl.style.opacity = '0';
                    setTimeout(() => {
                        titleEl.textContent = tela.label;
                        titleEl.style.opacity = '1';
                    }, 200);
                }
                
                // Esconder tabela
                tableContainer.classList.add('hidden');
                
                if (tela.id === 'curiosidade') {
                    // Pegar próxima curiosidade (em ordem)
                    const texto = CURIOSIDADES[curiosidadeIndex];
                    curiosidadeIndex = (curiosidadeIndex + 1) % CURIOSIDADES.length;
                    
                    document.getElementById('curiosidadeTexto').textContent = texto;
                    telaCuriosidade.classList.add('active');

                } else if (tela.id === 'produto') {
                    // Tela de produto (aleatório)
                    const produto = PRODUTOS[Math.floor(Math.random() * PRODUTOS.length)];
                    
                    document.getElementById('produtoIcone').textContent = produto.icone;
                    document.getElementById('produtoNome').textContent = produto.nome;
                    document.getElementById('produtoDescricao').textContent = produto.descricao;
                    telaProduto.classList.add('active');

                } else if (tela.id === 'branding') {
                    // Tela de branding (em ordem)
                    const brand = BRANDING[brandingIndex];
                    brandingIndex = (brandingIndex + 1) % BRANDING.length;
                    
                    document.getElementById('brandingSlogan').textContent = `"${brand.slogan}"`;
                    document.getElementById('brandingMensagem').textContent = brand.mensagem;
                    telaBranding.classList.add('active');
                }
            }
        }

        // ============================================
        // ROTAÇÃO AUTOMÁTICA DE TELAS
        // ============================================
        function proximaTela() {
            telaAtualIndex = (telaAtualIndex + 1) % TELAS.length;
            exibirTela();
        }

        function agendarProximaTela() {
            if (rotacaoTimeout) clearTimeout(rotacaoTimeout);
            
            // Verificar se a tela atual é branding para usar tempo maior
            const telaAtual = TELAS[telaAtualIndex];
            const tempo = telaAtual.id === 'branding' ? TEMPO_BRANDING : TEMPO_ROTACAO;
            
            rotacaoTimeout = setTimeout(() => {
                proximaTela();
                agendarProximaTela();
            }, tempo);
        }
        
        function iniciarRotacao() {
            agendarProximaTela();
        }

        // ============================================
        // ATUALIZAR TABELA
        // ============================================
        function atualizarTabela(entregas) {
            const tbody = document.getElementById('entregasTableBody');
            if (!tbody) return;

            const tela = TELAS[telaAtualIndex];
            const mensagemVazia = tela.status 
                ? `Nenhum pedido em ${tela.label}`
                : 'Nenhum pedido em andamento';

            if (!entregas || entregas.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="no-data">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                </svg>
                                <span>${mensagemVazia}</span>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            const html = entregas.map((entrega, index) => {
                const urgenteClass = entrega.urgente ? 'urgente' : '';
                const statusLabel = entrega.status.charAt(0).toUpperCase() + entrega.status.slice(1);
                const imagemHtml = entrega.imagem_caminho 
                    ? `<img src="${entrega.imagem_caminho}" alt="Miniatura" loading="lazy" onerror="this.parentElement.innerHTML='<span class=\\'miniatura-placeholder\\'>📋</span>'">`
                    : '<span class="miniatura-placeholder">📋</span>';

                return `
                    <tr class="${urgenteClass}" data-numero="${entrega.numero}" style="animation-delay: ${index * 0.03}s">
                        <td class="col-imagem"><div class="miniatura-container">${imagemHtml}</div></td>
                        <td class="col-produto"><span class="produto-nome">${entrega.primeiro_produto || 'Sem produto'}</span></td>
                        <td class="col-vendedor"><span class="vendedor-nome">${entrega.vendedor_nome || '—'}</span></td>
                        <td class="col-arte"><span class="arte-finalista-nome">${entrega.arte_finalista_nome || '—'}</span></td>
                        <td class="col-status"><span class="status-badge status-${entrega.status}">${statusLabel}</span></td>
                        <td class="col-atualizado"><span class="tempo-atualizado">${entrega.tempo_atualizado || '—'}</span></td>
                    </tr>
                `;
            }).join('');

            tbody.style.opacity = '0';
            setTimeout(() => {
                tbody.innerHTML = html;
                tbody.style.opacity = '1';
            }, 150);
        }

        // ============================================
        // CLIQUE NOS INDICADORES (manual)
        // ============================================
        document.querySelectorAll('.filter-dot').forEach((dot) => {
            dot.addEventListener('click', () => {
                const filterId = dot.dataset.filter;
                // Encontrar índice da tela correspondente
                const index = TELAS.findIndex(t => t.id === filterId);
                if (index !== -1) {
                    telaAtualIndex = index;
                    exibirTela();
                    iniciarRotacao(); // Reiniciar timer após clique manual
                }
            });
        });

        // ============================================
        // INICIALIZAÇÃO
        // ============================================
        // Buscar dados iniciais e exibir primeira tela
        atualizarDados().then(() => {
            exibirTela();
        });
        
        // Atualizar dados a cada 60 segundos (evitar rate limit)
        setInterval(atualizarDados, 60000);
        
        // Iniciar rotação de telas a cada 10 segundos
        iniciarRotacao();

        // ============================================
        // WAKE LOCK - MANTER TELA LIGADA
        // Impede que o Fire Stick/TV entre em hibernação
        // ============================================
        let wakeLock = null;
        let noSleepVideo = null;

        // Método 1: Wake Lock API (navegadores modernos)
        async function requestWakeLock() {
            if ('wakeLock' in navigator) {
                try {
                    wakeLock = await navigator.wakeLock.request('screen');
                    console.log('🔆 Wake Lock ativado');
                    
                    // Re-adquirir wake lock quando a aba voltar ao foco
                    wakeLock.addEventListener('release', () => {
                        console.log('🌙 Wake Lock liberado');
                    });
                    
                    return true;
                } catch (err) {
                    console.log('Wake Lock não disponível:', err.message);
                    return false;
                }
            }
            return false;
        }

        // Método 2: Fallback com vídeo invisível (Fire Stick, navegadores antigos)
        function createNoSleepVideo() {
            if (noSleepVideo) return;
            
            // Criar elemento de vídeo invisível com vídeo base64 mínimo
            noSleepVideo = document.createElement('video');
            noSleepVideo.setAttribute('playsinline', '');
            noSleepVideo.setAttribute('muted', '');
            noSleepVideo.setAttribute('loop', '');
            noSleepVideo.setAttribute('title', 'No Sleep');
            noSleepVideo.style.cssText = 'position:fixed;top:-1px;left:-1px;width:1px;height:1px;opacity:0.01;pointer-events:none;';
            
            // Vídeo base64 mínimo (1x1 pixel, transparente, ~500 bytes)
            // Este é um webm válido que mantém o navegador "ativo"
            const webmBase64 = 'data:video/webm;base64,GkXfo59ChoEBQveBAULygQRC84EIQoKIbWF0cm9za2FCh4EEQoWBAhhTgGcBAAAAAAACHhFNm3RALE27i1OrhBVJqWZTrIHfTbuMU6uEFlSua1OsggEuTbuMU6uEHFO7a1OsggIn7AEAAAAAAAAHAAAAAAAMRAGCw5oCAAAAAAAIRAGCw5oCAAAAAAAJRAGCw5oCAAAAAAAKRAGCw5oCAAAAAAARRhqCARVgPQb/E//6g/9A/8H/wv//AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==';
            
            // Tentar usar uma fonte de vídeo mais robusta
            const source = document.createElement('source');
            source.src = webmBase64;
            source.type = 'video/webm';
            noSleepVideo.appendChild(source);
            
            document.body.appendChild(noSleepVideo);
            
            // Tentar reproduzir
            const playPromise = noSleepVideo.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('🎬 NoSleep Video ativado');
                }).catch((err) => {
                    console.log('NoSleep Video falhou:', err.message);
                });
            }
        }

        // Método 3: Simular atividade periódica (movimento de mouse virtual)
        function simulateActivity() {
            // Criar e disparar evento de mouse move
            const event = new MouseEvent('mousemove', {
                bubbles: true,
                cancelable: true,
                clientX: Math.random() * window.innerWidth,
                clientY: Math.random() * window.innerHeight
            });
            document.dispatchEvent(event);
        }

        // Inicializar sistema de keep-alive
        async function initKeepAlive() {
            // Tentar Wake Lock API primeiro
            const hasWakeLock = await requestWakeLock();
            
            // Se Wake Lock não disponível, usar fallback do vídeo
            if (!hasWakeLock) {
                createNoSleepVideo();
            }
            
            // Simular atividade a cada 30 segundos (backup adicional)
            setInterval(simulateActivity, 30000);
            
            console.log('✅ Sistema Keep-Alive inicializado');
        }

        // Re-adquirir Wake Lock quando a página voltar ao foco
        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState === 'visible') {
                if ('wakeLock' in navigator && wakeLock === null) {
                    await requestWakeLock();
                }
                // Garantir que o vídeo está rodando
                if (noSleepVideo && noSleepVideo.paused) {
                    noSleepVideo.play().catch(() => {});
                }
            }
        });

        // Iniciar sistema keep-alive
        initKeepAlive();
    </script>
</body>
</html>
