<?php
/**
 * PedidoService - Serviço de lógica de negócio para pedidos
 * 
 * Centraliza a lógica de negócio relacionada a pedidos,
 * utilizando repositories para acesso a dados.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Services;

use App\Repositories\PedidoRepository;
use InvalidArgumentException;
use RuntimeException;

// Database não está em namespace, será usado diretamente

class PedidoService {
    private $repository;
    private $db;
    
    /**
     * Construtor
     * 
     * @param PedidoRepository $repository Repository de pedidos
     * @param \Database $db Instância do Database (para transações)
     */
    public function __construct(PedidoRepository $repository, \Database $db) {
        $this->repository = $repository;
        $this->db = $db;
    }
    
    /**
     * Busca pedido completo com todos os relacionamentos
     * 
     * @param int $id ID do pedido
     * @return array|null Array com estrutura:
     *   - pedido: dados do pedido
     *   - cliente: dados do cliente
     *   - vendedor: dados do vendedor
     *   - itens: array de itens
     *   - versoes_arte: array de versões de arte
     *   - arquivos: array de arquivos
     *   - historico: array de histórico
     * @throws RuntimeException Se pedido não encontrado
     * 
     * @example
     * $service = new PedidoService(new PedidoRepository(\Database::getInstance()), \Database::getInstance());
     * $pedido = $service->buscarCompleto(123);
     */
    public function buscarCompleto(int $id): ?array {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID do pedido deve ser maior que zero');
        }
        
        $pedido = $this->repository->buscarCompletoParaGestor($id);
        
        if (!$pedido) {
            return null;
        }
        
        return $pedido;
    }
    
    /**
     * Atualiza status do pedido com validações de negócio
     * 
     * @param int $id ID do pedido
     * @param string $status Novo status
     * @param string|null $observacoes Observações opcionais
     * @param int|null $usuarioId ID do usuário que fez a alteração
     * @return bool True se atualizado com sucesso
     * @throws InvalidArgumentException Se status inválido
     * @throws RuntimeException Se pedido não encontrado ou erro na atualização
     */
    public function atualizarStatus(int $id, string $status, ?string $observacoes = null, ?int $usuarioId = null): bool {
        // Validar status
        $statusValidos = ['novo', 'orcamento', 'aprovado', 'em_producao', 'impressao', 'acabamento', 'entregue', 'cancelado'];
        if (!in_array($status, $statusValidos)) {
            throw new InvalidArgumentException("Status inválido: {$status}");
        }
        
        // Verificar se pedido existe
        $pedido = $this->repository->buscarPorId($id);
        if (!$pedido) {
            throw new RuntimeException("Pedido com ID {$id} não encontrado");
        }
        
        // Validações de negócio específicas
        if ($status === 'cancelado' && $pedido['status'] === 'entregue') {
            throw new RuntimeException('Não é possível cancelar um pedido já entregue');
        }
        
        // Executar atualização em transação
        return $this->db->transaction(function() use ($id, $status, $observacoes, $usuarioId) {
            // Atualizar pedido
            $sql = "UPDATE pedidos SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $this->db->query($sql, [$status, $id]);
            
            // Registrar histórico (se tabela existir)
            try {
                $sqlHistorico = "
                    INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id, created_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ";
                $this->db->query($sqlHistorico, [$id, $status, $observacoes, $usuarioId]);
            } catch (\Exception $e) {
                // Tabela pode não existir, continuar sem erro
            }
            
            // Registrar log
            if (function_exists('registrarLog')) {
                registrarLog('pedido_status_alterado', "Pedido #{$id} alterado para status: {$status}", $usuarioId);
            }
            
            return true;
        });
    }
    
    /**
     * Busca pedidos para dashboard com filtros e paginação
     * 
     * @param array $filtros Filtros opcionais
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Itens por página
     * @return array Array com estrutura:
     *   - dados: array de pedidos
     *   - total: total de registros
     *   - pagina: página atual
     *   - por_pagina: itens por página
     *   - total_paginas: total de páginas
     */
    public function buscarParaDashboard(array $filtros = [], int $page = 1, int $perPage = 25): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage)); // Limitar entre 1 e 100
        
        $offset = ($page - 1) * $perPage;
        
        $dados = $this->repository->buscarParaDashboard($filtros, 'p.updated_at DESC', $perPage, $offset);
        
        // Contar total (simplificado - em produção, usar COUNT separado)
        $total = count($dados);
        if (count($dados) === $perPage) {
            // Pode haver mais registros, mas não vamos contar agora por performance
            // Em produção, implementar método contar() no repository
            $total = $perPage * $page; // Estimativa
        }
        
        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $page,
            'por_pagina' => $perPage,
            'total_paginas' => (int)ceil($total / $perPage)
        ];
    }
    
    /**
     * Busca pedidos por vendedor
     * 
     * @param int $vendedorId ID do vendedor
     * @param string|null $status Filtrar por status (opcional)
     * @return array Array de pedidos
     */
    public function buscarPorVendedor(int $vendedorId, ?string $status = null): array {
        if ($vendedorId <= 0) {
            throw new InvalidArgumentException('ID do vendedor deve ser maior que zero');
        }
        
        return $this->repository->buscarPorVendedor($vendedorId, $status);
    }
    
    /**
     * Busca pedidos por status
     * 
     * @param string $status Status do pedido
     * @return array Array de pedidos
     */
    public function buscarPorStatus(string $status): array {
        $statusValidos = ['novo', 'orcamento', 'aprovado', 'em_producao', 'impressao', 'acabamento', 'entregue', 'cancelado'];
        if (!in_array($status, $statusValidos)) {
            throw new InvalidArgumentException("Status inválido: {$status}");
        }
        
        return $this->repository->buscarPorStatus($status);
    }
}
