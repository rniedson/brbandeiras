<?php
// auth.php - Sistema de autenticação e autorização

/**
 * Verifica se o usuário está logado
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Requer que o usuário esteja logado
 * Redireciona para login se não estiver
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['erro'] = 'Você precisa estar logado para acessar esta página';
        header('Location: index.php');
        exit;
    }
}

/**
 * Verifica se o usuário tem um dos papéis especificados
 * @param array $roles Array com os papéis permitidos
 * @return bool
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Se está no modo "Ver Como", usar o perfil do usuário visualizado
    if (isset($_SESSION['ver_como_ativo']) && $_SESSION['ver_como_ativo']) {
        $perfil_atual = $_SESSION['ver_como_usuario']['perfil'];
    } else {
        $perfil_atual = $_SESSION['user_perfil'];
    }
    
    return in_array($perfil_atual, $roles);
}

/**
 * Requer que o usuário tenha um dos papéis especificados
 * @param array $roles Array com os papéis permitidos
 */
function requireRole($roles) {
    if (!hasRole($roles)) {
        $_SESSION['erro'] = 'Você não tem permissão para acessar esta página';
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Verifica se está no modo "Ver Como"
 * @return bool
 */
function isVerComoAtivo() {
    return isset($_SESSION['ver_como_ativo']) && $_SESSION['ver_como_ativo'] === true;
}

/**
 * Obtém o ID real do usuário (mesmo no modo "Ver Como")
 * @return int|null
 */
function getUserIdReal() {
    if (isVerComoAtivo()) {
        return $_SESSION['ver_como_original']['user_id'] ?? $_SESSION['user_id'];
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtém o ID efetivo do usuário (considerando modo "Ver Como")
 * @return int|null
 */
function getUserIdEfetivo() {
    if (isVerComoAtivo()) {
        return $_SESSION['ver_como_usuario']['id'];
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtém o perfil efetivo do usuário (considerando modo "Ver Como")
 * @return string|null
 */
function getPerfilEfetivo() {
    if (isVerComoAtivo()) {
        return $_SESSION['ver_como_usuario']['perfil'];
    }
    return $_SESSION['user_perfil'] ?? null;
}

/**
 * Obtém o nome efetivo do usuário (considerando modo "Ver Como")
 * @return string|null
 */
function getNomeEfetivo() {
    if (isVerComoAtivo()) {
        return $_SESSION['ver_como_usuario']['nome'];
    }
    return $_SESSION['user_nome'] ?? null;
}

/**
 * Verifica se uma ação de escrita/alteração é permitida
 * @return bool
 */
function podeExecutarAcao() {
    // No modo "Ver Como", bloquear ações de alteração por segurança
    if (isVerComoAtivo()) {
        return false;
    }
    return true;
}

/**
 * Requer que o usuário possa executar ações (não esteja em modo "Ver Como")
 */
function requirePodeExecutarAcao() {
    if (!podeExecutarAcao()) {
        $_SESSION['erro'] = 'Esta ação não pode ser executada no modo "Ver Como". Por favor, volte à sua conta normal.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit;
    }
}

/**
 * Obtém informações para display considerando modo "Ver Como"
 * @return array
 */
function getInfoUsuarioDisplay() {
    if (isVerComoAtivo()) {
        return [
            'nome' => $_SESSION['ver_como_usuario']['nome'],
            'email' => $_SESSION['ver_como_usuario']['email'],
            'perfil' => $_SESSION['ver_como_usuario']['perfil'],
            'modo_ver_como' => true,
            'gestor_nome' => $_SESSION['ver_como_original']['user_nome'],
            'gestor_id' => $_SESSION['ver_como_original']['user_id']
        ];
    }
    
    return [
        'nome' => $_SESSION['user_nome'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'perfil' => $_SESSION['user_perfil'] ?? '',
        'modo_ver_como' => false
    ];
}

/**
 * Registra log considerando modo "Ver Como"
 * @param string $acao
 * @param string $detalhes
 */
function registrarLogComVerComo($acao, $detalhes) {
    $usuario_id = getUserIdReal(); // Sempre usar o ID real para logs
    
    if (isVerComoAtivo()) {
        $detalhes = "[MODO VER COMO - Visualizando como: {$_SESSION['ver_como_usuario']['nome']}] " . $detalhes;
    }
    
    $db = getDb();
    $db->query("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip_address, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ", [
        $usuario_id,
        $acao,
        $detalhes,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
}

/**
 * Renova o ID da sessão periodicamente para melhorar segurança
 * Regenera o ID a cada 30 minutos de atividade
 */
function renovarSessaoSeNecessario(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar se há última atividade registrada
    if (isset($_SESSION['last_activity'])) {
        $tempoInativo = time() - $_SESSION['last_activity'];
        
        // Regenerar ID da sessão a cada 30 minutos (1800 segundos)
        if ($tempoInativo > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
        }
    } else {
        // Primeira vez, registrar atividade
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Verifica se a sessão expirou e destrói se necessário
 * @return bool True se sessão ainda válida, false se expirada
 */
function verificarSessaoExpirada(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Tempo máximo de inatividade: 2 horas (7200 segundos)
    $timeout = 7200;
    
    if (isset($_SESSION['last_activity'])) {
        $tempoInativo = time() - $_SESSION['last_activity'];
        
        if ($tempoInativo > $timeout) {
            // Sessão expirada
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Atualizar timestamp de última atividade
    $_SESSION['last_activity'] = time();
    return true;
}

// Inicializar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Renovar sessão se necessário (apenas se usuário estiver logado)
if (isLoggedIn()) {
    renovarSessaoSeNecessario();
    
    // Verificar se sessão expirou
    if (!verificarSessaoExpirada()) {
        // Sessão expirada, redirecionar para login
        $_SESSION['erro'] = 'Sua sessão expirou por inatividade. Por favor, faça login novamente.';
        header('Location: index.php');
        exit;
    }
}
?>