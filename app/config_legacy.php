<?php
/**
 * config_legacy.php - Configuração que mantém compatibilidade com código legado
 * 
 * Este arquivo pode ser usado em vez de config.php durante migração gradual.
 * Mantém variável $pdo global funcionando enquanto permite uso da nova arquitetura.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

// Iniciar sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carregar Database e LegacyAdapter
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/LegacyAdapter.php';

// Manter variável $pdo global para compatibilidade
$pdo = LegacyAdapter::getPdo();

// Manter $GLOBALS['pdo'] também (alguns arquivos usam isso)
if (!isset($GLOBALS['pdo'])) {
    $GLOBALS['pdo'] = $pdo;
}

// Carregar configurações de ambiente (para constantes)
$envPath = __DIR__ . '/../.env';
$env = @parse_ini_file($envPath, false, INI_SCANNER_TYPED);

if ($env === false) {
    throw new RuntimeException("Arquivo .env não encontrado em: {$envPath}");
}

// Configurar modo de desenvolvimento
if (($env['APP_ENV'] ?? 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

// Definir constantes do sistema (com verificação para evitar redefinição)
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'BR Bandeiras');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
}
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
