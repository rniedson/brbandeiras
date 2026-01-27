<?php
/**
 * Logger - Sistema de logging estruturado
 * 
 * Fornece logging consistente com níveis e contexto,
 * facilitando análise e debug.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

class Logger {
    /**
     * Níveis de log
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * Diretório de logs
     */
    private static $logDir = null;
    
    /**
     * Inicializa diretório de logs
     */
    private static function initLogDir(): string {
        if (self::$logDir === null) {
            self::$logDir = __DIR__ . '/../../storage/logs/';
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }
        }
        return self::$logDir;
    }
    
    /**
     * Registra log estruturado
     * 
     * @param string $level Nível do log (debug, info, warning, error, critical)
     * @param string $message Mensagem do log
     * @param array $context Contexto adicional (array associativo)
     * 
     * @example
     * Logger::log('error', 'Falha ao salvar pedido', ['pedido_id' => 123, 'usuario_id' => 5]);
     */
    public static function log(string $level, string $message, array $context = []): void {
        $logDir = self::initLogDir();
        
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // Formato: [TIMESTAMP] LEVEL: MESSAGE | CONTEXT
        $logEntry = "[{$timestamp}] {$levelUpper}: {$message}";
        
        if (!empty($context)) {
            $logEntry .= " | " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        $logEntry .= PHP_EOL;
        
        // Arquivo de log por data
        $logFile = $logDir . date('Y-m-d') . '.log';
        
        // Também registrar no log do sistema PHP se for erro crítico
        if ($level === self::LEVEL_ERROR || $level === self::LEVEL_CRITICAL) {
            error_log($logEntry);
        }
        
        // Escrever no arquivo
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Registra log de debug
     * 
     * @param string $message Mensagem
     * @param array $context Contexto adicional
     */
    public static function debug(string $message, array $context = []): void {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Registra log de informação
     * 
     * @param string $message Mensagem
     * @param array $context Contexto adicional
     */
    public static function info(string $message, array $context = []): void {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Registra log de aviso
     * 
     * @param string $message Mensagem
     * @param array $context Contexto adicional
     */
    public static function warning(string $message, array $context = []): void {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Registra log de erro
     * 
     * @param string $message Mensagem
     * @param array $context Contexto adicional
     */
    public static function error(string $message, array $context = []): void {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Registra log crítico
     * 
     * @param string $message Mensagem
     * @param array $context Contexto adicional
     */
    public static function critical(string $message, array $context = []): void {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * Registra exceção com contexto completo
     * 
     * @param \Exception $exception Exceção capturada
     * @param array $context Contexto adicional
     */
    public static function exception(\Exception $exception, array $context = []): void {
        $context['exception'] = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::error('Exception: ' . $exception->getMessage(), $context);
    }
}
