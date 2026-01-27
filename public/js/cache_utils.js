/**
 * Sistema de Cache Client-Side para BR Bandeiras
 * 
 * Fornece cache em localStorage e sessionStorage para melhorar performance
 */

const AppCache = {
    // Prefixo para evitar conflitos
    PREFIX: 'brb_',
    
    // TTLs padrão (em segundos)
    TTL: {
        SHORT: 60,        // 1 minuto
        MEDIUM: 300,      // 5 minutos
        LONG: 1800,       // 30 minutos
        DAY: 86400,       // 1 dia
        WEEK: 604800      // 1 semana
    },

    /**
     * Armazena item no localStorage com TTL
     */
    set(key, value, ttl = this.TTL.MEDIUM) {
        const item = {
            value: value,
            expires: Date.now() + (ttl * 1000),
            created: Date.now()
        };
        try {
            localStorage.setItem(this.PREFIX + key, JSON.stringify(item));
            return true;
        } catch (e) {
            // localStorage cheio ou indisponível
            console.warn('Cache: Não foi possível armazenar', key, e);
            return false;
        }
    },

    /**
     * Obtém item do localStorage
     */
    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(this.PREFIX + key);
            if (!item) return defaultValue;
            
            const data = JSON.parse(item);
            if (data.expires && data.expires < Date.now()) {
                // Expirado
                this.remove(key);
                return defaultValue;
            }
            return data.value;
        } catch (e) {
            return defaultValue;
        }
    },

    /**
     * Remove item do localStorage
     */
    remove(key) {
        localStorage.removeItem(this.PREFIX + key);
    },

    /**
     * Remove itens por prefixo
     */
    removeByPrefix(prefix) {
        const fullPrefix = this.PREFIX + prefix;
        const keys = Object.keys(localStorage).filter(k => k.startsWith(fullPrefix));
        keys.forEach(k => localStorage.removeItem(k));
    },

    /**
     * Limpa todo o cache da aplicação
     */
    clear() {
        const keys = Object.keys(localStorage).filter(k => k.startsWith(this.PREFIX));
        keys.forEach(k => localStorage.removeItem(k));
    },

    /**
     * Limpa itens expirados
     */
    cleanup() {
        const keys = Object.keys(localStorage).filter(k => k.startsWith(this.PREFIX));
        keys.forEach(key => {
            try {
                const item = JSON.parse(localStorage.getItem(key));
                if (item.expires && item.expires < Date.now()) {
                    localStorage.removeItem(key);
                }
            } catch (e) {}
        });
    },

    /**
     * Remember pattern - obtém do cache ou executa callback
     */
    async remember(key, ttl, callback) {
        const cached = this.get(key);
        if (cached !== null) {
            return cached;
        }
        
        const value = await callback();
        this.set(key, value, ttl);
        return value;
    },

    /**
     * Cache para sessão (sessionStorage)
     */
    session: {
        set(key, value) {
            try {
                sessionStorage.setItem(AppCache.PREFIX + key, JSON.stringify(value));
                return true;
            } catch (e) {
                return false;
            }
        },
        
        get(key, defaultValue = null) {
            try {
                const item = sessionStorage.getItem(AppCache.PREFIX + key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (e) {
                return defaultValue;
            }
        },
        
        remove(key) {
            sessionStorage.removeItem(AppCache.PREFIX + key);
        },
        
        clear() {
            const keys = Object.keys(sessionStorage).filter(k => k.startsWith(AppCache.PREFIX));
            keys.forEach(k => sessionStorage.removeItem(k));
        }
    }
};

/**
 * Cache específico para requisições AJAX
 */
const AjaxCache = {
    // Cache de requisições pendentes para evitar duplicação
    pending: new Map(),
    
    /**
     * Fetch com cache
     */
    async fetch(url, options = {}, ttl = AppCache.TTL.MEDIUM) {
        const cacheKey = `ajax_${url}_${JSON.stringify(options)}`;
        
        // Verificar cache
        const cached = AppCache.get(cacheKey);
        if (cached !== null) {
            return cached;
        }
        
        // Verificar se já tem requisição pendente para esta URL
        if (this.pending.has(cacheKey)) {
            return this.pending.get(cacheKey);
        }
        
        // Fazer requisição
        const promise = fetch(url, {
            ...options,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            }
        })
        .then(async response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            
            // Armazenar em cache
            AppCache.set(cacheKey, data, ttl);
            
            // Remover da lista de pendentes
            this.pending.delete(cacheKey);
            
            return data;
        })
        .catch(error => {
            this.pending.delete(cacheKey);
            throw error;
        });
        
        // Armazenar promessa pendente
        this.pending.set(cacheKey, promise);
        
        return promise;
    },
    
    /**
     * Invalida cache de uma URL específica
     */
    invalidate(urlPattern) {
        AppCache.removeByPrefix(`ajax_${urlPattern}`);
    },
    
    /**
     * Invalida todo o cache AJAX
     */
    invalidateAll() {
        AppCache.removeByPrefix('ajax_');
    }
};

