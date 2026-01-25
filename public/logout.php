<?php
// Iniciar a sessão para poder destruí-la
session_start();

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir a sessão completamente, apague também o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Opcional: registrar o logout no banco (se tiver tabela de logs)
if (isset($_SESSION['user_id'])) {
    require_once '../app/config.php';
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_acesso (usuario_id, ip, acao) VALUES (?, ?, 'logout')");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        // Não bloquear o logout se houver erro no log
    }
}

// Redirecionar para a página de login
header('Location: index.php');
exit;
