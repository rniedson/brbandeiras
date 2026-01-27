<?php
/**
 * CSRF - Proteção contra Cross-Site Request Forgery
 * 
 * Gera e valida tokens CSRF para proteger formulários contra ataques CSRF.
 * 
 * @version 1.0.0
 * @date 2026-01-25
 */

class CSRF {
    /**
     * Nome da chave na sessão para armazenar tokens
     */
    private const SESSION_KEY = 'csrf_tokens';
    
    /**
     * Tempo de expiração do token em segundos (padrão: 1 hora)
     */
    private const TOKEN_LIFETIME = 3600;
    
    /**
     * Número máximo de tokens armazenados por sessão
     */
    private const MAX_TOKENS = 10;
    
    /**
     * Gera um novo token CSRF e o armazena na sessão
     * 
     * @return string Token CSRF gerado
     */
    public static function getToken(): string {
        // Garantir que a sessão está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Inicializar array de tokens se não existir
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        
        // Gerar novo token
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        // Armazenar token com timestamp
        $_SESSION[self::SESSION_KEY][$token] = $timestamp;
        
        // Limpar tokens antigos e manter apenas os mais recentes
        self::cleanOldTokens();
        
        return $token;
    }
    
    /**
     * Valida um token CSRF
     * 
     * @param string $token Token a ser validado
     * @param bool $consume Se true, remove o token após validação (padrão: true)
     * @return bool True se o token é válido
     * @throws RuntimeException Se o token é inválido ou expirado
     */
    public static function validate(string $token, bool $consume = true): bool {
        // Garantir que a sessão está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar se há tokens na sessão
        if (!isset($_SESSION[self::SESSION_KEY]) || empty($_SESSION[self::SESSION_KEY])) {
            throw new RuntimeException('Token CSRF não encontrado. Recarregue a página e tente novamente.');
        }
        
        // Verificar se o token existe
        if (!isset($_SESSION[self::SESSION_KEY][$token])) {
            throw new RuntimeException('Token CSRF inválido. Recarregue a página e tente novamente.');
        }
        
        // Verificar se o token expirou
        $timestamp = $_SESSION[self::SESSION_KEY][$token];
        if (time() - $timestamp > self::TOKEN_LIFETIME) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            throw new RuntimeException('Token CSRF expirado. Recarregue a página e tente novamente.');
        }
        
        // Remover token após validação (one-time use)
        if ($consume) {
            unset($_SESSION[self::SESSION_KEY][$token]);
        }
        
        return true;
    }
    
    /**
     * Valida token sem lançar exceção (retorna false em caso de erro)
     * Útil para validações silenciosas
     * 
     * @param string $token Token a ser validado
     * @param bool $consume Se true, remove o token após validação
     * @return bool True se válido, false caso contrário
     */
    public static function isValid(string $token, bool $consume = true): bool {
        try {
            return self::validate($token, $consume);
        } catch (RuntimeException $e) {
            return false;
        }
    }
    
    /**
     * Limpa tokens antigos da sessão
     * Mantém apenas os tokens mais recentes
     */
    private static function cleanOldTokens(): void {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }
        
        $now = time();
        $tokens = $_SESSION[self::SESSION_KEY];
        
        // Remover tokens expirados
        foreach ($tokens as $token => $timestamp) {
            if ($now - $timestamp > self::TOKEN_LIFETIME) {
                unset($_SESSION[self::SESSION_KEY][$token]);
            }
        }
        
        // Se ainda houver muitos tokens, manter apenas os mais recentes
        if (count($_SESSION[self::SESSION_KEY]) > self::MAX_TOKENS) {
            // Ordenar por timestamp (mais recentes primeiro)
            arsort($_SESSION[self::SESSION_KEY]);
            // Manter apenas os MAX_TOKENS mais recentes
            $_SESSION[self::SESSION_KEY] = array_slice($_SESSION[self::SESSION_KEY], 0, self::MAX_TOKENS, true);
        }
    }
    
    /**
     * Gera campo hidden HTML para formulário
     * 
     * @return string HTML do campo hidden com token
     */
    public static function getField(): string {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Limpa todos os tokens da sessão
     * Útil para logout ou limpeza de sessão
     */
    public static function clear(): void {
        if (isset($_SESSION[self::SESSION_KEY])) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }
}
