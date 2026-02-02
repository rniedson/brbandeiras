<?php
// IMPORTANTE: Carregar ajax_helper ANTES de config.php
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

// Debug - log para diagnóstico (remover após resolver)
$debug_log = __DIR__ . '/../uploads/upload_debug.log';
$content_length = $_SERVER['CONTENT_LENGTH'] ?? 0;
$max_post = return_bytes(ini_get('post_max_size'));
$max_upload = return_bytes(ini_get('upload_max_filesize'));

$debug_data = date('Y-m-d H:i:s') . " - ";
$debug_data .= "CONTENT_LENGTH: $content_length | ";
$debug_data .= "POST_MAX: $max_post | ";
$debug_data .= "UPLOAD_MAX: $max_upload | ";
$debug_data .= "SESSION user_id: " . ($_SESSION['user_id'] ?? 'UNDEFINED') . " | ";
$debug_data .= "POST: " . json_encode($_POST) . " | ";
$debug_data .= "FILES keys: " . json_encode(array_keys($_FILES)) . "\n";
@file_put_contents($debug_log, $debug_data, FILE_APPEND);

// Função auxiliar para converter valores como "2M" para bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Verificar se o upload excedeu os limites ANTES de verificar $_FILES
if ($content_length > 0 && empty($_FILES) && empty($_POST)) {
    $max_size_mb = round($max_upload / 1024 / 1024, 1);
    AjaxResponse::error("Arquivo muito grande. Limite máximo: {$max_size_mb}MB. Configure upload_max_filesize no PHP.", 413);
}

// Verificar autenticação
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado. Faça login novamente.', 401);
}

// Processar apenas um arquivo por vez (AJAX)
if (!isset($_FILES['arquivo'])) {
    AjaxResponse::error('Nenhum arquivo enviado. Verifique o limite de upload.', 400);
}

if (!isset($_POST['pedido_id']) || empty($_POST['pedido_id'])) {
    AjaxResponse::error('ID do pedido não informado', 400);
}

$pedido_id = intval($_POST['pedido_id']);
if ($pedido_id <= 0) {
    AjaxResponse::error('ID do pedido inválido', 400);
}

$arquivo = $_FILES['arquivo'];

// Verificar se houve erro no upload
if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor',
        UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulário',
        UPLOAD_ERR_PARTIAL => 'Upload parcial',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo',
        UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
    ];
    $error_msg = $upload_errors[$arquivo['error']] ?? 'Erro desconhecido no upload';
    AjaxResponse::error($error_msg, 400);
}

try {
    // Validações
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'ai', 'cdr', 'psd', 'mp3', 'ogg', 'opus', 'm4a', 'wav', 'aac', 'amr', 'webm'];
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
            pedido_id, nome_arquivo, nome_original, caminho, tipo, tamanho, 
            usuario_id, uploaded_by, uploaded_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $pedido_id,
        $new_filename,           // nome_arquivo (nome único gerado)
        $arquivo['name'],        // nome_original (nome original)
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