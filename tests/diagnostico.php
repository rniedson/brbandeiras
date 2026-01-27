<?php
/**
 * VERIFICAÇÃO E CORREÇÃO DO BANCO DE DADOS
 * BR Bandeiras - Sistema de Gestão
 */

require_once '../app/config.php';

// Configurar para mostrar todos os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação e Correção do Banco de Dados</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1a1a1a; color: #00ff00; }
        h1 { color: #ffff00; }
        h2 { color: #00ffff; margin-top: 30px; }
        .success { color: #00ff00; }
        .error { color: #ff0000; }
        .warning { color: #ffaa00; }
        .info { color: #aaaaff; }
        pre { background: #0a0a0a; padding: 10px; border: 1px solid #333; overflow-x: auto; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #333; padding: 5px 10px; text-align: left; }
        th { background: #333; color: #fff; }
        .fix-button { 
            background: #ffaa00; 
            color: #000; 
            padding: 10px 20px; 
            border: none; 
            cursor: pointer; 
            font-weight: bold;
            margin: 10px 0;
        }
        .fix-button:hover { background: #ffcc00; }
    </style>
</head>
<body>

<h1>🔧 VERIFICAÇÃO E CORREÇÃO DO BANCO DE DADOS</h1>

<?php

$errors = [];
$warnings = [];
$success = [];

// ===========================================
// 1. VERIFICAR ESTRUTURA DAS TABELAS
// ===========================================
echo "<h2>1. VERIFICANDO ESTRUTURA DAS TABELAS</h2>";

// Verificar tabela PEDIDOS
echo "<h3>Tabela: pedidos</h3>";
$stmt = $pdo->query("
    SELECT column_name, data_type, character_maximum_length 
    FROM information_schema.columns 
    WHERE table_name = 'pedidos' 
    ORDER BY ordinal_position
");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$required_columns = [
    'forma_pagamento' => ['type' => 'character varying', 'length' => 50],
    'condicoes_pagamento' => ['type' => 'character varying', 'length' => 255]
];

echo "<table>";
echo "<tr><th>Coluna</th><th>Tipo</th><th>Tamanho</th><th>Status</th></tr>";

$pedidos_needs_fix = false;
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['column_name']}</td>";
    echo "<td>{$col['data_type']}</td>";
    echo "<td>{$col['character_maximum_length']}</td>";
    
    if (isset($required_columns[$col['column_name']])) {
        unset($required_columns[$col['column_name']]);
        echo "<td class='success'>✓ OK</td>";
    } else {
        echo "<td>-</td>";
    }
    echo "</tr>";
}

foreach ($required_columns as $col_name => $col_info) {
    echo "<tr>";
    echo "<td>{$col_name}</td>";
    echo "<td>{$col_info['type']}</td>";
    echo "<td>{$col_info['length']}</td>";
    echo "<td class='error'>✗ FALTANDO</td>";
    echo "</tr>";
    $pedidos_needs_fix = true;
    $errors[] = "Coluna {$col_name} faltando na tabela pedidos";
}
echo "</table>";

// Verificar tabela CLIENTES
echo "<h3>Tabela: clientes</h3>";
$stmt = $pdo->query("
    SELECT column_name, data_type, character_maximum_length 
    FROM information_schema.columns 
    WHERE table_name = 'clientes' 
    ORDER BY ordinal_position
");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Coluna</th><th>Tipo</th><th>Tamanho</th><th>Status</th></tr>";

$clientes_needs_fix = false;
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['column_name']}</td>";
    echo "<td>{$col['data_type']}</td>";
    echo "<td>{$col['character_maximum_length']}</td>";
    
    // Verificar problema específico com estado
    if ($col['column_name'] == 'estado' && $col['character_maximum_length'] > 2) {
        echo "<td class='error'>✗ TAMANHO INCORRETO (deve ser 2)</td>";
        $clientes_needs_fix = true;
        $errors[] = "Coluna estado com tamanho incorreto ({$col['character_maximum_length']} ao invés de 2)";
    } else {
        echo "<td class='success'>✓</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Verificar tabela ARTE_VERSOES
echo "<h3>Tabela: arte_versoes</h3>";
$stmt = $pdo->query("
    SELECT EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_name = 'arte_versoes'
    ) as existe
");
$exists = $stmt->fetch()['existe'];

if ($exists) {
    echo "<p class='success'>✓ Tabela existe</p>";
    
    // Mostrar estrutura
    $stmt = $pdo->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'arte_versoes' 
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Coluna</th><th>Tipo</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ TABELA NÃO EXISTE</p>";
    $errors[] = "Tabela arte_versoes não existe";
}

// ===========================================
// 2. APLICAR CORREÇÕES
// ===========================================
if (isset($_POST['apply_fixes'])) {
    echo "<h2>2. APLICANDO CORREÇÕES</h2>";
    
    $fixes_applied = 0;
    $fixes_failed = 0;
    
    // Corrigir tabela PEDIDOS
    if ($pedidos_needs_fix) {
        echo "<h3>Corrigindo tabela pedidos...</h3>";
        
        $fixes = [
            "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(50)",
            "ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS condicoes_pagamento VARCHAR(255)"
        ];
        
        foreach ($fixes as $sql) {
            try {
                $pdo->exec($sql);
                echo "<p class='success'>✓ Executado: " . htmlspecialchars($sql) . "</p>";
                $fixes_applied++;
            } catch (PDOException $e) {
                echo "<p class='error'>✗ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
                $fixes_failed++;
            }
        }
    }
    
    // Corrigir tabela CLIENTES (estado)
    if ($clientes_needs_fix) {
        echo "<h3>Corrigindo coluna estado em clientes...</h3>";
        
        try {
            // Processo de correção do tamanho da coluna
            $pdo->beginTransaction();
            
            // Criar coluna temporária
            $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS estado_temp VARCHAR(100)");
            
            // Copiar dados
            $pdo->exec("UPDATE clientes SET estado_temp = estado WHERE estado IS NOT NULL");
            
            // Remover coluna antiga
            $pdo->exec("ALTER TABLE clientes DROP COLUMN IF EXISTS estado");
            
            // Criar coluna nova com tamanho correto
            $pdo->exec("ALTER TABLE clientes ADD COLUMN estado VARCHAR(2)");
            
            // Restaurar dados (limitando a 2 caracteres)
            $pdo->exec("UPDATE clientes SET estado = LEFT(estado_temp, 2) WHERE estado_temp IS NOT NULL");
            
            // Remover coluna temporária
            $pdo->exec("ALTER TABLE clientes DROP COLUMN IF EXISTS estado_temp");
            
            $pdo->commit();
            echo "<p class='success'>✓ Coluna estado corrigida para VARCHAR(2)</p>";
            $fixes_applied++;
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<p class='error'>✗ Erro ao corrigir estado: " . htmlspecialchars($e->getMessage()) . "</p>";
            $fixes_failed++;
        }
    }
    
    // Criar tabela ARTE_VERSOES se não existir
    if (!$exists) {
        echo "<h3>Criando tabela arte_versoes...</h3>";
        
        $sql = "CREATE TABLE IF NOT EXISTS arte_versoes (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
            versao INTEGER NOT NULL DEFAULT 1,
            arquivo_nome VARCHAR(255) NOT NULL,
            arquivo_caminho VARCHAR(500) NOT NULL,
            aprovada BOOLEAN DEFAULT false,
            reprovada BOOLEAN DEFAULT false,
            comentario_arte TEXT,
            comentario_cliente TEXT,
            usuario_id INTEGER REFERENCES usuarios(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(pedido_id, versao)
        )";
        
        try {
            $pdo->exec($sql);
            echo "<p class='success'>✓ Tabela arte_versoes criada</p>";
            $fixes_applied++;
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
            $fixes_failed++;
        }
    }
    
    // Criar/corrigir tabela PEDIDO_ARTE
    echo "<h3>Verificando tabela pedido_arte...</h3>";
    
    try {
        // Verificar se existe pedido_artes (plural)
        $stmt = $pdo->query("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.tables 
                WHERE table_name = 'pedido_artes'
            ) as existe
        ");
        $plural_exists = $stmt->fetch()['existe'];
        
        if ($plural_exists) {
            // Migrar de pedido_artes para pedido_arte
            $pdo->exec("CREATE TABLE IF NOT EXISTS pedido_arte (
                id SERIAL PRIMARY KEY,
                pedido_id INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
                arte_finalista_id INTEGER REFERENCES usuarios(id),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(pedido_id)
            )");
            
            $pdo->exec("INSERT INTO pedido_arte (pedido_id, created_at, updated_at)
                        SELECT DISTINCT pedido_id, created_at, updated_at
                        FROM pedido_artes
                        ON CONFLICT (pedido_id) DO NOTHING");
            
            $pdo->exec("DROP TABLE pedido_artes CASCADE");
            
            echo "<p class='success'>✓ Tabela pedido_artes migrada para pedido_arte</p>";
            $fixes_applied++;
        } else {
            // Criar tabela pedido_arte se não existir
            $pdo->exec("CREATE TABLE IF NOT EXISTS pedido_arte (
                id SERIAL PRIMARY KEY,
                pedido_id INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
                arte_finalista_id INTEGER REFERENCES usuarios(id),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(pedido_id)
            )");
            echo "<p class='success'>✓ Tabela pedido_arte verificada/criada</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro com pedido_arte: " . htmlspecialchars($e->getMessage()) . "</p>";
        $fixes_failed++;
    }
    
    // Adicionar coluna caminho_arquivo em pedido_arquivos
    echo "<h3>Corrigindo tabela pedido_arquivos...</h3>";
    
    try {
        $pdo->exec("ALTER TABLE pedido_arquivos ADD COLUMN IF NOT EXISTS caminho_arquivo VARCHAR(500)");
        
        // Copiar dados se existir coluna caminho
        $stmt = $pdo->query("
            SELECT column_name FROM information_schema.columns 
            WHERE table_name = 'pedido_arquivos' AND column_name = 'caminho'
        ");
        if ($stmt->fetch()) {
            $pdo->exec("UPDATE pedido_arquivos SET caminho_arquivo = caminho 
                       WHERE caminho_arquivo IS NULL AND caminho IS NOT NULL");
            echo "<p class='success'>✓ Dados copiados de 'caminho' para 'caminho_arquivo'</p>";
        }
        
        echo "<p class='success'>✓ Coluna caminho_arquivo verificada</p>";
        $fixes_applied++;
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
        $fixes_failed++;
    }
    
    // Adicionar coluna usuario_id em pedido_arquivos
    try {
        $pdo->exec("ALTER TABLE pedido_arquivos ADD COLUMN IF NOT EXISTS usuario_id INTEGER REFERENCES usuarios(id)");
        echo "<p class='success'>✓ Coluna usuario_id verificada/criada</p>";
        $fixes_applied++;
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro ao criar coluna usuario_id: " . htmlspecialchars($e->getMessage()) . "</p>";
        $fixes_failed++;
    }
    
    // Resumo
    echo "<h2>RESUMO DAS CORREÇÕES</h2>";
    echo "<p class='info'>Correções aplicadas: <span class='success'>{$fixes_applied}</span></p>";
    echo "<p class='info'>Correções falhadas: <span class='error'>{$fixes_failed}</span></p>";
    
    if ($fixes_failed == 0) {
        echo "<h2 class='success'>✓ BANCO DE DADOS CORRIGIDO COM SUCESSO!</h2>";
        echo "<p>Agora você pode testar os arquivos do sistema.</p>";
    } else {
        echo "<h2 class='warning'>⚠ ALGUMAS CORREÇÕES FALHARAM</h2>";
        echo "<p>Verifique os erros acima e tente novamente.</p>";
    }
}

// ===========================================
// 3. MOSTRAR RESUMO E BOTÃO DE CORREÇÃO
// ===========================================
if (!isset($_POST['apply_fixes'])) {
    echo "<h2>RESUMO DOS PROBLEMAS ENCONTRADOS</h2>";
    
    if (count($errors) > 0) {
        echo "<h3 class='error'>Erros Encontrados:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li class='error'>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
        
        echo "<form method='POST'>";
        echo "<button type='submit' name='apply_fixes' class='fix-button'>";
        echo "🔧 APLICAR TODAS AS CORREÇÕES AUTOMATICAMENTE";
        echo "</button>";
        echo "</form>";
    } else {
        echo "<h3 class='success'>✓ Nenhum problema encontrado!</h3>";
        echo "<p>O banco de dados parece estar correto.</p>";
    }
}

// ===========================================
// 4. TESTE RÁPIDO DAS QUERIES
// ===========================================
echo "<h2>TESTE DE QUERIES</h2>";

try {
    // Teste 1: Query complexa de pedidos
    echo "<h3>Teste 1: Query de pedidos completos</h3>";
    $sql = "SELECT 
            p.*, 
            c.nome as cliente_nome,
            c.cidade,
            c.estado,
            p.forma_pagamento,
            p.condicoes_pagamento
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LIMIT 1";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<pre class='success'>✓ Query executada com sucesso</pre>";
        echo "<details><summary>Ver resultado</summary><pre>";
        print_r($result);
        echo "</pre></details>";
    } else {
        echo "<p class='warning'>⚠ Nenhum pedido encontrado para testar</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ Erro na query: " . htmlspecialchars($e->getMessage()) . "</p>";
}

try {
    // Teste 2: Verificar arte_versoes
    echo "<h3>Teste 2: Tabela arte_versoes</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM arte_versoes");
    $total = $stmt->fetch()['total'];
    echo "<p class='success'>✓ Tabela arte_versoes acessível (Total: {$total} registros)</p>";
} catch (PDOException $e) {
    echo "<p class='error'>✗ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>

<hr style="margin: 40px 0; border-color: #333;">
<p style="text-align: center; color: #666;">
    BR Bandeiras - Sistema de Gestão v1.0<br>
    Verificação executada em <?= date('d/m/Y H:i:s') ?>
</p>

</body>
</html>