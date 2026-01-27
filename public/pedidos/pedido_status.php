<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

// Validar ID do pedido
$pedido_id = validarPedidoId($_GET['id'] ?? null);
$novo_status = $_GET['status'] ?? null;
$observacao = $_GET['obs'] ?? '';

if (!$pedido_id || !$novo_status) {
    $_SESSION['erro'] = 'Parâmetros obrigatórios não informados';
    redirect('pedidos.php');
}

try {
    $pdo->beginTransaction();
    
    // Buscar dados do pedido atual
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone as cliente_telefone,
               v.nome as vendedor_nome, v.email as vendedor_email
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
    
    // Verificar permissões por perfil e status
    $permitido = false;
    $status_permitidos = [];
    
    switch ($_SESSION['user_perfil']) {
        case 'gestor':
            // Gestor pode alterar qualquer status
            $permitido = true;
            $status_permitidos = ['aprovado', 'pagamento_50', 'producao', 'pagamento_100', 'pronto', 'entregue', 'cancelado'];
            break;
            
        case 'vendedor':
            // Vendedor só pode alterar pedidos próprios e alguns status específicos
            if ($pedido['vendedor_id'] == $_SESSION['user_id']) {
                $permitido = true;
                $status_permitidos = ['aprovado', 'pagamento_50', 'pagamento_100', 'pronto', 'entregue'];
            }
            break;
            
        case 'producao':
            // Produção só pode alterar status relacionados à produção
            $permitido = true;
            $status_permitidos = ['producao', 'pronto'];
            break;
            
        case 'arte_finalista':
            // Arte finalista não pode alterar status de pedidos diretamente
            $permitido = false;
            break;
    }
    
    if (!$permitido) {
        throw new Exception('Você não tem permissão para alterar este pedido');
    }
    
    if (!in_array($novo_status, $status_permitidos)) {
        throw new Exception('Status não permitido para seu perfil');
    }
    
    // Verificar transições válidas de status
    $transicoes_validas = [
        'orcamento' => ['aprovado', 'cancelado'],
        'aprovado' => ['pagamento_50', 'cancelado'],
        'pagamento_50' => ['producao', 'cancelado'],
        'producao' => ['pagamento_100', 'pronto', 'cancelado'],
        'pagamento_100' => ['pronto', 'cancelado'],
        'pronto' => ['entregue', 'cancelado'],
        'entregue' => [], // Status final
        'cancelado' => [] // Status final
    ];
    
    // Gestor pode "pular" etapas se necessário
    if ($_SESSION['user_perfil'] !== 'gestor') {
        $status_atual = $pedido['status'];
        if (!in_array($novo_status, $transicoes_validas[$status_atual] ?? [])) {
            throw new Exception('Transição de status inválida: ' . ucfirst($status_atual) . ' → ' . ucfirst($novo_status));
        }
    }
    
    // Preparar observação baseada no status
    if (empty($observacao)) {
        switch ($novo_status) {
            case 'aprovado':
                $observacao = 'Orçamento aprovado - Pedido confirmado pelo cliente';
                break;
            case 'pagamento_50':
                $observacao = 'Entrada de 50% confirmada - Liberado para produção';
                break;
            case 'producao':
                $observacao = 'Pedido enviado para produção';
                break;
            case 'pagamento_100':
                $observacao = 'Pagamento final (50% restante) confirmado';
                break;
            case 'pronto':
                $observacao = 'Produção finalizada - Produto pronto para entrega';
                break;
            case 'entregue':
                $observacao = 'Produto entregue ao cliente - Pedido finalizado';
                break;
            case 'cancelado':
                $observacao = 'Pedido cancelado';
                break;
            default:
                $observacao = 'Status alterado para: ' . ucfirst(str_replace('_', ' ', $novo_status));
        }
    }
    
    // Verificar se o status realmente está mudando
    if ($pedido['status'] === $novo_status) {
        throw new Exception('O pedido já está no status: ' . ucfirst($novo_status));
    }
    
    // Atualizar status do pedido
    $campos_atualizacao = ['status = ?', 'updated_at = CURRENT_TIMESTAMP'];
    $valores_atualizacao = [$novo_status];
    
    // Definir campos específicos baseados no status
    switch ($novo_status) {
        case 'aprovado':
            $campos_atualizacao[] = 'data_aprovacao = CURRENT_TIMESTAMP';
            break;
        case 'pagamento_50':
            $campos_atualizacao[] = 'data_pagamento_entrada = CURRENT_TIMESTAMP';
            break;
        case 'producao':
            $campos_atualizacao[] = 'data_inicio_producao = CURRENT_TIMESTAMP';
            break;
        case 'pagamento_100':
            $campos_atualizacao[] = 'data_pagamento_final = CURRENT_TIMESTAMP';
            break;
        case 'pronto':
            $campos_atualizacao[] = 'data_conclusao = CURRENT_TIMESTAMP';
            break;
        case 'entregue':
            $campos_atualizacao[] = 'data_entrega = CURRENT_TIMESTAMP';
            break;
        case 'cancelado':
            $campos_atualizacao[] = 'data_cancelamento = CURRENT_TIMESTAMP';
            break;
    }
    
    $valores_atualizacao[] = $pedido_id;
    
    $sql_update = "UPDATE pedidos SET " . implode(', ', $campos_atualizacao) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql_update);
    $stmt->execute($valores_atualizacao);
    
    // Registrar mudança no histórico de produção
    $stmt = $pdo->prepare("
        INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id, created_at) 
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$pedido_id, $novo_status, $observacao, $_SESSION['user_id']]);
    
    // Registrar no log do sistema
    $acao = 'alterar_status_pedido';
    $detalhes = "Alterou status do pedido #{$pedido['numero']} de '{$pedido['status']}' para '{$novo_status}'. {$observacao}";
    
    registrarLog($acao, $detalhes);
    
    // Ações específicas para cada status
    switch ($novo_status) {
        case 'aprovado':
            // Enviar notificação para produção se configurado
            if ($pedido['cliente_email']) {
                // Aqui poderia enviar e-mail de confirmação
                // enviarEmailAprovacao($pedido);
            }
            break;
            
        case 'pagamento_50':
            // Criar notificação para produção
            $stmt = $pdo->prepare("
                INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link, created_at) 
                SELECT u.id, 'pedido_liberado', ?, ?, ?, CURRENT_TIMESTAMP
                FROM usuarios u 
                WHERE u.perfil IN ('producao', 'gestor') AND u.ativo = true
            ");
            $stmt->execute([
                'Pedido liberado para produção',
                "O pedido #{$pedido['numero']} foi liberado para produção após confirmação da entrada.",
                'pedido_detalhes.php?id=' . $pedido_id
            ]);
            break;
            
        case 'producao':
            // Marcar início efetivo da produção
            break;
            
        case 'pronto':
            // Notificar vendedor que produto está pronto
            if ($pedido['vendedor_id']) {
                $stmt = $pdo->prepare("
                    INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link, created_at) 
                    VALUES (?, 'pedido_pronto', ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    $pedido['vendedor_id'],
                    'Pedido pronto para entrega',
                    "O pedido #{$pedido['numero']} está pronto para ser entregue ao cliente.",
                    'pedido_detalhes.php?id=' . $pedido_id
                ]);
            }
            break;
            
        case 'entregue':
            // Finalizar pedido e calcular comissões se necessário
            break;
            
        case 'cancelado':
            // Reverter movimentações de estoque se necessário
            // Notificar equipes envolvidas
            break;
    }
    
    // Verificar se há prazo em atraso para notificar
    if (!in_array($novo_status, ['entregue', 'cancelado'])) {
        $prazo = new DateTime($pedido['prazo_entrega']);
        $hoje = new DateTime();
        if ($prazo < $hoje) {
            // Pedido em atraso - notificar gestor
            $stmt = $pdo->prepare("
                INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link, created_at) 
                SELECT u.id, 'pedido_atrasado', ?, ?, ?, CURRENT_TIMESTAMP
                FROM usuarios u 
                WHERE u.perfil = 'gestor' AND u.ativo = true
            ");
            $dias_atraso = $hoje->diff($prazo)->days;
            $stmt->execute([
                'Pedido em atraso',
                "O pedido #{$pedido['numero']} está {$dias_atraso} dias em atraso.",
                'pedido_detalhes.php?id=' . $pedido_id
            ]);
        }
    }
    
    $pdo->commit();
    
    // Mensagem de sucesso
    $status_labels = [
        'aprovado' => 'Aprovado',
        'pagamento_50' => 'Entrada Confirmada (50%)',
        'producao' => 'Em Produção',
        'pagamento_100' => 'Pagamento Final Confirmado',
        'pronto' => 'Pronto para Entrega',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado'
    ];
    
    $label_status = $status_labels[$novo_status] ?? ucfirst($novo_status);
    $_SESSION['mensagem'] = "Status do pedido #{$pedido['numero']} alterado para: {$label_status}";
    
    // Redirecionar de volta para detalhes
    redirect("pedido_detalhes.php?id={$pedido_id}");
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro ao alterar status do pedido: ' . $e->getMessage();
    $redirect_id = $pedido_id ?? '';
    redirect("pedido_detalhes.php?id={$redirect_id}");
}

