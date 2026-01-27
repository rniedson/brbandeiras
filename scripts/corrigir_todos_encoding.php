<?php
/**
 * Script completo para corrigir TODOS os caracteres corrompidos em arquivos PHP
 * Detecta e corrige problemas de encoding UTF-8
 */

// Padrões de substituição completos
$substituicoes = [
    // Função (várias variações)
    'Funão' => 'Função',
    'funão' => 'função',
    'Funões' => 'Funções',
    'funões' => 'funções',
    'Fun����o' => 'Função',
    'fun����o' => 'função',
    'Fun����es' => 'Funções',
    'fun����es' => 'funções',
    
    // Atenção
    'Atenão' => 'Atenção',
    'atenão' => 'atenção',
    'Aten����o' => 'Atenção',
    'aten����o' => 'atenção',
    
    // Informações
    'informaões' => 'informações',
    'Informaões' => 'Informações',
    'informa����es' => 'informações',
    'Informa����es' => 'Informações',
    
    // Versões
    'versões' => 'versões', // pode estar correto mas verificar variações
    'Versões' => 'Versões',
    'vers����es' => 'versões',
    'Vers����es' => 'Versões',
    'vers����o' => 'versão',
    'Vers����o' => 'Versão',
    
    // Produção
    'Produão' => 'Produção',
    'produão' => 'produção',
    'Produ����o' => 'Produção',
    'produ����o' => 'produção',
    
    // Orçamento
    'Or��amento' => 'Orçamento',
    'or��amento' => 'orçamento',
    'Or����amento' => 'Orçamento',
    'or����amento' => 'orçamento',
    
    // Histórico
    'Hist��rico' => 'Histórico',
    'hist��rico' => 'histórico',
    'Hist����rico' => 'Histórico',
    'hist����rico' => 'histórico',
    
    // Métricas
    'm��tricas' => 'métricas',
    'M��tricas' => 'Métricas',
    'm����tricas' => 'métricas',
    'M����tricas' => 'Métricas',
    
    // Necessário
    'necess��rio' => 'necessário',
    'Necess��rio' => 'Necessário',
    'necess����rio' => 'necessário',
    'Necess����rio' => 'Necessário',
    
    // Animação
    'Animaão' => 'Animação',
    'animaão' => 'animação',
    'Anima����o' => 'Animação',
    'anima����o' => 'animação',
    
    // Conteúdo
    'conte��do' => 'conteúdo',
    'Conte��do' => 'Conteúdo',
    'conte����do' => 'conteúdo',
    'Conte����do' => 'Conteúdo',
    
    // Situações
    'situaões' => 'situações',
    'Situaões' => 'Situações',
    'situa����es' => 'situações',
    'Situa����es' => 'Situações',
    
    // Impressão
    'impress��o' => 'impressão',
    'Impress��o' => 'Impressão',
    'impress����o' => 'impressão',
    'Impress����o' => 'Impressão',
    
    // Imprimíveis
    'imprim��veis' => 'imprimíveis',
    'Imprim��veis' => 'Imprimíveis',
    'imprim����veis' => 'imprimíveis',
    'Imprim����veis' => 'Imprimíveis',
    
    // Página
    'p��gina' => 'página',
    'P��gina' => 'Página',
    'p����gina' => 'página',
    'P����gina' => 'Página',
    
    // Título
    't��tulo' => 'título',
    'T��tulo' => 'Título',
    't����tulo' => 'título',
    'T����tulo' => 'Título',
    't��tulos' => 'títulos',
    'T��tulos' => 'Títulos',
    
    // Ações
    'Aões' => 'Ações',
    'aões' => 'ações',
    'A����es' => 'Ações',
    'a����es' => 'ações',
    'A����o' => 'Ação',
    'a����o' => 'ação',
    
    // Visão
    'Vis��o' => 'Visão',
    'vis��o' => 'visão',
    'Vis����o' => 'Visão',
    'vis����o' => 'visão',
    
    // Concluído
    'conclu��do' => 'concluído',
    'Conclu��do' => 'Concluído',
    'conclu����do' => 'concluído',
    'Conclu����do' => 'Concluído',
    
    // Observações
    'Observaões' => 'Observações',
    'observaões' => 'observações',
    'Observa����es' => 'Observações',
    'observa����es' => 'observações',
    'Observa����o' => 'Observação',
    'observa����o' => 'observação',
    
    // Comparação
    'Comparaão' => 'Comparação',
    'comparaão' => 'comparação',
    'Compara����o' => 'Comparação',
    'compara����o' => 'comparação',
    
    // Usuário
    'Usu��rio' => 'Usuário',
    'usu��rio' => 'usuário',
    'Usu����rio' => 'Usuário',
    'usu����rio' => 'usuário',
    
    // Alteração
    'Alteraão' => 'Alteração',
    'alteraão' => 'alteração',
    'Altera����o' => 'Alteração',
    'altera����o' => 'alteração',
    
    // Temporário
    'tempor��rio' => 'temporário',
    'Tempor��rio' => 'Temporário',
    'tempor����rio' => 'temporário',
    'Tempor����rio' => 'Temporário',
    
    // Cabeçalho
    'cabe��alho' => 'cabeçalho',
    'Cabe��alho' => 'Cabeçalho',
    'cabe����alho' => 'cabeçalho',
    'Cabe����alho' => 'Cabeçalho',
    
    // Após
    'ap��s' => 'após',
    'Ap��s' => 'Após',
    'ap����s' => 'após',
    'Ap����s' => 'Após',
    
    // Referência
    'refer��ncia' => 'referência',
    'Refer��ncia' => 'Referência',
    'refer����ncia' => 'referência',
    'Refer����ncia' => 'Referência',
    
    // Desnecessário
    'desnecess��rio' => 'desnecessário',
    'Desnecess��rio' => 'Desnecessário',
    'desnecess����rio' => 'desnecessário',
    'Desnecess����rio' => 'Desnecessário',
    
    // Aprovação
    'aprovaão' => 'aprovação',
    'Aprovaão' => 'Aprovação',
    'aprova����o' => 'aprovação',
    'Aprova����o' => 'Aprovação',
    
    // Disponíveis
    'dispon��veis' => 'disponíveis',
    'Dispon��veis' => 'Disponíveis',
    'dispon����veis' => 'disponíveis',
    'Dispon����veis' => 'Disponíveis',
    
    // Está
    'est��' => 'está',
    'Est��' => 'Está',
    'est����' => 'está',
    'Est����' => 'Está',
    
    // Código
    'C��d' => 'Cód',
    'c��d' => 'cód',
    'C����d' => 'Cód',
    'c����d' => 'cód',
    
    // Fluída
    'flu��da' => 'fluída',
    'Flu��da' => 'Fluída',
    'flu����da' => 'fluída',
    'Flu����da' => 'Fluída',
    
    // Caracteres de substituição Unicode (U+FFFD)
    "\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD\xEF\xBF\xBD" => 'ção',
    "\xEF\xBF\xBD\xEF\xBF\xBD" => 'ã',
    "\xEF\xBF\xBD" => '', // Remover caracteres de substituição isolados
];

