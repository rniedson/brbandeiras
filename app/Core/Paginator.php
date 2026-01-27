<?php
/**
 * Paginator - Sistema de paginação padronizado
 * 
 * Fornece paginação consistente em todas as listagens do sistema,
 * com suporte a diferentes tamanhos de página e navegação.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

class Paginator {
    /**
     * Tamanho padrão de página
     */
    const DEFAULT_PER_PAGE = 25;
    
    /**
     * Opções de tamanho de página disponíveis
     */
    const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    
    /**
     * Página padrão
     */
    const DEFAULT_PAGE = 1;
    
    /**
     * Pagina um conjunto de dados
     * 
     * @param array $dados Array completo de dados
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Itens por página
     * @return array Array com estrutura:
     *   - dados: array de dados da página atual
     *   - total: total de registros
     *   - pagina: página atual
     *   - por_pagina: itens por página
     *   - total_paginas: total de páginas
     *   - tem_anterior: se tem página anterior
     *   - tem_proxima: se tem próxima página
     * 
     * @example
     * $resultado = Paginator::paginate($todosOsDados, 2, 25);
     */
    public static function paginate(array $dados, int $page = self::DEFAULT_PAGE, int $perPage = self::DEFAULT_PER_PAGE): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        
        $total = count($dados);
        $totalPaginas = (int)ceil($total / $perPage);
        $page = min($page, max(1, $totalPaginas)); // Garantir que página está no range válido
        
        $offset = ($page - 1) * $perPage;
        $dadosPagina = array_slice($dados, $offset, $perPage);
        
        return [
            'dados' => $dadosPagina,
            'total' => $total,
            'pagina' => $page,
            'por_pagina' => $perPage,
            'total_paginas' => $totalPaginas,
            'tem_anterior' => $page > 1,
            'tem_proxima' => $page < $totalPaginas,
            'primeira_pagina' => 1,
            'ultima_pagina' => $totalPaginas
        ];
    }
    
    /**
     * Cria estrutura de paginação para uso em queries SQL
     * 
     * @param int $total Total de registros (do COUNT)
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Itens por página
     * @return array Array com estrutura de paginação
     */
    public static function create(int $total, int $page = self::DEFAULT_PAGE, int $perPage = self::DEFAULT_PER_PAGE): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        
        $totalPaginas = (int)ceil($total / $perPage);
        $page = min($page, max(1, $totalPaginas));
        
        return [
            'total' => $total,
            'pagina' => $page,
            'por_pagina' => $perPage,
            'total_paginas' => $totalPaginas,
            'tem_anterior' => $page > 1,
            'tem_proxima' => $page < $totalPaginas,
            'primeira_pagina' => 1,
            'ultima_pagina' => $totalPaginas,
            'offset' => ($page - 1) * $perPage,
            'limit' => $perPage
        ];
    }
    
    /**
     * Valida e normaliza parâmetros de paginação vindos de requisição
     * 
     * @param array $params Parâmetros da requisição ($_GET)
     * @return array Array com 'page' e 'per_page' validados
     */
    public static function parseParams(array $params): array {
        $page = isset($params['page']) ? (int)$params['page'] : self::DEFAULT_PAGE;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : self::DEFAULT_PER_PAGE;
        
        // Validar página
        $page = max(1, $page);
        
        // Validar e limitar per_page
        $perPage = max(1, min(100, $perPage)); // Máximo de 100 itens por página
        
        return [
            'page' => $page,
            'per_page' => $perPage
        ];
    }
    
    /**
     * Gera HTML de navegação de paginação (bootstrap-style)
     * 
     * @param array $pagination Array retornado por paginate() ou create()
     * @param string $urlBase URL base para links (ex: '/pedidos?page=')
     * @return string HTML da navegação
     */
    public static function render(array $pagination, string $urlBase = '?page='): string {
        if ($pagination['total_paginas'] <= 1) {
            return ''; // Não renderizar se só tem uma página
        }
        
        $html = '<nav aria-label="Navegação de páginas"><ul class="pagination">';
        
        // Botão anterior
        if ($pagination['tem_anterior']) {
            $prevPage = $pagination['pagina'] - 1;
            $html .= "<li class='page-item'><a class='page-link' href='{$urlBase}{$prevPage}'>Anterior</a></li>";
        } else {
            $html .= "<li class='page-item disabled'><span class='page-link'>Anterior</span></li>";
        }
        
        // Páginas numeradas
        $inicio = max(1, $pagination['pagina'] - 2);
        $fim = min($pagination['total_paginas'], $pagination['pagina'] + 2);
        
        if ($inicio > 1) {
            $html .= "<li class='page-item'><a class='page-link' href='{$urlBase}1'>1</a></li>";
            if ($inicio > 2) {
                $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
            }
        }
        
        for ($i = $inicio; $i <= $fim; $i++) {
            if ($i == $pagination['pagina']) {
                $html .= "<li class='page-item active'><span class='page-link'>{$i}</span></li>";
            } else {
                $html .= "<li class='page-item'><a class='page-link' href='{$urlBase}{$i}'>{$i}</a></li>";
            }
        }
        
        if ($fim < $pagination['total_paginas']) {
            if ($fim < $pagination['total_paginas'] - 1) {
                $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
            }
            $html .= "<li class='page-item'><a class='page-link' href='{$urlBase}{$pagination['total_paginas']}'>{$pagination['total_paginas']}</a></li>";
        }
        
        // Botão próximo
        if ($pagination['tem_proxima']) {
            $nextPage = $pagination['pagina'] + 1;
            $html .= "<li class='page-item'><a class='page-link' href='{$urlBase}{$nextPage}'>Próximo</a></li>";
        } else {
            $html .= "<li class='page-item disabled'><span class='page-link'>Próximo</span></li>";
        }
        
        $html .= '</ul></nav>';
        
        // Informação de registros
        $inicioRegistro = ($pagination['pagina'] - 1) * $pagination['por_pagina'] + 1;
        $fimRegistro = min($pagination['pagina'] * $pagination['por_pagina'], $pagination['total']);
        
        $html .= "<div class='pagination-info'>Mostrando {$inicioRegistro} a {$fimRegistro} de {$pagination['total']} registros</div>";
        
        return $html;
    }
}
