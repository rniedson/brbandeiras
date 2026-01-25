<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erro'] = 'Método inválido';
    header('Location: arte_finalista.php');
    exit;
}

try {
    $pedido_id = $_POST['pedido_id'] ?? null;
    $comentario_arte = trim($_POST['comentario_arte'] ?? '');
    
    if (!$pedido_id) {
        throw new Exception('Pedido não informado');
    }
    
    // Verificar se o usuário é o arte-finalista do pedido
    $stmt = $pdo->prepare("
        SELECT pa.arte_finalista_id 
        FROM pedido_arte pa 
        WHERE pa.pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido_arte = $stmt->fetch();
    
    // Se não tem arte-finalista, atribuir ao usuário atual
    if (!$pedido_arte) {
        $stmt = $pdo->prepare("
            INSERT INTO pedido_arte (pedido_id, arte_finalista_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$pedido_id, $_SESSION['user_id']]);
    } elseif ($pedido_arte['arte_finalista_id'] != $_SESSION['user_id'] && $_SESSION['user_perfil'] !== 'gestor') {
        throw new Exception('Você não é o arte-finalista deste pedido');
    }
    
    // Processar upload
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload do arquivo');
    }
    
    $arquivo = $_FILES['arquivo'];
    $nome_original = $arquivo['name'];
    $tamanho = $arquivo['size'];
    $tmp_name = $arquivo['tmp_name'];
    
    // Validações
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($tamanho > $max_size) {
        throw new Exception('Arquivo muito grande (máximo 50MB)');
    }
    
    $ext = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $extensoes_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($ext, $extensoes_permitidas)) {
        throw new Exception('Tipo de arquivo não permitido');
    }
    
    // Obter próxima versão
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(versao), 0) + 1 as proxima_versao
        FROM arte_versoes 
        WHERE pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $proxima_versao = $stmt->fetchColumn();
    
    // Criar diretório
    $upload_dir = '../uploads/artes/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Gerar nome único
    $novo_nome = $pedido_id . '_v' . $proxima_versao . '_' . uniqid() . '.' . $ext;
    $caminho_completo = $upload_dir . $novo_nome;
    $caminho_relativo = 'uploads/artes/' . $novo_nome;
    
    // Mover arquivo
    if (!move_uploaded_file($tmp_name, $caminho_completo)) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
    // Inserir versão
    $stmt = $pdo->prepare("
        INSERT INTO arte_versoes (
            pedido_id, versao, arquivo_nome, arquivo_caminho, 
            status, comentario_arte, usuario_id
        ) VALUES (?, ?, ?, ?, 'pendente', ?, ?)
    ");
    $stmt->execute([
        $pedido_id,
        $proxima_versao,
        $nome_original,
        $caminho_relativo,
        $comentario_arte ?: null,
        $_SESSION['user_id']
    ]);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'upload_arte_versao', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Upload arte versão $proxima_versao para pedido #$pedido_id",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $_SESSION['mensagem'] = 'Arte enviada com sucesso!';
    header("Location: arte_finalista_detalhes.php?id=$pedido_id");
    
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao enviar arte: ' . $e->getMessage();
    header('Location: arte_finalista.php');
}