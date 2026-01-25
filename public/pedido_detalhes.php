<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

$pedido_id = $_GET['id'] ?? null;

if (!$pedido_id) {
    header('Location: pedidos.php');
    exit;
}

// Verificar se o pedido existe e obter informações básicas
$stmt = $pdo->prepare("SELECT p.*, c.nome as cliente_nome FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    $_SESSION['erro'] = 'Pedido não encontrado';
    header('Location: pedidos.php');
    exit;
}

// Verificar permissões básicas
if ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] != $_SESSION['user_id']) {
    $_SESSION['erro'] = 'Você não tem permissão para visualizar este pedido';
    header('Location: pedidos.php');
    exit;
}

// Redirecionar para a página específica do role
switch ($_SESSION['user_perfil']) {
    case 'arte_finalista':
        header("Location: pedido_detalhes_arte_finalista.php?id={$pedido_id}");
        break;
    
    case 'producao':
        header("Location: pedido_detalhes_producao.php?id={$pedido_id}");
        break;
    
    case 'vendedor':
        header("Location: pedido_detalhes_vendedor.php?id={$pedido_id}");
        break;
    
    case 'gestor':
        header("Location: pedido_detalhes_gestor.php?id={$pedido_id}");
        break;
    
    default:
        $_SESSION['erro'] = 'Perfil de usuário não reconhecido';
        header('Location: dashboard.php');
        break;
}
exit;
?>