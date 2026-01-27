<?php
/**
 * Sistema de Cache para BR Bandeiras
 * 
 * Fornece cache em memória e arquivo para melhorar performance
 */

class Cache {
    private static $memoryCache = [];
    private static $cacheDir = null;
    private static $defaultTTL = 300; // 5 minutos padrão
    
    /**
     * Verifica se APCu está disponível e habilitado
     */
    public static function isApcuAvailable() {
        return extension_loaded('apcu') && apcu_enabled();
    }
    
    /**
     * Inicializa o diretório de cache
     */
    private static function initCacheDir() {
        if (self::$cacheDir === null) {
            self::$cacheDir = __DIR__ . '/../storage/cache/';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }
    
    /**
     * Gera chave de cache baseada em parâmetros
     */
    public static function key(...$parts) {
        return md5(implode(':', array_map(function($p) {
            return is_array($p) ? json_encode($p) : (string)$p;
        }, $parts)));
    }
    
    /**
     * Obtém item do cache de memória (mais rápido, dura apenas na requisição)
     */
    public static function memory($key, $default = null) {
        return self::$memoryCache[$key] ?? $default;
    }
    
    /**
     * Armazena item no cache de memória
     */
    public static function setMemory($key, $value) {
        self::$memoryCache[$key] = $value;
        return $value;
    }
    
    /**
     * Obtém item do cache (APCu → Memória Local → Arquivo → null)
     */
    public static function get($key, $default = null) {
        // Primeiro tenta memória local (mais rápido para mesma requisição)
        if (isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key];
        }
        
        // Depois tenta APCu (memória compartilhada entre requisições)
        if (self::isApcuAvailable()) {
            $cached = apcu_fetch($key);
            if ($cached !== false) {
                // Armazena também em memória local para esta requisição
                self::$memoryCache[$key] = $cached;
                return $cached;
            }
        }
        
        // Depois tenta arquivo
        $file = self::initCacheDir() . $key . '.cache';
        if (file_exists($file)) {
            $data = @unserialize(file_get_contents($file));
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                $value = $data['value'];
                // Armazena em memória local e APCu (se disponível)
                self::$memoryCache[$key] = $value;
                if (self::isApcuAvailable()) {
                    $ttl = $data['expires'] - time();
                    if ($ttl > 0) {
                        apcu_store($key, $value, $ttl);
                    }
                }
                return $value;
            }
            // Cache expirado, remove
            @unlink($file);
        }
        
