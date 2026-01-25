<?php
/**
 * Teste de conexão com PostgreSQL remoto
 * Execute: php test_conexao_remota.php
 */

echo "🔍 Testando conexão com PostgreSQL remoto...\n\n";

// Carregar .env
$envPath = __DIR__ . '/.env';
$env = @parse_ini_file($envPath, false, INI_SCANNER_TYPED);

if ($env === false) {
    die("❌ Arquivo .env não encontrado!\n");
}

echo "📋 Configurações do .env:\n";
echo "   DATABASE_URL: " . ($env['DATABASE_URL'] ?? 'não definido') . "\n";
echo "   DB_HOST: " . ($env['DB_HOST'] ?? 'não definido') . "\n";
echo "   DB_PORT: " . ($env['DB_PORT'] ?? 'não definido') . "\n";
echo "   DB_NAME: " . ($env['DB_NAME'] ?? 'não definido') . "\n";
echo "   DB_USER: " . ($env['DB_USER'] ?? 'não definido') . "\n";
echo "\n";

// Verificar drivers PDO
$availableDrivers = PDO::getAvailableDrivers();
echo "📦 Drivers PDO disponíveis: " . implode(', ', $availableDrivers) . "\n";

if (!in_array('pgsql', $availableDrivers)) {
    die("\n❌ Driver PostgreSQL não encontrado!\n");
}

echo "✅ Driver pgsql encontrado!\n\n";

// Teste 1: Usando DATABASE_URL
if (!empty($env['DATABASE_URL'])) {
    echo "🧪 Teste 1: Usando DATABASE_URL...\n";
    try {
        $databaseUrl = $env['DATABASE_URL'];
        // Garantir que tem schema
        if (strpos($databaseUrl, '?schema=') === false && strpos($databaseUrl, '&schema=') === false) {
            $schema = $env['DB_SCHEMA'] ?? 'public';
            $databaseUrl .= (strpos($databaseUrl, '?') === false ? '?' : '&') . 'schema=' . $schema;
        }
        
        $pdo = new PDO($databaseUrl, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        
        $version = $pdo->query('SELECT version()')->fetchColumn();
        echo "✅ Conexão estabelecida com sucesso!\n";
        echo "   Versão PostgreSQL: " . substr($version, 0, 50) . "...\n";
        echo "   Banco de dados: " . $pdo->query('SELECT current_database()')->fetchColumn() . "\n";
        echo "   Usuário: " . $pdo->query('SELECT current_user')->fetchColumn() . "\n";
        echo "\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro na conexão: " . $e->getMessage() . "\n\n";
    }
}

// Teste 2: Usando variáveis individuais
if (!empty($env['DB_HOST']) && !empty($env['DB_NAME'])) {
    echo "🧪 Teste 2: Usando variáveis individuais...\n";
    try {
        $host = $env['DB_HOST'];
        $port = (int)($env['DB_PORT'] ?? 5432);
        $name = $env['DB_NAME'];
        $user = $env['DB_USER'] ?? 'postgres';
        $pass = $env['DB_PASS'] ?? '';
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
        
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        
        $version = $pdo->query('SELECT version()')->fetchColumn();
        echo "✅ Conexão estabelecida com sucesso!\n";
        echo "   Versão PostgreSQL: " . substr($version, 0, 50) . "...\n";
        echo "   Banco de dados: " . $pdo->query('SELECT current_database()')->fetchColumn() . "\n";
        echo "   Usuário: " . $pdo->query('SELECT current_user')->fetchColumn() . "\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro na conexão: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Teste concluído!\n";
