<?php
require_once '../../../app/config.php';
require_once '../../../app/auth.php';
require_once '../../../app/functions.php';

requireLogin();

$pedido_id = $_GET['id'] ?? null;

if (!$pedido_id) {
    redirect('pedidos.php');
}

// Verificar se o pedido existe e obter informações básicas
$stmt = $pdo->prepare("SELECT p.*, c.nome as cliente_nome FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    $_SESSION['erro'] = 'Pedido não encontrado';
    redirect('pedidos.php');
}

// Verificar permissões básicas
if ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] != $_SESSION['user_id']) {
    $_SESSION['erro'] = 'Você não tem permissão para visualizar este pedido';
    redirect('pedidos.php');
}

// Redirecionar para a página específica do role
switch ($_SESSION['user_perfil']) {
    case 'arte_finalista':
        redirect("pedido_detalhes_arte_finalista.php?id={$pedido_id}");
        break;
    
    case 'producao':
        redirect("pedido_detalhes_producao.php?id={$pedido_id}");
        break;
    
    case 'vendedor':
        redirect("pedido_detalhes_vendedor.php?id={$pedido_id}");
        break;
    
    case 'gestor':
        redirect("pedido_detalhes_gestor.php?id={$pedido_id}");
        break;
    
    default:
        $_SESSION['erro'] = 'Perfil de usuário não reconhecido';
        redirect('dashboard.php');
        break;
}
?>