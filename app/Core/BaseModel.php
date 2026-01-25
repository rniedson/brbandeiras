<?php
/**
 * BaseModel - Classe base abstrata para modelos de dados
 * 
 * Fornece CRUD genérico e métodos auxiliares para todos os modelos.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once __DIR__ . '/Database.php';

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    /**
     * Construtor
     * 
     * @param Database $db Instância do Database
     */
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Busca um registro por ID
     * 
     * @param int $id ID do registro
     * @return array|null Array associativo ou null se não encontrado
     */
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca registros por campo específico
     * 
     * @param string $field Nome do campo
     * @param mixed $value Valor a buscar
     * @return array Array de registros
     */
    public function findBy(string $field, $value): array {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = ?";
        $stmt = $this->db->query($sql, [$value]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca todos os registros com condições opcionais
     * 
     * @param array $conditions Condições WHERE (campo => valor)
     * @param string|null $orderBy Ordenação (ex: "created_at DESC")
     * @param int|null $limit Limite de registros
     * @param int|null $offset Offset para paginação
     * @return array Array de registros
     */
    public function findAll(array $conditions = [], ?string $orderBy = null, ?int $limit = null, ?int $offset = null): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Adicionar condições WHERE
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        // Adicionar ORDER BY
        if ($orderBy !== null) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        // Adicionar LIMIT e OFFSET
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Cria um novo registro
     * 
     * @param array $data Dados do registro (campo => valor)
     * @return int ID do registro criado
     * @throws Exception Se dados inválidos
     */
    public function create(array $data): int {
        if (empty($data)) {
            throw new InvalidArgumentException('Dados não podem estar vazios');
        }
        
        // Remover campos não permitidos (como primaryKey se estiver presente)
        unset($data[$this->primaryKey]);
        
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ") 
                RETURNING {$this->primaryKey}";
        
        $stmt = $this->db->query($sql, array_values($data));
        $id = $stmt->fetchColumn();
        
        return (int)$id;
    }
    
    /**
     * Atualiza um registro existente
     * 
     * @param int $id ID do registro
     * @param array $data Dados para atualizar (campo => valor)
     * @return bool True se atualizado com sucesso
     * @throws Exception Se registro não encontrado ou dados inválidos
     */
    public function update(int $id, array $data): bool {
        if (empty($data)) {
            throw new InvalidArgumentException('Dados não podem estar vazios');
        }
        
        // Verificar se registro existe
        if (!$this->find($id)) {
            throw new RuntimeException("Registro com ID {$id} não encontrado na tabela {$this->table}");
        }
        
        // Remover primaryKey dos dados de atualização
        unset($data[$this->primaryKey]);
        
        if (empty($data)) {
            return false; // Nada para atualizar
        }
        
        $fields = array_map(fn($f) => "{$f} = ?", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . 
               " WHERE {$this->primaryKey} = ?";
        
        $params = array_merge(array_values($data), [$id]);
        $stmt = $this->db->query($sql, $params);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Deleta um registro
     * 
     * @param int $id ID do registro
     * @return bool True se deletado com sucesso
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Conta registros com condições opcionais
     * 
     * @param array $conditions Condições WHERE (campo => valor)
     * @return int Número de registros
     */
    public function count(array $conditions = []): int {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $stmt = $this->db->query($sql, $params);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Verifica se um registro existe
     * 
     * @param int $id ID do registro
     * @return bool
     */
    public function exists(int $id): bool {
        return $this->find($id) !== null;
    }
    
    /**
     * Executa query customizada (para casos especiais)
     * 
     * @param string $sql Query SQL
     * @param array $params Parâmetros
     * @return PDOStatement
     */
    protected function rawQuery(string $sql, array $params = []): PDOStatement {
        return $this->db->query($sql, $params);
    }
    
    /**
     * Obtém instância do Database (para uso em métodos específicos)
     * 
     * @return Database
     */
    protected function getDb(): Database {
        return $this->db;
    }
}
