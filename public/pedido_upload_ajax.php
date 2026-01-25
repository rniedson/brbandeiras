<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

header('Content-Type: application/json');

// Processar apenas um arquivo por vez (AJAX)
if (!isset($_FILES['arquivo']) || !isset($_POST['pedido_id'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$pedido_id = intval($_POST['pedido_id']);
$arquivo = $_FILES['arquivo'];

try {
    // Validações
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'ai', 'cdr', 'psd'];
    $max_size = 25 * 1024 * 1024; // 25MB
    
    $file_ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception("Tipo de arquivo não permitido");
    }
    
    if ($arquivo['size'] > $max_size) {
        throw new Exception("Arquivo muito grande (máx. 25MB)");
    }
    
    // Upload
    $upload_dir = '../uploads/pedidos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $new_filename = $pedido_id . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $new_filename;
    
    if (!move_uploaded_file($arquivo['tmp_name'], $file_path)) {
        throw new Exception("Erro ao mover arquivo");
    }
    
    // Salvar no banco
    $stmt = $pdo->prepare("
        INSERT INTO pedido_arquivos (
            pedido_id, nome_arquivo, caminho, tipo, tamanho, 
            usuario_id, uploaded_by, uploaded_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $pedido_id,
        $arquivo['name'],
        'uploads/pedidos/' . $new_filename,
        $arquivo['type'],
        $arquivo['size'],
        $_SESSION['user_id'],
        $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'arquivo_id' => $pdo->lastInsertId(),
        'nome' => $arquivo['name']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}