<?php
/**
 * ErrorHandler - Tratamento centralizado de erros e exceções
 * 
 * Captura e trata todos os erros e exceções não tratados,
 * logando apropriadamente e exibindo mensagens adequadas.
 * 
 * @version 1.0.0
 * @date 2026-01-25
 */

require_once __DIR__ . '/Logger.php';

use App\Core\Logger;

class ErrorHandler {
    /**
     * Ambiente da aplicação (development ou production)
     */
    private static $environment = 'production';
    
    /**
     * Inicializa o error handler
     * 
     * @param string $environment Ambiente da aplicação
     */
    public static function initialize(string $environment = 'production'): void {
        self::$environment = $environment;
        
        // Registrar handlers
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Trata exceções não capturadas
     * 
     * @param Throwable $exception Exceção lançada
     */
    public static function handleException(Throwable $exception): void {
        // Logar exceção
        self::logException($exception);
        
        // Responder apropriadamente
        self::sendResponse($exception);
    }
    
    /**
     * Trata erros PHP
     * 
     * @param int $severity Severidade do erro
     * @param string $message Mensagem do erro
     * @param string $file Arquivo onde ocorreu o erro
     * @param int $line Linha onde ocorreu o erro
     * @return bool True se erro foi tratado
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool {
        // Não tratar erros que são suprimidos com @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        // Converter erro em exceção
        $exception = new ErrorException($message, 0, $severity, $file, $line);
        
        // Logar erro
        self::logError($severity, $message, $file, $line);
        
        // Em desenvolvimento, lançar exceção para ver stack trace
        if (self::$environment === 'development') {
            throw $exception;
        }
        
        // Em produção, apenas logar e continuar
        return true;
    }
    
    /**
     * Trata erros fatais (shutdown)
     */
    public static function handleShutdown(): void {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            
            self::logException($exception);
            self::sendResponse($exception);
        }
    }
    
    /**
     * Loga exceção no Logger
     */
    private static function logException(Throwable $exception): void {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode(),
        ];
        
