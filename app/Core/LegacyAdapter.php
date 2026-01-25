<?php
/**
 * LegacyAdapter - Adaptador para compatibilidade com código legado
 * 
 * Fornece acesso ao PDO através de métodos estáticos para código que usa
 * global $pdo ou $GLOBALS['pdo']
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once __DIR__ . '/Database.php';

class LegacyAdapter {
    /**
     * Retorna instância PDO para uso em código legado
     * 
     * @return PDO
     */
    public static function getPdo(): PDO {
        return Database::getInstance()->getPdo();
    }
    
    /**
     * Executa query preparada (compatibilidade com código legado)
     * 
     * @param string $sql Query SQL
     * @param array $params Parâmetros
     * @return PDOStatement
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        return Database::getInstance()->query($sql, $params);
    }
    
    /**
     * Inicia transação (compatibilidade)
     * 
     * @return bool
     */
    public static function beginTransaction(): bool {
        return Database::getInstance()->beginTransaction();
    }
    
    /**
     * Confirma transação (compatibilidade)
     * 
     * @return bool
     */
    public static function commit(): bool {
        return Database::getInstance()->commit();
    }
    
    /**
     * Reverte transação (compatibilidade)
     * 
     * @return bool
     */
    public static function rollBack(): bool {
        return Database::getInstance()->rollBack();
    }
    
    /**
     * Verifica se está em transação (compatibilidade)
     * 
     * @return bool
     */
    public static function inTransaction(): bool {
        return Database::getInstance()->inTransaction();
    }
}
