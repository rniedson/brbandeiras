<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireLogin();

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
    // Buscar arquivo
    $stmt = $pdo->prepare("
        SELECT pa.*, p.vendedor_id 
        FROM pedido_arquivos pa
        INNER JOIN pedidos p ON pa.pedido_id = p.id
        WHERE pa.id = ?
    ");
    $stmt->execute([$id]);
    $arquivo = $stmt->fetch();
    
    if (!$arquivo) {
        throw new Exception('Arquivo não encontrado');
    }
    
    // Verificar permissão
    if ($_SESSION['user_perfil'] === 'vendedor' && 
        $arquivo['vendedor_id'] != $_SESSION['user_id'] && 
        $arquivo['usuario_id'] != $_SESSION['user_id']) {
        throw new Exception('Sem permissão para excluir este arquivo');
    }
    
    // Excluir arquivo físico
    $caminho_completo = '../' . $arquivo['caminho_arquivo'];
    if (file_exists($caminho_completo)) {
        unlink($caminho_completo);
    }
    
    // Excluir do banco
    $stmt = $pdo->prepare("DELETE FROM pedido_arquivos WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'excluir_arte', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Excluiu arquivo: {$arquivo['nome_arquivo']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Arquivo excluído com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}