<?php
/**
 * BaseModel - Classe base abstrata para modelos de dados
 * 
 * Fornece CRUD genérico e métodos auxiliares para todos os modelos.
 * 
 * @version 1.1.0
 * @date 2026-01-25
 */

require_once __DIR__ . '/Database.php';

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    /**
     * Whitelist de tabelas permitidas para prevenir SQL injection
     * Todas as tabelas usadas pelo sistema devem estar listadas aqui
     */
    private static $allowedTables = [
        'pedidos',
        'pedido_itens',
        'pedido_arte',
        'pedido_arquivos',
        'pedido_historico',
        'clientes',
        'cliente_grupos',
        'usuarios',
        'produtos_catalogo',
        'produtos_catalogo_precos',
        'fornecedores',
        'cotacoes',
        'cotacao_itens',
        'contas_receber',
        'contas_pagar',
        'comissoes',
        'metas_vendas',
        'empresa',
        'documentos_empresa',
        'logs_sistema',
        'producao_status',
        'estoque_movimentacoes',
    ];
    
    /**
     * Whitelist de caracteres permitidos em nomes de colunas
     * PostgreSQL permite: letras, números, underscore, e alguns caracteres especiais
     */
    private static function isValidIdentifier(string $identifier): bool {
        // Verificar se contém apenas caracteres válidos para identificadores PostgreSQL
        // Permitir: letras, números, underscore, e não pode começar com número
        return preg_match('/^[a-z_][a-z0-9_]*$/i', $identifier) === 1;
    }
    
    /**
     * Valida nome de tabela contra whitelist
     * 
     * @param string $table Nome da tabela
     * @throws InvalidArgumentException Se tabela não está na whitelist
     */
    protected function validateTableName(string $table): void {
        if (!in_array($table, self::$allowedTables, true)) {
            throw new InvalidArgumentException(
                "Tabela '{$table}' não está na whitelist permitida. " .
                "Tabelas permitidas: " . implode(', ', self::$allowedTables)
            );
        }
    }
    
    /**
     * Valida nome de coluna
     * 
     * @param string $column Nome da coluna
     * @throws InvalidArgumentException Se nome de coluna é inválido
     */
    protected function validateColumnName(string $column): void {
        if (!self::isValidIdentifier($column)) {
            throw new InvalidArgumentException(
                "Nome de coluna inválido: '{$column}'. " .
                "Use apenas letras, números e underscore."
            );
        }
    }
    
    /**
     * Escapa identificador PostgreSQL (tabela ou coluna)
     * Usa aspas duplas para escapar identificadores
     * 
     * @param string $identifier Identificador a escapar
     * @return string Identificador escapado
     */
    protected function escapeIdentifier(string $identifier): string {
        // Validar antes de escapar
        if (!self::isValidIdentifier($identifier)) {
            throw new InvalidArgumentException("Identificador inválido: {$identifier}");
        }
        // PostgreSQL usa aspas duplas para identificar identificadores
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
    
    /**
     * Construtor
     * 
     * @param Database $db Instância do Database
     */
    public function __construct(Database $db) {
        $this->db = $db;
        // Validar nome da tabela ao construir
        if (!empty($this->table)) {
            $this->validateTableName($this->table);
        }
    }
    
    /**
     * Busca um registro por ID
     * 
     * @param int $id ID do registro
     * @return array|null Array associativo ou null se não encontrado
     */
    public function find(int $id): ?array {
        // Validar tabela e primary key
        $this->validateTableName($this->table);
        $this->validateColumnName($this->primaryKey);
        
        // Usar identificadores escapados
        $tableEscaped = $this->escapeIdentifier($this->table);
        $primaryKeyEscaped = $this->escapeIdentifier($this->primaryKey);
        
        $sql = "SELECT * FROM {$tableEscaped} WHERE {$primaryKeyEscaped} = ? LIMIT 1";
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
        // Validar tabela e campo
        $this->validateTableName($this->table);
        $this->validateColumnName($field);
        
        // Usar identificadores escapados
        $tableEscaped = $this->escapeIdentifier($this->table);
        $fieldEscaped = $this->escapeIdentifier($field);
        
        $sql = "SELECT * FROM {$tableEscaped} WHERE {$fieldEscaped} = ?";
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
        // Validar tabela
        $this->validateTableName($this->table);
        $tableEscaped = $this->escapeIdentifier($this->table);
        
        $sql = "SELECT * FROM {$tableEscaped}";
        $params = [];
        
        // Adicionar condições WHERE
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                // Validar nome da coluna
                $this->validateColumnName($field);
                $fieldEscaped = $this->escapeIdentifier($field);
                $where[] = "{$fieldEscaped} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        // Adicionar ORDER BY (validar campos)
        if ($orderBy !== null) {
            // ORDER BY pode conter múltiplos campos separados por vírgula
            // Ex: "created_at DESC" ou "nome ASC, id DESC"
            $orderParts = array_map('trim', explode(',', $orderBy));
            $validatedOrderParts = [];
            
            foreach ($orderParts as $part) {
                // Separar campo e direção (ASC/DESC)
                $parts = preg_split('/\s+/', trim($part), 2);
                $field = $parts[0];
                $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
                
                // Validar campo
                $this->validateColumnName($field);
                $fieldEscaped = $this->escapeIdentifier($field);
                
                // Validar direção
                if (!in_array($direction, ['ASC', 'DESC'], true)) {
                    throw new InvalidArgumentException("Direção de ordenação inválida: {$direction}");
                }
                
                $validatedOrderParts[] = "{$fieldEscaped} {$direction}";
            }
            
            $sql .= " ORDER BY " . implode(", ", $validatedOrderParts);
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
        
        // Validar tabela
        $this->validateTableName($this->table);
        $tableEscaped = $this->escapeIdentifier($this->table);
        $primaryKeyEscaped = $this->escapeIdentifier($this->primaryKey);
        
        // Remover campos não permitidos (como primaryKey se estiver presente)
        unset($data[$this->primaryKey]);
        
        // Validar e escapar nomes de colunas
        $fields = [];
        foreach (array_keys($data) as $field) {
            $this->validateColumnName($field);
            $fields[] = $this->escapeIdentifier($field);
        }
        
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$tableEscaped} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ") 
                RETURNING {$primaryKeyEscaped}";
        
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
        
        // Validar tabela
        $this->validateTableName($this->table);
        $tableEscaped = $this->escapeIdentifier($this->table);
        $primaryKeyEscaped = $this->escapeIdentifier($this->primaryKey);
        
        // Verificar se registro existe
        if (!$this->find($id)) {
            throw new RuntimeException("Registro com ID {$id} não encontrado na tabela {$this->table}");
        }
        
        // Remover primaryKey dos dados de atualização
        unset($data[$this->primaryKey]);
        
        if (empty($data)) {
            return false; // Nada para atualizar
        }
        
        // Validar e escapar nomes de colunas
        $fields = [];
        foreach (array_keys($data) as $field) {
            $this->validateColumnName($field);
            $fieldEscaped = $this->escapeIdentifier($field);
            $fields[] = "{$fieldEscaped} = ?";
        }
        
        $sql = "UPDATE {$tableEscaped} SET " . implode(', ', $fields) . 
               " WHERE {$primaryKeyEscaped} = ?";
        
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
        // Validar tabela e primary key
        $this->validateTableName($this->table);
        $this->validateColumnName($this->primaryKey);
        
        $tableEscaped = $this->escapeIdentifier($this->table);
        $primaryKeyEscaped = $this->escapeIdentifier($this->primaryKey);
        
        $sql = "DELETE FROM {$tableEscaped} WHERE {$primaryKeyEscaped} = ?";
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
        // Validar tabela
        $this->validateTableName($this->table);
        $tableEscaped = $this->escapeIdentifier($this->table);
        
        $sql = "SELECT COUNT(*) FROM {$tableEscaped}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                // Validar nome da coluna
                $this->validateColumnName($field);
                $fieldEscaped = $this->escapeIdentifier($field);
                $where[] = "{$fieldEscaped} = ?";
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
