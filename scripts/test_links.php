<?php
/**
 * Script para testar todos os links do sistema após reorganização
 */

require_once __DIR__ . '/../app/config.php';

// Base URL para testes
$baseUrl = 'http://localhost/brbandeiras/public';

// Lista de links do menu para testar
$links = [
    // Dashboard
    'dashboard.php' => 'dashboard/dashboard.php',
    
    // Pedidos
    'pedidos.php' => 'pedidos/pedidos.php',
    'pedido_novo.php' => 'pedidos/pedido_novo.php',
    'orcamentos.php' => 'orcamentos/orcamentos.php',
    
    // Clientes
    'clientes.php' => 'clientes/clientes.php',
    'cliente_novo.php' => 'clientes/cliente_novo.php',
    
    // Produtos
    'catalogo.php' => 'produtos/catalogo.php',
    'catalogo_produto_novo.php' => 'produtos/catalogo_produto_novo.php',
    'catalogo_importar.php' => 'produtos/catalogo_importar.php',
    'catalogo_precos.php' => 'produtos/catalogo_precos.php',
    'categorias.php' => 'produtos/categorias_produtos.php',
    
    // Estoque
    'estoque.php' => 'estoque/estoque.php',
    
    // Produção
    'producao.php' => 'producao/producao.php',
    
    // Arte
    'artes.php' => 'arte/artes.php',
    
    // Usuários
    'usuarios.php' => 'usuarios/usuarios.php',
    
    // Perfil
    'perfil.php' => 'usuarios/perfil.php',
    'configuracoes_usuario.php' => 'usuarios/configuracoes_usuario.php',
    
    // Utils
    'logout.php' => 'logout.php',
    'ver_como_desativar.php' => 'utils/ver_como_desativar.php',
];

echo "=== Teste de Links após Reorganização ===\n\n";

$broken = [];
$working = [];
$notFound = [];

foreach ($links as $oldUrl => $newPath) {
    $fullUrl = $baseUrl . '/' . $oldUrl;
    $newUrl = $baseUrl . '/' . $newPath;
    
    // Testar URL antiga (deve redirecionar via .htaccess)
    $ch = curl_init($fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Verificar se arquivo existe no novo local
    $newFile = __DIR__ . '/../public/' . $newPath;
    $fileExists = file_exists($newFile);
    
    if ($httpCode == 200 || $httpCode == 302) {
        if ($fileExists) {
            $working[] = [
                'old' => $oldUrl,
                'new' => $newPath,
                'status' => 'OK',
                'http' => $httpCode
            ];
            echo "✓ {$oldUrl} → {$newPath} (HTTP {$httpCode})\n";
        } else {
            $notFound[] = [
                'old' => $oldUrl,
                'new' => $newPath,
                'status' => 'FILE_NOT_FOUND'
            ];
            echo "✗ {$oldUrl} → {$newPath} (Arquivo não encontrado)\n";
        }
    } else {
        $broken[] = [
            'old' => $oldUrl,
            'new' => $newPath,
            'status' => 'BROKEN',
            'http' => $httpCode
        ];
        echo "✗ {$oldUrl} → {$newPath} (HTTP {$httpCode})\n";
    }
}

echo "\n=== Resumo ===\n";
echo "✓ Funcionando: " . count($working) . "\n";
echo "✗ Quebrados: " . count($broken) . "\n";
echo "✗ Arquivo não encontrado: " . count($notFound) . "\n";

if (!empty($broken)) {
    echo "\n=== Links Quebrados ===\n";
    foreach ($broken as $link) {
        echo "- {$link['old']} (HTTP {$link['http']})\n";
    }
}

if (!empty($notFound)) {
    echo "\n=== Arquivos Não Encontrados ===\n";
    foreach ($notFound as $link) {
        echo "- {$link['new']}\n";
    }
}
