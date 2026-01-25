<?php
require 'app/config.php';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║     EXECUTANDO SCRIPT SQL OTIMIZADO (MELHORADO)          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$sql_file = __DIR__ . '/scripts/criar_tabelas_faltantes_otimizado.sql';

if (!file_exists($sql_file)) {
    die("❌ Arquivo não encontrado: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Remover comentários primeiro
$lines = explode("\n", $sql_content);
$clean_lines = [];
foreach ($lines as $line) {
    // Remover comentários de linha (-- comentário)
    $line = preg_replace('/--.*$/', '', $line);
    $clean_lines[] = $line;
}
$sql_content = implode("\n", $clean_lines);

// Remover comentários de bloco
$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

// Dividir em comandos SQL de forma mais inteligente
// Usar regex para encontrar comandos completos
preg_match_all('/(CREATE\s+(TABLE|INDEX|OR\s+REPLACE\s+FUNCTION|TRIGGER).*?)(?=CREATE|ALTER|COMMENT|INSERT|$)/is', $sql_content, $matches);

$commands = [];
if (!empty($matches[1])) {
    $commands = $matches[1];
} else {
    // Fallback: dividir por ponto e vírgula
    $parts = explode(';', $sql_content);
    foreach ($parts as $part) {
        $part = trim($part);
        if (!empty($part) && strlen($part) > 10) {
            $commands[] = $part;
        }
    }
}

// Também pegar comandos ALTER, COMMENT, INSERT separadamente
preg_match_all('/(ALTER\s+TABLE.*?);/is', $sql_content, $alters);
if (!empty($alters[1])) {
    $commands = array_merge($commands, $alters[1]);
}

preg_match_all('/(COMMENT\s+ON.*?);/is', $sql_content, $comments);
if (!empty($comments[1])) {
    $commands = array_merge($commands, $comments[1]);
}

preg_match_all('/(INSERT\s+INTO.*?);/is', $sql_content, $inserts);
if (!empty($inserts[1])) {
    $commands = array_merge($commands, $inserts[1]);
}

echo "📋 Total de comandos encontrados: " . count($commands) . "\n\n";

$pdo->beginTransaction();

$tabelas_criadas = [];
$indices_criados = 0;
$funcoes_criadas = 0;
$triggers_criados = 0;
$erros = [];

foreach ($commands as $idx => $command) {
    $command = trim($command);
    if (empty($command) || strlen($command) < 10) continue;
    
    // Garantir que termina com ponto e vírgula
    if (substr($command, -1) !== ';') {
        $command .= ';';
    }
    
    try {
        // CREATE TABLE
        if (preg_match('/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $command, $matches)) {
            $tabela_nome = $matches[2];
            $pdo->exec($command);
            if (!in_array($tabela_nome, $tabelas_criadas)) {
                $tabelas_criadas[] = $tabela_nome;
                echo "   ✓ Tabela criada: $tabela_nome\n";
            }
        }
        // CREATE INDEX
        elseif (preg_match('/CREATE\s+(OR\s+REPLACE\s+)?(UNIQUE\s+)?INDEX/i', $command)) {
            $pdo->exec($command);
            $indices_criados++;
        }
        // CREATE FUNCTION
        elseif (preg_match('/CREATE\s+OR\s+REPLACE\s+FUNCTION/i', $command)) {
            $pdo->exec($command);
            $funcoes_criadas++;
        }
        // CREATE TRIGGER
        elseif (preg_match('/CREATE\s+TRIGGER/i', $command)) {
            $pdo->exec($command);
            $triggers_criados++;
        }
        // ALTER TABLE
        elseif (preg_match('/ALTER\s+TABLE/i', $command)) {
            $pdo->exec($command);
        }
        // COMMENT
        elseif (preg_match('/COMMENT\s+ON/i', $command)) {
            $pdo->exec($command);
        }
        // INSERT
        elseif (preg_match('/INSERT\s+INTO/i', $command)) {
            $pdo->exec($command);
        }
    } catch (PDOException $e) {
        $error_msg = $e->getMessage();
        
        // Ignorar erros de "já existe"
        if (strpos($error_msg, 'already exists') !== false ||
            strpos($error_msg, 'duplicate') !== false ||
            strpos($error_msg, 'violates unique constraint') !== false ||
            strpos($error_msg, 'relation') !== false && strpos($error_msg, 'already exists') !== false) {
            // Ignorar silenciosamente
            continue;
        }
        
        // Log outros erros
        $erros[] = [
            'comando' => substr($command, 0, 80) . '...',
            'erro' => $error_msg
        ];
    }
}

$pdo->commit();

echo "\n✅ Script executado!\n";
echo "   • Tabelas criadas: " . count($tabelas_criadas) . "\n";
echo "   • Índices criados: $indices_criados\n";
echo "   • Funções criadas: $funcoes_criados\n";
echo "   • Triggers criados: $triggers_criados\n";
if (!empty($erros)) {
    echo "   • Avisos: " . count($erros) . " comandos com erros\n";
}
echo "\n";

// Verificar tabelas criadas
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
$tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "📊 VERIFICAÇÃO FINAL:\n";
echo "   • Tabelas encontradas no banco: " . count($tabelas) . " de 9\n\n";

if (!empty($tabelas)) {
    echo "✅ TABELAS CRIADAS:\n";
    foreach ($tabelas as $t) {
        // Verificar estrutura
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
], $tabelas);

if (!empty($faltantes)) {
    echo "⚠️  TABELAS AINDA FALTANTES:\n";
    foreach ($faltantes as $t) {
        echo "   ✗ $t\n";
    }
    echo "\n";
    echo "💡 DICA: Execute o SQL manualmente usando pgAdmin ou psql\n";
} else {
    echo "🎉 SUCESSO! Todas as 9 tabelas foram criadas!\n\n";
}