        // Adicionar informações da requisição
        if (isset($_SERVER['REQUEST_URI'])) {
            $context['request_uri'] = $_SERVER['REQUEST_URI'];
            $context['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $context['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        // Adicionar informações do usuário se disponível
        if (isset($_SESSION['user_id'])) {
            $context['user_id'] = $_SESSION['user_id'];
            $context['user_email'] = $_SESSION['user_email'] ?? 'unknown';
        }
        
        // Determinar nível de log baseado no tipo de exceção
        $level = Logger::LEVEL_ERROR;
        if ($exception instanceof Error || $exception instanceof ParseError) {
            $level = Logger::LEVEL_CRITICAL;
        }
        
        Logger::log($level, 'Exceção não tratada: ' . $exception->getMessage(), $context);
    }
    
    /**
     * Loga erro PHP
     */
    private static function logError(int $severity, string $message, string $file, int $line): void {
        $levelMap = [
            E_ERROR => Logger::LEVEL_ERROR,
            E_WARNING => Logger::LEVEL_WARNING,
            E_PARSE => Logger::LEVEL_CRITICAL,
            E_NOTICE => Logger::LEVEL_INFO,
            E_CORE_ERROR => Logger::LEVEL_CRITICAL,
            E_CORE_WARNING => Logger::LEVEL_WARNING,
            E_COMPILE_ERROR => Logger::LEVEL_CRITICAL,
            E_COMPILE_WARNING => Logger::LEVEL_WARNING,
            E_USER_ERROR => Logger::LEVEL_ERROR,
            E_USER_WARNING => Logger::LEVEL_WARNING,
            E_USER_NOTICE => Logger::LEVEL_INFO,
            E_STRICT => Logger::LEVEL_INFO,
            E_RECOVERABLE_ERROR => Logger::LEVEL_ERROR,
            E_DEPRECATED => Logger::LEVEL_WARNING,
            E_USER_DEPRECATED => Logger::LEVEL_WARNING,
        ];
        
        $level = $levelMap[$severity] ?? Logger::LEVEL_ERROR;
        
        $context = [
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
        ];
        
        if (isset($_SERVER['REQUEST_URI'])) {
            $context['request_uri'] = $_SERVER['REQUEST_URI'];
        }
        
        Logger::log($level, 'Erro PHP: ' . $message, $context);
    }
    
    /**
     * Envia resposta apropriada baseada no tipo de requisição
     */
    private static function sendResponse(Throwable $exception): void {
        // Verificar se é requisição AJAX
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            isset($_POST['ajax']) || isset($_GET['ajax'])
        );
        
        // Verificar se é CLI
        $isCli = php_sapi_name() === 'cli';
        
        if ($isAjax) {
            // Resposta JSON para AJAX
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => self::$environment === 'development' 
                    ? $exception->getMessage() 
                    : 'Ocorreu um erro ao processar sua solicitação.',
                'debug' => self::$environment === 'development' ? [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ] : null
            ]);
            exit;
        } elseif ($isCli) {
            // Resposta texto para CLI
            echo "ERRO: " . $exception->getMessage() . "\n";
            if (self::$environment === 'development') {
                echo "Arquivo: " . $exception->getFile() . "\n";
                echo "Linha: " . $exception->getLine() . "\n";
                echo "\nStack Trace:\n" . $exception->getTraceAsString() . "\n";
            }
            exit(1);
        } else {
            // Resposta HTML para requisições normais
            http_response_code(500);
            
            if (self::$environment === 'development') {
                // Em desenvolvimento, mostrar detalhes
                self::renderDevelopmentError($exception);
            } else {
                // Em produção, mostrar página genérica
                self::renderProductionError();
            }
            exit;
        }
    }
    
    /**
     * Renderiza página de erro para desenvolvimento
     */
    private static function renderDevelopmentError(Throwable $exception): void {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erro - BR Bandeiras</title>
            <style>
                body {
                    font-family: 'Courier New', monospace;
                    background: #1a1a1a;
                    color: #e0e0e0;
                    padding: 20px;
                    line-height: 1.6;
                }
                .error-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background: #2a2a2a;
                    border: 1px solid #444;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                }
                h1 {
                    color: #ff4444;
                    margin-top: 0;
                    border-bottom: 2px solid #ff4444;
                    padding-bottom: 10px;
                }
                .error-message {
                    background: #3a1a1a;
                    border-left: 4px solid #ff4444;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 4px;
                }
                .error-details {
                    background: #1a2a2a;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 4px;
                    overflow-x: auto;
                }
                .error-details h2 {
                    color: #44aaff;
                    margin-top: 0;
                }
                pre {
                    background: #0a0a0a;
                    padding: 15px;
                    border-radius: 4px;
                    overflow-x: auto;
                    font-size: 12px;
                }
                .file-info {
                    color: #88ff88;
                }
                .line-info {
                    color: #ffaa44;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>Erro no Sistema</h1>
                
                <div class="error-message">
                    <strong>Exceção:</strong> <?= htmlspecialchars(get_class($exception)) ?><br>
                    <strong>Mensagem:</strong> <?= htmlspecialchars($exception->getMessage()) ?>
                </div>
                
                <div class="error-details">
                    <h2>Detalhes</h2>
                    <p class="file-info">
                        <strong>Arquivo:</strong> <?= htmlspecialchars($exception->getFile()) ?>
                    </p>
                    <p class="line-info">
                        <strong>Linha:</strong> <?= $exception->getLine() ?>
                    </p>
                    <p>
                        <strong>Código:</strong> <?= $exception->getCode() ?>
                    </p>
                </div>
                
                <div class="error-details">
                    <h2>Stack Trace</h2>
                    <pre><?= htmlspecialchars($exception->getTraceAsString()) ?></pre>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Renderiza página de erro para produção
     */
    private static function renderProductionError(): void {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erro - BR Bandeiras</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f5f5f5;
                    color: #333;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 8px;
                    padding: 40px;
                    max-width: 500px;
                    text-align: center;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #d32f2f;
                    margin-top: 0;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                }
                .error-icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h1>Erro Interno</h1>
                <p>
                    Ocorreu um erro ao processar sua solicitação.
                    Por favor, tente novamente mais tarde.
                </p>
                <p>
                    Se o problema persistir, entre em contato com o suporte técnico.
                </p>
            </div>
        </body>
        </html>
        <?php
    }
}
