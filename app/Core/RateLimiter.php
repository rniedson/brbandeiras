<?php
/**
 * RateLimiter - Sistema de limitação de taxa de requisições
 * 
 * Previne ataques de força bruta e abuso do sistema limitando
 * o número de tentativas por IP em um período de tempo.
 * 
 * @version 1.0.0
 * @date 2026-01-25
 */

class RateLimiter {
    /**
     * Prefixo para chaves de cache
     */
    private const CACHE_PREFIX = 'rate_limit_';
    
    /**
     * Número máximo de tentativas permitidas
     */
    private const DEFAULT_MAX_ATTEMPTS = 5;
    
    /**
     * Janela de tempo em segundos (padrão: 15 minutos)
     */
    private const DEFAULT_WINDOW = 900;
    
    /**
     * Verifica se uma ação está dentro do limite de taxa
     * 
     * @param string $key Chave única para identificar a ação (ex: 'login', 'password_reset')
     * @param string|null $ip IP do cliente (null para usar IP atual)
     * @param int $maxAttempts Número máximo de tentativas
     * @param int $window Janela de tempo em segundos
     * @return bool True se dentro do limite, false se excedido
     */
    public static function check(string $key, ?string $ip = null, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS, int $window = self::DEFAULT_WINDOW): bool {
        $ip = $ip ?? self::getClientIp();
        $cacheKey = self::CACHE_PREFIX . $key . '_' . md5($ip);
        
        // Tentar usar APCu primeiro (mais rápido)
        if (extension_loaded('apcu') && apcu_enabled()) {
            return self::checkWithAPCu($cacheKey, $maxAttempts, $window);
        }
        
        // Fallback para sessão
        return self::checkWithSession($cacheKey, $maxAttempts, $window);
    }
    
    /**
     * Registra uma tentativa (sucesso ou falha)
     * 
     * @param string $key Chave única para identificar a ação
     * @param string|null $ip IP do cliente
     * @param int $window Janela de tempo em segundos
     */
    public static function recordAttempt(string $key, ?string $ip = null, int $window = self::DEFAULT_WINDOW): void {
        $ip = $ip ?? self::getClientIp();
        $cacheKey = self::CACHE_PREFIX . $key . '_' . md5($ip);
        
        // Tentar usar APCu primeiro
        if (extension_loaded('apcu') && apcu_enabled()) {
            self::recordAttemptWithAPCu($cacheKey, $window);
        } else {
            self::recordAttemptWithSession($cacheKey, $window);
        }
    }
    
    /**
     * Obtém número de tentativas restantes
     * 
     * @param string $key Chave única para identificar a ação
     * @param string|null $ip IP do cliente
     * @param int $maxAttempts Número máximo de tentativas
     * @param int $window Janela de tempo em segundos
     * @return int Número de tentativas restantes
     */
    public static function getRemainingAttempts(string $key, ?string $ip = null, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS, int $window = self::DEFAULT_WINDOW): int {
        $ip = $ip ?? self::getClientIp();
        $cacheKey = self::CACHE_PREFIX . $key . '_' . md5($ip);
        
        $attempts = 0;
        
        if (extension_loaded('apcu') && apcu_enabled()) {
            $data = apcu_fetch($cacheKey);
            if ($data !== false) {
                $attempts = $data['count'] ?? 0;
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION[$cacheKey])) {
                $data = $_SESSION[$cacheKey];
                if (time() - $data['timestamp'] < $window) {
                    $attempts = $data['count'] ?? 0;
                }
            }
        }
        
        return max(0, $maxAttempts - $attempts);
    }
    
    /**
     * Obtém tempo até o próximo reset em segundos
     * 
     * @param string $key Chave única para identificar a ação
     * @param string|null $ip IP do cliente
     * @param int $window Janela de tempo em segundos
     * @return int Tempo em segundos até reset (0 se não bloqueado)
     */
    public static function getTimeUntilReset(string $key, ?string $ip = null, int $window = self::DEFAULT_WINDOW): int {
        $ip = $ip ?? self::getClientIp();
        $cacheKey = self::CACHE_PREFIX . $key . '_' . md5($ip);
        
        $timestamp = 0;
        
        if (extension_loaded('apcu') && apcu_enabled()) {
            $data = apcu_fetch($cacheKey);
            if ($data !== false) {
                $timestamp = $data['timestamp'] ?? 0;
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION[$cacheKey])) {
                $data = $_SESSION[$cacheKey];
                $timestamp = $data['timestamp'] ?? 0;
            }
        }
        
        if ($timestamp > 0) {
            $elapsed = time() - $timestamp;
            $remaining = $window - $elapsed;
            return max(0, $remaining);
        }
        
        return 0;
    }
    
    /**
     * Limpa tentativas para uma chave específica
     * 
     * @param string $key Chave única para identificar a ação
     * @param string|null $ip IP do cliente
     */
    public static function clear(string $key, ?string $ip = null): void {
        $ip = $ip ?? self::getClientIp();
        $cacheKey = self::CACHE_PREFIX . $key . '_' . md5($ip);
        
        if (extension_loaded('apcu') && apcu_enabled()) {
            apcu_delete($cacheKey);
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION[$cacheKey]);
        }
    }
    
    /**
     * Verifica limite usando APCu
     */
    private static function checkWithAPCu(string $cacheKey, int $maxAttempts, int $window): bool {
        $data = apcu_fetch($cacheKey);
        
        if ($data === false) {
            return true; // Sem tentativas anteriores
        }
        
        // Verificar se ainda está na janela de tempo
        if (time() - $data['timestamp'] > $window) {
            apcu_delete($cacheKey);
            return true; // Janela expirada, permitir
        }
        
        // Verificar se excedeu o limite
        return ($data['count'] ?? 0) < $maxAttempts;
    }
    
    /**
     * Verifica limite usando sessão
     */
    private static function checkWithSession(string $cacheKey, int $maxAttempts, int $window): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[$cacheKey])) {
            return true; // Sem tentativas anteriores
        }
        
        $data = $_SESSION[$cacheKey];
        
        // Verificar se ainda está na janela de tempo
        if (time() - $data['timestamp'] > $window) {
            unset($_SESSION[$cacheKey]);
            return true; // Janela expirada, permitir
        }
        
        // Verificar se excedeu o limite
        return ($data['count'] ?? 0) < $maxAttempts;
    }
    
    /**
     * Registra tentativa usando APCu
     */
    private static function recordAttemptWithAPCu(string $cacheKey, int $window): void {
        $data = apcu_fetch($cacheKey);
        
        if ($data === false || time() - $data['timestamp'] > $window) {
            // Nova janela ou expirada
            $data = ['count' => 1, 'timestamp' => time()];
        } else {
            // Incrementar contador
            $data['count'] = ($data['count'] ?? 0) + 1;
        }
        
        apcu_store($cacheKey, $data, $window);
    }
    
    /**
     * Registra tentativa usando sessão
     */
    private static function recordAttemptWithSession(string $cacheKey, int $window): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[$cacheKey]) || time() - $_SESSION[$cacheKey]['timestamp'] > $window) {
            // Nova janela ou expirada
            $_SESSION[$cacheKey] = ['count' => 1, 'timestamp' => time()];
        } else {
            // Incrementar contador
            $_SESSION[$cacheKey]['count'] = ($_SESSION[$cacheKey]['count'] ?? 0) + 1;
        }
    }
    
    /**
     * Obtém IP do cliente
     * 
     * @return string IP do cliente
     */
    private static function getClientIp(): string {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
