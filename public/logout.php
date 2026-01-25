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
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/public/logout.php';
$scriptPath = str_replace('//', '/', $scriptPath);

// Encontrar onde está '/public/' no caminho
$publicPos = strpos($scriptPath, '/public/');
if ($publicPos !== false) {
    // Extrair tudo até '/public/' incluindo a barra final
    $basePath = substr($scriptPath, 0, $publicPos + 7); // 7 = strlen('/public/')
    // Garantir que termina com /
    $basePath = rtrim($basePath, '/') . '/';
    $redirectUrl = $basePath . 'index.php';
} else {
    // Fallback: tentar detectar de outra forma
    $scriptDir = dirname($scriptPath);
    if ($scriptDir === '/public' || strpos($scriptDir, '/public') !== false) {
        $redirectUrl = '/public/index.php';
    } else {
        $redirectUrl = '/brbandeiras/public/index.php';
    }
}

header('Location: ' . $redirectUrl);
exit;
