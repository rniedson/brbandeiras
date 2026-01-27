<?php
/**
 * PedidoRepository - Repository para acesso a dados de pedidos
 * 
 * Centraliza todas as queries SQL relacionadas a pedidos,
 * facilitando manutenção e otimização.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Repositories;

use PDOException;

// Database não está em namespace, será usado diretamente

class PedidoRepository {
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
     * Busca pedido completo com todos os relacionamentos para gestor
     * 
     * @param int $id ID do pedido
     * @return array|null Array com dados completos ou null se não encontrado
     * @throws PDOException Se erro de banco de dados
     */
    public function buscarCompletoParaGestor(int $id): ?array {
        $sql = "
            SELECT 
                p.*,
                c.nome as cliente_nome,
                c.tipo_pessoa,
                c.cpf_cnpj,
                c.telefone as cliente_telefone,
                c.email as cliente_email,
                c.endereco,
                c.numero,
                c.complemento,
                c.bairro,
                c.cidade,
                c.estado,
                c.cep,
                v.nome as vendedor_nome,
                v.email as vendedor_email,
                v.telefone as vendedor_telefone,
                pa.arte_finalista_id,
                af.nome as arte_finalista_nome,
                COUNT(DISTINCT av.id) as total_versoes_arte,
                MAX(av.versao) as ultima_versao_arte,
                COUNT(DISTINCT p2.id) as total_pedidos_cliente,
                COALESCE(SUM(p2.valor_final), 0) as total_vendido_cliente
            FROM pedidos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios v ON p.vendedor_id = v.id
            LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
            LEFT JOIN usuarios af ON pa.arte_finalista_id = af.id
            LEFT JOIN arte_versoes av ON av.pedido_id = p.id
            LEFT JOIN pedidos p2 ON p2.cliente_id = p.cliente_id AND p2.status = 'entregue'
            WHERE p.id = ?
            GROUP BY p.id, c.id, v.id, pa.id, af.id
        ";
        
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        // Buscar itens do pedido
        $result['itens'] = $this->buscarItens($id);
        
        // Buscar versões de arte
        $result['versoes_arte'] = $this->buscarVersoesArte($id);
        
        // Buscar arquivos
        $result['arquivos'] = $this->buscarArquivos($id);
        
        // Buscar histórico
        $result['historico'] = $this->buscarHistorico($id);
        
        return $result;
    }
    
    /**
     * Busca itens de um pedido
     * 
     * @param int $pedidoId ID do pedido
     * @return array Array de itens
     */
    public function buscarItens(int $pedidoId): array {
        $sql = "
            SELECT 
                pi.*, 
                pc.id as produto_codigo, 
                pc.nome as produto_nome
            FROM pedido_itens pi
            LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
            WHERE pi.pedido_id = ?
            ORDER BY pi.id
        ";
        
        $stmt = $this->db->query($sql, [$pedidoId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca versões de arte de um pedido
     * 
     * @param int $pedidoId ID do pedido
     * @return array Array de versões de arte
     */
    public function buscarVersoesArte(int $pedidoId): array {
        $sql = "
            SELECT 
                av.*, 
                u.nome as usuario_nome, 
                u.perfil as usuario_perfil
            FROM arte_versoes av
            LEFT JOIN usuarios u ON av.usuario_id = u.id
            WHERE av.pedido_id = ?
            ORDER BY av.versao DESC
        ";
        
        $stmt = $this->db->query($sql, [$pedidoId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca versões de arte aprovadas de um pedido
     * 
     * @param int $pedidoId ID do pedido
     * @return array Array de versões aprovadas
     */
    public function buscarVersoesArteAprovadas(int $pedidoId): array {
        $versoes = $this->buscarVersoesArte($pedidoId);
        
        return array_filter($versoes, function($v) {
            return (isset($v['aprovada']) && $v['aprovada'] == true) ||
                   (isset($v['status']) && $v['status'] === 'aprovada') ||
                   (isset($v['aprovado']) && $v['aprovado'] == true);
        });
    }
    
    /**
     * Busca arquivos de um pedido
     * 
     * @param int $pedidoId ID do pedido
     * @return array Array de arquivos
     */
    public function buscarArquivos(int $pedidoId): array {
        // Verificar se coluna usuario_id existe
        $temUsuarioId = $this->verificarColunaExiste('pedido_arquivos', 'usuario_id');
        
        if ($temUsuarioId) {
            $sql = "
                SELECT 
                    pa.*,
                    u.nome as usuario_nome
                FROM pedido_arquivos pa
                LEFT JOIN usuarios u ON pa.usuario_id = u.id
                WHERE pa.pedido_id = ?
                ORDER BY pa.created_at DESC
            ";
        } else {
            $sql = "
                SELECT pa.*
                FROM pedido_arquivos pa
                WHERE pa.pedido_id = ?
                ORDER BY pa.created_at DESC
            ";
        }
        
        $stmt = $this->db->query($sql, [$pedidoId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca histórico de um pedido
     * 
     * @param int $pedidoId ID do pedido
     * @return array Array de histórico
     */
    public function buscarHistorico(int $pedidoId): array {
        $sql = "
            SELECT 
                ps.*,
                u.nome as usuario_nome
            FROM producao_status ps
            LEFT JOIN usuarios u ON ps.usuario_id = u.id
            WHERE ps.pedido_id = ?
            ORDER BY ps.created_at DESC
        ";
        
        try {
            $stmt = $this->db->query($sql, [$pedidoId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Tabela pode não existir, retornar array vazio
            return [];
        }
    }
    
    /**
     * Busca pedidos para dashboard com filtros
     * 
     * @param array $filtros Filtros opcionais (status, urgente, vendedor_id, cliente_id)
     * @param string|null $orderBy Ordenação (padrão: p.updated_at DESC)
     * @param int|null $limit Limite de registros
     * @param int|null $offset Offset para paginação
     * @return array Array de pedidos
     */
    public function buscarParaDashboard(array $filtros = [], ?string $orderBy = 'p.updated_at DESC', ?int $limit = null, ?int $offset = null): array {
        $sql = "
            SELECT 
                p.id,
                p.numero,
                p.status,
                p.urgente,
                p.valor_total,
                p.valor_final,
                p.prazo_entrega,
                p.created_at,
                p.updated_at,
                c.nome as cliente_nome,
                c.telefone as cliente_telefone,
                v.nome as vendedor_nome,
                pa.arte_finalista_id as arte_responsavel_id,
                af.nome as arte_finalista_nome,
                (SELECT pc.nome FROM pedido_itens pi 
                 LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id 
                 WHERE pi.pedido_id = p.id 
                 ORDER BY pi.id LIMIT 1) as primeiro_produto
            FROM pedidos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios v ON p.vendedor_id = v.id
            LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
            LEFT JOIN usuarios af ON pa.arte_finalista_id = af.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Aplicar filtros
        if (isset($filtros['status']) && $filtros['status'] !== 'todos') {
            $sql .= " AND p.status = ?";
            $params[] = $filtros['status'];
        }
        
        if (isset($filtros['urgente']) && $filtros['urgente']) {
            $sql .= " AND p.urgente = true";
        }
        
        if (isset($filtros['vendedor_id'])) {
            $sql .= " AND p.vendedor_id = ?";
            $params[] = $filtros['vendedor_id'];
        }
        
        if (isset($filtros['cliente_id'])) {
            $sql .= " AND p.cliente_id = ?";
            $params[] = $filtros['cliente_id'];
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
     * Busca pedido básico por ID
     * 
     * @param int $id ID do pedido
     * @return array|null Array com dados básicos ou null
     */
    public function buscarPorId(int $id): ?array {
        $sql = "SELECT * FROM pedidos WHERE id = ? LIMIT 1";
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca pedidos por status
     * 
     * @param string $status Status do pedido
     * @param string|null $orderBy Ordenação
     * @return array Array de pedidos
     */
    public function buscarPorStatus(string $status, ?string $orderBy = 'created_at DESC'): array {
        $sql = "SELECT * FROM pedidos WHERE status = ?";
        
        if ($orderBy !== null) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        $stmt = $this->db->query($sql, [$status]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca pedidos por vendedor
     * 
     * @param int $vendedorId ID do vendedor
     * @param string|null $status Filtrar por status (opcional)
     * @return array Array de pedidos
     */
    public function buscarPorVendedor(int $vendedorId, ?string $status = null): array {
        $sql = "SELECT * FROM pedidos WHERE vendedor_id = ?";
        $params = [$vendedorId];
        
        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Verifica se uma coluna existe em uma tabela
     * 
     * @param string $tabela Nome da tabela
     * @param string $coluna Nome da coluna
     * @return bool True se existe
     */
    private function verificarColunaExiste(string $tabela, string $coluna): bool {
        static $cache = [];
        $cacheKey = "{$tabela}.{$coluna}";
        
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        
        try {
            $sql = "
                SELECT 1 FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = ? 
                AND column_name = ?
                LIMIT 1
            ";
            $stmt = $this->db->query($sql, [$tabela, $coluna]);
            $resultado = (bool)$stmt->fetchColumn();
            $cache[$cacheKey] = $resultado;
            return $resultado;
        } catch (PDOException $e) {
            return false;
        }
    }
}