/**
 * Cache específico para dados do Dashboard
 */
const DashboardCache = {
    // Chaves de cache
    KEYS: {
        PEDIDOS: 'dashboard_pedidos',
        STATS: 'dashboard_stats',
        CLIENTES: 'dashboard_clientes',
        PRODUTOS: 'dashboard_produtos',
        LAST_UPDATE: 'dashboard_last_update'
    },
    
    /**
     * Armazena dados do dashboard
     */
    setPedidos(pedidos) {
        AppCache.set(this.KEYS.PEDIDOS, pedidos, AppCache.TTL.SHORT);
        AppCache.set(this.KEYS.LAST_UPDATE, Date.now(), AppCache.TTL.SHORT);
    },
    
    /**
     * Obtém pedidos do cache
     */
    getPedidos() {
        return AppCache.get(this.KEYS.PEDIDOS);
    },
    
    /**
     * Verifica se cache está válido
     */
    isValid() {
        const lastUpdate = AppCache.get(this.KEYS.LAST_UPDATE);
        if (!lastUpdate) return false;
        
        // Cache válido por 1 minuto
        return (Date.now() - lastUpdate) < 60000;
    },
    
    /**
     * Invalida cache do dashboard
     */
    invalidate() {
        Object.values(this.KEYS).forEach(key => AppCache.remove(key));
    },
    
    /**
     * Cache de clientes para autocomplete
     */
    async getClientes(searchTerm, fetchCallback) {
        const cacheKey = `clientes_${searchTerm.toLowerCase().substring(0, 3)}`;
        
        return AppCache.remember(cacheKey, AppCache.TTL.MEDIUM, async () => {
            return await fetchCallback(searchTerm);
        });
    },
    
    /**
     * Cache de produtos para autocomplete
     */
    async getProdutos(searchTerm, fetchCallback) {
        const cacheKey = `produtos_${searchTerm.toLowerCase().substring(0, 3)}`;
        
        return AppCache.remember(cacheKey, AppCache.TTL.MEDIUM, async () => {
            return await fetchCallback(searchTerm);
        });
    }
};

/**
 * Preloader de dados - carrega dados comuns em background
 */
const DataPreloader = {
    /**
     * Pré-carrega dados comuns quando a página está ociosa
     */
    init() {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => this.preload());
        } else {
            setTimeout(() => this.preload(), 2000);
        }
    },
    
    /**
     * Executa pré-carregamento
     */
    async preload() {
        // Lista de endpoints para pré-carregar
        const endpoints = [
            { url: '../api/categorias.php', ttl: AppCache.TTL.LONG },
            { url: '../api/usuarios_ativos.php', ttl: AppCache.TTL.MEDIUM }
        ];
        
        for (const endpoint of endpoints) {
            try {
                // Só pré-carrega se não estiver em cache
                if (!AppCache.get(`ajax_${endpoint.url}_${JSON.stringify({})}`)) {
                    await AjaxCache.fetch(endpoint.url, {}, endpoint.ttl);
                }
            } catch (e) {
                // Ignora erros de pré-carregamento
            }
        }
    }
};

// Limpar cache expirado periodicamente
setInterval(() => AppCache.cleanup(), 60000);

// Exportar para uso global
window.AppCache = AppCache;
window.AjaxCache = AjaxCache;
window.DashboardCache = DashboardCache;
window.DataPreloader = DataPreloader;
