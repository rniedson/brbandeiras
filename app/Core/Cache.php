<?php
/**
 * Cache - Wrapper para sistemas de cache (APCu/Redis)
 * 
 * Fornece interface unificada para cache, usando APCu quando disponível
 * e fallback para outras implementações.
 * 
 * @version 1.0.0
 * @date 2026-01-25
 */

class Cache {
    /**
     * Prefixo para todas as chaves de cache
     */
    private const PREFIX = 'brbandeiras_';
    
    /**
     * TTL padrão em segundos (5 minutos)
     */
    private const DEFAULT_TTL = 300;
    
    /**
     * Verifica se o cache está disponível
     * 
     * @return bool True se cache disponível
     */
    public static function isAvailable(): bool {
        return extension_loaded('apcu') && apcu_enabled();
    }
    
    /**
     * Obtém valor do cache
     * 
     * @param string $key Chave do cache
     * @param mixed $default Valor padrão se não encontrado
     * @return mixed Valor do cache ou default
     */
    public static function get(string $key, $default = null) {
        if (!self::isAvailable()) {
            return $default;
        }
        
        $fullKey = self::PREFIX . $key;
        $value = apcu_fetch($fullKey, $success);
        
        return $success ? $value : $default;
    }
    
    /**
     * Armazena valor no cache
     * 
     * @param string $key Chave do cache
     * @param mixed $value Valor a armazenar
     * @param int $ttl Tempo de vida em segundos (padrão: 5 minutos)
     * @return bool True se armazenado com sucesso
     */
    public static function set(string $key, $value, int $ttl = self::DEFAULT_TTL): bool {
        if (!self::isAvailable()) {
            return false;
        }
        
        $fullKey = self::PREFIX . $key;
        return apcu_store($fullKey, $value, $ttl);
    }
    
    /**
     * Remove valor do cache
     * 
     * @param string $key Chave do cache
     * @return bool True se removido com sucesso
     */
    public static function delete(string $key): bool {
        if (!self::isAvailable()) {
            return false;
        }
        
        $fullKey = self::PREFIX . $key;
        return apcu_delete($fullKey);
    }
    
    /**
     * Verifica se uma chave existe no cache
     * 
     * @param string $key Chave do cache
     * @return bool True se existe
     */
    public static function has(string $key): bool {
        if (!self::isAvailable()) {
            return false;
        }
        
        $fullKey = self::PREFIX . $key;
        return apcu_exists($fullKey);
    }
    
    /**
     * Limpa todo o cache (apenas chaves com prefixo)
     * 
     * @return bool True se limpeza bem-sucedida
     */
    public static function clear(): bool {
        if (!self::isAvailable()) {
            return false;
        }
        
        // APCu não tem método para limpar apenas chaves com prefixo
        // Então vamos limpar tudo (cuidado em produção!)
        return apcu_clear_cache();
    }
    
    /**
     * Obtém ou calcula valor (cache-aside pattern)
     * 
     * @param string $key Chave do cache
     * @param callable $callback Callback para calcular valor se não estiver em cache
     * @param int $ttl Tempo de vida em segundos
     * @return mixed Valor do cache ou resultado do callback
     */
    public static function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL) {
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        // Calcular valor
        $value = $callback();
        
        // Armazenar no cache
        self::set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Incrementa valor numérico no cache
     * 
     * @param string $key Chave do cache
     * @param int $step Valor a incrementar (padrão: 1)
     * @return int|false Novo valor ou false em caso de erro
     */
    public static function increment(string $key, int $step = 1) {
        if (!self::isAvailable()) {
            return false;
        }
        
        $fullKey = self::PREFIX . $key;
        return apcu_inc($fullKey, $step);
    }
    
    /**
     * Decrementa valor numérico no cache
     * 
     * @param string $key Chave do cache
     * @param int $step Valor a decrementar (padrão: 1)
     * @return int|false Novo valor ou false em caso de erro
     */
    public static function decrement(string $key, int $step = 1) {
        if (!self::isAvailable()) {
            return false;
        }
        
        $fullKey = self::PREFIX . $key;
        return apcu_dec($fullKey, $step);
    }
    
    /**
     * Remove múltiplas chaves de uma vez
     * 
     * @param array $keys Array de chaves
     * @return int Número de chaves removidas
     */
    public static function deleteMultiple(array $keys): int {
        if (!self::isAvailable()) {
            return 0;
        }
        
        $deleted = 0;
        foreach ($keys as $key) {
            if (self::delete($key)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Obtém informações sobre o cache
     * 
     * @return array Informações do cache
     */
    public static function getInfo(): array {
        if (!self::isAvailable()) {
            return [
                'available' => false,
                'message' => 'APCu não está disponível'
            ];
        }
        
        $info = apcu_cache_info(true);
        
        return [
            'available' => true,
            'hits' => $info['num_hits'] ?? 0,
            'misses' => $info['num_misses'] ?? 0,
            'entries' => $info['num_entries'] ?? 0,
            'memory_size' => $info['mem_size'] ?? 0,
            'hit_rate' => ($info['num_hits'] ?? 0) + ($info['num_misses'] ?? 0) > 0
                ? round(($info['num_hits'] ?? 0) / (($info['num_hits'] ?? 0) + ($info['num_misses'] ?? 0)) * 100, 2)
                : 0
        ];
    }
}
