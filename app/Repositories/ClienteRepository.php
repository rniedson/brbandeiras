<?php
/**
 * ClienteRepository - Repository para acesso a dados de clientes
 * 
 * Centraliza todas as queries SQL relacionadas a clientes,
 * facilitando manutenção e otimização.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Repositories;

use PDOException;

// Database não está em namespace, será usado diretamente

class ClienteRepository {
    private $db;
    
    /**
     * Construtor
     * 
     * @param \Database $db Instância do Database
     */
    public function __construct(\Database $db) {
        $this->db = $db;
    }
    
    /**
     * Busca cliente por ID
     * 
     * @param int $id ID do cliente
     * @return array|null Array com dados do cliente ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array {
        $sql = "SELECT * FROM clientes WHERE id = ? LIMIT 1";
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca cliente por CPF/CNPJ
     * 
     * @param string $cpfCnpj CPF ou CNPJ do cliente
     * @return array|null Array com dados do cliente ou null se não encontrado
     */
    public function buscarPorCpfCnpj(string $cpfCnpj): ?array {
        // Limpar formatação
        $cpfCnpjLimpo = preg_replace('/\D/', '', $cpfCnpj);
        
        $sql = "SELECT * FROM clientes WHERE REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', '') = ? LIMIT 1";
        $stmt = $this->db->query($sql, [$cpfCnpjLimpo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca todos os clientes com filtros opcionais
     * 
     * @param array $filtros Filtros opcionais (nome, cidade, estado, tipo_pessoa)
     * @param string|null $orderBy Ordenação (padrão: nome ASC)
     * @param int|null $limit Limite de registros
     * @param int|null $offset Offset para paginação
     * @return array Array de clientes
     */
    public function buscarTodos(array $filtros = [], ?string $orderBy = 'nome ASC', ?int $limit = null, ?int $offset = null): array {
        $sql = "SELECT * FROM clientes WHERE 1=1";
        $params = [];
        
        // Aplicar filtros
        if (isset($filtros['nome']) && !empty($filtros['nome'])) {
            $sql .= " AND nome ILIKE ?";
            $params[] = '%' . $filtros['nome'] . '%';
        }
        
        if (isset($filtros['cidade']) && !empty($filtros['cidade'])) {
            $sql .= " AND cidade ILIKE ?";
            $params[] = '%' . $filtros['cidade'] . '%';
        }
        
        if (isset($filtros['estado']) && !empty($filtros['estado'])) {
            $sql .= " AND estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (isset($filtros['tipo_pessoa']) && !empty($filtros['tipo_pessoa'])) {
            $sql .= " AND tipo_pessoa = ?";
            $params[] = $filtros['tipo_pessoa'];
        }
        
        // Adicionar ordenação
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
     * Busca cliente com estatísticas de pedidos
     * 
     * @param int $id ID do cliente
     * @return array|null Array com dados do cliente e estatísticas ou null
     */
    public function buscarComEstatisticas(int $id): ?array {
        $cliente = $this->buscarPorId($id);
        
        if (!$cliente) {
            return null;
        }
        
        // Buscar estatísticas
        $sql = "
            SELECT 
                COUNT(*) as total_pedidos,
                COUNT(CASE WHEN status = 'entregue' THEN 1 END) as pedidos_entregues,
                COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as pedidos_cancelados,
                COALESCE(SUM(CASE WHEN status = 'entregue' THEN valor_final ELSE 0 END), 0) as total_vendido,
                MAX(created_at) as ultimo_pedido
            FROM pedidos
            WHERE cliente_id = ?
        ";
        
        $stmt = $this->db->query($sql, [$id]);
        $estatisticas = $stmt->fetch();
        
        $cliente['estatisticas'] = $estatisticas;
        
        return $cliente;
    }
    
    /**
     * Cria um novo cliente
     * 
     * @param array $dados Dados do cliente
     * @return int ID do cliente criado
     * @throws PDOException Se erro de banco de dados
     */
    public function criar(array $dados): int {
        $campos = array_keys($dados);
        $placeholders = array_fill(0, count($campos), '?');
        
        $sql = "
            INSERT INTO clientes (" . implode(', ', $campos) . ") 
            VALUES (" . implode(', ', $placeholders) . ") 
            RETURNING id
        ";
        
        $stmt = $this->db->query($sql, array_values($dados));
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Atualiza um cliente existente
     * 
     * @param int $id ID do cliente
     * @param array $dados Dados para atualizar
     * @return bool True se atualizado com sucesso
     * @throws PDOException Se erro de banco de dados
     */
    public function atualizar(int $id, array $dados): bool {
        if (empty($dados)) {
            return false;
        }
        
        unset($dados['id']); // Não permitir atualizar ID
        
        $campos = array_map(fn($f) => "{$f} = ?", array_keys($dados));
        $sql = "UPDATE clientes SET " . implode(', ', $campos) . " WHERE id = ?";
        
        $params = array_merge(array_values($dados), [$id]);
        $stmt = $this->db->query($sql, $params);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Deleta um cliente
     * 
     * @param int $id ID do cliente
     * @return bool True se deletado com sucesso
     */
    public function deletar(int $id): bool {
        $sql = "DELETE FROM clientes WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Conta clientes com filtros opcionais
     * 
     * @param array $filtros Filtros opcionais
     * @return int Número de clientes
     */
    public function contar(array $filtros = []): int {
        $sql = "SELECT COUNT(*) FROM clientes WHERE 1=1";
        $params = [];
        
        // Aplicar mesmos filtros de buscarTodos
        if (isset($filtros['nome']) && !empty($filtros['nome'])) {
            $sql .= " AND nome ILIKE ?";
            $params[] = '%' . $filtros['nome'] . '%';
        }
        
        if (isset($filtros['cidade']) && !empty($filtros['cidade'])) {
            $sql .= " AND cidade ILIKE ?";
            $params[] = '%' . $filtros['cidade'] . '%';
        }
        
        if (isset($filtros['estado']) && !empty($filtros['estado'])) {
            $sql .= " AND estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (isset($filtros['tipo_pessoa']) && !empty($filtros['tipo_pessoa'])) {
            $sql .= " AND tipo_pessoa = ?";
            $params[] = $filtros['tipo_pessoa'];
        }
        
        $stmt = $this->db->query($sql, $params);
        return (int)$stmt->fetchColumn();
    }
}
