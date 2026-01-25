<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['gestor']);

// Resposta JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $usuario_id = $_POST['usuario_id'] ?? null;
    
    if (!$usuario_id) {
        throw new Exception('ID do usuário não informado');
    }
    
    // Verificar se o usuário existe e está ativo
    $stmt = $pdo->prepare("SELECT id, nome, email, perfil, ativo FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario_alvo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_alvo) {
        throw new Exception('Usuário não encontrado');
    }
    
    if (!$usuario_alvo['ativo']) {
        throw new Exception('Não é possível visualizar como um usuário inativo');
    }
    
    if ($usuario_alvo['id'] == $_SESSION['user_id']) {
        throw new Exception('Você não pode visualizar como você mesmo');
    }
    
    // Salvar informações originais do gestor antes de ativar o modo
    if (!isset($_SESSION['ver_como_ativo'])) {
        $_SESSION['ver_como_original'] = [
            'user_id' => $_SESSION['user_id'],
            'user_nome' => $_SESSION['user_nome'],
            'user_email' => $_SESSION['user_email'],
            'user_perfil' => $_SESSION['user_perfil']
        ];
    }
    
    // Ativar modo "Ver Como"
    $_SESSION['ver_como_ativo'] = true;
    $_SESSION['ver_como_usuario'] = [
        'id' => $usuario_alvo['id'],
        'nome' => $usuario_alvo['nome'],
        'email' => $usuario_alvo['email'],
        'perfil' => $usuario_alvo['perfil']
    ];
    
    // Temporariamente assumir o perfil do usuário alvo (mas manter ID original para logs)
    $_SESSION['user_perfil'] = $usuario_alvo['perfil'];
    
    // Registrar no log
    registrarLog(
        'ver_como_ativado',
        "Gestor #{$_SESSION['ver_como_original']['user_id']} ({$_SESSION['ver_como_original']['user_nome']}) " .
        "ativou modo 'Ver Como' para usuário #{$usuario_alvo['id']} ({$usuario_alvo['nome']}) - Perfil: {$usuario_alvo['perfil']}"
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Modo "Ver Como" ativado com sucesso',
        'usuario' => [
            'nome' => $usuario_alvo['nome'],
            'perfil' => $usuario_alvo['perfil']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>