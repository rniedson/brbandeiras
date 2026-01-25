<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$pedido_id = $_POST['pedido_id'] ?? null;

if (!$pedido_id) {
    echo json_encode(['success' => false, 'message' => 'Pedido não informado']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar pedido
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome as vendedor_nome
        FROM pedidos p
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE p.id = ? AND p.status = 'entregue'
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado ou não está entregue');
    }
    
    // Taxa de comissão padrão (5%)
    $taxa_comissao = 5.0;
    $valor_comissao = $pedido['valor_final'] * $taxa_comissao / 100;
    
    // Verificar se já existe registro de comissão
    $stmt = $pdo->prepare("SELECT id FROM comissoes WHERE pedido_id = ?");
    $stmt->execute([$pedido_id]);
    $comissao_existente = $stmt->fetch();
    
    if ($comissao_existente) {
        // Atualizar comissão existente
        $stmt = $pdo->prepare("
            UPDATE comissoes 
            SET status_pagamento = 'pago',
                data_pagamento = CURRENT_DATE,
                updated_at = CURRENT_TIMESTAMP
            WHERE pedido_id = ?
        ");
        $stmt->execute([$pedido_id]);
    } else {
        // Criar novo registro de comissão
        $stmt = $pdo->prepare("
            INSERT INTO comissoes (
                pedido_id, vendedor_id, valor_pedido, 
                taxa_comissao, valor_comissao, status_pagamento, data_pagamento
            ) VALUES (?, ?, ?, ?, ?, 'pago', CURRENT_DATE)
        ");
        $stmt->execute([
            $pedido_id,
            $pedido['vendedor_id'],
            $pedido['valor_final'],
            $taxa_comissao,
            $valor_comissao
        ]);
    }
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'pagar_comissao', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Pagou comissão do pedido #{$pedido['numero']} - Vendedor: {$pedido['vendedor_nome']} - Valor: " . formatarMoeda($valor_comissao),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Comissão marcada como paga com sucesso'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Se a tabela não existir, criar mensagem amigável
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'A tabela de comissões ainda não foi criada no banco de dados.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao processar pagamento: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
