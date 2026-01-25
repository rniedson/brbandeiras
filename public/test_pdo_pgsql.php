<?php
/**
 * Teste de PDO PostgreSQL
 * Acesse: http://localhost/brbandeiras/public/test_pdo_pgsql.php
 */

// Tentar usar PHP do Homebrew se disponível
if (file_exists('/opt/homebrew/bin/php')) {
    // Mostrar informações
    echo "<h1>Teste de PDO PostgreSQL</h1>";
    echo "<h2>Informações do PHP</h2>";
    echo "<pre>";
    echo "Versão PHP: " . PHP_VERSION . "\n";
    echo "SAPI: " . PHP_SAPI . "\n";
    echo "Caminho PHP: " . PHP_BINARY . "\n\n";
    
    echo "Extensões PDO disponíveis:\n";
    foreach (get_loaded_extensions() as $ext) {
        if (strpos($ext, 'pdo') !== false) {
            echo "  ✅ $ext\n";
        }
    }
    echo "</pre>";
    
    // Tentar carregar config
    echo "<h2>Teste de Conexão</h2>";
    try {
        require_once __DIR__ . '/../app/config.php';
        echo "<p style='color: green; font-weight: bold;'>✅ Conexão estabelecida com sucesso!</p>";
        echo "<p>Banco de dados: " . $env['DB_NAME'] . "</p>";
        echo "<p>Host: " . $env['DB_HOST'] . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red; font-weight: bold;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
} else {
    echo "<h1>❌ PHP do Homebrew não encontrado</h1>";
    echo "<p>Instale com: <code>brew install php</code></p>";
}
