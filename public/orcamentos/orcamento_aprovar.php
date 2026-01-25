<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = $_GET['id'] ?? null;
} else {
    $id = $_POST['id'] ?? null;
}

if (!$id) {
    $_SESSION['erro'] = 'Orçamento não informado';
    header('Location: orcamentos.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar orçamento
    $stmt = $pdo->prepare("
        SELECT p.*, c.email as cliente_email, c.nome as cliente_nome
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = ? AND p.status = 'orcamento'
    ");
    $stmt->execute([$id]);
    $orcamento = $stmt->fetch();
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado ou já foi processado');
    }
    
    // Verificar permissão
    if ($_SESSION['user_perfil'] === 'vendedor' && $orcamento['vendedor_id'] != $_SESSION['user_id']) {
        throw new Exception('Você não tem permissão para aprovar este orçamento');
    }
    
    // Atualizar status para aprovado
    $stmt = $pdo->prepare("
        UPDATE pedidos 
        SET status = 'aprovado', 
            data_aprovacao = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    
    // Registrar no histórico
    $observacoes = $_POST['observacoes'] ?? 'Orçamento aprovado pelo cliente';
    $stmt = $pdo->prepare("
        INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id) 
        VALUES (?, 'aprovado', ?, ?)
    ");
    $stmt->execute([$id, $observacoes, $_SESSION['user_id']]);
    
    // Log do sistema
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'aprovar_orcamento', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Aprovou orçamento #{$orcamento['numero']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    // Enviar e-mail se solicitado
    if (isset($_POST['enviar_email']) && $_POST['enviar_email'] == '1' && $orcamento['cliente_email']) {
        enviarEmailAprovacao($orcamento);
    }
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Orçamento #{$orcamento['numero']} aprovado com sucesso!";
    header('Location: pedido_detalhes.php?id=' . $id);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro'] = 'Erro ao aprovar orçamento: ' . $e->getMessage();
    header('Location: orcamento_detalhes.php?id=' . $id);
}

function enviarEmailAprovacao($orcamento) {
    $assunto = "Orçamento #{$orcamento['numero']} - Aprovado";
    
    $mensagem = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Orçamento Aprovado!</h2>
        <p>Olá {$orcamento['cliente_nome']},</p>
        <p>Seu orçamento <strong>#{$orcamento['numero']}</strong> foi aprovado com sucesso!</p>
        
        <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px;'>
            <p><strong>Valor Total:</strong> " . formatarMoeda($orcamento['valor_final']) . "</p>
            <p><strong>Prazo de Entrega:</strong> " . formatarData($orcamento['prazo_entrega']) . "</p>
        </div>
        
        <p>Seu pedido já está em produção. Acompanhe o status através do link:</p>
        <p><a href='" . BASE_URL . "pedido_acompanhar.php?token=" . md5($orcamento['id'] . $orcamento['created_at']) . "' 
              style='display: inline-block; padding: 10px 20px; background: #22c55e; color: white; text-decoration: none; border-radius: 5px;'>
              Acompanhar Pedido
        </a></p>
        
        <p>Qualquer dúvida, entre em contato conosco.</p>
        
        <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
        <p style='color: #666; font-size: 12px;'>
            Este é um e-mail automático. Por favor, não responda.
        </p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SISTEMA_EMAIL . "\r\n";
    
    mail($orcamento['cliente_email'], $assunto, $mensagem, $headers);
}