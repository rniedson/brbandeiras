<?php
// IMPORTANTE: Carregar ajax_helper ANTES de config.php
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

// Processar apenas um arquivo por vez (AJAX)
if (!isset($_FILES['arquivo']) || !isset($_POST['pedido_id'])) {
    AjaxResponse::error('Dados incompletos', 400);
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
    
    // Verificar conexão com banco
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não disponível');
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
    
    AjaxResponse::success([
        'arquivo_id' => $pdo->lastInsertId(),
        'nome' => $arquivo['name']
    ], 'Arquivo enviado com sucesso');
    
} catch (PDOException $e) {
    AjaxResponse::error('Erro de banco de dados: ' . $e->getMessage());
} catch (Exception $e) {
    AjaxResponse::error($e->getMessage());
}