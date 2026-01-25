<?php
/**
 * Database - Classe Singleton para gerenciamento de conexão PDO
 * 
 * Fornece acesso centralizado ao banco de dados com suporte a transações
 * e compatibilidade com código legado.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

class Database {
    private static $instance = null;
    private $pdo = null;
    private $env = null;
    
    /**
     * Construtor privado para implementar Singleton
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Previne clonagem da instância
     */
    private function __clone() {}
    
    /**
     * Previne deserialização da instância
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Obtém instância única da classe (Singleton)
     * 
     * @return Database
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Estabelece conexão com o banco de dados
     * Reutiliza lógica de app/config.php
     */
    private function connect(): void {
        // Carregar configurações do .env
        $envPath = __DIR__ . '/../../.env';
        $this->env = @parse_ini_file($envPath, false, INI_SCANNER_TYPED);
        
        if ($this->env === false) {
            throw new RuntimeException("Arquivo .env não encontrado em: {$envPath}");
        }
        
        // Ler DATABASE_URL diretamente do arquivo
        $databaseUrl = null;
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (preg_match('/^DATABASE_URL\s*=\s*(.+)$/m', $envContent, $matches)) {
                $databaseUrl = trim($matches[1], " \t\n\r\0\x0B\"'");
                // Se não tiver schema na URL, adicionar
                if (strpos($databaseUrl, '?schema=') === false && strpos($databaseUrl, '&schema=') === false) {
                    $schema = $this->env['DB_SCHEMA'] ?? 'public';
                    $databaseUrl .= (strpos($databaseUrl, '?') === false ? '?' : '&') . 'schema=' . $schema;
                }
            }
        }
        
        // Verificar driver PostgreSQL
        $availableDrivers = PDO::getAvailableDrivers();
        if (!in_array('pgsql', $availableDrivers)) {
            throw new RuntimeException("Driver PostgreSQL (pdo_pgsql) não encontrado. Drivers disponíveis: " . implode(', ', $availableDrivers));
        }
        
        // Montar DSN e credenciais
        if (!empty($databaseUrl)) {
            $dsn = $databaseUrl;
            $user = null;
            $pass = null;
        } else {
            $host = $this->env['DB_HOST'] ?? '127.0.0.1';
            $port = (int)($this->env['DB_PORT'] ?? 5432);
            $name = $this->env['DB_NAME'] ?? '';
            $user = $this->env['DB_USER'] ?? '';
            $pass = $this->env['DB_PASS'] ?? '';
            
            if ($name === '' || $user === '') {
                throw new RuntimeException('DB_NAME/DB_USER não definidos no .env');
            }
            
            $dsn = "pgsql:host={$host};port={$port};dbname={$name};options='--client_encoding=UTF8'";
        }
        
        // Opções de conexão (mesmas de app/config.php)
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
            PDO::ATTR_PERSISTENT         => false,
        ];
        
        try {
            // Criar conexão PDO
            if (!empty($databaseUrl) && $user === null) {
                $this->pdo = new PDO($dsn, null, null, $options);
            } else {
                $this->pdo = new PDO($dsn, $user, $pass, $options);
            }
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            throw new RuntimeException("Erro ao conectar ao banco de dados: {$errorMessage}", 0, $e);
        }
    }
    
    /**
     * Executa uma query preparada e retorna o statement
     * 
     * @param string $sql Query SQL com placeholders (? ou :nome)
     * @param array $params Parâmetros para bind
     * @return PDOStatement
     * @throws PDOException
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Executa uma transação com callback
     * 
     * @param callable $callback Função a executar dentro da transação
     * @return mixed Retorno do callback
     * @throws Exception Se o callback lançar exceção, a transação é revertida
     */
    public function transaction(callable $callback) {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Retorna instância PDO para uso direto (compatibilidade)
     * 
     * @return PDO
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }
    
    /**
     * Verifica se está em uma transação ativa
     * 
     * @return bool
     */
    public function inTransaction(): bool {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Inicia uma transação manualmente
     * 
     * @return bool
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Confirma uma transação manualmente
     * 
     * @return bool
     */
    public function commit(): bool {
        return $this->pdo->commit();
    }
    
    /**
     * Reverte uma transação manualmente
     * 
     * @return bool
     */
    public function rollBack(): bool {
        return $this->pdo->rollBack();
    }
    
    /**
     * Retorna o último ID inserido (útil para PostgreSQL)
     * 
     * @param string|null $name Nome da sequência (opcional para PostgreSQL)
     * @return string
     */
    public function lastInsertId(?string $name = null): string {
        return $this->pdo->lastInsertId($name);
    }
}
