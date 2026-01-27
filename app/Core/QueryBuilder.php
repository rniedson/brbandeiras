<?php
/**
 * QueryBuilder - Construtor de queries SQL
 * 
 * Facilita construção de queries SQL de forma segura e legível,
 * prevenindo SQL injection e facilitando manutenção.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

// Database não está em namespace, será usado diretamente

class QueryBuilder {
    private $db;
    private $select = [];
    private $from = null;
    private $joins = [];
    private $where = [];
    private $whereParams = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit = null;
    private $offset = null;
    
    /**
     * Construtor
     * 
     * @param \Database $db Instância do Database
     */
    public function __construct(\Database $db) {
        $this->db = $db;
    }
    
    /**
     * Define campos SELECT
     * 
     * @param string|array $fields Campos a selecionar
     * @return self
     * 
     * @example
     * $qb->select('id, nome')
     * $qb->select(['id', 'nome', 'email'])
     */
    public function select($fields): self {
        if (is_array($fields)) {
            $this->select = array_merge($this->select, $fields);
        } else {
            $this->select[] = $fields;
        }
        return $this;
    }
    
    /**
     * Define tabela FROM
     * 
     * @param string $table Nome da tabela
     * @param string|null $alias Alias da tabela (opcional)
     * @return self
     * 
     * @example
     * $qb->from('pedidos', 'p')
     */
    public function from(string $table, ?string $alias = null): self {
        $this->from = $alias ? "{$table} {$alias}" : $table;
        return $this;
    }
    
    /**
     * Adiciona JOIN
     * 
     * @param string $table Tabela para join
     * @param string $condition Condição do join
     * @param string $type Tipo de join (INNER, LEFT, RIGHT, FULL)
     * @param string|null $alias Alias da tabela (opcional)
     * @return self
     * 
     * @example
     * $qb->join('clientes c', 'c.id = p.cliente_id', 'LEFT')
     */
    public function join(string $table, string $condition, string $type = 'INNER', ?string $alias = null): self {
        $tableName = $alias ? "{$table} {$alias}" : $table;
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $tableName,
            'condition' => $condition
        ];
        return $this;
    }
    
    /**
     * Adiciona LEFT JOIN (atalho)
     * 
     * @param string $table Tabela para join
     * @param string $condition Condição do join
     * @param string|null $alias Alias da tabela (opcional)
     * @return self
     */
    public function leftJoin(string $table, string $condition, ?string $alias = null): self {
        return $this->join($table, $condition, 'LEFT', $alias);
    }
    
    /**
     * Adiciona condição WHERE
     * 
     * @param string $field Campo ou condição completa
     * @param string|null $operator Operador (=, !=, >, <, LIKE, etc) ou valor se $value for null
     * @param mixed|null $value Valor para comparação
     * @return self
     * 
     * @example
     * $qb->where('status', '=', 'ativo')
     * $qb->where('idade', '>', 18)
     * $qb->where('nome', 'LIKE', '%João%')
     */
    public function where(string $field, ?string $operator = null, $value = null): self {
        if ($operator === null) {
            // Condição completa
            $this->where[] = $field;
        } else {
            // Campo, operador, valor
            $placeholder = '?';
            $this->where[] = "{$field} {$operator} {$placeholder}";
            $this->whereParams[] = $value;
        }
        return $this;
    }
    
    /**
     * Adiciona condição WHERE com OR
     * 
     * @param string $field Campo
     * @param string $operator Operador
     * @param mixed $value Valor
     * @return self
     */
    public function orWhere(string $field, string $operator, $value): self {
        if (!empty($this->where)) {
            $this->where[] = 'OR';
        }
        return $this->where($field, $operator, $value);
    }
    
    /**
     * Adiciona condição WHERE IN
     * 
     * @param string $field Campo
     * @param array $values Valores
     * @return self
     */
    public function whereIn(string $field, array $values): self {
        if (empty($values)) {
            $this->where[] = "1=0"; // Sempre falso se array vazio
            return $this;
        }
        
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->where[] = "{$field} IN ({$placeholders})";
        $this->whereParams = array_merge($this->whereParams, $values);
        return $this;
    }
    
    /**
     * Adiciona ORDER BY
     * 
     * @param string $field Campo para ordenar
     * @param string $direction Direção (ASC ou DESC)
     * @return self
     */
    public function orderBy(string $field, string $direction = 'ASC'): self {
        $this->orderBy[] = "{$field} " . strtoupper($direction);
        return $this;
    }
    
    /**
     * Adiciona GROUP BY
     * 
     * @param string|array $fields Campos para agrupar
     * @return self
     */
    public function groupBy($fields): self {
        if (is_array($fields)) {
            $this->groupBy = array_merge($this->groupBy, $fields);
        } else {
            $this->groupBy[] = $fields;
        }
        return $this;
    }
    
    /**
     * Adiciona HAVING
     * 
     * @param string $condition Condição HAVING
     * @return self
     */
    public function having(string $condition): self {
        $this->having[] = $condition;
        return $this;
    }
    
    /**
     * Define LIMIT
     * 
     * @param int $limit Limite de registros
     * @return self
     */
    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Define OFFSET
     * 
     * @param int $offset Offset para paginação
     * @return self
     */
    public function offset(int $offset): self {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Gera SQL final
     * 
     * @return string Query SQL
     */
    public function toSql(): string {
        $sql = [];
        
        // SELECT
        if (empty($this->select)) {
            $sql[] = "SELECT *";
        } else {
            $sql[] = "SELECT " . implode(', ', $this->select);
        }
        
        // FROM
        if ($this->from) {
            $sql[] = "FROM {$this->from}";
        }
        
        // JOINs
        foreach ($this->joins as $join) {
            $sql[] = "{$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }
        
        // WHERE
        if (!empty($this->where)) {
            $sql[] = "WHERE " . implode(' ', $this->where);
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql[] = "GROUP BY " . implode(', ', $this->groupBy);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $sql[] = "HAVING " . implode(' AND ', $this->having);
        }
        
        // ORDER BY
        if (!empty($this->orderBy)) {
            $sql[] = "ORDER BY " . implode(', ', $this->orderBy);
        }
        
        // LIMIT
        if ($this->limit !== null) {
            $sql[] = "LIMIT {$this->limit}";
        }
        
        // OFFSET
        if ($this->offset !== null) {
            $sql[] = "OFFSET {$this->offset}";
        }
        
        return implode(' ', $sql);
    }
    
    /**
     * Executa a query e retorna statement
     * 
     * @return \PDOStatement
     */
    public function execute(): \PDOStatement {
        return $this->db->query($this->toSql(), $this->whereParams);
    }
    
    /**
     * Executa e retorna todos os resultados
     * 
     * @return array
     */
    public function get(): array {
        $stmt = $this->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Executa e retorna primeiro resultado
     * 
     * @return array|null
     */
    public function first(): ?array {
        $stmt = $this->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Reseta o builder para nova query
     * 
     * @return self
     */
    public function reset(): self {
        $this->select = [];
        $this->from = null;
        $this->joins = [];
        $this->where = [];
        $this->whereParams = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        return $this;
    }
}
