<?php
/**
 * Teste para verificar geração de links no header
 */

// Simular diferentes cenários de SCRIPT_NAME
$scenarios = [
    '/public/dashboard/dashboard.php',
    '/public/pedidos/pedidos.php',
    '/public/pedidos/detalhes/pedido_detalhes.php',
    '/public/index.php',
    '/brbandeiras/public/dashboard/dashboard.php',
    '/brbandeiras/public/pedidos/pedidos.php',
];

echo "=== Teste de Geração de Links no Header ===\n\n";

foreach ($scenarios as $scriptPath) {
    $_SERVER['SCRIPT_NAME'] = $scriptPath;
    
    // Código do header
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $scriptPath = str_replace('//', '/', $scriptPath);
    
    $publicPos = strpos($scriptPath, '/public/');
    if ($publicPos !== false) {
        $basePath = substr($scriptPath, 0, $publicPos + 7);
        $baseUrl = $basePath;
    } else {
        $baseUrl = '/brbandeiras/public/';
    }
    
    $baseUrl = rtrim($baseUrl, '/') . '/';
    
    echo "Script: {$scriptPath}\n";
    echo "  baseUrl: {$baseUrl}\n";
    echo "  logout: {$baseUrl}logout.php\n";
    echo "  perfil: {$baseUrl}usuarios/perfil.php\n";
    echo "  dashboard: {$baseUrl}dashboard/dashboard.php\n";
    echo "\n";
}
