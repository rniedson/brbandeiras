<?php
// Versão de debug para identificar o problema

// Limpar qualquer buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Habilitar exibição de erros temporariamente para debug
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Teste 1: Verificar se o arquivo está sendo executado
file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Arquivo executado\n", FILE_APPEND);

try {
    // Teste 2: Verificar se consegue carregar config.php
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Tentando carregar config.php\n", FILE_APPEND);
    
    require_once '../../app/config.php';
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - config.php carregado\n", FILE_APPEND);
    
    // Teste 3: Verificar sessão
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Verificando sessão\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_test.txt', "SESSION: " . print_r($_SESSION, true) . "\n", FILE_APPEND);
    
    require_once '../../app/auth.php';
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - auth.php carregado\n", FILE_APPEND);
    
    // Verificar autenticação
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'has_updates' => false,
            'count' => 0,
            'last_update' => null,
            'error' => 'Não autenticado',
            'debug' => 'Sessão não encontrada'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Usuário autenticado: " . $_SESSION['user_id'] . "\n", FILE_APPEND);
    
    // Verificar conexão
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - PDO disponível\n", FILE_APPEND);
    
    // Headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Processar parâmetros
    $lastCheck = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
    if ($lastCheck && !strtotime($lastCheck)) {
        $lastCheck = date('Y-m-d H:i:s', strtotime('-1 hour'));
    }
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Executando query com lastCheck: $lastCheck\n", FILE_APPEND);
    
    // Query
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, MAX(updated_at) as last_update
        FROM pedidos 
        WHERE updated_at > ?
    ");
    $stmt->execute([$lastCheck]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Query executada com sucesso\n", FILE_APPEND);
    
    // Resposta
    $response = [
        'has_updates' => (int)$result['count'] > 0,
        'count' => (int)$result['count'],
        'last_update' => $result['last_update'] ?? null
    ];
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Enviando resposta\n", FILE_APPEND);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - Resposta enviada\n", FILE_APPEND);
    
} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/debug_test.txt', date('Y-m-d H:i:s') . " - ERRO: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_test.txt', "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'has_updates' => false,
        'count' => 0,
        'last_update' => null,
        'error' => 'Erro: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
