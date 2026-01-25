<?php
/**
 * Teste para verificar cálculo de baseUrl
 */

// Simular diferentes cenários
$scenarios = [
    '/brbandeiras/public/index.php' => 0,
    '/brbandeiras/public/dashboard/dashboard.php' => 1,
    '/brbandeiras/public/pedidos/pedidos.php' => 1,
    '/brbandeiras/public/pedidos/detalhes/pedido_detalhes.php' => 2,
    '/brbandeiras/public/clientes/clientes.php' => 1,
];

echo "=== Teste de Cálculo de baseUrl ===\n\n";

foreach ($scenarios as $scriptPath => $expectedDepth) {
    $publicPath = '/brbandeiras/public';
    $relativePath = str_replace($publicPath, '', dirname($scriptPath));
    $relativePath = trim($relativePath, '/');
    $depth = $relativePath ? substr_count($relativePath, '/') + 1 : 0;
    $baseUrl = $depth > 0 ? str_repeat('../', $depth) : '';
    
    $status = $depth === $expectedDepth ? '✓' : '✗';
    echo "{$status} {$scriptPath}\n";
    echo "   Esperado: {$expectedDepth} níveis → '{$baseUrl}'\n";
    echo "   Calculado: {$depth} níveis → '{$baseUrl}'\n";
    echo "   Exemplo logout: '{$baseUrl}logout.php'\n\n";
}
