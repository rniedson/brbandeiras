<?php
/**
 * ModelFactory - Factory para criação de models
 * 
 * Simplifica a criação de models e repositories,
 * centralizando dependências e facilitando testes.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

use App\Models\Pedido;
use App\Repositories\PedidoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\AuditoriaRepository;
use App\Services\PedidoService;
use App\Services\ClienteService;
use App\Services\AuditoriaService;

class ModelFactory {
    private static $db = null;
    
    /**
     * Obtém instância do Database (singleton)
     * 
     * @return \Database
     */
    private static function getDb(): \Database {
        if (self::$db === null) {
            self::$db = \Database::getInstance();
        }
        return self::$db;
    }
    
    /**
     * Cria instância do model Pedido
     * 
     * @return Pedido
     */
    public static function pedido(): Pedido {
        return new Pedido(self::getDb());
    }
    
    /**
     * Cria instância do repository PedidoRepository
     * 
     * @return PedidoRepository
     */
    public static function pedidoRepository(): PedidoRepository {
        return new PedidoRepository(self::getDb());
    }
    
    /**
     * Cria instância do repository ClienteRepository
     * 
     * @return ClienteRepository
     */
    public static function clienteRepository(): ClienteRepository {
        return new ClienteRepository(self::getDb());
    }
    
    /**
     * Cria instância do service PedidoService
     * 
     * @return PedidoService
     */
    public static function pedidoService(): PedidoService {
        $db = self::getDb();
        return new PedidoService(
            new PedidoRepository($db),
            $db
        );
    }
    
    /**
     * Cria instância do service ClienteService
     * 
     * @return ClienteService
     */
    public static function clienteService(): ClienteService {
        $db = self::getDb();
        return new ClienteService(
            new ClienteRepository($db),
            $db
        );
    }
    
    /**
     * Cria instância do repository AuditoriaRepository
     * 
     * @return \App\Repositories\AuditoriaRepository
     */
    public static function auditoriaRepository(): \App\Repositories\AuditoriaRepository {
        return new \App\Repositories\AuditoriaRepository(self::getDb());
    }
    
    /**
     * Cria instância do service AuditoriaService
     * 
     * @return AuditoriaService
     */
    public static function auditoriaService(): AuditoriaService {
        $db = self::getDb();
        return new AuditoriaService(
            new \App\Repositories\AuditoriaRepository($db),
            $db
        );
    }
    
    /**
     * Define instância customizada do Database (útil para testes)
     * 
     * @param \Database|null $db Instância do Database ou null para resetar
     */
    public static function setDb(?\Database $db): void {
        self::$db = $db;
    }
}
