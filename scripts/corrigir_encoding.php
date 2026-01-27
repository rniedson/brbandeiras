<?php
/**
 * Script para corrigir caracteres corrompidos em arquivos PHP
 */

$arquivos = [
    'public/api/calendario_pedidos.php',
    'public/arte/arte_finalista.php',
    'public/arte/arte_finalista_detalhes.php',
    'public/arte/arte_finalista_upload.php',
    'public/arte/arte_upload.php',
    'public/clientes/cliente_detalhes.php',
    'public/clientes/clientes.php',
    'public/clientes/clientes_processar_importacao.php',
    'public/clientes/clientes_processar_lote.php',
    'public/clientes/clientes_template_csv.php',
    'public/dashboard/check_updates_debug.php',
    'public/dashboard/check_updates_simple.php',
    'public/dashboard/dashboard_arte_finalista.php',
    'public/dashboard/dashboard_gestor.php',
    'public/dashboard/dashboard_producao.php',
    'public/dashboard/dashboard_security.php',
    'public/dashboard/dashboard_vendedor.php',
    'public/estoque/movimentacao_nova.php',
    'public/orcamentos/orcamento.php',
    'public/orcamentos/orcamento_aprovar.php',
    'public/orcamentos/orcamento_detalhes.php',
    'public/orcamentos/orcamento_pdf.php',
    'public/orcamentos/orcamento_reprovar.php',
    'public/pedidos/pedido_atualizar.php',
    'public/pedidos/pedido_detalhes.php',
    'public/pedidos/pedido_editar.php',
    'public/pedidos/pedido_novo.php',
    'public/pedidos/pedido_salvar.php',
    'public/pedidos/pedido_status.php',
    'public/pedidos/pedido_upload_ajax.php',
];

$substituicoes = [
    // Palavras comuns
    'Fun����o' => 'Função',
    'fun����o' => 'função',
    'n��o' => 'não',
    'informa����es' => 'informações',
    'Informa����es' => 'Informações',
    'vers��es' => 'versões',
    'Vers��es' => 'Versões',
    'vers��o' => 'versão',
    'Vers��o' => 'Versão',
    'Produ����o' => 'Produção',
    'produ����o' => 'produção',
    'Or��amento' => 'Orçamento',
    'or��amento' => 'orçamento',
    'Hist��rico' => 'Histórico',
    'hist��rico' => 'histórico',
    'm��tricas' => 'métricas',
    'M��tricas' => 'Métricas',
    'necess��rio' => 'necessário',
    'Necess��rio' => 'Necessário',
    'Anima����o' => 'Animação',
    'anima����o' => 'animação',
    'flu��da' => 'fluída',
    'Flu��da' => 'Fluída',
    'conte��do' => 'conteúdo',
    'Conte��do' => 'Conteúdo',
    'situa����es' => 'situações',
    'Situa����es' => 'Situações',
    'impress��o' => 'impressão',
    'Impress��o' => 'Impressão',
    'imprim��veis' => 'imprimíveis',
    'Imprim��veis' => 'Imprimíveis',
    'p��gina' => 'página',
    'P��gina' => 'Página',
    'T��tulo' => 'Título',
    't��tulo' => 'título',
    't��tulos' => 'títulos',
    'A����es' => 'Ações',
    'a����es' => 'ações',
    'A����o' => 'Ação',
    'a����o' => 'ação',
    'Vis��o' => 'Visão',
    'vis��o' => 'visão',
    'conclu��do' => 'concluído',
    'Conclu��do' => 'Concluído',
    'C��d' => 'Cód',
    'Observa����es' => 'Observações',
    'observa����es' => 'observações',
    'Observa����o' => 'Observação',
    'observa����o' => 'observação',
    'Compara����o' => 'Comparação',
    'compara����o' => 'comparação',
    'Usu��rio' => 'Usuário',
    'usu��rio' => 'usuário',
    'Altera����o' => 'Alteração',
    'altera����o' => 'alteração',
    'tempor��rio' => 'temporário',
    'Tempor��rio' => 'Temporário',
    'cabe��alho' => 'cabeçalho',
    'Cabe��alho' => 'Cabeçalho',
    'ap��s' => 'após',
    'Ap��s' => 'Após',
    'refer��ncia' => 'referência',
    'Refer��ncia' => 'Referência',
    'desnecess��rio' => 'desnecessário',
    'Desnecess��rio' => 'Desnecessário',
    'aprova����o' => 'aprovação',
    'Aprova����o' => 'Aprovação',
    'dispon��veis' => 'disponíveis',
    'Dispon��veis' => 'Disponíveis',
    'est��' => 'está',
    'Est��' => 'Está',
    'Fun����es' => 'Funções',
    'fun����es' => 'funções',
    'n��o ��' => 'não é',
    'n��o existe' => 'não existe',
    'n��o encontrado' => 'não encontrado',
    'n��o encontrada' => 'não encontrada',
    'n��o encontrados' => 'não encontrados',
    'n��o encontradas' => 'não encontradas',
    'n��o pode' => 'não pode',
    'n��o deve' => 'não deve',
    'n��o foi' => 'não foi',
    'n��o foram' => 'não foram',
    'n��o tem' => 'não tem',
    'n��o tem permiss' => 'não tem permissão',
    'n��o tem permiss��o' => 'não tem permissão',
    'n��o reconhecido' => 'não reconhecido',
    'n��o reconhecida' => 'não reconhecida',
    'n��o reconhecidos' => 'não reconhecidos',
    'n��o reconhecidas' => 'não reconhecidas',
    // Caracteres especiais
    '���' => '•',
    ' �� ' => ' • ',
    // Ícones/emojis corrompidos
    "icon: '����'" => "icon: '📋'",
    "icon: '������'" => "icon: '🏭'",
    "icon: '����'" => "icon: '📊'",
    "icon: '����'" => "icon: '📝'",
    "icon: '����'" => "icon: '📄'",
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
