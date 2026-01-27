<?php
/**
 * Script para Verificar e Corrigir BOM/Espaços em Arquivos PHP
 * 
 * Este script verifica arquivos PHP para:
 * - BOM (Byte Order Mark) no início do arquivo
 * - Espaços em branco antes de <?php
 * - Outros problemas que podem causar ERR_EMPTY_RESPONSE
 * 
 * Uso:
 *   php scripts/verificar_bom.php [--fix] [--path=./public]
 */

$options = getopt('', ['fix', 'path::']);
$fixMode = isset($options['fix']);
$searchPath = $options['path'] ?? __DIR__ . '/../public';

$problems = [];
$fixed = 0;

/**
 * Verifica se um arquivo tem BOM
 */
function hasBOM($filePath) {
    $handle = fopen($filePath, 'rb');
    $bytes = fread($handle, 3);
    fclose($handle);
    
    // BOM UTF-8: EF BB BF
    return $bytes === "\xEF\xBB\xBF";
}

/**
 * Verifica se há espaços antes de <?php
 */
function hasSpacesBeforePHP($filePath) {
    $content = file_get_contents($filePath);
    
    // Verificar se há espaços/tabs antes de <?php na primeira linha
    if (preg_match('/^[\s\t]+<\?php/', $content)) {
        return true;
    }
    
    // Verificar se há BOM seguido de espaços
    if (preg_match('/^\xEF\xBB\xBF[\s\t]*<\?php/', $content)) {
        return true;
    }
    
    return false;
}

/**
 * Corrige problemas em um arquivo
 */
function fixFile($filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Remover BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    
    // Remover espaços antes de <?php
    $content = preg_replace('/^[\s\t]+(<\?php)/', '$1', $content);
    
    // Garantir que começa com <?php (sem espaços)
    if (!preg_match('/^<\?php/', $content)) {
        // Se não começa com <?php, pode ser um problema maior
        return false;
    }
    
    // Salvar apenas se houve mudanças
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return true;
    }
    
    return false;
}

/**
 * Processa um arquivo
 */
function processFile($filePath, $fixMode) {
    global $problems, $fixed;
    
    $issues = [];
    
    if (hasBOM($filePath)) {
        $issues[] = 'BOM encontrado';
    }
    
    if (hasSpacesBeforePHP($filePath)) {
        $issues[] = 'Espaços antes de <?php';
    }
    
    if (!empty($issues)) {
        $problems[] = [
            'file' => $filePath,
            'issues' => $issues
        ];
        
        if ($fixMode) {
            if (fixFile($filePath)) {
                $fixed++;
                echo "✓ Corrigido: $filePath\n";
            } else {
                echo "✗ Erro ao corrigir: $filePath\n";
            }
        }
    }
}

/**
 * Processa diretório recursivamente
 */
function processDirectory($dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            processFile($file->getPathname(), $GLOBALS['fixMode']);
        }
    }
}

echo "Verificando arquivos PHP em: $searchPath\n";
echo str_repeat('=', 60) . "\n\n";

if (!is_dir($searchPath)) {
    die("Erro: Diretório não encontrado: $searchPath\n");
}

processDirectory($searchPath);

echo "\n" . str_repeat('=', 60) . "\n";
echo "Resultado:\n";
echo "- Arquivos verificados: " . count($problems) . " com problemas\n";

if ($fixMode) {
    echo "- Arquivos corrigidos: $fixed\n";
} else {
    echo "\nPara corrigir automaticamente, execute com --fix:\n";
    echo "  php scripts/verificar_bom.php --fix\n";
}

if (!empty($problems)) {
    echo "\nArquivos com problemas:\n";
    foreach ($problems as $problem) {
        echo "\n  " . $problem['file'] . ":\n";
        foreach ($problem['issues'] as $issue) {
            echo "    - $issue\n";
        }
    }
}

echo "\n";
