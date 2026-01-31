<?php
/**
 * Quiosque - Visualização pública para TV
 * Layout estilo painel de aeroporto
 * Não requer autenticação
 */

require_once '../app/config.php';

// Buscar estatísticas públicas (sem autenticação) - Apenas 3 KPIs
try {
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
    $stats = ['arte' => 0, 'producao' => 0, 'pronto' => 0];
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
        LIMIT 15
    ");
    $proximas_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar próximas entregas: " . $e->getMessage());
    $proximas_entregas = [];
}

$empresa_nome = defined('NOME_EMPRESA') ? NOME_EMPRESA : 'BR Bandeiras';
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
            font-family: 'Segoe UI', 'Roboto Mono', monospace;
            background: #0a0a12;
            color: #ffffff;
            min-height: 100vh;
            overflow: hidden;
        }

        /* ============================================
           ELEMENTOS ABSTRATOS ANIMADOS
           ============================================ */
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

        .gradient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.1;
            animation: float 25s ease-in-out infinite;
        }

        .gradient-orb-1 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, #f5b800 0%, transparent 70%);
            top: -150px;
            right: -150px;
        }

        .gradient-orb-2 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #0066cc 0%, transparent 70%);
            bottom: -100px;
            left: -100px;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
        }

        /* Linhas animadas */
        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(245, 184, 0, 0.3), transparent);
            animation: scanDown 8s linear infinite;
        }

        .scan-line-1 { animation-delay: 0s; }
        .scan-line-2 { animation-delay: -4s; }

        @keyframes scanDown {
            0% { top: -2px; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }

        /* ============================================
           LAYOUT PRINCIPAL - ESTILO AEROPORTO
           ============================================ */
        .airport-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }

        /* Header compacto */
        .airport-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(180deg, rgba(245, 184, 0, 0.15) 0%, transparent 100%);
            border-bottom: 2px solid #f5b800;
            margin-bottom: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-bar {
            width: 4px;
            height: 40px;
            background: #f5b800;
            box-shadow: 0 0 10px rgba(245, 184, 0, 0.5);
        }

        .logo-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f5b800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .current-time {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            font-family: 'Roboto Mono', monospace;
        }

        .update-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background: #00ff00;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        /* KPIs em linha horizontal */
        .kpi-bar {
            display: flex;
            justify-content: center;
            gap: 3rem;
            padding: 1rem 0;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
        }

        .kpi-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 2rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 4px solid;
        }

        .kpi-item.arte { border-color: #3b82f6; }
        .kpi-item.producao { border-color: #f59e0b; }
        .kpi-item.pronto { border-color: #10b981; }

        .kpi-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Roboto Mono', monospace;
            line-height: 1;
        }

        .kpi-item.arte .kpi-value { color: #3b82f6; }
        .kpi-item.producao .kpi-value { color: #f59e0b; }
        .kpi-item.pronto .kpi-value { color: #10b981; }

        /* Tabela estilo aeroporto */
        .flight-board {
            flex: 1;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .board-header {
            display: grid;
            grid-template-columns: 120px 1fr 150px 150px 140px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16162a 100%);
            border-bottom: 2px solid #f5b800;
            padding: 1rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 0.875rem;
            color: #f5b800;
        }

        .board-header span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .board-body {
            overflow-y: auto;
            max-height: calc(100vh - 280px);
        }

        .board-row {
            display: grid;
            grid-template-columns: 120px 1fr 150px 150px 140px;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            animation: rowFadeIn 0.5s ease-out;
        }

        @keyframes rowFadeIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .board-row:nth-child(even) {
            background: rgba(255, 255, 255, 0.02);
        }

        .board-row:hover {
            background: rgba(245, 184, 0, 0.05);
        }

        .board-row.urgente {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
        }

        .board-row.urgente:nth-child(even) {
            background: rgba(239, 68, 68, 0.15);
        }

        .board-cell {
            display: flex;
            align-items: center;
            font-size: 1rem;
        }

        .pedido-numero {
            font-family: 'Roboto Mono', monospace;
            font-weight: 700;
            color: #f5b800;
            font-size: 1.125rem;
        }

        .cliente-nome {
            color: #ffffff;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .prazo-entrega {
            font-family: 'Roboto Mono', monospace;
            color: rgba(255, 255, 255, 0.8);
        }

        .prazo-entrega.atrasado {
            color: #ef4444;
            font-weight: 600;
        }

        .prazo-entrega.hoje {
            color: #f59e0b;
            font-weight: 600;
        }

        /* Status badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-arte {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-producao {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-pronto {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-orcamento {
            background: rgba(139, 92, 246, 0.2);
            color: #a78bfa;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .urgente-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: #ef4444;
            color: white;
            border-radius: 4px;
            font-size: 0.625rem;
            font-weight: 700;
            margin-left: 0.5rem;
            animation: urgentePulse 1.5s infinite;
        }

        @keyframes urgentePulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Mensagem quando não há pedidos */
        .no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem;
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.25rem;
        }

        .no-data svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Footer */
        .airport-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.5rem;
            margin-top: 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Scrollbar estilizada */
        .board-body::-webkit-scrollbar {
            width: 8px;
        }

        .board-body::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }

        .board-body::-webkit-scrollbar-thumb {
            background: rgba(245, 184, 0, 0.3);
            border-radius: 4px;
        }

        .board-body::-webkit-scrollbar-thumb:hover {
            background: rgba(245, 184, 0, 0.5);
        }

        /* Responsividade para TV */
        @media (min-width: 1920px) {
            .airport-header {
                padding: 1rem 2rem;
            }

            .logo-title {
                font-size: 2rem;
            }

            .current-time {
                font-size: 2rem;
            }

            .kpi-value {
                font-size: 3.5rem;
            }

            .kpi-label {
                font-size: 1rem;
            }

            .board-header {
                font-size: 1rem;
                padding: 1.25rem 2rem;
            }

            .board-row {
                padding: 1.25rem 2rem;
            }

            .board-cell {
                font-size: 1.125rem;
            }

            .pedido-numero {
                font-size: 1.25rem;
            }

            .status-badge {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }
        }

        /* 4K */
        @media (min-width: 2560px) {
            .logo-title {
                font-size: 2.5rem;
            }

            .current-time {
                font-size: 2.5rem;
            }

            .kpi-value {
                font-size: 4.5rem;
            }

            .board-header {
                font-size: 1.25rem;
            }

            .board-cell {
                font-size: 1.375rem;
            }
        }
    </style>
</head>
<body>
    <!-- Elementos Abstratos Animados -->
    <div class="abstract-bg">
        <div class="gradient-orb gradient-orb-1"></div>
        <div class="gradient-orb gradient-orb-2"></div>
        <div class="scan-line scan-line-1"></div>
        <div class="scan-line scan-line-2"></div>
    </div>

    <div class="airport-container">
        <!-- Header -->
        <header class="airport-header">
            <div class="header-left">
                <div class="logo-bar"></div>
                <span class="logo-title"><?= htmlspecialchars($empresa_nome) ?></span>
            </div>
            <div class="header-right">
                <div class="current-time" id="currentTime"></div>
                <div class="update-indicator">
                    <div class="pulse-dot"></div>
                    <span id="updateStatus">Ao vivo</span>
                </div>
            </div>
        </header>

        <!-- KPIs em barra horizontal -->
        <div class="kpi-bar">
            <div class="kpi-item arte">
                <div class="kpi-label">Em Arte</div>
                <div class="kpi-value" id="statArte"><?= $stats['arte'] ?></div>
            </div>
            <div class="kpi-item producao">
                <div class="kpi-label">Em Produção</div>
                <div class="kpi-value" id="statProducao"><?= $stats['producao'] ?></div>
            </div>
            <div class="kpi-item pronto">
                <div class="kpi-label">Prontos</div>
                <div class="kpi-value" id="statPronto"><?= $stats['pronto'] ?></div>
            </div>
        </div>

        <!-- Tabela de Pedidos estilo Aeroporto -->
        <div class="flight-board">
            <div class="board-header">
                <span>
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                    </svg>
                    Pedido
                </span>
                <span>Cliente</span>
                <span>Prazo</span>
                <span>Status</span>
                <span>Criado em</span>
            </div>
            <div class="board-body" id="boardBody">
                <?php if (!empty($proximas_entregas)): ?>
                    <?php foreach ($proximas_entregas as $index => $entrega): 
                        $isAtrasado = $entrega['prazo_entrega'] && strtotime($entrega['prazo_entrega']) < strtotime('today');
                        $isHoje = $entrega['prazo_entrega'] && date('Y-m-d', strtotime($entrega['prazo_entrega'])) === date('Y-m-d');
                    ?>
                    <div class="board-row <?= $entrega['urgente'] ? 'urgente' : '' ?>" data-numero="<?= htmlspecialchars($entrega['numero']) ?>" style="animation-delay: <?= $index * 0.05 ?>s">
                        <div class="board-cell">
                            <span class="pedido-numero">#<?= htmlspecialchars($entrega['numero']) ?></span>
                            <?php if ($entrega['urgente']): ?>
                                <span class="urgente-badge">⚡ URGENTE</span>
                            <?php endif; ?>
                        </div>
                        <div class="board-cell">
                            <span class="cliente-nome"><?= htmlspecialchars($entrega['cliente_nome'] ?: 'Cliente não informado') ?></span>
                        </div>
                        <div class="board-cell">
                            <span class="prazo-entrega <?= $isAtrasado ? 'atrasado' : ($isHoje ? 'hoje' : '') ?>">
                                <?= $entrega['prazo_entrega'] ? date('d/m/Y', strtotime($entrega['prazo_entrega'])) : '—' ?>
                            </span>
                        </div>
                        <div class="board-cell">
                            <span class="status-badge status-<?= htmlspecialchars($entrega['status']) ?>">
                                <?= htmlspecialchars(ucfirst($entrega['status'])) ?>
                            </span>
                        </div>
                        <div class="board-cell">
                            <span style="color: rgba(255,255,255,0.5); font-size: 0.875rem;">
                                <?= $entrega['created_at'] ? date('d/m/Y', strtotime($entrega['created_at'])) : '—' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span>Nenhum pedido em andamento</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="airport-footer">
            <span><?= htmlspecialchars($empresa_nome) ?> - Sistema de Gestão</span>
            <span id="lastUpdate">Atualização automática a cada 5s</span>
        </footer>
    </div>

    <script>
        // Atualizar hora atual
        function updateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('currentTime').textContent = timeStr;
        }

        updateTime();
        setInterval(updateTime, 1000);

        // Função para atualizar dados via AJAX
        async function atualizarDados() {
            try {
                const statusEl = document.getElementById('updateStatus');
                if (statusEl) statusEl.textContent = 'Atualizando...';

                const response = await fetch('api/quiosque_data.php?t=' + Date.now());
                
                if (!response.ok) throw new Error('Erro na requisição');

                const data = await response.json();

                if (data.success) {
                    atualizarEstatisticas(data.stats);
                    atualizarTabela(data.entregas);
                    
                    if (statusEl) statusEl.textContent = 'Ao vivo';
                    document.getElementById('lastUpdate').textContent = 
                        `Última atualização: ${new Date().toLocaleTimeString('pt-BR')}`;
                }
            } catch (error) {
                console.error('Erro ao atualizar dados:', error);
                const statusEl = document.getElementById('updateStatus');
                if (statusEl) statusEl.textContent = 'Erro';
            }
        }

        // Atualizar estatísticas com animação
        function atualizarEstatisticas(stats) {
            const elementos = {
                'arte': document.getElementById('statArte'),
                'producao': document.getElementById('statProducao'),
                'pronto': document.getElementById('statPronto')
            };

            Object.keys(elementos).forEach(key => {
                const el = elementos[key];
                if (el) {
                    const valorAtual = parseInt(el.textContent) || 0;
                    const valorNovo = stats[key] || 0;

                    if (valorAtual !== valorNovo) {
                        el.style.transform = 'scale(1.2)';
                        el.style.transition = 'transform 0.3s ease';
                        el.textContent = valorNovo;
                        setTimeout(() => { el.style.transform = 'scale(1)'; }, 300);
                    }
                }
            });
        }

        // Atualizar tabela
        function atualizarTabela(entregas) {
            const container = document.getElementById('boardBody');
            if (!container) return;

            if (!entregas || entregas.length === 0) {
                container.innerHTML = `
                    <div class="no-data">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span>Nenhum pedido em andamento</span>
                    </div>
                `;
                return;
            }

            const hoje = new Date().toISOString().split('T')[0];

            const html = entregas.map((entrega, index) => {
                const isAtrasado = entrega.prazo_entrega_raw && entrega.prazo_entrega_raw < hoje;
                const isHoje = entrega.prazo_entrega_raw === hoje;
                const prazoClass = isAtrasado ? 'atrasado' : (isHoje ? 'hoje' : '');
                const urgenteClass = entrega.urgente ? 'urgente' : '';
                const urgenteBadge = entrega.urgente ? '<span class="urgente-badge">⚡ URGENTE</span>' : '';

                return `
                    <div class="board-row ${urgenteClass}" data-numero="${entrega.numero}" style="animation-delay: ${index * 0.05}s">
                        <div class="board-cell">
                            <span class="pedido-numero">#${entrega.numero}</span>
                            ${urgenteBadge}
                        </div>
                        <div class="board-cell">
                            <span class="cliente-nome">${entrega.cliente_nome}</span>
                        </div>
                        <div class="board-cell">
                            <span class="prazo-entrega ${prazoClass}">
                                ${entrega.prazo_entrega || '—'}
                            </span>
                        </div>
                        <div class="board-cell">
                            <span class="status-badge status-${entrega.status}">
                                ${entrega.status.charAt(0).toUpperCase() + entrega.status.slice(1)}
                            </span>
                        </div>
                        <div class="board-cell">
                            <span style="color: rgba(255,255,255,0.5); font-size: 0.875rem;">
                                ${entrega.created_at || '—'}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');

            container.style.opacity = '0.5';
            setTimeout(() => {
                container.innerHTML = html;
                container.style.opacity = '1';
            }, 200);
        }

        // Atualizar a cada 5 segundos
        setInterval(atualizarDados, 5000);
        setTimeout(atualizarDados, 5000);
    </script>
</body>
</html>
