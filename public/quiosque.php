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
    <div class="auto-refresh-indicator" id="refreshIndicator">
        Carregado: <?= date('H:i:s') ?>
    </div>

    <div class="quiosque-container">
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
                        indicator.textContent = `Atualizado: ${updateTime.toLocaleTimeString('pt-BR')}`;
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

        // Atualizar dados a cada 30 segundos
        setInterval(atualizarDados, 30000);

        // Primeira atualização após 30 segundos
        setTimeout(atualizarDados, 30000);
    </script>
</body>
</html>
