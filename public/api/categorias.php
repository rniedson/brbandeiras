<?php
/**
 * API de Categorias de Produtos com Cache
 * 
 * Retorna categorias com cache de 1 hora (dados estáticos)
 */

define('AJAX_REQUEST', true);

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/cache.php';
require_once '../../app/ajax_helper.php';

AjaxResponse::init();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

// Usar cache estático (1 hora)
$categorias = StaticCache::categoriasProdutos($pdo);

// Headers de cache para o cliente (1 hora)
CacheHeaders::shortCache(3600);

AjaxResponse::success(['categorias' => $categorias]);
