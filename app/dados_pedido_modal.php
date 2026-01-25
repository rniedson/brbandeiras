<?php
require_once 'config.php';
require_once 'auth.php';

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Cache headers
header('Content-Type: application/json');
header('Cache-Control: private, max-age=300'); // Cache por 5 minutos

try {
    // Buscar clientes
    $clientes = $pdo->query("
        SELECT 
            id, nome, nome_fantasia, cpf_cnpj, telefone, celular, whatsapp, email, codigo_sistema,
            COALESCE(celular, whatsapp, telefone) as telefone_principal
        FROM clientes 
        WHERE ativo = true 
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Buscar produtos
    $produtos = $pdo->query("
        SELECT p.*, c.nome as categoria_nome 
        FROM produtos_catalogo p
        LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
        WHERE p.ativo = true AND p.estoque_disponivel = true
        ORDER BY c.nome, p.nome
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Organizar produtos por categoria
    $produtosPorCategoria = [];
    foreach ($produtos as $produto) {
        $categoria = $produto['categoria_nome'] ?: 'Sem Categoria';
        if (!isset($produtosPorCategoria[$categoria])) {
            $produtosPorCategoria[$categoria] = [];
        }
        $produtosPorCategoria[$categoria][] = $produto;
    }

    echo json_encode([
        'clientes' => $clientes,
        'produtos' => $produtosPorCategoria,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar dados']);
}