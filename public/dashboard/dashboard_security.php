<?php
// dashboard_security.php - Middleware de segurança para dashboards

function requireDashboardAccess($dashboard_type) {
    requireLogin();
    
    $user_perfil = $_SESSION['user_perfil'];
    $user_id = $_SESSION['user_id'];
    $user_nome = $_SESSION['user_nome'];
    
    // Mapeamento de dashboards permitidos por perfil
    $dashboards_permitidos = [
        'gestor' => ['dashboard_gestor.php'],
        'vendedor' => ['dashboard_vendedor.php'],
        'producao' => ['dashboard_producao.php'],
        'arte_finalista' => ['dashboard_arte_finalista.php']
    ];
    
    // Verificar se o perfil pode acessar este dashboard
    $arquivo_atual = basename($_SERVER['PHP_SELF']);
    
    if (!isset($dashboards_permitidos[$user_perfil]) || 
        !in_array($arquivo_atual, $dashboards_permitidos[$user_perfil])) {
        
        // Registrar tentativa de acesso indevido
        registrarLog('acesso_negado_dashboard', 
            "Usuário {$user_nome} (ID: {$user_id}, Perfil: {$user_perfil}) tentou acessar {$arquivo_atual}");
        
        // Definir mensagem de erro específica
        $dashboard_correto = $dashboards_permitidos[$user_perfil][0];
        $_SESSION['erro'] = "Acesso negado. Você será redirecionado para seu dashboard.";
        
        // Redirecionar para dashboard correto
        header('Location: ' . $dashboard_correto);
        exit;
    }
    
    // Se chegou aqui, acesso é válido
    registrarLog('acesso_dashboard', 
        "Usuário {$user_nome} acessou {$arquivo_atual}");
}

// Função para verificar se usuário está tentando burlar sistema
function detectarTentativaBurla() {
    $user_id = $_SESSION['user_id'];
    
    // Verificar se há múltiplas tentativas de acesso negado nos últimos 5 minutos
    $db = getDb();
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM logs_sistema 
        WHERE usuario_id = ? 
        AND acao = 'acesso_negado_dashboard' 
        AND created_at > NOW() - INTERVAL '5 minutes'
    ", [$user_id]);
    $tentativas = $stmt->fetchColumn();
    
    if ($tentativas >= 3) {
        // Bloquear usuário temporariamente
        $_SESSION['erro'] = 'Muitas tentativas de acesso indevido. Contate o administrador.';
        
        registrarLog('suspeita_burla_sistema', 
            "Usuário {$_SESSION['user_nome']} fez {$tentativas} tentativas de acesso indevido");
        
        // Opcional: fazer logout forçado
        session_destroy();
        header('Location: login.php');
        exit;
    }
}
?>

<!-- COMO USAR NOS DASHBOARDS: -->
<?php
/*
// No início de cada dashboard específico, SUBSTITUIR:
require_once '../../app/auth.php';
requireLogin();
requireRole(['perfil']);

// POR:
require_once '../../app/auth.php';
require_once 'dashboard_security.php';
requireDashboardAccess('dashboard_tipo');
detectarTentativaBurla();
*/
?>