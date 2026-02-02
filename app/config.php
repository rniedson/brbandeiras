<?php
// apps/br-bandeiras/app/config.php (ajuste o caminho conforme seu projeto)
session_start();

// 1) Carrega .env com caminho absoluto (evita ../ confuso)
$envPath = __DIR__.'/../.env';
$env = @parse_ini_file($envPath, false, INI_SCANNER_TYPED);
if ($env === false) {
    throw new RuntimeException("Arquivo .env não encontrado em: {$envPath}");
}

// Ler DATABASE_URL diretamente do arquivo (parse_ini_file pode não ler URLs completas corretamente)
$databaseUrl = null;
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/^DATABASE_URL\s*=\s*(.+)$/m', $envContent, $matches)) {
        $databaseUrl = trim($matches[1], " \t\n\r\0\x0B\"'");
        // Se não tiver schema na URL, adicionar
        if (strpos($databaseUrl, '?schema=') === false && strpos($databaseUrl, '&schema=') === false) {
            $schema = $env['DB_SCHEMA'] ?? 'public';
            $databaseUrl .= (strpos($databaseUrl, '?') === false ? '?' : '&') . 'schema=' . $schema;
        }
    }
}

// 2) Modo dev: exibir erros (NUNCA em produção)
if (($env['APP_ENV'] ?? 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

// 3) Monta DSN - usar DATABASE_URL se disponível, senão montar manualmente
$availableDrivers = PDO::getAvailableDrivers();
if (!in_array('pgsql', $availableDrivers)) {
    // Tentar usar PHP do Homebrew se disponível (solução temporária)
    if (file_exists('/opt/homebrew/bin/php')) {
        $homebrewPhp = '/opt/homebrew/bin/php';
        $testCmd = $homebrewPhp . ' -m 2>&1 | grep pdo_pgsql';
        $hasPgsql = shell_exec($testCmd);
        
        if (!empty($hasPgsql)) {
            // PHP do Homebrew tem pgsql, mas não podemos usar diretamente aqui
            // Mostrar mensagem clara sobre como resolver
            $errorMsg = "⚠️ PROBLEMA DETECTADO\n\n";
            $errorMsg .= "O PHP do AMPPS não tem pdo_pgsql, mas o PHP do Homebrew TEM!\n\n";
            $errorMsg .= "Drivers disponíveis no AMPPS: " . implode(', ', $availableDrivers) . "\n";
            $errorMsg .= "PHP atual: " . PHP_BINARY . " (versão " . PHP_VERSION . ")\n\n";
            $errorMsg .= "═══════════════════════════════════════════════════════════\n";
            $errorMsg .= "SOLUÇÃO RÁPIDA (execute no Terminal):\n";
            $errorMsg .= "═══════════════════════════════════════════════════════════\n\n";
            $errorMsg .= "sudo mv /Applications/AMPPS/apps/php82/bin/php /Applications/AMPPS/apps/php82/bin/php.original\n";
            $errorMsg .= "sudo ln -sf /opt/homebrew/bin/php /Applications/AMPPS/apps/php82/bin/php\n";
            $errorMsg .= "sudo sed -i.bak 's/^extension=pdo_pgsql.so/;extension=pdo_pgsql.so/' /Applications/AMPPS/apps/php82/etc/php.ini\n\n";
            $errorMsg .= "Depois REINICIE o Apache no painel do AMPPS!\n\n";
            $errorMsg .= "═══════════════════════════════════════════════════════════\n";
            $errorMsg .= "📄 Veja arquivo: COMANDOS_COPIAR_COLAR.txt\n";
            $errorMsg .= "═══════════════════════════════════════════════════════════\n";
        } else {
            $errorMsg = "❌ ERRO: Nem o PHP do AMPPS nem do Homebrew tem pdo_pgsql!\n\n";
            $errorMsg .= "Instale com: brew install php\n";
        }
    } else {
        $errorMsg = "❌ ERRO CRÍTICO: Driver PostgreSQL não encontrado!\n\n";
        $errorMsg .= "O PHP do AMPPS não tem a extensão pdo_pgsql instalada.\n\n";
        $errorMsg .= "Drivers PDO disponíveis: " . implode(', ', $availableDrivers) . "\n";
        $errorMsg .= "PHP sendo usado: " . PHP_BINARY . "\n";
        $errorMsg .= "Versão PHP: " . PHP_VERSION . "\n\n";
        $errorMsg .= "Instale PHP do Homebrew: brew install php\n";
    }
    
    // Lançar exceção (ErrorHandler vai tratar)
    throw new RuntimeException($errorMsg);
}

// Montar DSN e credenciais
if (!empty($databaseUrl)) {
    // Usar DATABASE_URL diretamente (mais confiável para conexões remotas)
    // IMPORTANTE: gssencmode=disable evita crash no Apache prefork com libpq/Kerberos
    if (strpos($databaseUrl, 'gssencmode=') === false) {
        $databaseUrl .= (strpos($databaseUrl, '?') === false ? '?' : '&') . 'gssencmode=disable';
    }
    $dsn = $databaseUrl;
    $user = null;
    $pass = null;
} else {
    // Montar DSN manualmente a partir das variáveis individuais
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = (int)($env['DB_PORT'] ?? 5432);
    $name = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASS'] ?? '';
    
    if ($name === '' || $user === '') {
        throw new RuntimeException('DB_NAME/DB_USER não definidos no .env');
    }
    
    // IMPORTANTE: gssencmode=disable evita crash no Apache prefork com libpq/Kerberos
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};gssencmode=disable;options='--client_encoding=UTF8'";
}

// 4) Conexão PDO com opções recomendadas para conexões remotas
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 10, // Timeout de 10 segundos para conexões remotas
    PDO::ATTR_PERSISTENT          => false, // Não usar conexão persistente para remoto
];

