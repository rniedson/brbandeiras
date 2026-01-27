<?php
/**
 * Sistema de Pré-carregamento de Dados para BR Bandeiras
 * 
 * Carrega dados frequentes em APCu para melhorar performance
 * Similar ao React que mantém estado em memória
 */

require_once __DIR__ . '/cache.php';

class DataPreloader {
    
    /**
     * Pré-carrega dados frequentes em APCu
     * Executa após a resposta ao usuário (não bloqueia requisição)
     */
    public static function warmup(PDO $pdo) {
        // Só executa se APCu estiver disponível
        if (!Cache::isApcuAvailable()) {
            return false;
        }
        
        // Registra função para executar após resposta ao usuário
        register_shutdown_function(function() use ($pdo) {
            try {
                self::loadCategoriasProdutos($pdo);
                self::loadClientesAtivos($pdo);
                self::loadProdutosCatalogo($pdo);
                self::loadConfiguracoes($pdo);
                self::loadUsuariosAtivos($pdo);
            } catch (Exception $e) {
                // Log erro silenciosamente para não afetar resposta ao usuário
                error_log("DataPreloader::warmup error: " . $e->getMessage());
            }
        });
        
        return true;
    }
    
    /**
     * Pré-carrega categorias de produtos
     */
    private static function loadCategoriasProdutos(PDO $pdo) {
        $key = Cache::key('static_categorias_produtos');
        
        // Verifica se já está em cache
        if (Cache::get($key) !== null) {
            return;
        }
        
        // Carrega e armazena em cache (1 hora)
        StaticCache::categoriasProdutos($pdo);
    }
    
    /**
     * Pré-carrega lista de clientes ativos (limitado para não sobrecarregar)
     */
    private static function loadClientesAtivos(PDO $pdo) {
        $key = Cache::key('preload_clientes_ativos');
        
        // Verifica se já está em cache
        if (Cache::get($key) !== null) {
            return;
        }
        
        // Carrega apenas IDs e nomes principais (cache de 10 minutos)
        Cache::remember($key, 600, function() use ($pdo) {
            $stmt = $pdo->query("
                SELECT id, nome, nome_fantasia, cpf_cnpj
                FROM clientes 
                WHERE ativo = true 
                ORDER BY nome
                LIMIT 500
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
    
    /**
     * Pré-carrega produtos do catálogo (limitado)
     */
    private static function loadProdutosCatalogo(PDO $pdo) {
        $key = Cache::key('preload_produtos_catalogo');
        
        // Verifica se já está em cache
        if (Cache::get($key) !== null) {
            return;
        }
        
        // Carrega produtos mais usados (cache de 15 minutos)
        Cache::remember($key, 900, function() use ($pdo) {
            $stmt = $pdo->query("
                SELECT p.id, p.codigo, p.nome, p.preco, p.categoria_id,
                       c.nome as categoria_nome
                FROM produtos_catalogo p
                LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
                WHERE p.ativo = true AND p.estoque_disponivel = true
                ORDER BY p.nome
                LIMIT 500
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
    
    /**
     * Pré-carrega configurações do sistema
     */
    private static function loadConfiguracoes(PDO $pdo) {
        $key = Cache::key('static_configuracoes');
        
        // Verifica se já está em cache
        if (Cache::get($key) !== null) {
            return;
        }
        
        // Carrega configurações (cache de 30 minutos)
        StaticCache::configuracoes($pdo);
    }
    
    /**
     * Pré-carrega usuários ativos
     */
    private static function loadUsuariosAtivos(PDO $pdo) {
        $key = Cache::key('static_usuarios_ativos');
        
        // Verifica se já está em cache
        if (Cache::get($key) !== null) {
            return;
        }
        
        // Carrega usuários ativos (cache de 10 minutos)
        StaticCache::usuariosAtivos($pdo);
    }
    
    /**
     * Pré-carrega dados específicos do calendário (meses adjacentes)
     */
    public static function preloadCalendarMonths(PDO $pdo, $mesAtual, $anoAtual) {
        if (!Cache::isApcuAvailable()) {
            return false;
        }
        
        // Pré-carrega mês atual, anterior e próximo
        $meses = [
            ['mes' => $mesAtual, 'ano' => $anoAtual],
            ['mes' => $mesAtual - 1, 'ano' => $anoAtual - ($mesAtual == 1 ? 1 : 0)],
            ['mes' => $mesAtual + 1, 'ano' => $anoAtual + ($mesAtual == 12 ? 1 : 0)]
        ];
        
        foreach ($meses as $m) {
            $mes = $m['mes'];
            $ano = $m['ano'];
            
            // Normalizar mês
            if ($mes < 1) { $mes = 12; $ano--; }
            if ($mes > 12) { $mes = 1; $ano++; }
            
            $key = Cache::key('calendario_pedidos', $mes, $ano);
            
            // Se não estiver em cache, pré-carrega em background
            if (Cache::get($key) === null) {
                register_shutdown_function(function() use ($pdo, $mes, $ano, $key) {
                    try {
                        $primeiroDia = sprintf('%04d-%02d-01', $ano, $mes);
                        $ultimoDia = date('Y-m-t', strtotime($primeiroDia));
                        
                        $stmt = $pdo->prepare("
                            SELECT 
                                p.id, p.numero, p.status, p.urgente, p.valor_total,
                                p.prazo_entrega, p.created_at,
                                c.nome as cliente_nome
                            FROM pedidos p
                            LEFT JOIN clientes c ON p.cliente_id = c.id
                            WHERE p.prazo_entrega BETWEEN :inicio AND :fim
                            AND p.status NOT IN ('cancelado')
                            ORDER BY p.prazo_entrega, p.urgente DESC, p.created_at
                        ");
                        $stmt->execute([
                            'inicio' => $primeiroDia,
                            'fim' => $ultimoDia
                        ]);
                        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $pedidosPorData = [];
                        foreach ($pedidos as $pedido) {
                            $data = date('Y-m-d', strtotime($pedido['prazo_entrega']));
                            if (!isset($pedidosPorData[$data])) {
                                $pedidosPorData[$data] = [];
                            }
                            $pedidosPorData[$data][] = $pedido;
                        }
                        
                        $primeiroDiaObj = mktime(0, 0, 0, $mes, 1, $ano);
                        
                        $data = [
                            'mes' => $mes,
                            'ano' => $ano,
                            'dias_no_mes' => (int)date('t', $primeiroDiaObj),
                            'dia_semana_inicio' => (int)date('w', $primeiroDiaObj),
                            'pedidos_por_data' => $pedidosPorData,
                            'total_pedidos' => count($pedidos),
                            'cached_at' => date('Y-m-d H:i:s')
                        ];
                        
                        Cache::set($key, $data, 120); // Cache de 2 minutos
                    } catch (Exception $e) {
                        error_log("DataPreloader::preloadCalendarMonths error: " . $e->getMessage());
                    }
                });
            }
        }
        
        return true;
    }
    
    /**
     * Limpa todos os dados pré-carregados
     */
    public static function clearPreloaded() {
        Cache::forgetByPrefix('preload_');
        Cache::forgetByPrefix('static_');
    }
}
