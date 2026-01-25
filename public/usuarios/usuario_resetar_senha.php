<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$userId = $_POST['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não informado']);
    exit;
}

try {
    // Gerar nova senha
    $novaSenha = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    
    // Atualizar senha
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, forcar_troca_senha = true WHERE id = ?");
    $stmt->execute([$senhaHash, $userId]);
    
    // Log da ação
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch();
    
    $stmt = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, detalhes) VALUES (?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        'resetar_senha',
        "Resetou senha de: {$usuario['nome']} ({$usuario['email']})"
    ]);
    
    echo json_encode([
        'success' => true, 
        'senha' => $novaSenha,
        'message' => 'Senha resetada com sucesso'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