// Buscar todos os arquivos PHP recursivamente
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

// Função para corrigir arquivo
function corrigirArquivo($caminho, $substituicoes) {
    if (!file_exists($caminho)) {
        return ['status' => 'error', 'message' => 'Arquivo não encontrado'];
    }
    
    // Ler arquivo
    $conteudo = file_get_contents($caminho);
    $original = $conteudo;
    
    // Aplicar todas as substituições
    foreach ($substituicoes as $corrompido => $correto) {
        $conteudo = str_replace($corrompido, $correto, $conteudo);
    }
    
    // Verificar se houve mudanças
    if ($conteudo === $original) {
        return ['status' => 'skip', 'message' => 'Sem alterações'];
    }
    
    // Fazer backup
    $backup = $caminho . '.backup_' . date('YmdHis');
    if (!copy($caminho, $backup)) {
        return ['status' => 'error', 'message' => 'Erro ao criar backup'];
    }
    
    // Salvar arquivo corrigido em UTF-8
    if (file_put_contents($caminho, $conteudo) === false) {
        return ['status' => 'error', 'message' => 'Erro ao salvar arquivo'];
    }
    
    // Garantir que o arquivo está em UTF-8
    $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
        file_put_contents($caminho, $conteudo);
    }
    
    return ['status' => 'success', 'message' => 'Corrigido', 'backup' => $backup];
}

// Diretório base
$diretorioBase = __DIR__ . '/../public';

echo "🔍 Buscando arquivos PHP em: $diretorioBase\n";
$arquivos = buscarArquivosPHP($diretorioBase);

echo "📁 Encontrados " . count($arquivos) . " arquivos PHP\n\n";

$corrigidos = 0;
$erros = 0;
$pulados = 0;

foreach ($arquivos as $arquivo) {
    $relativo = str_replace(__DIR__ . '/../', '', $arquivo);
    $resultado = corrigirArquivo($arquivo, $substituicoes);
    
    switch ($resultado['status']) {
        case 'success':
            echo "✅ Corrigido: $relativo\n";
            $corrigidos++;
            break;
        case 'skip':
            echo "⏭️  Sem alterações: $relativo\n";
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
echo "  ✅ Arquivos corrigidos: $corrigidos\n";
echo "  ⏭️  Arquivos sem alterações: $pulados\n";
echo "  ❌ Erros: $erros\n";
echo "  📁 Total de arquivos processados: " . count($arquivos) . "\n";
echo "════════════════════════════════════════════════\n";

if ($corrigidos > 0) {
    echo "\n💡 Dica: Backups foram criados com sufixo .backup_YYYYMMDDHHMMSS\n";
    echo "   Você pode removê-los após verificar que tudo está funcionando.\n";
}
