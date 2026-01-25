<?php
require 'app/config.php';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║     CRIAÇÃO DE TABELAS - EXECUÇÃO FINAL                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$sql_file = __DIR__ . '/scripts/criar_tabelas_faltantes_otimizado.sql';

if (!file_exists($sql_file)) {
    die("❌ Arquivo não encontrado: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Remover comentários
$sql_content = preg_replace('/--.*$/m', '', $sql_content);
$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

// Dividir por CREATE TABLE, CREATE INDEX, etc. usando lookahead
$pattern = '/(CREATE\s+(TABLE|INDEX|OR\s+REPLACE\s+FUNCTION|TRIGGER).*?)(?=CREATE\s+(TABLE|INDEX|OR\s+REPLACE\s+FUNCTION|TRIGGER)|ALTER\s+TABLE|COMMENT\s+ON|INSERT\s+INTO|$)/is';
preg_match_all($pattern, $sql_content, $create_matches);

$commands = [];
if (!empty($create_matches[1])) {
    $commands = $create_matches[1];
}

// Adicionar ALTER TABLE, COMMENT, INSERT separadamente
preg_match_all('/(ALTER\s+TABLE.*?);/is', $sql_content, $alters);
if (!empty($alters[1])) {
    foreach ($alters[1] as $alter) {
        $commands[] = trim($alter);
    }
}

preg_match_all('/(COMMENT\s+ON.*?);/is', $sql_content, $comments);
if (!empty($comments[1])) {
    foreach ($comments[1] as $comment) {
        $commands[] = trim($comment);
    }
}

preg_match_all('/(INSERT\s+INTO.*?);/is', $sql_content, $inserts);
if (!empty($inserts[1])) {
    foreach ($inserts[1] as $insert) {
        $commands[] = trim($insert);
    }
}

// Se não encontrou comandos, dividir por ponto e vírgula
if (empty($commands)) {
    $parts = explode(';', $sql_content);
    foreach ($parts as $part) {
        $part = trim($part);
        if (!empty($part) && strlen($part) > 20 && 
            (preg_match('/CREATE|ALTER|COMMENT|INSERT/i', $part))) {
            $commands[] = $part;
        }
    }
}

echo "📋 Comandos encontrados: " . count($commands) . "\n\n";

$pdo->beginTransaction();

$tabelas_criadas = [];
$sucessos = 0;
$erros = 0;
$ignorados = 0;

foreach ($commands as $idx => $command) {
    $command = trim($command);
    if (empty($command) || strlen($command) < 10) continue;
    
    // Garantir que termina com ponto e vírgula
    if (substr($command, -1) !== ';') {
        $command .= ';';
    }
    
    try {
        $pdo->exec($command);
        $sucessos++;
        
        // Detectar criação de tabela
        if (preg_match('/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $command, $matches)) {
            $tabela_nome = $matches[2];
            if (!in_array($tabela_nome, $tabelas_criadas)) {
                $tabelas_criadas[] = $tabela_nome;
                echo "   ✓ Tabela: $tabela_nome\n";
            }
        }
    } catch (PDOException $e) {
        $error_msg = $e->getMessage();
        
        // Ignorar erros de "já existe"
        if (strpos($error_msg, 'already exists') !== false ||
            strpos($error_msg, 'duplicate') !== false ||
            strpos($error_msg, 'violates unique constraint') !== false) {
            $ignorados++;
            continue;
        }
        
        $erros++;
        // Mostrar apenas primeiros 5 erros
        if ($erros <= 5) {
            echo "   ⚠️  Erro no comando " . ($idx + 1) . ": " . substr($error_msg, 0, 80) . "...\n";
        }
    }
}

$pdo->commit();

echo "\n📊 RESUMO:\n";
echo "   • Comandos executados com sucesso: $sucessos\n";
echo "   • Comandos ignorados (já existem): $ignorados\n";
echo "   • Erros: $erros\n";
echo "   • Tabelas criadas: " . count($tabelas_criadas) . "\n\n";

// Verificar tabelas no banco
$query = "
    SELECT table_name 
    FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_type = 'BASE TABLE'
    AND table_name IN ('fornecedores', 'cotacoes', 'cotacao_itens', 'contas_receber', 
                       'contas_pagar', 'comissoes', 'metas_vendas', 'empresa', 
                       'documentos_empresa')
    ORDER BY table_name
";

$stmt = $pdo->query($query);
$tabelas_verificadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "🔍 VERIFICAÇÃO FINAL:\n";
echo "   • Tabelas encontradas no banco: " . count($tabelas_verificadas) . " de 9\n\n";

if (!empty($tabelas_verificadas)) {
    echo "✅ TABELAS CRIADAS:\n";
    foreach ($tabelas_verificadas as $t) {
        $stmt2 = $pdo->query("
            SELECT COUNT(*) 
            FROM information_schema.columns 
            WHERE table_schema = 'public' AND table_name = '$t'
        ");
        $col_count = $stmt2->fetchColumn();
        echo "   ✓ $t ($col_count colunas)\n";
    }
    echo "\n";
}

$faltantes = array_diff([
    'fornecedores', 'cotacoes', 'cotacao_itens', 'contas_receber', 
    'contas_pagar', 'comissoes', 'metas_vendas', 'empresa', 
    'documentos_empresa'
], $tabelas_verificadas);

if (!empty($faltantes)) {
    echo "⚠️  TABELAS AINDA FALTANTES (" . count($faltantes) . "):\n";
    foreach ($faltantes as $t) {
        echo "   ✗ $t\n";
    }
    echo "\n";
} else {
    echo "🎉 SUCESSO! Todas as 9 tabelas foram criadas!\n\n";
}
