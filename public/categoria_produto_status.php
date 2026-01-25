<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    // Buscar categoria
    $stmt = $pdo->prepare("SELECT nome, ativo FROM categorias_produtos WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
        exit;
    }
    
    $novo_status = !$categoria['ativo'];
    
    // Atualizar status
    $stmt = $pdo->prepare("UPDATE categorias_produtos SET ativo = ? WHERE id = ?");
    $stmt->execute([$novo_status, $id]);
    
    // Se desativando, verificar se há produtos ativos
    if (!$novo_status) {
        $stmt = $pdo->prepare("
            UPDATE produtos_catalogo 
            SET ativo = false 
            WHERE categoria_id = ? AND ativo = true
        ");
        $stmt->execute([$id]);
        
        $produtos_desativados = $stmt->rowCount();
        
        if ($produtos_desativados > 0) {
            $detalhes = "Desativou categoria: {$categoria['nome']} ($produtos_desativados produtos também desativados)";
        } else {
            $detalhes = "Desativou categoria: {$categoria['nome']}";
        }
    } else {
        $detalhes = "Ativou categoria: {$categoria['nome']}";
    }
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'alterar_status_categoria', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $detalhes,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Status alterado com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}