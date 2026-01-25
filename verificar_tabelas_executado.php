<?php
require 'app/config.php';

try {
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║     VERIFICAÇÃO COMPLETA PÓS-EXECUÇÃO                     ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    $referenciadas = [
        'fornecedores', 'cotacoes', 'cotacao_itens', 'contas_receber', 
        'contas_pagar', 'comissoes', 'metas_vendas', 'empresa', 
        'documentos_empresa'
    ];
    
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_type = 'BASE TABLE'
        AND table_name IN ('" . implode("', '", $referenciadas) . "')
        ORDER BY table_name
    ");
    $criadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $faltantes = array_diff($referenciadas, $criadas);
    
    echo "📊 RESULTADO DA VERIFICAÇÃO:\n";
    echo "   • Tabelas necessárias: " . count($referenciadas) . "\n";
    echo "   • Tabelas encontradas: " . count($criadas) . "\n";
    echo "   • Tabelas faltantes: " . count($faltantes) . "\n\n";
    
    if (!empty($criadas)) {
        echo "✅ TABELAS CRIADAS (" . count($criadas) . "):\n";
        foreach ($criadas as $t) {
            // Verificar estrutura básica
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
    
    if (!empty($faltantes)) {
        echo "⚠️  TABELAS AINDA FALTANTES (" . count($faltantes) . "):\n";
        foreach ($faltantes as $t) {
            echo "   ✗ $t\n";
        }
        echo "\n";
    } else {
        echo "🎉 SUCESSO! Todas as tabelas foram criadas!\n\n";
    }
    
    // Verificar estrutura detalhada de uma tabela criada
    if (in_array('fornecedores', $criadas)) {
        echo "🔍 DETALHES DA TABELA 'fornecedores':\n";
        $stmt = $pdo->query("
            SELECT column_name, data_type, character_maximum_length, is_nullable
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'fornecedores'
            ORDER BY ordinal_position
            LIMIT 5
        ");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            $len = $col['character_maximum_length'] ? "({$col['character_maximum_length']})" : '';
            echo "   • {$col['column_name']}: {$col['data_type']}$len\n";
        }
        echo "   ... (mostrando primeiras 5 colunas)\n\n";
    }
    
    // Verificar índices
    if (in_array('contas_receber', $criadas)) {
        echo "🔍 ÍNDICES DA TABELA 'contas_receber':\n";
        $stmt = $pdo->query("
            SELECT indexname, indexdef
            FROM pg_indexes 
            WHERE schemaname = 'public' AND tablename = 'contas_receber'
            ORDER BY indexname
            LIMIT 5
        ");
        $indices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indices as $idx) {
            echo "   • {$idx['indexname']}\n";
        }
        echo "   Total: " . count($indices) . " índices\n\n";
    }
    
    // Testar criação de uma tabela simples
    echo "🧪 TESTANDO PERMISSÕES...\n";
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS teste_verificacao (
                id SERIAL PRIMARY KEY,
                nome CHARACTER VARYING(100) NOT NULL
            )
        ");
        
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = 'public' AND table_name = 'teste_verificacao'
        ");
        $existe = $stmt->fetchColumn();
        
        if ($existe) {
            echo "   ✅ Permissões OK - Tabela de teste criada\n";
            $pdo->exec("DROP TABLE IF EXISTS teste_verificacao");
            echo "   ✅ Tabela de teste removida\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Erro ao criar tabela de teste: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
