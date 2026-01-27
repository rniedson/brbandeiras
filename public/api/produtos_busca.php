<?php
/**
 * API de Busca de Produtos com Cache
 * 
 * Retorna produtos para autocomplete com cache de 10 minutos
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

$termo = trim($_GET['termo'] ?? '');

if (strlen($termo) < 2) {
    AjaxResponse::success(['produtos' => []]);
}

// Gerar chave de cache baseada no termo (primeiros 3 caracteres)
$termoPrefixo = strtolower(substr($termo, 0, 3));
$cacheKey = Cache::key('produtos_busca', $termoPrefixo);

// Buscar do cache ou banco
$produtos = Cache::remember($cacheKey, 600, function() use ($pdo, $termoPrefixo) {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.codigo, p.nome, p.preco, p.categoria_id,
            c.nome as categoria_nome
        FROM produtos_catalogo p
        LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
        WHERE p.ativo = true 
        AND (
            LOWER(p.nome) LIKE :termo 
            OR LOWER(p.codigo) LIKE :termo
        )
        ORDER BY p.nome
        LIMIT 50
    ");
    $stmt->execute(['termo' => $termoPrefixo . '%']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

// Filtrar resultado pelo termo completo (se necessário)
if (strlen($termo) > 3) {
    $termoLower = strtolower($termo);
    $produtos = array_filter($produtos, function($p) use ($termoLower) {
        return stripos($p['nome'], $termoLower) !== false 
            || stripos($p['codigo'], $termoLower) !== false;
    });
    $produtos = array_values($produtos);
}

// Headers de cache para o cliente (10 minutos)
CacheHeaders::shortCache(600);

AjaxResponse::success(['produtos' => $produtos]);
