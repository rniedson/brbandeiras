<?php
// dashboard.php - Redirecionamento baseado no perfil do usuário
require_once '../app/config.php';
require_once '../app/auth.php';

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
        header('Location: dashboard_arte_finalista.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
        break;
        
    default:
        $_SESSION['erro'] = 'Perfil de usuário não reconhecido';
        header('Location: login.php');
        break;
}

exit;
?>