exit;

// Função auxiliar para validar transições (se necessário em outros contextos)
function validarTransicaoStatus($status_atual, $novo_status, $perfil_usuario) {
    $transicoes = [
        'orcamento' => [
            'gestor' => ['aprovado', 'cancelado'],
            'vendedor' => ['aprovado', 'cancelado'],
            'producao' => [],
            'arte_finalista' => []
        ],
        'aprovado' => [
            'gestor' => ['pagamento_50', 'producao', 'cancelado'], // Gestor pode pular entrada
            'vendedor' => ['pagamento_50', 'cancelado'],
            'producao' => [],
            'arte_finalista' => []
        ],
        'pagamento_50' => [
            'gestor' => ['producao', 'pagamento_100', 'pronto', 'cancelado'], // Gestor flexível
            'vendedor' => ['producao', 'cancelado'],
            'producao' => ['producao', 'pronto'],
            'arte_finalista' => []
        ],
        'producao' => [
            'gestor' => ['pagamento_100', 'pronto', 'entregue', 'cancelado'], // Gestor flexível
            'vendedor' => ['pagamento_100', 'pronto'],
            'producao' => ['pronto'],
            'arte_finalista' => []
        ],
        'pagamento_100' => [
            'gestor' => ['pronto', 'entregue', 'cancelado'],
            'vendedor' => ['pronto', 'entregue'],
            'producao' => ['pronto'],
            'arte_finalista' => []
        ],
        'pronto' => [
            'gestor' => ['entregue', 'cancelado'],
            'vendedor' => ['entregue'],
            'producao' => [],
            'arte_finalista' => []
        ],
        'entregue' => [
            'gestor' => [], // Status final
            'vendedor' => [],
            'producao' => [],
            'arte_finalista' => []
        ],
        'cancelado' => [
            'gestor' => [], // Status final
            'vendedor' => [],
            'producao' => [],
            'arte_finalista' => []
        ]
    ];
    
    $status_permitidos = $transicoes[$status_atual][$perfil_usuario] ?? [];
    return in_array($novo_status, $status_permitidos);
}

// Função auxiliar para obter nome legível do status
function obterLabelStatus($status) {
    $labels = [
        'orcamento' => 'Orçamento',
        'aprovado' => 'Aprovado',
        'pagamento_50' => 'Entrada 50%',
        'producao' => 'Em Produção',
        'pagamento_100' => 'Pagamento Final',
        'pronto' => 'Pronto para Entrega',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado'
    ];
    
    return $labels[$status] ?? ucfirst($status);
}

// Função para enviar notificações por WhatsApp (se implementada)
function enviarNotificacaoWhatsApp($telefone, $mensagem) {
    // Implementar integração com API do WhatsApp se necessário
    return true;
}
?>