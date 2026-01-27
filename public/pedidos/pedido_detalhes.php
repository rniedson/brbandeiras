<?php
/**
 * Redirecionador para detalhes do pedido
 * 
 * Este arquivo redireciona para a página de detalhes específica
 * baseada no perfil do usuário.
 */

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

// Validar ID do pedido
$pedido_id = validarPedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID de pedido inválido';
    header('Location: pedidos.php');
    exit;
}

// Verificar se o pedido existe e obter informações básicas
try {
    $stmt = $pdo->prepare("SELECT p.*, c.nome as cliente_nome FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar pedido: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao carregar dados do pedido';
    header('Location: pedidos.php');
    exit;
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
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
        header("Location: detalhes/pedido_detalhes_arte_finalista.php?id={$pedido_id}");
        break;
    
    case 'producao':
        header("Location: detalhes/pedido_detalhes_producao.php?id={$pedido_id}");
        break;
    
    case 'vendedor':
        header("Location: detalhes/pedido_detalhes_vendedor.php?id={$pedido_id}");
        break;
    
    case 'gestor':
        header("Location: detalhes/pedido_detalhes_gestor.php?id={$pedido_id}");
        break;
    
    default:
        $_SESSION['erro'] = 'Perfil de usuário não reconhecido';
        header('Location: ../dashboard/dashboard.php');
        break;
}
exit;
