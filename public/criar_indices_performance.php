<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

$mensagem = null;
$erro = null;
$indices_criados = [];
$erros_indices = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_indices'])) {
    try {
        // Ler o arquivo SQL
        $sql_file = __DIR__ . '/../scripts/criar_indices_performance.sql';
        
        if (!file_exists($sql_file)) {
            throw new Exception('Arquivo SQL não encontrado: ' . $sql_file);
        }
        
        $sql_content = file_get_contents($sql_file);
        
        // Remover comentários
        $sql_content = preg_replace('/--.*$/m', '', $sql_content);
        $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
        
        // Dividir por ponto e vírgula
        $commands = [];
        $parts = explode(';', $sql_content);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || strlen($part) < 10) continue;
            
            // Garantir que termina com ponto e vírgula
            if (substr($part, -1) !== ';') {
                $part .= ';';
            }
            
            if (preg_match('/CREATE\s+INDEX/i', $part)) {
                $commands[] = $part;
            }
        }
        
        echo "<div class='p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg mb-4'>";
        echo "<p class='text-blue-800 dark:text-blue-200'>Executando " . count($commands) . " comandos CREATE INDEX...</p>";
        echo "</div>";
        
        foreach ($commands as $command) {
            try {
                $pdo->exec($command);
                
                // Extrair nome do índice
                if (preg_match('/CREATE\s+INDEX\s+CONCURRENTLY\s+IF\s+NOT\s+EXISTS\s+(\w+)/i', $command, $matches) ||
                    preg_match('/CREATE\s+INDEX\s+CONCURRENTLY\s+(\w+)/i', $command, $matches)) {
                    $indice_nome = $matches[1];
                    if (!in_array($indice_nome, $indices_criados)) {
                        $indices_criados[] = $indice_nome;
                    }
                }
            } catch (PDOException $e) {
                $error_msg = $e->getMessage();
                
                // Ignorar erros de "já existe"
                if (strpos($error_msg, 'already exists') === false &&
                    strpos($error_msg, 'duplicate') === false) {
                    $erros_indices[] = [
                        'comando' => substr($command, 0, 100) . '...',
                        'erro' => $error_msg
                    ];
                    error_log("Erro ao criar índice: " . $error_msg);
                }
            }
        }
        
        if (!empty($indices_criados)) {
            $mensagem = 'Índices criados/verificados com sucesso: ' . count($indices_criados) . ' índices';
        } else {
            $mensagem = 'Todos os índices já existem ou foram criados anteriormente.';
        }
        
    } catch (Exception $e) {
        $erro = 'Erro ao criar índices: ' . $e->getMessage();
        error_log("Erro ao criar índices: " . $e->getMessage());
    }
}

// Verificar índices existentes
try {
    $stmt = $pdo->query("
        SELECT 
            tablename,
            indexname,
            pg_size_pretty(pg_relation_size(indexrelid)) as tamanho
        FROM pg_indexes
        WHERE schemaname = 'public'
          AND indexname LIKE 'idx_%'
        ORDER BY tablename, indexname
    ");
    $indices_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $indices_existentes = [];
}

$titulo = 'Criar Índices de Performance';
include '../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Criar Índices de Performance</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Crie índices otimizados para melhorar significativamente a performance das queries</p>
    </div>
    
    <?php if ($mensagem): ?>
    <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="text-green-800 dark:text-green-200"><?= htmlspecialchars($mensagem) ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span class="text-red-800 dark:text-red-200"><?= htmlspecialchars($erro) ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Índices Criados -->
    <?php if (!empty($indices_criados)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Índices Criados</h2>
        </div>
        <div class="p-6">
            <div class="space-y-2">
                <?php foreach ($indices_criados as $idx): ?>
                <div class="flex items-center p-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-green-800 dark:text-green-200 font-medium"><?= htmlspecialchars($idx) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Índices Existentes -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Índices de Performance Existentes</h2>
        </div>
        <div class="p-6">
            <?php if (!empty($indices_existentes)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tabela</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Índice</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tamanho</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($indices_existentes as $idx): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"><?= htmlspecialchars($idx['tablename']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-gray-100"><?= htmlspecialchars($idx['indexname']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($idx['tamanho']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-600 dark:text-gray-400">Nenhum índice de performance encontrado.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ação -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Criar Índices</h2>
        </div>
        <div class="p-6">
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Clique no botão abaixo para criar automaticamente todos os índices de performance.
                Os índices serão criados usando <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">CREATE INDEX CONCURRENTLY</code> para não bloquear as tabelas.
            </p>
            
            <form method="POST" onsubmit="return confirm('Deseja realmente criar os índices de performance? Esta operação pode levar alguns minutos.');">
                <button type="submit" name="criar_indices" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-database mr-2"></i>Criar Índices de Performance
                </button>
            </form>
            
            <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <strong>Nota:</strong> Se preferir criar manualmente, execute o arquivo SQL em 
                    <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">scripts/criar_indices_performance.sql</code>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include '../views/layouts/_footer.php'; ?>
