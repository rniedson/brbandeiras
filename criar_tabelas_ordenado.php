<?php
require 'app/config.php';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║     CRIAÇÃO DE TABELAS - ORDENADO E SEM TRANSAÇÃO        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$sql_file = __DIR__ . '/scripts/criar_tabelas_faltantes_otimizado.sql';

if (!file_exists($sql_file)) {
    die("❌ Arquivo não encontrado: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Remover comentários
$sql_content = preg_replace('/--.*$/m', '', $sql_content);
$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

// Dividir por ponto e vírgula, mas manter comandos completos
$parts = explode(';', $sql_content);
$commands = [];

foreach ($parts as $part) {
    $part = trim($part);
    if (empty($part) || strlen($part) < 10) continue;
    
    // Classificar comandos por tipo
    if (preg_match('/CREATE\s+TABLE/i', $part)) {
        $commands[] = ['type' => 'TABLE', 'sql' => $part];
    } elseif (preg_match('/CREATE\s+(OR\s+REPLACE\s+)?FUNCTION/i', $part)) {
        $commands[] = ['type' => 'FUNCTION', 'sql' => $part];
    } elseif (preg_match('/CREATE\s+TRIGGER/i', $part)) {
        $commands[] = ['type' => 'TRIGGER', 'sql' => $part];
    } elseif (preg_match('/CREATE\s+(UNIQUE\s+)?INDEX/i', $part)) {
        $commands[] = ['type' => 'INDEX', 'sql' => $part];
    } elseif (preg_match('/ALTER\s+TABLE/i', $part)) {
        $commands[] = ['type' => 'ALTER', 'sql' => $part];
    } elseif (preg_match('/COMMENT\s+ON/i', $part)) {
        $commands[] = ['type' => 'COMMENT', 'sql' => $part];
    } elseif (preg_match('/INSERT\s+INTO/i', $part)) {
        $commands[] = ['type' => 'INSERT', 'sql' => $part];
    }
}

// Ordenar: primeiro FUNCTIONS, depois TABLES, depois INDEXES, depois TRIGGERS, depois ALTER, depois COMMENTS, depois INSERTS
$order = ['FUNCTION' => 1, 'TABLE' => 2, 'INDEX' => 3, 'TRIGGER' => 4, 'ALTER' => 5, 'COMMENT' => 6, 'INSERT' => 7];
usort($commands, function($a, $b) use ($order) {
    $orderA = $order[$a['type']] ?? 99;
    $orderB = $order[$b['type']] ?? 99;
    return $orderA <=> $orderB;
});

echo "📋 Total de comandos: " . count($commands) . "\n";
echo "   • Funções: " . count(array_filter($commands, fn($c) => $c['type'] === 'FUNCTION')) . "\n";
echo "   • Tabelas: " . count(array_filter($commands, fn($c) => $c['type'] === 'TABLE')) . "\n";
echo "   • Índices: " . count(array_filter($commands, fn($c) => $c['type'] === 'INDEX')) . "\n";
echo "   • Triggers: " . count(array_filter($commands, fn($c) => $c['type'] === 'TRIGGER')) . "\n";
echo "   • Outros: " . count(array_filter($commands, fn($c) => !in_array($c['type'], ['FUNCTION', 'TABLE', 'INDEX', 'TRIGGER']))) . "\n\n";

// Executar SEM transação (cada comando independente)
$tabelas_criadas = [];
$sucessos = 0;
$erros = 0;
$ignorados = 0;

foreach ($commands as $idx => $cmd) {
    $command = trim($cmd['sql']);
    if (empty($command)) continue;
    
    // Garantir que termina com ponto e vírgula
    if (substr($command, -1) !== ';') {
        $command .= ';';
    }
    
    try {
        $pdo->exec($command);
        $sucessos++;
        
        // Detectar criação de tabela
        if ($cmd['type'] === 'TABLE' && preg_match('/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $command, $matches)) {
            $tabela_nome = $matches[2];
            if (!in_array($tabela_nome, $tabelas_criadas)) {
                $tabelas_criadas[] = $tabela_nome;
                echo "   ✓ Tabela criada: $tabela_nome\n";
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
        // Mostrar apenas primeiros 10 erros
        if ($erros <= 10) {
            echo "   ⚠️  Erro [" . $cmd['type'] . "]: " . substr($error_msg, 0, 100) . "...\n";
        }
    }
}

echo "\n📊 RESUMO DA EXECUÇÃO:\n";
echo "   • Sucessos: $sucessos\n";
echo "   • Ignorados (já existem): $ignorados\n";
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
        
        $stmt3 = $pdo->query("
            SELECT COUNT(*) 
            FROM pg_indexes 
            WHERE schemaname = 'public' AND tablename = '$t'
        ");
        $idx_count = $stmt3->fetchColumn();
        
        echo "   ✓ $t ($col_count colunas, $idx_count índices)\n";
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
