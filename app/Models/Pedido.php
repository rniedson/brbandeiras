<?php
/**
 * Pedido - Modelo para entidade Pedido
 * 
 * Implementa lógica específica de pedidos usando BaseModel
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once __DIR__ . '/../Core/BaseModel.php';

class Pedido extends BaseModel {
    protected $table = 'pedidos';
    protected $primaryKey = 'id';
    
    /**
     * Gera número único de pedido no formato YYYYMMDD-XXXX
     * 
     * @param string $telefone Telefone do cliente (opcional, para sufixo)
     * @return string Número do pedido gerado
     */
    public function gerarNumero(string $telefone = ''): string {
        $data = date('Ymd');
        
        // Extrair últimos 4 dígitos do telefone (se fornecido)
        $telefone_limpo = preg_replace('/\D/', '', $telefone);
        $final_telefone = substr($telefone_limpo, -4);
        
        // Se não tiver 4 dígitos, preencher com zeros
        if (strlen($final_telefone) < 4) {
            $final_telefone = str_pad($final_telefone, 4, '0', STR_PAD_LEFT);
        }
        
        // Buscar último número do dia
        $sql = "SELECT numero FROM {$this->table} WHERE numero LIKE ? ORDER BY numero DESC LIMIT 1";
        $stmt = $this->db->query($sql, ["{$data}-%"]);
        $ultimo = $stmt->fetchColumn();
        
        if ($ultimo) {
            // Extrair sequência do número existente
            $partes = explode('-', $ultimo);
            $sequencia = isset($partes[1]) ? intval($partes[1]) + 1 : 1;
        } else {
            $sequencia = 1;
        }
        
        // Formato: YYYYMMDD-XXXX-TTTT
        return sprintf("%s-%04d-%s", $data, $sequencia, $final_telefone);
    }
    
    /**
     * Busca pedido com dados do cliente
     * 
     * @param int $id ID do pedido
     * @return array|null Array com dados do pedido e cliente ou null
     */
    public function comCliente(int $id): ?array {
        $sql = "
            SELECT 
                p.*,
                c.nome as cliente_nome,
                c.telefone as cliente_telefone,
                c.email as cliente_email,
                c.cpf_cnpj as cliente_cpf_cnpj,
                c.endereco as cliente_endereco,
                c.cidade as cliente_cidade,
                c.estado as cliente_estado,
                c.cep as cliente_cep,
                c.tipo_pessoa as cliente_tipo
            FROM {$this->table} p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            WHERE p.id = ?
            LIMIT 1
        ";
        
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca pedido com itens relacionados
     * 
     * @param int $id ID do pedido
     * @return array|null Array com dados do pedido e itens ou null
     */
    public function comItens(int $id): ?array {
        // Buscar pedido
        $pedido = $this->comCliente($id);
        if (!$pedido) {
            return null;
        }
        
        // Buscar itens
        $sql = "
            SELECT 
                pi.*,
                pc.codigo as produto_codigo,
                pc.nome as produto_nome
            FROM pedido_itens pi
            LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
            WHERE pi.pedido_id = ?
            ORDER BY pi.id
        ";
        
        $stmt = $this->db->query($sql, [$id]);
        $pedido['itens'] = $stmt->fetchAll();
        
        return $pedido;
    }
    
    /**
     * Busca pedidos por status
     * 
     * @param string $status Status do pedido
     * @param string|null $orderBy Ordenação (padrão: created_at DESC)
     * @return array Array de pedidos
     */
    public function findByStatus(string $status, ?string $orderBy = 'created_at DESC'): array {
        return $this->findAll(['status' => $status], $orderBy);
    }
    
    /**
     * Busca pedidos por vendedor
     * 
     * @param int $vendedorId ID do vendedor
     * @param string|null $status Filtrar por status (opcional)
     * @return array Array de pedidos
     */
    public function findByVendedor(int $vendedorId, ?string $status = null): array {
        $conditions = ['vendedor_id' => $vendedorId];
        
        if ($status !== null) {
            $conditions['status'] = $status;
        }
        
        return $this->findAll($conditions, 'created_at DESC');
    }
    
    /**
     * Busca pedidos completos para dashboard (com relacionamentos)
     * 
     * @param array $filtros Filtros opcionais (status, urgente, etc)
     * @param string|null $orderBy Ordenação
     * @return array Array de pedidos com dados relacionados
     */
    public function findParaDashboard(array $filtros = [], ?string $orderBy = 'p.updated_at DESC'): array {
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
                u.nome as vendedor_nome,
                pa.arte_finalista_id as arte_responsavel_id,
                ua.nome as arte_finalista_nome,
                (SELECT pc.nome FROM pedido_itens pi 
                 LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id 
                 WHERE pi.pedido_id = p.id 
                 ORDER BY pi.id LIMIT 1) as primeiro_produto
            FROM {$this->table} p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios u ON p.vendedor_id = u.id
            LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
            LEFT JOIN usuarios ua ON pa.arte_finalista_id = ua.id
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
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca pedido completo com todos os relacionamentos
     * 
     * @param int $id ID do pedido
     * @return array|null Array completo ou null
     */
    public function findCompleto(int $id): ?array {
        $sql = "
            SELECT 
                p.*,
                c.nome as cliente_nome,
                c.tipo_pessoa,
                c.cpf_cnpj,
                c.telefone as cliente_telefone,
                c.email as cliente_email,
                c.endereco,
                c.numero as cliente_numero,
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
                (SELECT COUNT(*) FROM arte_versoes WHERE pedido_id = p.id) as total_versoes_arte,
                (SELECT MAX(versao) FROM arte_versoes WHERE pedido_id = p.id) as ultima_versao_arte,
                (SELECT COUNT(*) FROM pedidos WHERE cliente_id = p.cliente_id) as total_pedidos_cliente,
                (SELECT SUM(valor_final) FROM pedidos WHERE cliente_id = p.cliente_id AND status = 'entregue') as total_vendido_cliente
            FROM {$this->table} p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios v ON p.vendedor_id = v.id
            LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
            LEFT JOIN usuarios af ON pa.arte_finalista_id = af.id
            WHERE p.id = ?
            LIMIT 1
        ";
        
        $stmt = $this->db->query($sql, [$id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        // Buscar itens do pedido
        $sqlItens = "
            SELECT pi.*, pc.codigo as produto_codigo, pc.nome as produto_nome
            FROM pedido_itens pi
            LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
            WHERE pi.pedido_id = ?
            ORDER BY pi.id
        ";
        
        $stmtItens = $this->db->query($sqlItens, [$id]);
        $result['itens'] = $stmtItens->fetchAll();
        
        return $result;
    }
    
    /**
     * Atualiza status do pedido
     * 
     * @param int $id ID do pedido
     * @param string $status Novo status
     * @param string|null $observacoes Observações opcionais
     * @param int|null $usuarioId ID do usuário que fez a alteração
     * @return bool
     */
    public function atualizarStatus(int $id, string $status, ?string $observacoes = null, ?int $usuarioId = null): bool {
        $this->db->beginTransaction();
        try {
            // Atualizar pedido
            $this->update($id, [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Registrar histórico de status (se tabela existir)
            if ($this->tabelaExiste('producao_status')) {
                $sql = "
                    INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id, created_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ";
                $this->db->query($sql, [$id, $status, $observacoes, $usuarioId]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Verifica se uma tabela existe (método auxiliar)
     * 
     * @param string $tabela Nome da tabela
     * @return bool
     */
    private function tabelaExiste(string $tabela): bool {
        try {
            $sql = "SELECT 1 FROM information_schema.tables 
                    WHERE table_schema = 'public' AND table_name = ? LIMIT 1";
            $stmt = $this->db->query($sql, [$tabela]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }
}
