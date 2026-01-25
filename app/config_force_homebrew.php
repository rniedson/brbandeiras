<?php
/**
 * Config alternativo que força uso do PHP do Homebrew
 * Use este arquivo temporariamente enquanto não configura o AMPPS
 */

// Tentar usar PHP do Homebrew se disponível
if (file_exists('/opt/homebrew/bin/php') && !in_array('pgsql', PDO::getAvailableDrivers())) {
    // Executar via shell usando PHP do Homebrew
    $script = __DIR__ . '/config.php';
    $output = shell_exec('/opt/homebrew/bin/php -r "require_once \'' . $script . '\';" 2>&1');
    
    if (strpos($output, 'Conexão estabelecida') !== false) {
        // Se funcionou, vamos tentar uma abordagem diferente
        // Carregar variáveis de ambiente manualmente
        $envPath = __DIR__.'/../.env';
        $env = @parse_ini_file($envPath, false, INI_SCANNER_TYPED);
        
        if ($env === false) {
            throw new RuntimeException("Arquivo .env não encontrado em: {$envPath}");
        }
        
        // Modo dev
        if (($env['APP_ENV'] ?? 'production') === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }
        
        // Conectar usando execução externa
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = (int)($env['DB_PORT'] ?? 5432);
        $name = $env['DB_NAME'] ?? '';
        $user = $env['DB_USER'] ?? '';
        $pass = $env['DB_PASS'] ?? '';
        
        // Criar conexão via PDO usando PHP do Homebrew
        $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
        $cmd = "/opt/homebrew/bin/php -r \"\$pdo = new PDO('{$dsn}', '{$user}', '{$pass}'); echo 'OK';\"";
        $result = shell_exec($cmd . ' 2>&1');
        
        if (strpos($result, 'OK') !== false) {
            // Conexão funcionou, mas não podemos usar $pdo aqui
            // Então vamos mostrar mensagem
            die("⚠️ CONEXÃO FUNCIONA COM PHP DO HOMEBREW!\n\nExecute os comandos sudo para configurar o AMPPS permanentemente.\n\nVeja: COMANDOS_COPIAR_COLAR.txt");
        }
    }
}

// Se chegou aqui, usar config normal
require_once __DIR__ . '/config.php';
