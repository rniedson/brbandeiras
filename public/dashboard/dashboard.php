<?php
// dashboard.php - Redirecionamento baseado no perfil do usuário
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireLogin();

// Redirecionar para o dashboard específico do perfil
switch ($_SESSION['user_perfil']) {
    case 'gestor':
        header('Location: dashboard_gestor.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        break;
        
    case 'vendedor':
        header('Location: dashboard_vendedor.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        break;
        
    case 'producao':
        header('Location: dashboard_producao.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        break;
        
    case 'arte_finalista':
    case 'arte':
        header('Location: dashboard_arte_finalista.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        break;
        
    case 'admin':
    case 'financeiro':
        // Admin e financeiro usam o dashboard do gestor
        header('Location: dashboard_gestor.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        break;
        
    default:
        $_SESSION['erro'] = 'Perfil de usuário não reconhecido: ' . ($_SESSION['user_perfil'] ?? 'não definido');
        header('Location: ../index.php');
        break;
}

exit;
?>