        return $default;
    }
    
    /**
     * Armazena item no cache (APCu → Memória Local → Arquivo)
     */
    public static function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? self::$defaultTTL;
        
        // Armazena em memória local (para esta requisição)
        self::$memoryCache[$key] = $value;
        
        // Armazena em APCu primeiro (memória compartilhada - mais rápido)
        if (self::isApcuAvailable()) {
            apcu_store($key, $value, $ttl);
        }
        
        // Armazena em arquivo (backup/fallback)
        $file = self::initCacheDir() . $key . '.cache';
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        @file_put_contents($file, serialize($data), LOCK_EX);
        return $value;
    }
    
    /**
     * Remove item do cache (APCu → Memória Local → Arquivo)
     */
    public static function forget($key) {
        // Remove de memória local
        unset(self::$memoryCache[$key]);
        
        // Remove de APCu
        if (self::isApcuAvailable()) {
            apcu_delete($key);
        }
        
        // Remove arquivo
        $file = self::initCacheDir() . $key . '.cache';
        if (file_exists($file)) {
            @unlink($file);
        }
    }
    
    /**
     * Remove itens do cache por prefixo (APCu → Memória Local → Arquivo)
     */
    public static function forgetByPrefix($prefix) {
        // Limpa memória local
        foreach (array_keys(self::$memoryCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset(self::$memoryCache[$key]);
            }
        }
        
        // Limpa APCu (itera sobre todas as chaves - pode ser custoso, mas necessário)
        if (self::isApcuAvailable()) {
            $info = apcu_cache_info();
            if (isset($info['cache_list'])) {
                foreach ($info['cache_list'] as $entry) {
                    if (isset($entry['info']) && strpos($entry['info'], $prefix) === 0) {
                        apcu_delete($entry['info']);
                    }
                }
            }
        }
        
        // Limpa arquivos
        $dir = self::initCacheDir();
        foreach (glob($dir . $prefix . '*.cache') as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Limpa todo o cache (APCu → Memória Local → Arquivo)
     */
    public static function flush() {
        // Limpa memória local
        self::$memoryCache = [];
        
        // Limpa APCu
        if (self::isApcuAvailable()) {
            apcu_clear_cache();
        }
        
        // Limpa arquivos
        $dir = self::initCacheDir();
        foreach (glob($dir . '*.cache') as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Retorna estatísticas do cache
     */
    public static function stats() {
        $stats = [
            'memory_local' => [
                'count' => count(self::$memoryCache),
                'keys' => array_keys(self::$memoryCache)
            ],
            'apcu' => null,
            'files' => [
                'count' => 0,
                'size' => 0
            ]
        ];
        
        // Estatísticas APCu
        if (self::isApcuAvailable()) {
            $apcuInfo = apcu_cache_info();
            $stats['apcu'] = [
                'enabled' => true,
                'hits' => $apcuInfo['num_hits'] ?? 0,
                'misses' => $apcuInfo['num_misses'] ?? 0,
                'entries' => $apcuInfo['num_entries'] ?? 0,
                'memory_size' => $apcuInfo['mem_size'] ?? 0,
                'memory_size_formatted' => self::formatBytes($apcuInfo['mem_size'] ?? 0)
            ];
        } else {
            $stats['apcu'] = ['enabled' => false];
        }
        
        // Estatísticas de arquivos
        $dir = self::initCacheDir();
        $files = glob($dir . '*.cache');
        if ($files) {
            $stats['files']['count'] = count($files);
            foreach ($files as $file) {
                $stats['files']['size'] += filesize($file);
            }
            $stats['files']['size_formatted'] = self::formatBytes($stats['files']['size']);
        }
        
        return $stats;
    }
    
    /**
     * Retorna estatísticas específicas do APCu
     */
    public static function apcuStats() {
        if (!self::isApcuAvailable()) {
            return ['enabled' => false];
        }
        
        return apcu_cache_info();
    }
    
    /**
     * Formata bytes em formato legível
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Obtém ou armazena (remember pattern)
     */
    public static function remember($key, $ttl, callable $callback) {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }
    
    /**
     * Cache específico para queries do banco
     */
    public static function query($key, $ttl, PDO $pdo, $sql, $params = []) {
        return self::remember($key, $ttl, function() use ($pdo, $sql, $params) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
    
    /**
     * Cache para dados do usuário/sessão (cache curto)
     */
    public static function user($userId, $key, $ttl, callable $callback) {
        $cacheKey = "user_{$userId}_{$key}";
        return self::remember($cacheKey, $ttl, $callback);
    }
    
    /**
     * Invalida cache do usuário
     */
    public static function invalidateUser($userId) {
        self::forgetByPrefix("user_{$userId}_");
    }
}

/**
 * Classe para cache de dados estáticos (configurações, listas, etc)
 */
class StaticCache {
    
    /**
     * Cache de categorias de produtos (muda raramente)
     */
    public static function categoriasProdutos(PDO $pdo) {
        return Cache::remember('static_categorias_produtos', 3600, function() use ($pdo) {
            $stmt = $pdo->query("SELECT * FROM categorias_produtos ORDER BY nome");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
    
    /**
     * Cache de configurações do sistema
     */
    public static function configuracoes(PDO $pdo) {
        return Cache::remember('static_configuracoes', 1800, function() use ($pdo) {
            $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
            $configs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $configs[$row['chave']] = $row['valor'];
            }
            return $configs;
        });
    }
    
    /**
     * Cache de usuários ativos (para selects)
     */
    public static function usuariosAtivos(PDO $pdo) {
        return Cache::remember('static_usuarios_ativos', 600, function() use ($pdo) {
            $stmt = $pdo->query("SELECT id, nome, perfil FROM usuarios WHERE ativo = true ORDER BY nome");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
    
    /**
     * Cache de status disponíveis
     */
    public static function statusPedidos() {
        return [
            'orcamento' => ['label' => 'Comercial', 'color' => 'green'],
            'arte' => ['label' => 'Arte', 'color' => 'lime'],
            'producao' => ['label' => 'Produção', 'color' => 'yellow'],
            'pronto' => ['label' => 'Expedição', 'color' => 'amber'],
            'entregue' => ['label' => 'Entregue', 'color' => 'gray']
        ];
    }
    
    /**
     * Invalida caches estáticos (chamar quando editar configurações)
     */
    public static function invalidate() {
        Cache::forgetByPrefix('static_');
    }
}

/**
 * Headers de cache para respostas HTTP
 */
class CacheHeaders {
    
    /**
     * Define headers para conteúdo que não deve ser cacheado
     */
    public static function noCache() {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    /**
     * Define headers para conteúdo estático (CSS, JS, imagens)
     */
    public static function staticContent($maxAge = 86400) {
        header("Cache-Control: public, max-age={$maxAge}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    }
    
    /**
     * Define headers para conteúdo que pode ser cacheado brevemente
     */
    public static function shortCache($maxAge = 300) {
        header("Cache-Control: private, max-age={$maxAge}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    }
    
    /**
     * Define ETag para cache condicional
     */
    public static function etag($content) {
        $etag = '"' . md5($content) . '"';
        header("ETag: {$etag}");
        
        // Verificar se cliente tem versão atual
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
            http_response_code(304);
            exit;
        }
        
        return $etag;
    }
}
