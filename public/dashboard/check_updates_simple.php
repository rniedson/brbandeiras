<?php
// Arquivo separado para check_updates - mais simples e direto
// Isso evita problemas com buffer de saída do arquivo principal

// IMPORTANTE: Não limpar buffers aqui - pode causar problemas com session_start()
// Apenas garantir que não há output antes dos headers

// Configurar tratamento de erros ANTES de qualquer coisa
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Marcar que é uma requisição AJAX para evitar die() no config.php
define('AJAX_REQUEST', true);

// Variável para rastrear se já enviamos resposta
$response_sent = false;

// Registrar função de shutdown para capturar erros fatais
register_shutdown_function(function() use (&$response_sent) {
    // Se já enviamos resposta, não fazer nada
    if ($response_sent) {
        return;
    }
    
    $error = error_get_last();
    
    // Se houver erro fatal e ainda não enviamos resposta
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Limpar qualquer output anterior
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'has_updates' => false,
            'count' => 0,
            'last_update' => null,
            'error' => 'Erro fatal: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
    } elseif (!$response_sent) {
        // Se chegou aqui sem enviar resposta e sem erro fatal, algo deu errado
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'has_updates' => false,
            'count' => 0,
            'last_update' => null,
            'error' => 'Resposta não enviada'
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // Carregar configurações usando __DIR__ para caminhos absolutos
    // O config.php já inicia a sessão, então não precisamos fazer nada especial
    require_once __DIR__ . '/../../app/config.php';
    require_once __DIR__ . '/../../app/auth.php';
    
    // Verificar autenticação
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $response_sent = true;
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'has_updates' => false,
            'count' => 0,
            'last_update' => null,
            'error' => 'Não autenticado'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Verificar conexão
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    // Headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Processar parâmetros
    $lastCheck = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
    if ($lastCheck && !strtotime($lastCheck)) {
        $lastCheck = date('Y-m-d H:i:s', strtotime('-1 hour'));
    }
    
    // Query
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, MAX(updated_at) as last_update
        FROM pedidos 
        WHERE updated_at > ?
    ");
    $stmt->execute([$lastCheck]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Resposta
    $response_sent = true;
    echo json_encode([
        'has_updates' => (int)$result['count'] > 0,
        'count' => (int)$result['count'],
        'last_update' => $result['last_update'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    $response_sent = true;
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log("Erro PDO em check_updates_simple: " . $e->getMessage());
    echo json_encode([
        'has_updates' => false,
        'count' => 0,
        'last_update' => null,
        'error' => 'Erro de conexão com banco de dados'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $response_sent = true;
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log("Erro em check_updates_simple: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'has_updates' => false,
        'count' => 0,
        'last_update' => null,
        'error' => 'Erro ao verificar atualizações: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
