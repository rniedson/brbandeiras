<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Orçamento não informado']);
    exit;
}

try {
    // Buscar orçamento com dados completos
    $stmt = $pdo->prepare("
        SELECT p.*, 
               c.nome as cliente_nome, 
               c.email as cliente_email,
               c.telefone as cliente_telefone,
               u.nome as vendedor_nome,
               u.email as vendedor_email,
               u.telefone as vendedor_telefone
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE p.id = ? AND p.status = 'orcamento'
    ");
    $stmt->execute([$id]);
    $orcamento = $stmt->fetch();
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
    
    if (!$orcamento['cliente_email']) {
        throw new Exception('Cliente não possui e-mail cadastrado');
    }
    
    // Verificar permissão
    if ($_SESSION['user_perfil'] === 'vendedor' && $orcamento['vendedor_id'] != $_SESSION['user_id']) {
        throw new Exception('Sem permissão para enviar este orçamento');
    }
    
    // Buscar itens
    $stmt = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll();
    
    // Gerar token para visualização
    $token = md5($id . $orcamento['created_at']);
    
    // Montar e-mail
    $assunto = "Orçamento #{$orcamento['numero']} - " . NOME_EMPRESA;
    
    $mensagem = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: #22c55e; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .items-table th, .items-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .items-table th { background: #f5f5f5; }
            .total { background: #f5f5f5; padding: 15px; margin: 20px 0; text-align: right; }
            .button { display: inline-block; padding: 12px 30px; background: #22c55e; color: white; text-decoration: none; border-radius: 5px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Orçamento #{$orcamento['numero']}</h1>
            </div>
            
            <div class='content'>
                <p>Olá <strong>{$orcamento['cliente_nome']}</strong>,</p>
                <p>Segue abaixo o orçamento solicitado:</p>
                
                <table class='items-table'>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qtd</th>
                            <th>Valor Unit.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($itens as $item) {
        $mensagem .= "
                        <tr>
                            <td>" . htmlspecialchars($item['descricao']) . "</td>
                            <td>" . number_format($item['quantidade'], 0, ',', '.') . "</td>
                            <td>" . formatarMoeda($item['valor_unitario']) . "</td>
                            <td>" . formatarMoeda($item['valor_total']) . "</td>
                        </tr>";
    }
    
    $mensagem .= "
                    </tbody>
                </table>
                
                <div class='total'>
                    <p><strong>Subtotal:</strong> " . formatarMoeda($orcamento['valor_total']) . "</p>";
    
    if ($orcamento['desconto'] > 0) {
        $mensagem .= "<p><strong>Desconto:</strong> " . formatarMoeda($orcamento['desconto']) . "</p>";
    }
    
    $mensagem .= "
                    <p style='font-size: 18px;'><strong>Total:</strong> " . formatarMoeda($orcamento['valor_final']) . "</p>
                </div>
                
                <p><strong>Prazo de Entrega:</strong> " . formatarData($orcamento['prazo_entrega']) . "</p>";
    
    if ($orcamento['observacoes']) {
        $mensagem .= "<p><strong>Observações:</strong><br>" . nl2br(htmlspecialchars($orcamento['observacoes'])) . "</p>";
    }
    
    $mensagem .= "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . BASE_URL . "orcamento_visualizar.php?token=$token' class='button'>
                        Visualizar Orçamento Completo
                    </a>
                </div>
                
                <p>Para aprovar este orçamento, clique no botão acima ou entre em contato conosco.</p>
                
                <div class='footer'>
                    <p><strong>Vendedor:</strong> {$orcamento['vendedor_nome']}</p>";
    
    if ($orcamento['vendedor_email']) {
        $mensagem .= "<p><strong>E-mail:</strong> {$orcamento['vendedor_email']}</p>";
    }
    if ($orcamento['vendedor_telefone']) {
        $mensagem .= "<p><strong>Telefone:</strong> {$orcamento['vendedor_telefone']}</p>";
    }
    
    $mensagem .= "
                    <p style='margin-top: 20px;'>Este orçamento tem validade de 7 dias a partir da data de envio.</p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    // Enviar e-mail
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SISTEMA_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ($orcamento['vendedor_email'] ?: SISTEMA_EMAIL) . "\r\n";
    
    if (mail($orcamento['cliente_email'], $assunto, $mensagem, $headers)) {
        // Registrar envio
        $stmt = $pdo->prepare("
            INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id) 
            VALUES (?, 'orcamento', ?, ?)
        ");
        $stmt->execute([
            $id,
            "Orçamento enviado por e-mail para " . $orcamento['cliente_email'],
            $_SESSION['user_id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'E-mail enviado com sucesso!']);
    } else {
        throw new Exception('Erro ao enviar e-mail');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}