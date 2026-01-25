<?php
/**
 * test_fase1.php - Testes da Fase 1 - Estrutura Base MVC
 * 
 * Valida implementação das classes Core e Model
 * Execute via CLI: php app/test_fase1.php
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

// Configurar para exibir erros
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "===========================================================\n";
echo "TESTE FASE 1 - Estrutura Base MVC\n";
echo "===========================================================\n\n";

$testes_passaram = 0;
$testes_falharam = 0;

function teste($nome, $condicao, $mensagem_erro = '') {
    global $testes_passaram, $testes_falharam;
    
    if ($condicao) {
        echo "✅ {$nome}\n";
        $testes_passaram++;
    } else {
        echo "❌ {$nome}";
        if ($mensagem_erro) {
            echo " - {$mensagem_erro}";
        }
        echo "\n";
        $testes_falharam++;
    }
}

// Teste 1: Carregar Database
echo "1. Testando Database...\n";
try {
    require_once __DIR__ . '/Core/Database.php';
    $db = Database::getInstance();
    teste("Database singleton criado", $db !== null);
    teste("Database é instância de Database", $db instanceof Database);
    
    // Verificar que é realmente singleton
    $db2 = Database::getInstance();
    teste("Database é singleton (mesma instância)", $db === $db2);
    
    // Testar getPdo
    $pdo = $db->getPdo();
    teste("getPdo() retorna PDO", $pdo instanceof PDO);
    
} catch (Exception $e) {
    teste("Database carregado", false, $e->getMessage());
}

echo "\n";

// Teste 2: Testar query simples
echo "2. Testando queries...\n";
try {
    $db = Database::getInstance();
    
    // Teste query simples
    $stmt = $db->query("SELECT 1 as teste");
    $result = $stmt->fetch();
    teste("Query simples executada", isset($result['teste']) && $result['teste'] == 1);
    
    // Teste query com parâmetros
    $stmt = $db->query("SELECT ? as valor", [42]);
    $result = $stmt->fetch();
    teste("Query com parâmetros", isset($result['valor']) && $result['valor'] == 42);
    
} catch (Exception $e) {
    teste("Queries funcionando", false, $e->getMessage());
}

echo "\n";

// Teste 3: Testar transações
echo "3. Testando transações...\n";
try {
    $db = Database::getInstance();
    
    $resultado = $db->transaction(function($db) {
        $db->query("SELECT 1");
        return "sucesso";
    });
    
    teste("Transação com callback", $resultado === "sucesso");
    teste("Transação commitada", !$db->inTransaction());
    
    // Teste rollback
    try {
        $db->transaction(function($db) {
            throw new Exception("Erro de teste");
        });
        teste("Transação com rollback", false, "Deveria ter lançado exceção");
    } catch (Exception $e) {
        teste("Transação com rollback", $e->getMessage() === "Erro de teste");
    }
    
} catch (Exception $e) {
    teste("Transações funcionando", false, $e->getMessage());
}

echo "\n";

// Teste 4: Carregar BaseModel
echo "4. Testando BaseModel...\n";
try {
    require_once __DIR__ . '/Core/BaseModel.php';
    teste("BaseModel carregado", class_exists('BaseModel'));
    teste("BaseModel é abstrata", (new ReflectionClass('BaseModel'))->isAbstract());
    
} catch (Exception $e) {
    teste("BaseModel carregado", false, $e->getMessage());
}

echo "\n";

// Teste 5: Testar modelo Pedido
echo "5. Testando modelo Pedido...\n";
try {
    require_once __DIR__ . '/Models/Pedido.php';
    $db = Database::getInstance();
    $pedidoModel = new Pedido($db);
    
    teste("Modelo Pedido criado", $pedidoModel instanceof Pedido);
    teste("Modelo Pedido herda BaseModel", $pedidoModel instanceof BaseModel);
    
    // Testar método gerarNumero
    $numero = $pedidoModel->gerarNumero('11987654321');
    teste("gerarNumero() retorna string", is_string($numero));
    teste("gerarNumero() tem formato correto", preg_match('/^\d{8}-\d{4}-\d{4}$/', $numero) === 1);
    
    // Testar método count
    $total = $pedidoModel->count();
    teste("count() retorna número", is_int($total) && $total >= 0);
    
    // Testar método findAll (se houver pedidos)
    $pedidos = $pedidoModel->findAll([], 'id DESC', 5);
    teste("findAll() retorna array", is_array($pedidos));
    
    // Se houver pedidos, testar find
    if ($total > 0) {
        $primeiroPedido = $pedidoModel->findAll([], 'id ASC', 1);
        if (!empty($primeiroPedido)) {
            $id = $primeiroPedido[0]['id'];
            $pedido = $pedidoModel->find($id);
            teste("find() retorna registro", $pedido !== null && isset($pedido['id']));
        }
    }
    
} catch (Exception $e) {
    teste("Modelo Pedido funcionando", false, $e->getMessage());
}

echo "\n";

// Teste 6: Testar LegacyAdapter
echo "6. Testando LegacyAdapter...\n";
try {
    require_once __DIR__ . '/Core/LegacyAdapter.php';
    
    $pdo = LegacyAdapter::getPdo();
    teste("LegacyAdapter::getPdo() retorna PDO", $pdo instanceof PDO);
    
    $stmt = LegacyAdapter::query("SELECT 1 as teste");
    $result = $stmt->fetch();
    teste("LegacyAdapter::query() funciona", isset($result['teste']) && $result['teste'] == 1);
    
} catch (Exception $e) {
    teste("LegacyAdapter funcionando", false, $e->getMessage());
}

echo "\n";

// Teste 7: Testar config_legacy
echo "7. Testando config_legacy.php...\n";
try {
    // Simular variáveis de ambiente
    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
    
    ob_start();
    // Suprimir warning de session_start se já iniciada
    $oldErrorReporting = error_reporting(E_ALL & ~E_WARNING);
    require_once __DIR__ . '/config_legacy.php';
    error_reporting($oldErrorReporting);
    ob_end_clean();
    
    teste("config_legacy.php carregado", isset($pdo));
    teste("\$pdo global é PDO", isset($pdo) && $pdo instanceof PDO);
    teste("\$GLOBALS['pdo'] definido", isset($GLOBALS['pdo']));
    
} catch (Exception $e) {
    teste("config_legacy.php funcionando", false, $e->getMessage());
}

echo "\n";

// Resumo
echo "===========================================================\n";
echo "RESUMO DOS TESTES\n";
echo "===========================================================\n";
echo "✅ Testes passaram: {$testes_passaram}\n";
echo "❌ Testes falharam: {$testes_falharam}\n";
echo "📊 Total: " . ($testes_passaram + $testes_falharam) . "\n\n";

if ($testes_falharam === 0) {
    echo "🎉 Todos os testes passaram! Fase 1 implementada com sucesso.\n";
    exit(0);
} else {
    echo "⚠️ Alguns testes falharam. Revise a implementação.\n";
    exit(1);
}
