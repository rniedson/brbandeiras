<?php
/**
 * Script para FORÇAR todos os arquivos PHP a estarem em UTF-8
 * Detecta encoding e converte se necessário
 */

function buscarArquivosPHP($diretorio) {
    $arquivos = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($diretorio, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $arquivos[] = $file->getPathname();
        }
    }
    
    return $arquivos;
}

function converterParaUTF8($caminho) {
    if (!file_exists($caminho)) {
        return ['status' => 'error', 'message' => 'Arquivo não encontrado'];
    }
    
    $conteudo = file_get_contents($caminho);
    $original = $conteudo;
    
    // Detectar encoding atual
    $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
    
    // Se não for UTF-8, converter
    if ($encoding && $encoding !== 'UTF-8') {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
    }
    
    // Remover BOM se existir
    if (substr($conteudo, 0, 3) === "\xEF\xBB\xBF") {
        $conteudo = substr($conteudo, 3);
    }
    
    // Aplicar correções de caracteres corrompidos conhecidos
    $correcoes = [
        'Funão' => 'Função',
        'funão' => 'função',
        'Funões' => 'Funções',
        'funões' => 'funções',
        'Atenão' => 'Atenção',
        'atenão' => 'atenção',
        'informaões' => 'informações',
        'Informaões' => 'Informações',
        'Produão' => 'Produção',
        'produão' => 'produção',
        'Animaão' => 'Animação',
        'animaão' => 'animação',
        'situaões' => 'situações',
        'Situaões' => 'Situações',
        'Aões' => 'Ações',
        'aões' => 'ações',
        'Observaões' => 'Observações',
        'observaões' => 'observações',
        'Comparaão' => 'Comparação',
        'comparaão' => 'comparação',
        'Alteraão' => 'Alteração',
        'alteraão' => 'alteração',
        'aprovaão' => 'aprovação',
        'Aprovaão' => 'Aprovação',
    ];
    
    foreach ($correcoes as $corrompido => $correto) {
        $conteudo = str_replace($corrompido, $correto, $conteudo);
    }
    
    // Verificar se houve mudanças
    if ($conteudo === $original && $encoding === 'UTF-8') {
        return ['status' => 'skip', 'message' => 'Já está em UTF-8'];
    }
    
    // Fazer backup
    $backup = $caminho . '.backup_utf8_' . date('YmdHis');
    copy($caminho, $backup);
    
    // Salvar em UTF-8 sem BOM
    if (file_put_contents($caminho, $conteudo) === false) {
        return ['status' => 'error', 'message' => 'Erro ao salvar'];
    }
    
    $mensagem = $encoding !== 'UTF-8' ? "Convertido de $encoding para UTF-8" : "Corrigido e salvo em UTF-8";
    
    return ['status' => 'success', 'message' => $mensagem, 'encoding' => $encoding, 'backup' => $backup];
}

$diretorioBase = __DIR__ . '/../public';

echo "🔍 Buscando arquivos PHP em: $diretorioBase\n";
$arquivos = buscarArquivosPHP($diretorioBase);

echo "📁 Encontrados " . count($arquivos) . " arquivos PHP\n\n";

$convertidos = 0;
$corrigidos = 0;
$erros = 0;
$pulados = 0;

foreach ($arquivos as $arquivo) {
    $relativo = str_replace(__DIR__ . '/../', '', $arquivo);
    $resultado = converterParaUTF8($arquivo);
    
    switch ($resultado['status']) {
        case 'success':
            if (isset($resultado['encoding']) && $resultado['encoding'] !== 'UTF-8') {
                echo "🔄 Convertido: $relativo ({$resultado['encoding']} → UTF-8)\n";
                $convertidos++;
            } else {
                echo "✅ Corrigido: $relativo\n";
                $corrigidos++;
            }
            break;
        case 'skip':
            $pulados++;
            break;
        case 'error':
            echo "❌ Erro em $relativo: {$resultado['message']}\n";
            $erros++;
            break;
    }
}

echo "\n════════════════════════════════════════════════\n";
echo "📊 RESUMO FINAL\n";
echo "════════════════════════════════════════════════\n";
echo "  🔄 Arquivos convertidos: $convertidos\n";
echo "  ✅ Arquivos corrigidos: $corrigidos\n";
echo "  ⏭️  Arquivos já em UTF-8: $pulados\n";
echo "  ❌ Erros: $erros\n";
echo "  📁 Total processado: " . count($arquivos) . "\n";
echo "════════════════════════════════════════════════\n";

if ($convertidos > 0 || $corrigidos > 0) {
    echo "\n💡 CAUSA DO PROBLEMA:\n";
    echo "   Os arquivos foram salvos com encoding incorreto (ISO-8859-1 ou Windows-1252)\n";
    echo "   em vez de UTF-8. Isso causa corrupção de caracteres acentuados.\n\n";
    echo "💡 SOLUÇÃO APLICADA:\n";
    echo "   - Conversão automática para UTF-8\n";
    echo "   - Correção de caracteres corrompidos conhecidos\n";
    echo "   - Remoção de BOM (Byte Order Mark)\n";
    echo "   - Backups criados com sufixo .backup_utf8_YYYYMMDDHHMMSS\n";
}
