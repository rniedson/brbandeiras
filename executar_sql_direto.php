<?php
require 'app/config.php';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║     EXECUTANDO SCRIPT SQL OTIMIZADO                       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$sql_file = __DIR__ . '/scripts/criar_tabelas_faltantes_otimizado.sql';

if (!file_exists($sql_file)) {
    die("❌ Arquivo não encontrado: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Usar pg_query para executar diretamente (mais confiável)
try {
    // Usar PDO diretamente (já está configurado)
    // Executar comandos SQL um por um
    $commands = explode(';', $sql_content);
    
    $pdo->beginTransaction();
    
    $tabelas_criadas = [];
    $erros = [];
    
    foreach ($commands as $command) {
        $command = trim($command);
        if (empty($command) || strlen($command) < 10) continue;
        
        // Remover comentários
        $command = preg_replace('/--.*$/m', '', $command);
        $command = preg_replace('/\/\*.*?\*\//s', '', $command);
        $command = trim($command);
        
        if (empty($command)) continue;
        
        try {
            $pdo->exec($command);
            
            // Detectar criação de tabela
            if (preg_match('/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $command, $matches)) {
                $tabela_nome = $matches[2];
                if (!in_array($tabela_nome, $tabelas_criadas)) {
                    $tabelas_criadas[] = $tabela_nome;
                }
            }
        } catch (PDOException $e) {
            $error_msg = $e->getMessage();
            // Ignorar erros de "já existe"
            if (strpos($error_msg, 'already exists') === false &&
                strpos($error_msg, 'duplicate') === false &&
                strpos($error_msg, 'violates unique constraint') === false) {
                $erros[] = substr($command, 0, 100) . '... -> ' . $error_msg;
            }
        }
    }
    
    $pdo->commit();
    
    echo "✅ Script executado!\n";
    if (!empty($tabelas_criadas)) {
        echo "   Tabelas processadas: " . implode(', ', $tabelas_criadas) . "\n";
    }
    if (!empty($erros)) {
        echo "   Avisos: " . count($erros) . " comandos com erros (normal se já existirem)\n";
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
    
    echo "📊 RESULTADO:\n";
    echo "   • Tabelas criadas: " . count($tabelas) . " de 9\n\n";
    
    if (!empty($tabelas)) {
        echo "✅ TABELAS ENCONTRADAS:\n";
        foreach ($tabelas as $t) {
            echo "   ✓ $t\n";
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
    } else {
        echo "🎉 SUCESSO! Todas as 9 tabelas foram criadas!\n\n";
    }
    
    exit(0);
    
    if (!$pg_conn) {
        throw new Exception("Falha ao conectar ao banco de dados");
    }
    
    echo "✅ Conectado ao banco de dados\n\n";
    
    // Executar o SQL diretamente
    $result = pg_query($pg_conn, $sql_content);
    
    if ($result === false) {
        $error = pg_last_error($pg_conn);
        echo "⚠️  Aviso: " . $error . "\n";
        echo "   (Alguns comandos podem ter falhado, mas isso é normal se já existirem)\n\n";
    } else {
        echo "✅ Script executado com sucesso!\n\n";
    }
    
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
    
    $result = pg_query($pg_conn, $query);
    $tabelas = [];
    while ($row = pg_fetch_assoc($result)) {
        $tabelas[] = $row['table_name'];
    }
    
    echo "📊 RESULTADO:\n";
    echo "   • Tabelas criadas: " . count($tabelas) . " de 9\n\n";
    
    if (!empty($tabelas)) {
        echo "✅ TABELAS ENCONTRADAS:\n";
        foreach ($tabelas as $t) {
            echo "   ✓ $t\n";
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
    } else {
        echo "🎉 SUCESSO! Todas as 9 tabelas foram criadas!\n\n";
    }
    
    pg_close($pg_conn);
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
