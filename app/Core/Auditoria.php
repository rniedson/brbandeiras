<?php
/**
 * Auditoria - Classe helper para registro automático de auditoria
 * 
 * Integra com EventDispatcher para capturar eventos automaticamente
 * e registrar no sistema de auditoria.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

use App\Services\AuditoriaService;
use App\Repositories\AuditoriaRepository;

// Carregar Database se ainda não foi carregado (não está em namespace)
if (!class_exists('Database')) {
    require_once __DIR__ . '/Database.php';
}

class Auditoria {
    private static $service = null;
    
    /**
     * Inicializa o sistema de auditoria
     * Registra listeners automáticos para eventos comuns
     */
    public static function inicializar(): void {
        // Database não está em namespace, usar diretamente
        $db = \Database::getInstance();
        $repository = new AuditoriaRepository($db);
        self::$service = new AuditoriaService($repository, $db);
        
        // Registrar listeners automáticos
        self::registrarListeners();
    }
    
    /**
     * Registra listeners automáticos para eventos
     */
    private static function registrarListeners(): void {
        // Listener para pedidos
        EventDispatcher::listen('pedido.criado', function($data) {
            self::registrar('pedido_criado', "Pedido #{$data['numero']} criado", null, [
                'entidade_tipo' => 'pedido',
                'entidade_id' => $data['id'] ?? null,
                'dados_novos' => $data
            ]);
        }, 100);
        
        EventDispatcher::listen('pedido.atualizado', function($data) {
            self::registrar('pedido_atualizado', "Pedido #{$data['numero']} atualizado", null, [
                'entidade_tipo' => 'pedido',
                'entidade_id' => $data['id'] ?? null,
                'dados_anteriores' => $data['dados_anteriores'] ?? null,
                'dados_novos' => $data['dados_novos'] ?? null
            ]);
        }, 100);
        
        EventDispatcher::listen('pedido.status_alterado', function($data) {
            self::registrar('pedido_status_alterado', 
                "Status do pedido #{$data['pedido_id']} alterado de '{$data['status_anterior']}' para '{$data['status_novo']}'",
                null,
                [
                    'entidade_tipo' => 'pedido',
                    'entidade_id' => $data['pedido_id'] ?? null,
                    'dados_anteriores' => ['status' => $data['status_anterior']],
                    'dados_novos' => ['status' => $data['status_novo']]
                ]
            );
        }, 100);
        
        EventDispatcher::listen('pedido.deletado', function($data) {
            self::registrar('pedido_deletado', "Pedido #{$data['numero']} deletado", null, [
                'entidade_tipo' => 'pedido',
                'entidade_id' => $data['id'] ?? null,
                'dados_anteriores' => $data
            ]);
        }, 100);
        
        // Listener para clientes
        EventDispatcher::listen('cliente.criado', function($data) {
            self::registrar('cliente_criado', "Cliente '{$data['nome']}' criado", null, [
                'entidade_tipo' => 'cliente',
                'entidade_id' => $data['id'] ?? null,
                'dados_novos' => $data
            ]);
        }, 100);
        
        EventDispatcher::listen('cliente.atualizado', function($data) {
            self::registrar('cliente_atualizado', "Cliente '{$data['nome']}' atualizado", null, [
                'entidade_tipo' => 'cliente',
                'entidade_id' => $data['id'] ?? null,
                'dados_anteriores' => $data['dados_anteriores'] ?? null,
                'dados_novos' => $data['dados_novos'] ?? null
            ]);
        }, 100);
        
        // Listener para login/logout
        EventDispatcher::listen('usuario.login', function($data) {
            self::registrar('login', "Usuário fez login", $data['usuario_id'] ?? null);
        }, 100);
        
        EventDispatcher::listen('usuario.logout', function($data) {
            self::registrar('logout', "Usuário fez logout", $data['usuario_id'] ?? null);
        }, 100);
        
        // Listener genérico para qualquer ação
        EventDispatcher::listen('auditoria.*', function($data) {
            $acao = $data['acao'] ?? 'acao_desconhecida';
            $detalhes = $data['detalhes'] ?? '';
            $usuarioId = $data['usuario_id'] ?? null;
            $contexto = $data['contexto'] ?? [];
            
            self::registrar($acao, $detalhes, $usuarioId, $contexto);
        }, 50);
    }
    
    /**
     * Registra uma ação de auditoria manualmente
     * 
     * @param string $acao Ação realizada
     * @param string $detalhes Detalhes da ação
     * @param int|null $usuarioId ID do usuário (null usa sessão)
     * @param array $contexto Contexto adicional
     * @return int ID do registro criado
     */
    public static function registrar(string $acao, string $detalhes = '', ?int $usuarioId = null, array $contexto = []): int {
        if (self::$service === null) {
            self::inicializar();
        }
        
        return self::$service->registrar($acao, $detalhes, $usuarioId, $contexto);
    }
    
    /**
     * Obtém instância do service (para uso avançado)
     * 
     * @return AuditoriaService
     */
    public static function getService(): AuditoriaService {
        if (self::$service === null) {
            self::inicializar();
        }
        
        return self::$service;
    }
}
