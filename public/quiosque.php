<?php
/**
 * Quiosque - Visualização pública para TV
 * Não requer autenticação
 */

require_once '../app/config.php';

// Buscar estatísticas públicas (sem autenticação) - Apenas 3 KPIs
try {
    // Estatísticas de pedidos por status - Apenas Arte, Produção e Prontos
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) FILTER (WHERE status = 'arte') as arte,
            COUNT(*) FILTER (WHERE status = 'producao') as producao,
            COUNT(*) FILTER (WHERE status = 'pronto') as pronto
        FROM pedidos
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_map('intval', $stats);
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas do quiosque: " . $e->getMessage());
    $stats = [
        'arte' => 0,
        'producao' => 0,
        'pronto' => 0
    ];
}

// Buscar próximas entregas - Incluir todos os pedidos ativos
try {
    $stmt = $pdo->query("
        SELECT 
            p.numero,
            p.prazo_entrega,
            c.nome as cliente_nome,
            p.urgente,
            p.status,
            p.created_at
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.status NOT IN ('entregue', 'cancelado')
        ORDER BY 
            CASE WHEN p.prazo_entrega IS NOT NULL THEN 0 ELSE 1 END,
            p.prazo_entrega ASC NULLS LAST,
            p.created_at DESC
        LIMIT 20
    ");
    $proximas_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar próximas entregas: " . $e->getMessage());
    $proximas_entregas = [];
}

// Informações da empresa
$empresa_nome = defined('NOME_EMPRESA') ? NOME_EMPRESA : 'BR Bandeiras';
$empresa_email = defined('EMAIL_EMPRESA') ? EMAIL_EMPRESA : 'contato@brbandeiras.com.br';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiosque - <?= htmlspecialchars($empresa_nome) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #041801 0%, #0d2818 50%, #041801 100%);
            color: #ffffff;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }

        /* ============================================
           ELEMENTOS ABSTRATOS ANIMADOS
           ============================================ */
        
        /* Container para elementos de fundo */
        .abstract-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        /* Gradiente animado de fundo */
        .gradient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: float 20s ease-in-out infinite;
        }

        .gradient-orb-1 {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, #f5b800 0%, transparent 70%);
            top: -200px;
            right: -200px;
            animation-delay: 0s;
        }

        .gradient-orb-2 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, #0d5c1e 0%, transparent 70%);
            bottom: -150px;
            left: -150px;
            animation-delay: -7s;
        }

        .gradient-orb-3 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #f5b800 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -14s;
            opacity: 0.08;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            25% {
                transform: translate(30px, -30px) scale(1.05);
            }
            50% {
                transform: translate(-20px, 20px) scale(0.95);
            }
            75% {
                transform: translate(20px, 10px) scale(1.02);
            }
        }

        /* Linhas aleatórias animadas percorrendo a tela */
        .random-lines {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        /* Linhas horizontais da esquerda para direita */
        .line-h {
            position: absolute;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(245, 184, 0, 0.2), rgba(245, 184, 0, 0.4), rgba(245, 184, 0, 0.2), transparent);
            animation: moveLineH linear infinite;
        }

        .line-h-1 { top: 15%; width: 300px; left: -300px; animation-duration: 8s; animation-delay: 0s; }
        .line-h-2 { top: 35%; width: 400px; left: -400px; animation-duration: 12s; animation-delay: -2s; }
        .line-h-3 { top: 55%; width: 250px; left: -250px; animation-duration: 6s; animation-delay: -4s; }
        .line-h-4 { top: 75%; width: 350px; left: -350px; animation-duration: 10s; animation-delay: -1s; }
        .line-h-5 { top: 25%; width: 200px; left: -200px; animation-duration: 7s; animation-delay: -3s; }
        .line-h-6 { top: 85%; width: 280px; left: -280px; animation-duration: 9s; animation-delay: -5s; }

        @keyframes moveLineH {
            0% { transform: translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateX(calc(100vw + 400px)); opacity: 0; }
        }

        /* Linhas horizontais da direita para esquerda */
        .line-h-reverse {
            position: absolute;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(13, 92, 30, 0.3), rgba(13, 92, 30, 0.5), rgba(13, 92, 30, 0.3), transparent);
            animation: moveLineHReverse linear infinite;
        }

        .line-h-r-1 { top: 20%; width: 320px; right: -320px; animation-duration: 11s; animation-delay: -1s; }
        .line-h-r-2 { top: 45%; width: 280px; right: -280px; animation-duration: 8s; animation-delay: -3s; }
        .line-h-r-3 { top: 70%; width: 360px; right: -360px; animation-duration: 13s; animation-delay: -2s; }

        @keyframes moveLineHReverse {
            0% { transform: translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateX(calc(-100vw - 400px)); opacity: 0; }
        }

        /* Linhas verticais de cima para baixo */
        .line-v {
            position: absolute;
            width: 1px;
            background: linear-gradient(180deg, transparent, rgba(245, 184, 0, 0.15), rgba(245, 184, 0, 0.3), rgba(245, 184, 0, 0.15), transparent);
            animation: moveLineV linear infinite;
        }

        .line-v-1 { left: 10%; height: 200px; top: -200px; animation-duration: 10s; animation-delay: 0s; }
        .line-v-2 { left: 30%; height: 300px; top: -300px; animation-duration: 14s; animation-delay: -3s; }
        .line-v-3 { left: 50%; height: 250px; top: -250px; animation-duration: 8s; animation-delay: -5s; }
        .line-v-4 { left: 70%; height: 180px; top: -180px; animation-duration: 12s; animation-delay: -2s; }
        .line-v-5 { left: 90%; height: 220px; top: -220px; animation-duration: 9s; animation-delay: -4s; }

        @keyframes moveLineV {
            0% { transform: translateY(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(calc(100vh + 300px)); opacity: 0; }
        }

        /* Linhas diagonais */
        .line-d {
            position: absolute;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(245, 184, 0, 0.25), rgba(255, 255, 255, 0.1), rgba(245, 184, 0, 0.25), transparent);
            transform-origin: left center;
            animation: moveLineDiag linear infinite;
        }

        .line-d-1 { top: 0; left: -300px; width: 400px; transform: rotate(25deg); animation-duration: 15s; animation-delay: 0s; }
        .line-d-2 { top: 20%; left: -250px; width: 350px; transform: rotate(35deg); animation-duration: 12s; animation-delay: -4s; }
        .line-d-3 { top: 40%; left: -200px; width: 300px; transform: rotate(20deg); animation-duration: 18s; animation-delay: -8s; }
        .line-d-4 { top: 60%; left: -350px; width: 450px; transform: rotate(30deg); animation-duration: 14s; animation-delay: -2s; }

        @keyframes moveLineDiag {
            0% { transform: rotate(25deg) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: rotate(25deg) translateX(calc(150vw)); opacity: 0; }
        }

        /* Linhas diagonais reversas */
        .line-d-reverse {
            position: absolute;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(13, 92, 30, 0.2), rgba(245, 184, 0, 0.15), rgba(13, 92, 30, 0.2), transparent);
            transform-origin: right center;
            animation: moveLineDiagReverse linear infinite;
        }

        .line-d-r-1 { top: 10%; right: -300px; width: 380px; transform: rotate(-30deg); animation-duration: 16s; animation-delay: -3s; }
        .line-d-r-2 { top: 50%; right: -250px; width: 320px; transform: rotate(-20deg); animation-duration: 11s; animation-delay: -6s; }
        .line-d-r-3 { top: 80%; right: -280px; width: 360px; transform: rotate(-35deg); animation-duration: 13s; animation-delay: -1s; }

        @keyframes moveLineDiagReverse {
            0% { transform: rotate(-30deg) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: rotate(-30deg) translateX(calc(-150vw)); opacity: 0; }
        }

        /* Linhas curtas rápidas (meteoros) */
        .meteor {
            position: absolute;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(245, 184, 0, 0.6), rgba(255, 255, 255, 0.8));
            border-radius: 2px;
            animation: meteorMove linear infinite;
        }

        .meteor-1 { top: 5%; left: -100px; width: 100px; transform: rotate(45deg); animation-duration: 3s; animation-delay: 0s; }
        .meteor-2 { top: 30%; left: -80px; width: 80px; transform: rotate(40deg); animation-duration: 4s; animation-delay: -2s; }
        .meteor-3 { top: 60%; left: -120px; width: 120px; transform: rotate(50deg); animation-duration: 3.5s; animation-delay: -1s; }
        .meteor-4 { top: 85%; left: -90px; width: 90px; transform: rotate(35deg); animation-duration: 4.5s; animation-delay: -3s; }

        @keyframes meteorMove {
            0% { transform: rotate(45deg) translateX(0); opacity: 0; }
            5% { opacity: 1; }
            95% { opacity: 1; }
            100% { transform: rotate(45deg) translateX(calc(150vw)); opacity: 0; }
        }

        /* Partículas flutuantes */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(245, 184, 0, 0.3);
            border-radius: 50%;
            animation: particleFloat 10s ease-in-out infinite;
        }

        .particle:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; animation-duration: 12s; }
        .particle:nth-child(2) { left: 20%; top: 80%; animation-delay: -2s; animation-duration: 10s; }
        .particle:nth-child(3) { left: 30%; top: 40%; animation-delay: -4s; animation-duration: 14s; }
        .particle:nth-child(4) { left: 40%; top: 60%; animation-delay: -1s; animation-duration: 11s; }
        .particle:nth-child(5) { left: 50%; top: 30%; animation-delay: -3s; animation-duration: 13s; }
        .particle:nth-child(6) { left: 60%; top: 70%; animation-delay: -5s; animation-duration: 9s; }
        .particle:nth-child(7) { left: 70%; top: 50%; animation-delay: -2s; animation-duration: 15s; }
        .particle:nth-child(8) { left: 80%; top: 25%; animation-delay: -4s; animation-duration: 12s; }
        .particle:nth-child(9) { left: 90%; top: 85%; animation-delay: -1s; animation-duration: 10s; }
        .particle:nth-child(10) { left: 15%; top: 55%; animation-delay: -3s; animation-duration: 11s; }
        .particle:nth-child(11) { left: 85%; top: 45%; animation-delay: -5s; animation-duration: 13s; }
        .particle:nth-child(12) { left: 45%; top: 15%; animation-delay: -2s; animation-duration: 14s; }

        @keyframes particleFloat {
            0%, 100% {
                transform: translate(0, 0) scale(1);
                opacity: 0.3;
            }
            25% {
                transform: translate(20px, -30px) scale(1.5);
                opacity: 0.6;
            }
            50% {
                transform: translate(-10px, 20px) scale(0.8);
                opacity: 0.2;
            }
            75% {
                transform: translate(15px, 10px) scale(1.2);
                opacity: 0.5;
            }
        }

        /* Hexágonos decorativos */
        .hexagon {
            position: absolute;
            width: 60px;
            height: 35px;
            background: rgba(245, 184, 0, 0.03);
            clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%);
            animation: hexRotate 30s linear infinite;
        }

        .hexagon-1 { top: 10%; right: 10%; animation-delay: 0s; }
        .hexagon-2 { bottom: 20%; left: 5%; animation-delay: -10s; width: 80px; height: 46px; }
        .hexagon-3 { top: 60%; right: 15%; animation-delay: -20s; width: 40px; height: 23px; }

        @keyframes hexRotate {
            0% { transform: rotate(0deg); opacity: 0.03; }
            50% { opacity: 0.08; }
            100% { transform: rotate(360deg); opacity: 0.03; }
        }

        /* Ondas sutis no fundo */
        .wave {
            position: absolute;
            width: 200%;
            height: 200px;
            background: linear-gradient(180deg, transparent, rgba(245, 184, 0, 0.02), transparent);
            animation: wave 20s ease-in-out infinite;
        }

        .wave-1 { bottom: 0; animation-delay: 0s; }
        .wave-2 { bottom: 50px; animation-delay: -5s; opacity: 0.5; }

        @keyframes wave {
            0%, 100% {
                transform: translateX(-25%) rotate(-2deg);
            }
            50% {
                transform: translateX(-15%) rotate(2deg);
            }
        }

        /* Pulso sutil nos cards */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 16px;
            border: 1px solid rgba(245, 184, 0, 0.1);
            animation: cardPulse 4s ease-in-out infinite;
            pointer-events: none;
        }

        .stat-card {
            position: relative;
        }

        @keyframes cardPulse {
            0%, 100% {
                border-color: rgba(245, 184, 0, 0.1);
                box-shadow: 0 0 0 rgba(245, 184, 0, 0);
            }
            50% {
                border-color: rgba(245, 184, 0, 0.3);
                box-shadow: 0 0 20px rgba(245, 184, 0, 0.1);
            }
        }

        /* Efeito de brilho no valor dos KPIs */
        .stat-value {
            text-shadow: 0 0 30px rgba(245, 184, 0, 0.3);
            animation: valueGlow 3s ease-in-out infinite;
        }

        @keyframes valueGlow {
            0%, 100% {
                text-shadow: 0 0 20px rgba(245, 184, 0, 0.2);
            }
            50% {
                text-shadow: 0 0 40px rgba(245, 184, 0, 0.5), 0 0 60px rgba(245, 184, 0, 0.2);
            }
        }

        /* Container principal */
        .quiosque-container {
            max-width: 100%;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header - Reduzido para 1/4 do espaço vertical */
        .quiosque-header {
            text-align: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(245, 184, 0, 0.3);
        }

        .quiosque-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .logo-bar {
            width: 3px;
            height: 20px;
            background: #f5b800;
            box-shadow: 0 0 10px rgba(245, 184, 0, 0.5);
        }

        .logo-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
        }

        .logo-subtitle {
            font-size: 0.625rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 0.125rem;
        }

        .current-time {
            font-size: 0.875rem;
            color: #f5b800;
            margin-top: 0.25rem;
            font-weight: 600;
        }

        /* Grid de estatísticas - 3 colunas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(245, 184, 0, 0.2);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(245, 184, 0, 0.3);
        }

        .stat-card.urgente {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.15);
        }

        .stat-card.urgente .stat-value {
            color: #fca5a5;
        }

        .stat-label {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 3.5rem;
            font-weight: 800;
            color: #f5b800;
            line-height: 1;
        }

        /* Seção de próximas entregas */
        .entregas-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(245, 184, 0, 0.2);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #f5b800;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .entregas-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .entrega-item {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid #f5b800;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .entrega-item.urgente {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .entrega-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .entrega-numero {
            font-size: 1.25rem;
            font-weight: 700;
            color: #f5b800;
            margin-bottom: 0.5rem;
        }

        .entrega-cliente {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.25rem;
        }

        .entrega-data {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .urgente-badge {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Footer */
        .quiosque-footer {
            text-align: center;
            padding: 2rem 0;
            border-top: 1px solid rgba(245, 184, 0, 0.2);
            margin-top: auto;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Animações */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card,
        .entrega-item {
            animation: fadeIn 0.6s ease-out;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }
        .stat-card:nth-child(7) { animation-delay: 0.7s; }

        /* Responsividade para TV */
        @media (min-width: 1920px), 
               (min-width: 1200px) and (min-height: 800px) {
            .quiosque-container {
                padding: 3rem;
            }

            .logo-title {
                font-size: 2rem;
            }

            .logo-subtitle {
                font-size: 0.875rem;
            }

            .current-time {
                font-size: 1.25rem;
            }

            .stat-card {
                padding: 3rem;
            }

            .stat-label {
                font-size: 1.5rem;
            }

            .stat-value {
                font-size: 5rem;
            }

            .section-title {
                font-size: 2.5rem;
            }

            .entrega-numero {
                font-size: 1.5rem;
            }

            .entrega-cliente {
                font-size: 1.25rem;
            }

            .stats-grid {
                gap: 3rem;
            }
        }

        /* 4K */
        @media (min-width: 2560px) {
            .logo-title {
                font-size: 2.5rem;
            }

            .stat-value {
                font-size: 6rem;
            }

            .section-title {
                font-size: 3rem;
            }
        }

        /* Auto-refresh */
        .auto-refresh-indicator {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.7);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        /* Animação para valores que mudam */
        .stat-value {
            transition: transform 0.3s ease;
        }

        /* Transição suave para lista de entregas */
        #entregasList {
            transition: opacity 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Elementos Abstratos Animados de Fundo -->
    <div class="abstract-bg">
        <!-- Orbes de gradiente -->
        <div class="gradient-orb gradient-orb-1"></div>
        <div class="gradient-orb gradient-orb-2"></div>
        <div class="gradient-orb gradient-orb-3"></div>
        
        <!-- Linhas aleatórias animadas -->
        <div class="random-lines">
            <!-- Linhas horizontais esquerda para direita -->
            <div class="line-h line-h-1"></div>
            <div class="line-h line-h-2"></div>
            <div class="line-h line-h-3"></div>
            <div class="line-h line-h-4"></div>
            <div class="line-h line-h-5"></div>
            <div class="line-h line-h-6"></div>
            
            <!-- Linhas horizontais direita para esquerda -->
            <div class="line-h-reverse line-h-r-1"></div>
            <div class="line-h-reverse line-h-r-2"></div>
            <div class="line-h-reverse line-h-r-3"></div>
            
            <!-- Linhas verticais -->
            <div class="line-v line-v-1"></div>
            <div class="line-v line-v-2"></div>
            <div class="line-v line-v-3"></div>
            <div class="line-v line-v-4"></div>
            <div class="line-v line-v-5"></div>
            
            <!-- Linhas diagonais -->
            <div class="line-d line-d-1"></div>
            <div class="line-d line-d-2"></div>
            <div class="line-d line-d-3"></div>
            <div class="line-d line-d-4"></div>
            
            <!-- Linhas diagonais reversas -->
            <div class="line-d-reverse line-d-r-1"></div>
            <div class="line-d-reverse line-d-r-2"></div>
            <div class="line-d-reverse line-d-r-3"></div>
            
            <!-- Meteoros (linhas rápidas) -->
            <div class="meteor meteor-1"></div>
            <div class="meteor meteor-2"></div>
            <div class="meteor meteor-3"></div>
            <div class="meteor meteor-4"></div>
        </div>
        
        <!-- Partículas flutuantes -->
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        
        <!-- Hexágonos decorativos -->
        <div class="hexagon hexagon-1"></div>
        <div class="hexagon hexagon-2"></div>
        <div class="hexagon hexagon-3"></div>
        
        <!-- Ondas sutis -->
        <div class="wave wave-1"></div>
        <div class="wave wave-2"></div>
    </div>

    <div class="auto-refresh-indicator" id="refreshIndicator">
        Atualização automática a cada 5s
    </div>

    <div class="quiosque-container" style="position: relative; z-index: 1;">
        <!-- Header -->
        <header class="quiosque-header">
            <div class="quiosque-logo">
                <div class="logo-bar"></div>
                <div>
                    <h1 class="logo-title"><?= htmlspecialchars($empresa_nome) ?></h1>
                    <p class="logo-subtitle">Sistema de Gestão de Fábrica de Bandeiras</p>
                </div>
            </div>
            <div class="current-time" id="currentTime"></div>
        </header>

        <!-- Estatísticas - Apenas 3 KPIs -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-label">Em Arte</div>
                <div class="stat-value" id="statArte"><?= $stats['arte'] ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Em Produção</div>
                <div class="stat-value" id="statProducao"><?= $stats['producao'] ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Prontos</div>
                <div class="stat-value" id="statPronto"><?= $stats['pronto'] ?></div>
            </div>
        </div>

        <!-- Próximas Entregas -->
        <div class="entregas-section">
            <h2 class="section-title">
                <svg width="32" height="32" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
                Próximas Entregas
            </h2>
            <div class="entregas-list" id="entregasList">
                <?php if (!empty($proximas_entregas)): ?>
                    <?php foreach ($proximas_entregas as $entrega): ?>
                    <div class="entrega-item <?= $entrega['urgente'] ? 'urgente' : '' ?>" data-numero="<?= htmlspecialchars($entrega['numero']) ?>">
                        <div class="entrega-numero">
                            Pedido #<?= htmlspecialchars($entrega['numero']) ?>
                            <?php if ($entrega['urgente']): ?>
                                <span class="urgente-badge">URGENTE</span>
                            <?php endif; ?>
                        </div>
                        <div class="entrega-cliente"><?= htmlspecialchars($entrega['cliente_nome'] ?: 'Cliente não informado') ?></div>
                        <div class="entrega-data">
                            <?php if ($entrega['prazo_entrega']): ?>
                                Prazo: <?= date('d/m/Y', strtotime($entrega['prazo_entrega'])) ?>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.5);">Prazo não definido</span>
                            <?php endif; ?>
                        </div>
                        <div class="entrega-status" style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-top: 0.25rem;">
                            Status: <?= htmlspecialchars(ucfirst($entrega['status'])) ?>
                            <?php if ($entrega['created_at']): ?>
                                | Criado em: <?= date('d/m/Y', strtotime($entrega['created_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="entrega-item" style="text-align: center; padding: 2rem;">
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 1.125rem;">
                            Nenhuma entrega agendada no momento
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="quiosque-footer">
            <p><?= htmlspecialchars($empresa_nome) ?> - <?= htmlspecialchars($empresa_email) ?></p>
            <p style="margin-top: 0.5rem; font-size: 0.875rem;">Atualizado automaticamente</p>
        </footer>
    </div>

    <script>
        // Atualizar hora atual
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZone: 'America/Sao_Paulo'
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('pt-BR', options);
        }

        updateTime();
        setInterval(updateTime, 1000);

        // Função para atualizar dados via AJAX
        async function atualizarDados() {
            try {
                // Adicionar indicador visual de atualização
                const indicator = document.querySelector('.auto-refresh-indicator');
                if (indicator) {
                    indicator.style.opacity = '0.5';
                    indicator.textContent = 'Atualizando...';
                }

                const response = await fetch('api/quiosque_data.php?t=' + Date.now());
                
                if (!response.ok) {
                    throw new Error('Erro na requisição');
                }

                const data = await response.json();

                if (data.success) {
                    // Atualizar estatísticas com animação
                    atualizarEstatisticas(data.stats);
                    
                    // Atualizar lista de entregas
                    atualizarEntregas(data.entregas);

                    // Atualizar timestamp no indicador
                    if (indicator) {
                        const updateTime = new Date(data.timestamp);
                        indicator.textContent = `Atualizado: ${updateTime.toLocaleTimeString('pt-BR')} | Próxima: ${new Date(Date.now() + 5000).toLocaleTimeString('pt-BR')}`;
                        indicator.style.opacity = '1';
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar dados:', error);
                const indicator = document.querySelector('.auto-refresh-indicator');
                if (indicator) {
                    indicator.textContent = 'Erro na atualização';
                    indicator.style.opacity = '1';
                }
            }
        }

        // Atualizar estatísticas com animação suave
        function atualizarEstatisticas(stats) {
            const elementos = {
                'arte': document.getElementById('statArte'),
                'producao': document.getElementById('statProducao'),
                'pronto': document.getElementById('statPronto')
            };

            Object.keys(elementos).forEach(key => {
                const elemento = elementos[key];
                if (elemento) {
                    const valorAtual = parseInt(elemento.textContent) || 0;
                    const valorNovo = stats[key] || 0;

                    if (valorAtual !== valorNovo) {
                        // Animação de mudança
                        elemento.style.transform = 'scale(1.1)';
                        elemento.style.transition = 'transform 0.3s ease';
                        
                        // Atualizar valor
                        elemento.textContent = valorNovo;

                        // Voltar ao normal
                        setTimeout(() => {
                            elemento.style.transform = 'scale(1)';
                        }, 300);
                    }
                }
            });
        }

        // Atualizar lista de entregas
        function atualizarEntregas(entregas) {
            const container = document.getElementById('entregasList');
            if (!container) return;

            // Se não há entregas
            if (!entregas || entregas.length === 0) {
                container.innerHTML = `
                    <div class="entrega-item" style="text-align: center; padding: 2rem;">
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 1.125rem;">
                            Nenhuma entrega agendada no momento
                        </div>
                    </div>
                `;
                return;
            }

            // Criar HTML das entregas
            const html = entregas.map(entrega => {
                const urgenteClass = entrega.urgente ? 'urgente' : '';
                const prazoHtml = entrega.prazo_entrega 
                    ? `Prazo: ${entrega.prazo_entrega}` 
                    : '<span style="color: rgba(255,255,255,0.5);">Prazo não definido</span>';
                const urgenteBadge = entrega.urgente 
                    ? '<span class="urgente-badge">URGENTE</span>' 
                    : '';
                const createdHtml = entrega.created_at 
                    ? ` | Criado em: ${entrega.created_at}` 
                    : '';

                return `
                    <div class="entrega-item ${urgenteClass}" data-numero="${entrega.numero}">
                        <div class="entrega-numero">
                            Pedido #${entrega.numero}
                            ${urgenteBadge}
                        </div>
                        <div class="entrega-cliente">${entrega.cliente_nome}</div>
                        <div class="entrega-data">${prazoHtml}</div>
                        <div class="entrega-status" style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-top: 0.25rem;">
                            Status: ${entrega.status.charAt(0).toUpperCase() + entrega.status.slice(1)}${createdHtml}
                        </div>
                    </div>
                `;
            }).join('');

            // Fade out
            container.style.opacity = '0.5';
            container.style.transition = 'opacity 0.3s ease';

            setTimeout(() => {
                container.innerHTML = html;
                container.style.opacity = '1';
            }, 300);
        }

        // Atualizar dados a cada 5 segundos para dar impressão de tempo real
        setInterval(atualizarDados, 5000);

        // Primeira atualização após 5 segundos
        setTimeout(atualizarDados, 5000);
    </script>
</body>
</html>
