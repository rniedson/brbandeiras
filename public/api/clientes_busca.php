<?php
/**
 * API de Busca de Clientes com Cache
 * 
 * Retorna clientes para autocomplete com cache de 5 minutos
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
    AjaxResponse::success(['clientes' => []]);
}

// Gerar chave de cache baseada no termo (primeiros 3 caracteres)
$termoPrefixo = strtolower(substr($termo, 0, 3));
$cacheKey = Cache::key('clientes_busca', $termoPrefixo);

// Buscar do cache ou banco
$clientes = Cache::remember($cacheKey, 300, function() use ($pdo, $termoPrefixo) {
    $stmt = $pdo->prepare("
        SELECT id, nome, cpf_cnpj, telefone_principal, email, cidade, estado
        FROM clientes 
        WHERE ativo = true 
        AND (
            LOWER(nome) LIKE :termo 
            OR cpf_cnpj LIKE :termo_doc
        )
        ORDER BY nome
        LIMIT 50
    ");
    $stmt->execute([
        'termo' => $termoPrefixo . '%',
        'termo_doc' => $termoPrefixo . '%'
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

// Filtrar resultado pelo termo completo (se necessário)
if (strlen($termo) > 3) {
    $termoLower = strtolower($termo);
    $clientes = array_filter($clientes, function($c) use ($termoLower) {
        return stripos($c['nome'], $termoLower) !== false 
            || stripos($c['cpf_cnpj'] ?? '', $termoLower) !== false;
    });
    $clientes = array_values($clientes);
}

// Headers de cache para o cliente (5 minutos)
CacheHeaders::shortCache(300);

AjaxResponse::success(['clientes' => $clientes]);
