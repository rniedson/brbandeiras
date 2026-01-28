<?php
// Iniciar a sessão para poder destruí-la
session_start();

// Salvar user_id ANTES de destruir a sessão (para logging)
$userId = $_SESSION['user_id'] ?? null;

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
if ($userId) {
    require_once __DIR__ . '/../app/config.php';
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_acesso (usuario_id, ip, acao) VALUES (?, ?, 'logout')");
        $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Não bloquear o logout se houver erro no log
    }
}

// Redirecionar para a página de login
// Calcular caminho base automaticamente
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

// Se o DocumentRoot termina com /public ou contém /var/www/, estamos em produção
if (preg_match('#/public/?$#', $documentRoot) || 
    (strpos($scriptName, '/public/') === false && strpos($documentRoot, '/var/www/') !== false)) {
    // Produção: DocumentRoot = /var/www/brbandeiras/public
    $redirectUrl = '/index.php';
} else {
    // Desenvolvimento: DocumentRoot pode ser /Applications/AMPPS/www ou similar
    $publicPos = strpos($scriptName, '/public/');
    if ($publicPos !== false) {
        $basePath = substr($scriptName, 0, $publicPos + 8); // 8 = strlen('/public/')
        $redirectUrl = $basePath . 'index.php';
    } else {
        // Fallback: usar caminho padrão de desenvolvimento
        $redirectUrl = '/brbandeiras/public/index.php';
    }
}

header('Location: ' . $redirectUrl);
exit;
