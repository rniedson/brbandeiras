<?php
/**
 * Sistema de Configuração Centralizado
 * 
 * Fornece acesso centralizado e type-safe às configurações do sistema,
 * com cache interno para melhor performance.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

class Config {
    /**
     * Cache interno de configurações
     * @var array
     */
    private static $cache = [];
    
    /**
     * Variáveis de ambiente carregadas
     * @var array|null
     */
    private static $env = null;
    
    /**
     * Carrega variáveis de ambiente se ainda não foram carregadas
     */
    private static function loadEnv(): void {
        if (self::$env === null) {
            $envPath = __DIR__ . '/../../.env';
            self::$env = @parse_ini_file($envPath, false, INI_SCANNER_TYPED) ?: [];
        }
    }
    
    /**
     * Obtém valor de configuração
     * 
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão se não encontrado
     * @return mixed Valor da configuração ou padrão
     * 
     * @example
     * $dbHost = Config::get('DB_HOST', 'localhost');
     */
    public static function get(string $key, $default = null) {
        // Verifica cache primeiro
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Carrega .env se necessário
        self::loadEnv();
        
        // Busca em $_ENV primeiro, depois em self::$env
        $value = $_ENV[$key] ?? self::$env[$key] ?? $default;
        
        // Armazena no cache
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    /**
     * Obtém valor de configuração como inteiro
     * 
     * @param string $key Chave da configuração
     * @param int $default Valor padrão se não encontrado
     * @return int Valor convertido para inteiro
     * 
     * @example
     * $port = Config::getInt('DB_PORT', 5432);
     */
    public static function getInt(string $key, int $default = 0): int {
        $value = self::get($key, $default);
        return (int)$value;
    }
    
    /**
     * Obtém valor de configuração como booleano
     * 
     * @param string $key Chave da configuração
     * @param bool $default Valor padrão se não encontrado
     * @return bool Valor convertido para booleano
     * 
     * @example
     * $isDev = Config::getBool('APP_ENV', false);
     */
    public static function getBool(string $key, bool $default = false): bool {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        
        return (bool)$value;
    }
    
    /**
     * Obtém valor de configuração como string
     * 
     * @param string $key Chave da configuração
     * @param string $default Valor padrão se não encontrado
     * @return string Valor como string
     * 
     * @example
     * $dbName = Config::getString('DB_NAME', '');
     */
    public static function getString(string $key, string $default = ''): string {
        $value = self::get($key, $default);
        return (string)$value;
    }
    
    /**
     * Obtém valor de configuração como array
     * 
     * @param string $key Chave da configuração
     * @param array $default Valor padrão se não encontrado
     * @return array Valor como array
     * 
     * @example
     * $allowedOrigins = Config::getArray('CORS_ALLOWED_ORIGINS', []);
     */
    public static function getArray(string $key, array $default = []): array {
        $value = self::get($key, $default);
        
        if (is_array($value)) {
            return $value;
        }
        
        // Tenta fazer explode se for string separada por vírgula
        if (is_string($value) && !empty($value)) {
            return array_map('trim', explode(',', $value));
        }
        
        return $default;
    }
    
    /**
     * Define valor de configuração (útil para testes)
     * 
     * @param string $key Chave da configuração
     * @param mixed $value Valor a definir
     */
    public static function set(string $key, $value): void {
        self::$cache[$key] = $value;
    }
    
    /**
     * Limpa cache de configurações (útil para testes)
     */
    public static function clearCache(): void {
        self::$cache = [];
        self::$env = null;
    }
    
    /**
     * Verifica se uma configuração existe
     * 
     * @param string $key Chave da configuração
     * @return bool True se existe, false caso contrário
     */
    public static function has(string $key): bool {
        self::loadEnv();
        return isset($_ENV[$key]) || isset(self::$env[$key]) || isset(self::$cache[$key]);
    }
}
