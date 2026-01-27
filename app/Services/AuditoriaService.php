<?php
/**
 * AuditoriaService - Serviço de lógica de negócio para auditoria
 * 
 * Centraliza a lógica de negócio relacionada ao sistema de auditoria.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Services;

use App\Repositories\AuditoriaRepository;
use InvalidArgumentException;

// Database não está em namespace, será usado diretamente

class AuditoriaService {
    private $repository;
    private $db;
    
    /**
     * Construtor
     * 
     * @param AuditoriaRepository $repository Repository de auditoria
     * @param \Database $db Instância do Database
     */
    public function __construct(AuditoriaRepository $repository, \Database $db) {
        $this->repository = $repository;
        $this->db = $db;
    }
    
    /**
     * Registra uma ação de auditoria
     * 
     * @param string $acao Ação realizada
     * @param string $detalhes Detalhes da ação
     * @param int|null $usuarioId ID do usuário (null usa sessão)
     * @param array $contexto Contexto adicional (entidade_tipo, entidade_id, etc)
     * @return int ID do registro criado
     */
    public function registrar(string $acao, string $detalhes = '', ?int $usuarioId = null, array $contexto = []): int {
        if (empty($acao)) {
            throw new InvalidArgumentException('Ação não pode estar vazia');
        }
        
        // Obter usuário da sessão se não fornecido
        if ($usuarioId === null) {
            $usuarioId = $_SESSION['user_id'] ?? null;
        }
        
        $dados = [
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'detalhes' => $detalhes,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'entidade_tipo' => $contexto['entidade_tipo'] ?? null,
            'entidade_id' => $contexto['entidade_id'] ?? null,
            'dados_anteriores' => isset($contexto['dados_anteriores']) ? json_encode($contexto['dados_anteriores']) : null,
            'dados_novos' => isset($contexto['dados_novos']) ? json_encode($contexto['dados_novos']) : null
        ];
        
        return $this->repository->registrar($dados);
    }
    
    /**
     * Busca registros de auditoria com filtros e paginação
     * 
     * @param array $filtros Filtros opcionais
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Itens por página
     * @return array Array com estrutura paginada
     */
    public function buscar(array $filtros = [], int $page = 1, int $perPage = 50): array {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage)); // Limitar entre 1 e 200
        
        $offset = ($page - 1) * $perPage;
        
        $dados = $this->repository->buscar($filtros, 'created_at DESC', $perPage, $offset);
        $total = $this->repository->contar($filtros);
        
        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $page,
            'por_pagina' => $perPage,
            'total_paginas' => (int)ceil($total / $perPage)
        ];
    }
    
    /**
     * Busca atividades por período (dia/semana/mês)
     * 
     * @param string $periodo 'dia', 'semana', 'mes'
     * @param array $filtros Filtros adicionais
     * @return array Estatísticas do período
     */
    public function buscarPorPeriodo(string $periodo = 'dia', array $filtros = []): array {
        $periodosValidos = ['dia', 'semana', 'mes'];
        if (!in_array($periodo, $periodosValidos)) {
            throw new InvalidArgumentException("Período inválido. Use: " . implode(', ', $periodosValidos));
        }
        
        return $this->repository->buscarEstatisticas($periodo, $filtros);
    }
    
    /**
     * Busca atividades por usuário
     * 
     * @param int $usuarioId ID do usuário
     * @param array $filtros Filtros adicionais
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @return array Array paginado de atividades
     */
    public function buscarPorUsuario(int $usuarioId, array $filtros = [], int $page = 1, int $perPage = 50): array {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('ID do usuário deve ser maior que zero');
        }
        
        $filtros['usuario_id'] = $usuarioId;
        return $this->buscar($filtros, $page, $perPage);
    }
    
    /**
     * Busca ações mais frequentes
     * 
     * @param int $limite Limite de resultados
     * @param array $filtros Filtros opcionais
     * @return array Array de ações com contagem
     */
    public function buscarAcoesFrequentes(int $limite = 10, array $filtros = []): array {
        return $this->repository->buscarAcoesFrequentes($limite, $filtros);
    }
    
    /**
     * Gera relatório resumido de atividades
     * 
     * @param string $periodo 'dia', 'semana', 'mes'
     * @param array $filtros Filtros opcionais
     * @return array Relatório resumido
     */
    public function gerarRelatorio(string $periodo = 'dia', array $filtros = []): array {
        $estatisticas = $this->buscarPorPeriodo($periodo, $filtros);
        $acoesFrequentes = $this->buscarAcoesFrequentes(10, $filtros);
        
        // Calcular totais
        $totalAtividades = 0;
        $totalUsuarios = 0;
        $acoesUnicas = [];
        
        foreach ($estatisticas as $stat) {
            $totalAtividades += $stat['total'];
            $totalUsuarios = max($totalUsuarios, $stat['usuarios_unicos']);
            $acoesUnicas[] = $stat['acoes_unicas'];
        }
        
        return [
            'periodo' => $periodo,
            'data_inicio' => $this->getDataInicioPeriodo($periodo),
            'data_fim' => date('Y-m-d H:i:s'),
            'total_atividades' => $totalAtividades,
            'total_usuarios_unicos' => $totalUsuarios,
            'total_acoes_unicas' => count(array_unique($acoesUnicas)),
            'estatisticas_por_data' => $estatisticas,
            'acoes_frequentes' => $acoesFrequentes
        ];
    }
    
    /**
     * Obtém data de início do período
     * 
     * @param string $periodo 'dia', 'semana', 'mes'
     * @return string Data no formato Y-m-d H:i:s
     */
    private function getDataInicioPeriodo(string $periodo): string {
        switch ($periodo) {
            case 'dia':
                return date('Y-m-d 00:00:00');
            case 'semana':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'mes':
                return date('Y-m-01 00:00:00');
            default:
                return date('Y-m-d 00:00:00');
        }
    }
}
