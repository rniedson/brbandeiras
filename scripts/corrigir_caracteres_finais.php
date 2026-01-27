<?php
/**
 * Script para corrigir caracteres corrompidos finais encontrados
 */

$substituicoes = [
    // Função
    'Funão' => 'Função',
    'funão' => 'função',
    'Funões' => 'Funções',
    'funões' => 'funções',
    
    // Atenção
    'Atenão' => 'Atenção',
    'atenão' => 'atenção',
    
    // Outros padrões comuns que podem ter sido perdidos
    'informaões' => 'informações',
    'Informaões' => 'Informações',
    'versões' => 'versões', // já está correto, mas pode ter variações
    'Produão' => 'Produção',
    'produão' => 'produção',
    'necessário' => 'necessário', // já está correto
    'Animaão' => 'Animação',
    'animaão' => 'animação',
    'conteúdo' => 'conteúdo', // já está correto
    'situaões' => 'situações',
    'Situaões' => 'Situações',
    'impressão' => 'impressão', // já está correto
    'página' => 'página', // já está correto
    'Título' => 'Título', // já está correto
    'Aões' => 'Ações',
    'aões' => 'ações',
    'Visão' => 'Visão', // já está correto
    'concluído' => 'concluído', // já está correto
    'Observaões' => 'Observações',
    'observaões' => 'observações',
    'Comparaão' => 'Comparação',
    'comparaão' => 'comparação',
    'Alteraão' => 'Alteração',
    'alteraão' => 'alteração',
    'temporário' => 'temporário', // já está correto
    'cabeçalho' => 'cabeçalho', // já está correto
    'após' => 'após', // já está correto
    'referência' => 'referência', // já está correto
    'desnecessário' => 'desnecessário', // já está correto
    'aprovaão' => 'aprovação',
    'Aprovaão' => 'Aprovação',
    'disponíveis' => 'disponíveis', // já está correto
    'está' => 'está', // já está correto
];

$arquivos = [
    'public/dashboard/dashboard_gestor.php',
    'public/dashboard/dashboard_security.php',
    'public/pedidos/pedido_editar.php',
    'public/pedidos/pedido_status.php',
    'public/pedidos/pedido_novo.php',
    'public/pedidos/pedido_salvar.php',
    'public/pedidos/detalhes/pedido_detalhes_arte_finalista.php',
    'public/utils/view.php',
    'public/utils/download.php',
    'public/orcamentos/orcamento.php',
    'public/orcamentos/orcamento_pdf.php',
    'public/usuarios/usuarios.php',
    'public/clientes/clientes.php',
    'public/produtos/catalogo_produto_salvar.php',
    'public/produtos/catalogo_produto_atualizar.php',
];

$corrigidos = 0;
$erros = 0;

foreach ($arquivos as $arquivo) {
    $caminho = __DIR__ . '/../' . $arquivo;
    
    if (!file_exists($caminho)) {
        echo "⚠️  Arquivo não encontrado: $arquivo\n";
        continue;
    }
    
    $conteudo = file_get_contents($caminho);
    $conteudoOriginal = $conteudo;
    
    // Aplicar todas as substituições
    foreach ($substituicoes as $corrompido => $correto) {
        $conteudo = str_replace($corrompido, $correto, $conteudo);
    }
    
    // Verificar se houve mudanças
    if ($conteudo !== $conteudoOriginal) {
        // Fazer backup
        copy($caminho, $caminho . '.backup_' . date('YmdHis'));
        
        // Salvar arquivo corrigido
        if (file_put_contents($caminho, $conteudo)) {
            echo "✅ Corrigido: $arquivo\n";
            $corrigidos++;
        } else {
            echo "❌ Erro ao salvar: $arquivo\n";
            $erros++;
        }
    } else {
        echo "⏭️  Sem alterações: $arquivo\n";
    }
}

echo "\n════════════════════════════════════════════════\n";
echo "Resumo:\n";
echo "  ✅ Arquivos corrigidos: $corrigidos\n";
echo "  ❌ Erros: $erros\n";
echo "════════════════════════════════════════════════\n";
