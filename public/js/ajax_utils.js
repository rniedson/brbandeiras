/**
 * Utilitário para Requisições AJAX
 * 
 * Função helper para fazer requisições AJAX com validação adequada
 * e tratamento de erros consistente
 * 
 * Uso:
 *   const data = await ajaxRequest('/api/endpoint.php', {
 *       method: 'POST',
 *       body: JSON.stringify({ campo: 'valor' })
 *   });
 */

/**
 * Faz uma requisição AJAX com validação completa
 * 
 * @param {string} url URL do endpoint
 * @param {object} options Opções do fetch (method, headers, body, etc)
 * @returns {Promise<object>} Dados JSON da resposta
 * @throws {Error} Se houver erro na requisição ou resposta inválida
 */
async function ajaxRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache'
        },
        cache: 'no-store'
    };
    
    // Mesclar opções padrão com opções fornecidas
    const config = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...(options.headers || {})
        }
    };
    
    try {
        const response = await fetch(url, config);
        
        // Verificar status HTTP
        if (!response.ok) {
            // Tentar obter mensagem de erro do JSON
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                if (errorData.error) {
                    errorMessage = errorData.error;
                }
            } catch (e) {
                // Se não conseguir parsear JSON, usar mensagem padrão
            }
            throw new Error(errorMessage);
        }
        
        // Verificar Content-Type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta não é JSON. Content-Type: ' + contentType);
        }
        
        // Parsear JSON
        const data = await response.json();
        
        // Verificar se há erro na resposta (mesmo com status 200)
        if (data.error && !data.success) {
            throw new Error(data.error);
        }
        
        return data;
        
    } catch (error) {
        // Log do erro para debugging
        console.error('Erro na requisição AJAX:', {
            url: url,
            error: error.message,
            stack: error.stack
        });
        
        // Re-throw para permitir tratamento pelo chamador
        throw error;
    }
}

/**
 * Faz uma requisição GET AJAX
 * 
 * @param {string} url URL do endpoint
 * @param {object} params Parâmetros de query (serão adicionados à URL)
 * @returns {Promise<object>} Dados JSON da resposta
 */
async function ajaxGet(url, params = {}) {
    // Construir query string
    const queryString = new URLSearchParams(params).toString();
    const fullUrl = queryString ? `${url}?${queryString}` : url;
    
    return ajaxRequest(fullUrl, {
        method: 'GET'
    });
}

/**
 * Faz uma requisição POST AJAX
 * 
 * @param {string} url URL do endpoint
 * @param {object} data Dados para enviar no body
 * @returns {Promise<object>} Dados JSON da resposta
 */
async function ajaxPost(url, data = {}) {
    return ajaxRequest(url, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

/**
 * Faz uma requisição PUT AJAX
 * 
 * @param {string} url URL do endpoint
 * @param {object} data Dados para enviar no body
 * @returns {Promise<object>} Dados JSON da resposta
 */
async function ajaxPut(url, data = {}) {
    return ajaxRequest(url, {
        method: 'PUT',
        body: JSON.stringify(data)
    });
}

/**
 * Faz uma requisição DELETE AJAX
 * 
 * @param {string} url URL do endpoint
 * @returns {Promise<object>} Dados JSON da resposta
 */
async function ajaxDelete(url) {
    return ajaxRequest(url, {
        method: 'DELETE'
    });
}

// Exportar funções (compatível com módulos ES6 e uso direto)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        ajaxRequest,
        ajaxGet,
        ajaxPost,
        ajaxPut,
        ajaxDelete
    };
}
