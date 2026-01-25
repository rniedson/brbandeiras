<?php
/**
 * Script para atualizar includes relativos após reorganização
 * Atualiza caminhos ../app/ e ../views/ para os novos caminhos
 */

$baseDir = dirname(__DIR__);
$publicDir = $baseDir . '/public';

// Mapeamento de profundidade: diretório => níveis acima
$depthMap = [
    'pedidos' => 2,           // public/pedidos/ -> ../../app/
    'pedidos/detalhes' => 3,   // public/pedidos/detalhes/ -> ../../../app/
    'clientes' => 2,
    'produtos' => 2,
    'orcamentos' => 2,
    'producao' => 2,
    'arte' => 2,
    'estoque' => 2,
    'usuarios' => 2,
    'dashboard' => 2,
    'relatorios' => 2,
    'calendario' => 2,
    'utils' => 2,
];

// Padrões de substituição
$patterns = [
    // Arquivos na raiz de public/ (index.php, login.php, etc.) - mantém ../app/
    '/^require_once\s+[\'"]\.\.\/app\/(config|auth|functions)\.php[\'"];$/m' => function($match, $depth) {
        if ($depth == 1) return $match[0]; // Mantém se já está correto
        $path = str_repeat('../', $depth) . 'app/' . $match[1] . '.php';
        return "require_once '{$path}';";
    },
    
    // Arquivos em subdiretórios - atualiza para ../../app/ ou ../../../app/
    '/require_once\s+[\'"]\.\.\/app\/(config|auth|functions)\.php[\'"];/' => function($match, $depth) {
        $path = str_repeat('../', $depth) . 'app/' . $match[1] . '.php';
        return "require_once '{$path}';";
    },
    
    // Views - atualiza para layouts/
    '/include\s+[\'"]\.\.\/views\/_header\.php[\'"];/' => function($match, $depth) {
        $path = str_repeat('../', $depth) . 'views/layouts/_header.php';
        return "include '{$path}';";
    },
    
    '/include\s+[\'"]\.\.\/views\/_footer\.php[\'"];/' => function($match, $depth) {
        $path = str_repeat('../', $depth) . 'views/layouts/_footer.php';
        return "include '{$path}';";
    },
    
    '/require\s+[\'"]\.\.\/views\/_header\.php[\'"];/' => function($match, $depth) {
        $path = str_repeat('../', $depth) . 'views/layouts/_header.php';
        return "require '{$path}';";
    },
    
    '/require\s+[\'"]\.\.\/views\/_footer\.php[\'"];/' => function($match, $depth) {
        $path = str_repeat('../', $depth) . 'views/layouts/_footer.php';
        return "require '{$path}';";
    },
];

function updateFile($filePath, $depth) {
    global $patterns;
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Aplicar padrões de substituição
    foreach ($patterns as $pattern => $replacement) {
        if (is_callable($replacement)) {
            $content = preg_replace_callback($pattern, function($matches) use ($replacement, $depth) {
                return $replacement($matches, $depth);
            }, $content);
        } else {
            $content = preg_replace($pattern, $replacement, $content);
        }
    }
    
    // Salvar apenas se houver mudanças
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return true;
    }
    
    return false;
}

function processDirectory($dir, $relativePath = '') {
    global $depthMap;
    
    $files = scandir($dir);
    $updated = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $fullPath = $dir . '/' . $file;
        $relPath = $relativePath ? $relativePath . '/' . $file : $file;
        
        if (is_dir($fullPath)) {
            $updated += processDirectory($fullPath, $relPath);
        } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            // Determinar profundidade
            $depth = 1; // Padrão para arquivos na raiz de public/
            
            foreach ($depthMap as $path => $d) {
                if (strpos($relPath, $path) === 0) {
                    $depth = $d;
                    break;
                }
            }
            
            // Contar níveis reais
            $levels = substr_count($relPath, '/') + 1;
            if ($levels > 1) {
                $depth = $levels;
            }
            
            if (updateFile($fullPath, $depth)) {
                echo "✓ Atualizado: {$relPath}\n";
                $updated++;
            }
        }
    }
    
    return $updated;
}

echo "Atualizando includes nos arquivos PHP...\n\n";

$updated = processDirectory($publicDir);

echo "\n✓ Total de arquivos atualizados: {$updated}\n";
