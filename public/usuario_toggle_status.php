<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$userId = $_POST['user_id'] ?? null;
$ativo = $_POST['ativo'] === 'true';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não informado']);
    exit;
}

// Não permitir desativar a si mesmo
if ($userId == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Você não pode desativar seu próprio usuário']);
    exit;
}

try {
    // Atualizar status
    $stmt = $pdo->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?");
    $stmt->execute([$ativo, $userId]);
    
    // Log da ação
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch();
    
    $acao = $ativo ? 'ativar_usuario' : 'desativar_usuario';
    $detalhes = ($ativo ? 'Ativou' : 'Desativou') . " usuário: {$usuario['nome']} ({$usuario['email']})";
    
    $stmt = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, detalhes) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $acao, $detalhes]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
