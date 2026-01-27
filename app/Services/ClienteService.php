<?php
/**
 * ClienteService - Serviço de lógica de negócio para clientes
 * 
 * Centraliza a lógica de negócio relacionada a clientes,
 * utilizando repositories para acesso a dados.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Services;

use App\Repositories\ClienteRepository;
use InvalidArgumentException;
use RuntimeException;

// Database não está em namespace, será usado diretamente

class ClienteService {
    private $repository;
    private $db;
    
    /**
     * Construtor
     * 
     * @param ClienteRepository $repository Repository de clientes
     * @param \Database $db Instância do Database (para transações)
     */
    public function __construct(ClienteRepository $repository, \Database $db) {
        $this->repository = $repository;
        $this->db = $db;
    }
    
    /**
     * Busca cliente por ID
     * 
     * @param int $id ID do cliente
     * @return array|null Array com dados do cliente ou null se não encontrado
     * @throws InvalidArgumentException Se ID inválido
     */
    public function buscarPorId(int $id): ?array {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID do cliente deve ser maior que zero');
        }
        
        return $this->repository->buscarPorId($id);
    }
    
    /**
     * Busca cliente por CPF/CNPJ
     * 
     * @param string $cpfCnpj CPF ou CNPJ do cliente
     * @return array|null Array com dados do cliente ou null se não encontrado
     */
    public function buscarPorCpfCnpj(string $cpfCnpj): ?array {
        if (empty($cpfCnpj)) {
            throw new InvalidArgumentException('CPF/CNPJ não pode estar vazio');
        }
        
        return $this->repository->buscarPorCpfCnpj($cpfCnpj);
    }
    
    /**
     * Busca cliente com estatísticas de pedidos
     * 
     * @param int $id ID do cliente
     * @return array|null Array com dados do cliente e estatísticas ou null
     */
    public function buscarComEstatisticas(int $id): ?array {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID do cliente deve ser maior que zero');
        }
        
        return $this->repository->buscarComEstatisticas($id);
    }
    
    /**
     * Cria um novo cliente com validações
     * 
     * @param array $dados Dados do cliente
     * @return int ID do cliente criado
     * @throws InvalidArgumentException Se dados inválidos
     * @throws RuntimeException Se cliente já existe (mesmo CPF/CNPJ)
     */
    public function criar(array $dados): int {
        // Validações básicas
        if (empty($dados['nome'])) {
            throw new InvalidArgumentException('Nome do cliente é obrigatório');
        }
        
        // Verificar se já existe cliente com mesmo CPF/CNPJ
        if (!empty($dados['cpf_cnpj'])) {
            $existente = $this->repository->buscarPorCpfCnpj($dados['cpf_cnpj']);
            if ($existente) {
                throw new RuntimeException('Já existe um cliente cadastrado com este CPF/CNPJ');
            }
        }
        
        return $this->repository->criar($dados);
    }
    
    /**
     * Atualiza um cliente existente
     * 
     * @param int $id ID do cliente
     * @param array $dados Dados para atualizar
     * @return bool True se atualizado com sucesso
     * @throws InvalidArgumentException Se dados inválidos
     * @throws RuntimeException Se cliente não encontrado
     */
    public function atualizar(int $id, array $dados): bool {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID do cliente deve ser maior que zero');
        }
        
        // Verificar se cliente existe
        $cliente = $this->repository->buscarPorId($id);
        if (!$cliente) {
            throw new RuntimeException("Cliente com ID {$id} não encontrado");
        }
        
        // Verificar se CPF/CNPJ não está sendo usado por outro cliente
        if (!empty($dados['cpf_cnpj']) && $dados['cpf_cnpj'] !== $cliente['cpf_cnpj']) {
            $existente = $this->repository->buscarPorCpfCnpj($dados['cpf_cnpj']);
            if ($existente && $existente['id'] != $id) {
                throw new RuntimeException('Já existe outro cliente cadastrado com este CPF/CNPJ');
            }
        }
        
        return $this->repository->atualizar($id, $dados);
    }
    
    /**
     * Deleta um cliente (com validação de pedidos relacionados)
     * 
     * @param int $id ID do cliente
     * @param bool $forcar Se true, força exclusão mesmo com pedidos relacionados
     * @return bool True se deletado com sucesso
     * @throws RuntimeException Se cliente tem pedidos relacionados e não está forçando
     */
    public function deletar(int $id, bool $forcar = false): bool {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID do cliente deve ser maior que zero');
        }
        
        // Verificar se cliente existe
        $cliente = $this->repository->buscarPorId($id);
        if (!$cliente) {
            throw new RuntimeException("Cliente com ID {$id} não encontrado");
        }
        
        // Verificar se tem pedidos relacionados
        if (!$forcar) {
            $estatisticas = $this->repository->buscarComEstatisticas($id);
            if ($estatisticas && isset($estatisticas['estatisticas']['total_pedidos']) && $estatisticas['estatisticas']['total_pedidos'] > 0) {
                throw new RuntimeException('Não é possível excluir cliente com pedidos relacionados. Use $forcar = true para forçar exclusão.');
            }
        }
        
        return $this->repository->deletar($id);
    }
    
    /**
     * Busca todos os clientes com filtros e paginação
     * 
     * @param array $filtros Filtros opcionais
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Itens por página
     * @return array Array com estrutura:
     *   - dados: array de clientes
     *   - total: total de registros
     *   - pagina: página atual
     *   - por_pagina: itens por página
     *   - total_paginas: total de páginas
     */
    public function buscarTodos(array $filtros = [], int $page = 1, int $perPage = 25): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage)); // Limitar entre 1 e 100
        
        $offset = ($page - 1) * $perPage;
        
        $dados = $this->repository->buscarTodos($filtros, 'nome ASC', $perPage, $offset);
        $total = $this->repository->contar($filtros);
        
        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $page,
            'por_pagina' => $perPage,
            'total_paginas' => (int)ceil($total / $perPage)
        ];
    }
}
