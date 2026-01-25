<?php
/**
 * Script para validar todos os links do menu após reorganização
 */

$baseDir = dirname(__DIR__);
$publicDir = $baseDir . '/public';

// Mapeamento de URLs antigas para novos caminhos
$urlMap = [
    'dashboard.php' => 'dashboard/dashboard.php',
    'pedidos.php' => 'pedidos/pedidos.php',
    'pedido_novo.php' => 'pedidos/pedido_novo.php',
    'orcamentos.php' => 'orcamentos/orcamentos.php',
    'clientes.php' => 'clientes/clientes.php',
    'cliente_novo.php' => 'clientes/cliente_novo.php',
    'catalogo.php' => 'produtos/catalogo.php',
    'catalogo_produto_novo.php' => 'produtos/catalogo_produto_novo.php',
    'catalogo_importar.php' => 'produtos/catalogo_importar.php',
    'catalogo_precos.php' => 'produtos/catalogo_precos.php',
    'categorias.php' => 'produtos/categorias_produtos.php',
    'estoque.php' => 'estoque/estoque.php',
    'producao.php' => 'producao/producao.php',
    'usuarios.php' => 'usuarios/usuarios.php',
    'perfil.php' => 'usuarios/perfil.php',
    'configuracoes_usuario.php' => 'usuarios/configuracoes_usuario.php',
    'logout.php' => 'logout.php',
    'ver_como_desativar.php' => 'utils/ver_como_desativar.php',
];

// Links que podem não existir ainda (futuros)
$linksFuturos = [
    'aprovacoes.php',
    'impressao.php',
    'ordem_servico.php',
    'expedicao.php',
    'cliente_grupos.php',
    'cliente_historico.php',
    'estoque_movimentos.php',
    'fornecedores.php',
    'fornecedor_novo.php',
    'cotacoes.php',
    'financeiro_dashboard.php',
    'contas_receber.php',
    'comissoes.php',
    'metas.php',
    'relatorio_vendas.php',
    'relatorio_financeiro.php',
    'relatorio_artes.php',
    'empresa.php',
    'filiais.php',
    'documentos.php',
];

echo "=== Validação de Links do Menu ===\n\n";

$working = [];
$broken = [];
$missing = [];

// Validar links mapeados
foreach ($urlMap as $oldUrl => $newPath) {
    $fullPath = $publicDir . '/' . $newPath;
    
    if (file_exists($fullPath)) {
        $working[] = [
            'old' => $oldUrl,
            'new' => $newPath,
            'status' => 'OK'
        ];
        echo "✓ {$oldUrl} → {$newPath}\n";
    } else {
        $missing[] = [
            'old' => $oldUrl,
            'new' => $newPath,
            'status' => 'MISSING'
        ];
        echo "✗ {$oldUrl} → {$newPath} (ARQUIVO NÃO ENCONTRADO)\n";
    }
}

// Verificar links futuros
echo "\n=== Links Futuros (não implementados ainda) ===\n";
foreach ($linksFuturos as $link) {
    $found = false;
    foreach (glob($publicDir . '/**/' . $link, GLOB_BRACE) as $file) {
        $found = true;
        $relative = str_replace($publicDir . '/', '', $file);
        echo "⚠ {$link} encontrado em: {$relative}\n";
        break;
    }
    if (!$found) {
        echo "○ {$link} (não existe - pode ser futuro)\n";
    }
}

echo "\n=== Resumo ===\n";
echo "✓ Funcionando: " . count($working) . "\n";
echo "✗ Arquivo não encontrado: " . count($missing) . "\n";
echo "○ Links futuros: " . count($linksFuturos) . "\n";

if (!empty($missing)) {
    echo "\n=== Arquivos Não Encontrados ===\n";
    foreach ($missing as $item) {
        echo "- {$item['old']} → {$item['new']}\n";
    }
}

echo "\n=== Verificação de Rewrite Rules ===\n";
$htaccess = $publicDir . '/.htaccess';
if (file_exists($htaccess)) {
    $content = file_get_contents($htaccess);
    $rulesFound = 0;
    foreach ($urlMap as $oldUrl => $newPath) {
        $pattern = preg_quote($oldUrl, '/');
        if (preg_match("/RewriteRule.*{$pattern}/", $content)) {
            $rulesFound++;
        }
    }
    echo "✓ Rewrite rules encontradas: {$rulesFound}/" . count($urlMap) . "\n";
} else {
    echo "✗ Arquivo .htaccess não encontrado!\n";
}
