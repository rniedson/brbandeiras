<?php
/**
 * EventDispatcher - Sistema de eventos/listeners
 * 
 * Implementa padrão Observer para desacoplar código e facilitar extensibilidade.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

class EventDispatcher {
    /**
     * Listeners registrados por evento
     * @var array
     */
    private static $listeners = [];
    
    /**
     * Registra um listener para um evento
     * 
     * @param string $event Nome do evento
     * @param callable $listener Função callback a executar
     * @param int $priority Prioridade (maior = executa primeiro, padrão: 0)
     * 
     * @example
     * EventDispatcher::listen('pedido.criado', function($pedido) {
     *     Logger::info('Novo pedido criado', ['pedido_id' => $pedido['id']]);
     * });
     */
    public static function listen(string $event, callable $listener, int $priority = 0): void {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        
        self::$listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority
        ];
        
        // Ordenar por prioridade (maior primeiro)
        usort(self::$listeners[$event], function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
    }
    
    /**
     * Remove um listener de um evento
     * 
     * @param string $event Nome do evento
     * @param callable|null $listener Listener específico a remover (null remove todos)
     */
    public static function remove(string $event, ?callable $listener = null): void {
        if (!isset(self::$listeners[$event])) {
            return;
        }
        
        if ($listener === null) {
            unset(self::$listeners[$event]);
            return;
        }
        
        self::$listeners[$event] = array_filter(
            self::$listeners[$event],
            function($item) use ($listener) {
                return $item['listener'] !== $listener;
            }
        );
    }
    
    /**
     * Dispara um evento executando todos os listeners registrados
     * 
     * @param string $event Nome do evento
     * @param mixed $data Dados a passar para os listeners
     * @return array Array com resultados de cada listener
     * 
     * @example
     * EventDispatcher::dispatch('pedido.criado', ['id' => 123, 'cliente_id' => 5]);
     */
    public static function dispatch(string $event, $data = null): array {
        $results = [];
        
        if (!isset(self::$listeners[$event])) {
            return $results;
        }
        
        foreach (self::$listeners[$event] as $item) {
            try {
                $result = call_user_func($item['listener'], $data);
                $results[] = $result;
            } catch (\Exception $e) {
                // Log erro mas não interrompe execução de outros listeners
                if (class_exists('App\Core\Logger')) {
                    Logger::error('Erro ao executar listener', [
                        'event' => $event,
                        'exception' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Verifica se há listeners para um evento
     * 
     * @param string $event Nome do evento
     * @return bool True se há listeners registrados
     */
    public static function hasListeners(string $event): bool {
        return isset(self::$listeners[$event]) && !empty(self::$listeners[$event]);
    }
    
    /**
     * Retorna lista de eventos com listeners registrados
     * 
     * @return array Array de nomes de eventos
     */
    public static function getEvents(): array {
        return array_keys(self::$listeners);
    }
    
    /**
     * Limpa todos os listeners (útil para testes)
     */
    public static function clear(): void {
        self::$listeners = [];
    }
}
