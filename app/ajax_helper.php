<?php
/**
 * AjaxResponse Helper
 * 
 * Classe helper para padronizar respostas AJAX e evitar ERR_EMPTY_RESPONSE
 * 
 * Uso:
 *   require_once '../../app/ajax_helper.php';
 *   AjaxResponse::init();
 *   
 *   // Sucesso
 *   AjaxResponse::json(['success' => true, 'data' => $resultado]);
 *   
 *   // Erro
 *   AjaxResponse::error('Mensagem de erro', 500);
 */

class AjaxResponse {
    private static $response_sent = false;
    
    /**
     * Inicializa o helper AJAX
     * Deve ser chamado ANTES de carregar config.php
     */
    public static function init() {
        // Define AJAX_REQUEST antes de carregar config para evitar die() com HTML
        if (!defined('AJAX_REQUEST')) {
            define('AJAX_REQUEST', true);
        }
        
        // Configura tratamento de erros
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        
        // Registra shutdown function para capturar erros fatais
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Envia resposta JSON
     * 
     * @param array $data Dados para enviar como JSON
     * @param int $statusCode Código HTTP (padrão: 200)
     */
    public static function json($data, $statusCode = 200) {
        self::$response_sent = true;
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Envia resposta de erro JSON
     * 
     * @param string $message Mensagem de erro
     * @param int $statusCode Código HTTP (padrão: 500)
     */
    public static function error($message, $statusCode = 500) {
        error_log("AJAX Error [$statusCode]: " . $message);
        self::json([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
    
    /**
     * Envia resposta de sucesso JSON
     * 
     * @param mixed $data Dados para enviar
     * @param string $message Mensagem opcional
     * @param int $statusCode Código HTTP (padrão: 200)
     */
    public static function success($data = null, $message = null, $statusCode = 200) {
        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message !== null) {
            $response['message'] = $message;
        }
        self::json($response, $statusCode);
    }
    
    /**
     * Verifica se já foi enviada uma resposta
     * 
     * @return bool
     */
    public static function isResponseSent() {
        return self::$response_sent;
    }
    
    /**
     * Handler de shutdown para capturar erros fatais
     * Chamado automaticamente quando o script termina
     */
    public static function handleShutdown() {
        // Se já enviamos resposta, não fazer nada
        if (self::$response_sent) {
            return;
        }
        
        $error = error_get_last();
        
        // Se houver erro fatal e ainda não enviamos resposta
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Limpar qualquer output anterior
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Erro fatal: ' . $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ], JSON_UNESCAPED_UNICODE);
        } elseif (!self::$response_sent) {
            // Se chegou aqui sem enviar resposta e sem erro fatal, algo deu errado
            while (ob_get_level()) {
                ob_end_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Resposta não enviada'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
