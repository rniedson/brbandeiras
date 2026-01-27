<?php
/**
 * Processador de autenticação (login)
 * 
 * Este arquivo só processa requisições POST do formulário de login.
 * Acessos diretos via GET são redirecionados para a página inicial.
 */

require_once '../app/config.php';
require_once '../app/Core/RateLimiter.php';

// Só processar requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Acesso direto via GET - redirecionar para página inicial
    header('Location: index.php');
    exit;
}

// Validar token CSRF
try {
    CSRF::validate($_POST['csrf_token'] ?? '');
} catch (RuntimeException $e) {
    $_SESSION['erro'] = $e->getMessage();
    header('Location: index.php');
    exit;
}

// Verificar rate limiting antes de processar login
if (!RateLimiter::check('login', null, 5, 900)) { // 5 tentativas em 15 minutos
    $remainingTime = RateLimiter::getTimeUntilReset('login', null, 900);
    $minutes = ceil($remainingTime / 60);
    $_SESSION['erro'] = "Muitas tentativas de login. Tente novamente em {$minutes} minuto(s).";
    header('Location: index.php');
    exit;
}

// Processar login
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$senha = $_POST['senha'] ?? '';

// Validar dados obrigatórios
if (empty($email) || empty($senha)) {
    RateLimiter::recordAttempt('login'); // Registrar tentativa inválida
    $_SESSION['erro'] = 'E-mail e senha são obrigatórios';
    header('Location: index.php');
    exit;
}

try {
    // Verificar se conexão PDO está disponível
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    $stmt = $pdo->prepare("SELECT id, nome, email, senha, perfil FROM usuarios WHERE email = ? AND ativo = true");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Login bem-sucedido - limpar tentativas de rate limiting
        RateLimiter::clear('login');
        
        // Login bem-sucedido
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_nome'] = $usuario['nome'];
        $_SESSION['user_perfil'] = $usuario['perfil'];
        $_SESSION['last_activity'] = time(); // Registrar atividade para renovação de sessão
        
        // Log de acesso (com tratamento de erro silencioso)
        try {
            $stmt = $pdo->prepare("INSERT INTO logs_acesso (usuario_id, acao, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $usuario['id'],
                'login',
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Não bloquear login se falhar log
            error_log("Erro ao registrar log de acesso: " . $e->getMessage());
        }
        
        header('Location: dashboard/dashboard.php');
        exit;
    } else {
        // Login falhou - registrar tentativa
        RateLimiter::recordAttempt('login');
        
        $remainingAttempts = RateLimiter::getRemainingAttempts('login', null, 5, 900);
        if ($remainingAttempts <= 0) {
            $remainingTime = RateLimiter::getTimeUntilReset('login', null, 900);
            $minutes = ceil($remainingTime / 60);
            $_SESSION['erro'] = "Muitas tentativas de login. Tente novamente em {$minutes} minuto(s).";
        } else {
            $_SESSION['erro'] = "E-mail ou senha inválidos. Você tem {$remainingAttempts} tentativa(s) restante(s).";
        }
        
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erro de login (PDO): " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao processar login. Tente novamente.';
    header('Location: index.php');
    exit;
} catch (Exception $e) {
    error_log("Erro de login: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao processar login. Tente novamente.';
    header('Location: index.php');
    exit;
}