try {
    // Se usar DATABASE_URL, passar como string única; senão usar user/pass separados
    if (!empty($databaseUrl) && $user === null) {
        $pdo = new PDO($dsn, null, null, $options);
    } else {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (PDOException $e) {
    // Verificar se o erro é por falta de driver
    $errorMessage = $e->getMessage();
    $isDriverMissing = (strpos($errorMessage, 'could not find driver') !== false || 
                        strpos($errorMessage, 'driver not found') !== false);
    
    // Mostra erro detalhado em ambiente de dev
    if (($env['APP_ENV'] ?? 'production') === 'development') {
        $errorMsg = "Erro de conexão com o banco de dados:\n\n";
        $errorMsg .= "Mensagem: " . $errorMessage . "\n\n";
        
        if ($isDriverMissing) {
            $errorMsg .= "⚠️ PROBLEMA: Extensão PostgreSQL não encontrada!\n\n";
            $errorMsg .= "O PHP não tem o driver PDO PostgreSQL instalado.\n\n";
            $errorMsg .= "SOLUÇÕES:\n\n";
            $errorMsg .= "1. Instalar PostgreSQL client library:\n";
            $errorMsg .= "   brew install libpq\n\n";
            $errorMsg .= "2. Instalar extensão PHP PostgreSQL:\n";
            $errorMsg .= "   /Applications/AMPPS/apps/php82/bin/pecl install pdo_pgsql\n\n";
            $errorMsg .= "3. OU usar o PHP do Homebrew (que já tem pdo_pgsql):\n";
            $errorMsg .= "   Configure o AMPPS para usar: /opt/homebrew/bin/php\n\n";
            $errorMsg .= "4. OU adaptar o código para usar MySQL:\n";
            $errorMsg .= "   O AMPPS já tem pdo_mysql instalado\n\n";
        } else {
            $errorMsg .= "Configurações tentadas:\n";
            $errorMsg .= "- Host: {$host}\n";
            $errorMsg .= "- Porta: {$port}\n";
            $errorMsg .= "- Banco: {$name}\n";
            $errorMsg .= "- Usuário: {$user}\n\n";
            $errorMsg .= "Verifique:\n";
            $errorMsg .= "1. Se o PostgreSQL está rodando\n";
            $errorMsg .= "2. Se as credenciais no arquivo .env estão corretas\n";
            $errorMsg .= "3. Se o banco de dados '{$name}' existe\n";
            $errorMsg .= "4. Se o usuário '{$user}' tem permissão para acessar o banco\n";
            $errorMsg .= "5. Se há conectividade de rede até {$host}:{$port}\n\n";
        }
        
        // Lançar exceção (ErrorHandler vai tratar)
        throw new PDOException($errorMsg);
    }
    
    // Lançar exceção genérica (ErrorHandler vai tratar)
    if ($isDriverMissing) {
        throw new PDOException("Erro: Extensão PostgreSQL não encontrada. Entre em contato com o administrador do sistema.");
    }
    throw new PDOException("Erro de conexão com o banco de dados. Verifique as configurações.");
}

// 5) Constantes do sistema (com verificação para evitar redefinição - compatível com PHP 9)
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'BR Bandeiras');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
}

// Adicionar no app/config.php
if (!defined('NOME_EMPRESA')) {
    define('NOME_EMPRESA', 'BR Bandeiras');
}
if (!defined('CNPJ_EMPRESA')) {
    define('CNPJ_EMPRESA', '00.000.000/0001-00');
}
if (!defined('ENDERECO_EMPRESA')) {
    define('ENDERECO_EMPRESA', 'Rua Exemplo, 123 - Centro - Cidade/UF');
}
if (!defined('TELEFONE_EMPRESA')) {
    define('TELEFONE_EMPRESA', '(62) 0000-0000');
}
if (!defined('EMAIL_EMPRESA')) {
    define('EMAIL_EMPRESA', 'contato@brbandeiras.com.br');
}
if (!defined('SISTEMA_EMAIL')) {
    define('SISTEMA_EMAIL', 'sistema@brbandeiras.com.br');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://95.217.128.95/br-bandeiras/public/');
}

// 6) Carregar Database primeiro (não está em namespace)
require_once __DIR__ . '/Core/Database.php';

// Carregar CSRF (deve estar disponível após session_start)
require_once __DIR__ . '/Core/CSRF.php';

// 7) Carregar autoloader PSR-4
require_once __DIR__ . '/autoload.php';

// 8) Inicializar Error Handler (deve ser antes de qualquer código que possa gerar erros)
require_once __DIR__ . '/Core/ErrorHandler.php';
$appEnv = $env['APP_ENV'] ?? 'production';
ErrorHandler::initialize($appEnv);

// 9) Inicializar sistema de auditoria
use App\Core\Auditoria;
Auditoria::inicializar();

// 10) Inicializar sistema de pré-carregamento de dados (APCu) se disponível
// Carrega dados frequentes em memória compartilhada para melhor performance
if (extension_loaded('apcu') && apcu_enabled() && isset($pdo)) {
    require_once __DIR__ . '/preloader.php';
    DataPreloader::warmup($pdo);
}
