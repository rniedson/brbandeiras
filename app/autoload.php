<?php
/**
 * Autoloader PSR-4
 * 
 * Sistema de carregamento automático de classes seguindo o padrão PSR-4.
 * Permite carregar classes automaticamente sem necessidade de múltiplos require_once.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Não é uma classe do namespace App, ignorar
        return;
    }
    
    // Remove o prefixo do namespace
    $relativeClass = substr($class, $len);
    
    // Converte namespace para caminho de arquivo
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // Se o arquivo existir, carregá-lo
    if (file_exists($file)) {
        require $file;
    }
});

// Autoloader para classes sem namespace (compatibilidade com código legado)
spl_autoload_register(function ($class) {
    // Classes principais sem namespace
    $legacyClasses = [
        'Database' => __DIR__ . '/Core/Database.php',
        'BaseModel' => __DIR__ . '/Core/BaseModel.php',
        'Pedido' => __DIR__ . '/Models/Pedido.php',
        'Cache' => __DIR__ . '/cache.php',
    ];
    
    if (isset($legacyClasses[$class])) {
        if (file_exists($legacyClasses[$class])) {
            require $legacyClasses[$class];
        }
    }
});
