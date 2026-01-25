<?php
require_once '../app/config.php';

echo "<h2>Verificação da Estrutura do Banco de Dados</h2>";

try {
    // Verificar tabelas
    echo "<h3>Tabelas existentes:</h3>";
    $tables = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<pre>" . print_r($tables, true) . "</pre>";
    
    // Verificar estrutura da tabela pedidos
    echo "<h3>Estrutura da tabela 'pedidos':</h3>";
    $columns = $pdo->query("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'pedidos' 
        AND table_schema = 'public'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Coluna</th><th>Tipo</th><th>Nullable</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['column_name']}</td>";
        echo "<td>{$col['data_type']}</td>";
        echo "<td>{$col['is_nullable']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Testar query básica
    echo "<h3>Teste de Query Básica:</h3>";
    $test = $pdo->query("SELECT COUNT(*) as total FROM pedidos")->fetch();
    echo "Total de pedidos no banco: " . $test['total'] . "<br>";
    
    $test2 = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE status = 'orcamento'")->fetch();
    echo "Total de orçamentos: " . $test2['total'] . "<br>";
    
    // Verificar se há algum pedido com problema
    echo "<h3>Verificando dados dos pedidos:</h3>";
    $pedidos = $pdo->query("
        SELECT id, numero, status, created_at, cliente_id, vendedor_id 
        FROM pedidos 
        WHERE status = 'orcamento' 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>" . print_r($pedidos, true) . "</pre>";
    
} catch (PDOException $e) {
    echo "<div style='color: red;'>";
    echo "Erro PDO: " . $e->getMessage() . "<br>";
    echo "Código: " . $e->getCode() . "<br>";
    echo "</div>";
}
?>