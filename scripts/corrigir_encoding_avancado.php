<?php
/**
 * Script avançado para corrigir caracteres corrompidos em arquivos PHP
 * Detecta padrões comuns de corrupção de encoding
 */

function detectarECorrigir($arquivo) {
    if (!file_exists($arquivo)) {
        return false;
    }
    
    $conteudo = file_get_contents($arquivo);
    $original = $conteudo;
    
    // Padrões de substituição baseados em palavras comuns corrompidas
    $padroes = [
        // Função
        '/Fun\xEF\xBF\xBD{3,4}o/u' => 'Função',
        '/fun\xEF\xBF\xBD{3,4}o/u' => 'função',
        '/Fun[^\x00-\x7F]{3,4}o/u' => 'Função',
        '/fun[^\x00-\x7F]{3,4}o/u' => 'função',
        
        // Não
        '/n\xEF\xBF\xBD{2}o/u' => 'não',
        '/n[^\x00-\x7F]{2}o/u' => 'não',
        
        // Informações
        '/informa\xEF\xBF\xBD{3,4}es/u' => 'informações',
        '/Informa\xEF\xBF\xBD{3,4}es/u' => 'Informações',
        '/informa[^\x00-\x7F]{3,4}es/u' => 'informações',
        '/Informa[^\x00-\x7F]{3,4}es/u' => 'Informações',
        
        // Versões
        '/vers\xEF\xBF\xBD{2}es/u' => 'versões',
        '/Vers\xEF\xBF\xBD{2}es/u' => 'Versões',
        '/vers[^\x00-\x7F]{2}es/u' => 'versões',
        '/Vers[^\x00-\x7F]{2}es/u' => 'Versões',
        '/vers\xEF\xBF\xBD{2}o/u' => 'versão',
        '/Vers\xEF\xBF\xBD{2}o/u' => 'Versão',
        '/vers[^\x00-\x7F]{2}o/u' => 'versão',
        '/Vers[^\x00-\x7F]{2}o/u' => 'Versão',
        
        // Produção
        '/Produ\xEF\xBF\xBD{3,4}o/u' => 'Produção',
        '/produ\xEF\xBF\xBD{3,4}o/u' => 'produção',
        '/Produ[^\x00-\x7F]{3,4}o/u' => 'Produção',
        '/produ[^\x00-\x7F]{3,4}o/u' => 'produção',
        
        // Orçamento
        '/Or\xEF\xBF\xBD{2}amento/u' => 'Orçamento',
        '/or\xEF\xBF\xBD{2}amento/u' => 'orçamento',
        '/Or[^\x00-\x7F]{2}amento/u' => 'Orçamento',
        '/or[^\x00-\x7F]{2}amento/u' => 'orçamento',
        
        // Histórico
        '/Hist\xEF\xBF\xBD{2}rico/u' => 'Histórico',
        '/hist\xEF\xBF\xBD{2}rico/u' => 'histórico',
        '/Hist[^\x00-\x7F]{2}rico/u' => 'Histórico',
        '/hist[^\x00-\x7F]{2}rico/u' => 'histórico',
        
        // Outras palavras comuns
        '/m\xEF\xBF\xBD{2}tricas/u' => 'métricas',
        '/M\xEF\xBF\xBD{2}tricas/u' => 'Métricas',
        '/necess\xEF\xBF\xBD{2}rio/u' => 'necessário',
        '/Necess\xEF\xBF\xBD{2}rio/u' => 'Necessário',
        '/Anima\xEF\xBF\xBD{3,4}o/u' => 'Animação',
        '/anima\xEF\xBF\xBD{3,4}o/u' => 'animação',
        '/conte\xEF\xBF\xBD{2}do/u' => 'conteúdo',
        '/Conte\xEF\xBF\xBD{2}do/u' => 'Conteúdo',
        '/situa\xEF\xBF\xBD{2}es/u' => 'situações',
        '/Situa\xEF\xBF\xBD{2}es/u' => 'Situações',
        '/impress\xEF\xBF\xBD{2}o/u' => 'impressão',
        '/Impress\xEF\xBF\xBD{2}o/u' => 'Impressão',
        '/p\xEF\xBF\xBD{2}gina/u' => 'página',
        '/P\xEF\xBF\xBD{2}gina/u' => 'Página',
        '/T\xEF\xBF\xBD{2}tulo/u' => 'Título',
        '/t\xEF\xBF\xBD{2}tulo/u' => 'título',
        '/A\xEF\xBF\xBD{3,4}es/u' => 'Ações',
        '/a\xEF\xBF\xBD{3,4}es/u' => 'ações',
        '/A\xEF\xBF\xBD{3,4}o/u' => 'Ação',
        '/a\xEF\xBF\xBD{3,4}o/u' => 'ação',
        '/Vis\xEF\xBF\xBD{2}o/u' => 'Visão',
        '/vis\xEF\xBF\xBD{2}o/u' => 'visão',
        '/conclu\xEF\xBF\xBD{2}do/u' => 'concluído',
        '/Conclu\xEF\xBF\xBD{2}do/u' => 'Concluído',
        '/Observa\xEF\xBF\xBD{3,4}es/u' => 'Observações',
        '/observa\xEF\xBF\xBD{3,4}es/u' => 'observações',
        '/Observa\xEF\xBF\xBD{3,4}o/u' => 'Observação',
        '/observa\xEF\xBF\xBD{3,4}o/u' => 'observação',
        '/Usu\xEF\xBF\xBD{2}rio/u' => 'Usuário',
        '/usu\xEF\xBF\xBD{2}rio/u' => 'usuário',
        '/Altera\xEF\xBF\xBD{3,4}o/u' => 'Alteração',
        '/altera\xEF\xBF\xBD{3,4}o/u' => 'alteração',
        '/tempor\xEF\xBF\xBD{2}rio/u' => 'temporário',
        '/Tempor\xEF\xBF\xBD{2}rio/u' => 'Temporário',
        '/cabe\xEF\xBF\xBD{2}alho/u' => 'cabeçalho',
        '/Cabe\xEF\xBF\xBD{2}alho/u' => 'Cabeçalho',
        '/ap\xEF\xBF\xBD{2}s/u' => 'após',
        '/Ap\xEF\xBF\xBD{2}s/u' => 'Após',
        '/refer\xEF\xBF\xBD{2}ncia/u' => 'referência',
        '/Refer\xEF\xBF\xBD{2}ncia/u' => 'Referência',
        '/desnecess\xEF\xBF\xBD{2}rio/u' => 'desnecessário',
        '/Desnecess\xEF\xBF\xBD{2}rio/u' => 'Desnecessário',
        '/aprova\xEF\xBF\xBD{3,4}o/u' => 'aprovação',
        '/Aprova\xEF\xBF\xBD{3,4}o/u' => 'Aprovação',
        '/dispon\xEF\xBF\xBD{2}veis/u' => 'disponíveis',
        '/Dispon\xEF\xBF\xBD{2}veis/u' => 'Disponíveis',
        '/est\xEF\xBF\xBD{2}/u' => 'está',
        '/Est\xEF\xBF\xBD{2}/u' => 'Está',
        '/Fun\xEF\xBF\xBD{3,4}es/u' => 'Funções',
        '/fun\xEF\xBF\xBD{3,4}es/u' => 'funções',
    ];
    
    foreach ($padroes as $padrao => $substituicao) {
        $conteudo = preg_replace($padrao, $substituicao, $conteudo);
    }
    
    // Substituições simples de strings
    $substituicoes = [
        "\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD" => 'ção',
        "\xEF\xBF\xBD\xEF\xBF\xBD" => 'ã',
        ' ��� ' => ' • ',
        "icon: '\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD'" => "icon: '📋'",
        "icon: '\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD'" => "icon: '🏭'",
    ];
    
    foreach ($substituicoes as $corrompido => $correto) {
        $conteudo = str_replace($corrompido, $correto, $conteudo);
    }
    
    if ($conteudo !== $original) {
        // Backup
        copy($arquivo, $arquivo . '.backup_' . date('YmdHis'));
        
        // Salvar
        return file_put_contents($arquivo, $conteudo);
    }
    
    return false;
}

// Buscar todos os arquivos PHP
$arquivos = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/../public')
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $arquivos[] = $file->getPathname();
    }
}

$corrigidos = 0;
$erros = 0;

echo "Verificando " . count($arquivos) . " arquivos PHP...\n\n";

foreach ($arquivos as $arquivo) {
    $relativo = str_replace(__DIR__ . '/../', '', $arquivo);
    
    if (detectarECorrigir($arquivo)) {
        echo "✅ Corrigido: $relativo\n";
        $corrigidos++;
    }
}

echo "\n════════════════════════════════════════════════\n";
echo "Resumo:\n";
echo "  ✅ Arquivos corrigidos: $corrigidos\n";
echo "════════════════════════════════════════════════\n";
