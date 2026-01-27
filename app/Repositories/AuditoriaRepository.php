<?php
/**
 * AuditoriaRepository - Repository para acesso a dados de auditoria
 * 
 * Centraliza queries relacionadas ao sistema de auditoria e logs.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Repositories;

use PDOException;

// Database não está em namespace, será usado diretamente

class AuditoriaRepository {
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
     * Registra uma ação de auditoria
     * 
     * @param array $dados Dados da auditoria:
     *   - usuario_id: ID do usuário
     *   - acao: Ação realizada
     *   - detalhes: Detalhes da ação
     *   - ip: IP do usuário
     *   - user_agent: User agent do navegador
     *   - entidade_tipo: Tipo de entidade afetada (ex: 'pedido', 'cliente')
     *   - entidade_id: ID da entidade afetada
     *   - dados_anteriores: Dados antes da alteração (JSON)
     *   - dados_novos: Dados após alteração (JSON)
     * @return int ID do registro criado
     */
    public function registrar(array $dados): int {
        $campos = [
            'usuario_id',
            'acao',
            'detalhes',
            'ip',
            'user_agent',
            'entidade_tipo',
            'entidade_id',
            'dados_anteriores',
            'dados_novos',
            'created_at'
        ];
        
        $valores = [];
        $placeholders = [];
        
        foreach ($campos as $campo) {
            if (isset($dados[$campo])) {
                $valores[] = $dados[$campo];
                $placeholders[] = '?';
            } else {
                // Valores padrão
                if ($campo === 'created_at') {
                    $valores[] = date('Y-m-d H:i:s');
                    $placeholders[] = '?';
                } else {
                    $valores[] = null;
                    $placeholders[] = '?';
                }
            }
        }
        
        // Verificar estrutura da tabela e adaptar
        $sql = "
            INSERT INTO logs_sistema (
                usuario_id, 
                acao, 
                detalhes, 
                " . ($this->verificarColunaExiste('logs_sistema', 'ip') ? 'ip' : 'ip_address') . ",
                " . ($this->verificarColunaExiste('logs_sistema', 'user_agent') ? 'user_agent' : '') . "
                " . ($this->verificarColunaExiste('logs_sistema', 'entidade_tipo') ? ', entidade_tipo' : '') . "
                " . ($this->verificarColunaExiste('logs_sistema', 'entidade_id') ? ', entidade_id' : '') . "
                " . ($this->verificarColunaExiste('logs_sistema', 'dados_anteriores') ? ', dados_anteriores' : '') . "
                " . ($this->verificarColunaExiste('logs_sistema', 'dados_novos') ? ', dados_novos' : '') . "
                created_at
            ) VALUES (" . implode(', ', $placeholders) . ")
            RETURNING id
        ";
        
        // Simplificar para estrutura atual
        $sql = "
            INSERT INTO logs_sistema (
                usuario_id, 
                acao, 
                detalhes, 
                " . ($this->verificarColunaExiste('logs_sistema', 'ip') ? 'ip' : 'ip_address') . ",
                created_at
            ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            RETURNING id
        ";
        
        $stmt = $this->db->query($sql, [
            $dados['usuario_id'] ?? null,
            $dados['acao'] ?? '',
            $dados['detalhes'] ?? '',
            $dados['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Busca registros de auditoria com filtros
     * 
     * @param array $filtros Filtros opcionais:
     *   - usuario_id: Filtrar por usuário
     *   - acao: Filtrar por ação
     *   - data_inicio: Data inicial (Y-m-d)
     *   - data_fim: Data final (Y-m-d)
     *   - entidade_tipo: Tipo de entidade
     *   - entidade_id: ID da entidade
     *   - ip: IP do usuário
     * @param string|null $orderBy Ordenação (padrão: created_at DESC)
     * @param int|null $limit Limite de registros
     * @param int|null $offset Offset para paginação
     * @return array Array de registros de auditoria
     */
    public function buscar(array $filtros = [], ?string $orderBy = 'created_at DESC', ?int $limit = null, ?int $offset = null): array {
        $sql = "
            SELECT 
                l.*,
                u.nome as usuario_nome,
                u.email as usuario_email,
                u.perfil as usuario_perfil
            FROM logs_sistema l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Aplicar filtros
        if (isset($filtros['usuario_id']) && $filtros['usuario_id'] > 0) {
            $sql .= " AND l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (isset($filtros['acao']) && !empty($filtros['acao'])) {
            $sql .= " AND l.acao = ?";
            $params[] = $filtros['acao'];
        }
        
        if (isset($filtros['data_inicio']) && !empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(l.created_at) >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (isset($filtros['data_fim']) && !empty($filtros['data_fim'])) {
            $sql .= " AND DATE(l.created_at) <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        if (isset($filtros['ip']) && !empty($filtros['ip'])) {
            $colunaIp = $this->verificarColunaExiste('logs_sistema', 'ip') ? 'ip' : 'ip_address';
            $sql .= " AND l.{$colunaIp} = ?";
            $params[] = $filtros['ip'];
        }
        
        // Busca por texto nos detalhes
        if (isset($filtros['busca']) && !empty($filtros['busca'])) {
            $sql .= " AND (l.detalhes ILIKE ? OR l.acao ILIKE ?)";
            $busca = '%' . $filtros['busca'] . '%';
            $params[] = $busca;
            $params[] = $busca;
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
     * Conta registros de auditoria com filtros
     * 
     * @param array $filtros Mesmos filtros de buscar()
     * @return int Total de registros
     */
    public function contar(array $filtros = []): int {
        $sql = "SELECT COUNT(*) FROM logs_sistema l WHERE 1=1";
        $params = [];
        
        // Aplicar mesmos filtros de buscar()
        if (isset($filtros['usuario_id']) && $filtros['usuario_id'] > 0) {
            $sql .= " AND l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (isset($filtros['acao']) && !empty($filtros['acao'])) {
            $sql .= " AND l.acao = ?";
            $params[] = $filtros['acao'];
        }
        
        if (isset($filtros['data_inicio']) && !empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(l.created_at) >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (isset($filtros['data_fim']) && !empty($filtros['data_fim'])) {
            $sql .= " AND DATE(l.created_at) <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        if (isset($filtros['ip']) && !empty($filtros['ip'])) {
            $colunaIp = $this->verificarColunaExiste('logs_sistema', 'ip') ? 'ip' : 'ip_address';
            $sql .= " AND l.{$colunaIp} = ?";
            $params[] = $filtros['ip'];
        }
        
        if (isset($filtros['busca']) && !empty($filtros['busca'])) {
            $sql .= " AND (l.detalhes ILIKE ? OR l.acao ILIKE ?)";
            $busca = '%' . $filtros['busca'] . '%';
            $params[] = $busca;
            $params[] = $busca;
        }
        
        $stmt = $this->db->query($sql, $params);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Busca estatísticas de auditoria por período
     * 
     * @param string $periodo 'dia', 'semana', 'mes'
     * @param array $filtros Filtros adicionais
     * @return array Estatísticas
     */
    public function buscarEstatisticas(string $periodo = 'dia', array $filtros = []): array {
        $sql = "
            SELECT 
                DATE(l.created_at) as data,
                COUNT(*) as total,
                COUNT(DISTINCT l.usuario_id) as usuarios_unicos,
                COUNT(DISTINCT l.acao) as acoes_unicas
            FROM logs_sistema l
            WHERE 1=1
        ";
        
        $params = [];
        
        // Aplicar filtro de período
        $dataInicio = $this->getDataInicioPeriodo($periodo);
        if ($dataInicio) {
            $sql .= " AND l.created_at >= ?";
            $params[] = $dataInicio;
        }
        
        // Aplicar outros filtros
        if (isset($filtros['usuario_id']) && $filtros['usuario_id'] > 0) {
            $sql .= " AND l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        $sql .= " GROUP BY DATE(l.created_at) ORDER BY data DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca ações mais frequentes
     * 
     * @param int $limite Limite de resultados
     * @param array $filtros Filtros opcionais
     * @return array Array de ações com contagem
     */
    public function buscarAcoesFrequentes(int $limite = 10, array $filtros = []): array {
        $sql = "
            SELECT 
                l.acao,
                COUNT(*) as total,
                COUNT(DISTINCT l.usuario_id) as usuarios_unicos
            FROM logs_sistema l
            WHERE 1=1
        ";
        
        $params = [];
        
        // Aplicar filtros
        if (isset($filtros['data_inicio']) && !empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(l.created_at) >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (isset($filtros['data_fim']) && !empty($filtros['data_fim'])) {
            $sql .= " AND DATE(l.created_at) <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        $sql .= " GROUP BY l.acao ORDER BY total DESC LIMIT ?";
        $params[] = $limite;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca atividades por usuário
     * 
     * @param int $usuarioId ID do usuário
     * @param array $filtros Filtros adicionais
     * @return array Array de atividades
     */
    public function buscarPorUsuario(int $usuarioId, array $filtros = []): array {
        $filtros['usuario_id'] = $usuarioId;
        return $this->buscar($filtros);
    }
    
    /**
     * Obtém data de início do período
     * 
     * @param string $periodo 'dia', 'semana', 'mes'
     * @return string|null Data no formato Y-m-d H:i:s
     */
    private function getDataInicioPeriodo(string $periodo): ?string {
        switch ($periodo) {
            case 'dia':
                return date('Y-m-d 00:00:00');
            case 'semana':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'mes':
                return date('Y-m-01 00:00:00');
            default:
                return null;
        }
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
