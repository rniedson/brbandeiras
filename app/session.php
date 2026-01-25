<?php
/**
 * Configurações e funções de gerenciamento de sessão
 */

// Configurar parâmetros de sessão seguros
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // HTTPS em produção
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 3600); // 1 hora de inatividade

// Função para verificar timeout de sessão
function checkSessionTimeout() {
    $timeout = 3600; // 1 hora em segundos
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];
        
        if ($elapsed_time >= $timeout) {
            // Sessão expirada
            session_destroy();
            $_SESSION['mensagem'] = 'Sua sessão expirou. Por favor, faça login novamente.';
            header('Location: index.php');
            exit;
        }
    }
    
    // Atualizar timestamp da última atividade
    $_SESSION['last_activity'] = time();
}

// Função para regenerar ID de sessão periodicamente (segurança)
function regenerateSessionId() {
    if (!isset($_SESSION['regenerated'])) {
        $_SESSION['regenerated'] = time();
    } elseif (time() - $_SESSION['regenerated'] > 1800) { // 30 minutos
        session_regenerate_id(true);
        $_SESSION['regenerated'] = time();
    }
}

// Aplicar verificações em páginas autenticadas
if (isset($_SESSION['user_id'])) {
    checkSessionTimeout();
    regenerateSessionId();
}
