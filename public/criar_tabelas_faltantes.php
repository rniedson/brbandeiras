<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

$mensagem = null;
$erro = null;
$tabelas_criadas = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_tabelas'])) {
    try {
        // Ler o arquivo SQL otimizado
        $sql_file = __DIR__ . '/../scripts/criar_tabelas_faltantes_otimizado.sql';
        
        if (!file_exists($sql_file)) {
            throw new Exception('Arquivo SQL não encontrado: ' . $sql_file);
        }
        
        $sql_content = file_get_contents($sql_file);
        
        // Remover comentários de linha (-- comentário)
        $sql_content = preg_replace('/--.*$/m', '', $sql_content);
        
        // Remover comentários de bloco (/* comentário */)
        $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
        
        // Dividir em comandos individuais (separados por ;)
        // Usar regex mais robusta para dividir comandos SQL
        $commands = [];
        $current_command = '';
        $in_string = false;
        $string_char = '';
        
        for ($i = 0; $i < strlen($sql_content); $i++) {
            $char = $sql_content[$i];
            
            if (!$in_string && ($char === '"' || $char === "'")) {
                $in_string = true;
                $string_char = $char;
                $current_command .= $char;
            } elseif ($in_string && $char === $string_char) {
                // Verificar se não é escape
                if ($i == 0 || $sql_content[$i-1] !== '\\') {
                    $in_string = false;
                }
                $current_command .= $char;
            } elseif (!$in_string && $char === ';') {
                $cmd = trim($current_command);
                if (!empty($cmd)) {
                    $commands[] = $cmd;
                }
                $current_command = '';
            } else {
                $current_command .= $char;
            }
        }
        
        // Adicionar último comando se não terminou com ;
        if (!empty(trim($current_command))) {
            $commands[] = trim($current_command);
        }
        
        $pdo->beginTransaction();
        
        foreach ($commands as $command) {
            $command = trim($command);
            if (empty($command)) continue;
            
            // Extrair nome da tabela do comando CREATE TABLE
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(\w+)/i', $command, $matches) ||
                preg_match('/CREATE\s+TABLE\s+(\w+)/i', $command, $matches)) {
                $tabela_nome = $matches[1];
                
                try {
                    $pdo->exec($command);
                    $tabelas_criadas[] = $tabela_nome;
                } catch (PDOException $e) {
                    // Se a tabela já existe, ignorar
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'duplicate') === false) {
                        throw $e;
                    }
                }
            } elseif (preg_match('/CREATE\s+(OR\s+REPLACE\s+)?(UNIQUE\s+)?INDEX/i', $command)) {
                // Criar índices
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    // Ignorar se índice já existe
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'duplicate') === false) {
                        error_log("Erro ao criar índice: " . $e->getMessage());
                    }
                }
            } elseif (preg_match('/CREATE\s+OR\s+REPLACE\s+FUNCTION/i', $command)) {
                // Criar funções
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    error_log("Erro ao criar função: " . $e->getMessage());
                }
            } elseif (preg_match('/CREATE\s+TRIGGER/i', $command)) {
                // Criar triggers
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        error_log("Erro ao criar trigger: " . $e->getMessage());
                    }
                }
            } elseif (preg_match('/ALTER\s+TABLE/i', $command)) {
                // Adicionar constraints
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'duplicate') === false) {
                        error_log("Erro ao adicionar constraint: " . $e->getMessage());
                    }
                }
            } elseif (preg_match('/INSERT\s+INTO/i', $command)) {
                // Executar INSERTs
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    // Ignorar conflitos
                    if (strpos($e->getMessage(), 'duplicate key') === false &&
                        strpos($e->getMessage(), 'unique constraint') === false) {
                        error_log("Erro ao inserir dados: " . $e->getMessage());
                    }
                }
            } elseif (preg_match('/COMMENT\s+ON/i', $command)) {
                // Comentários
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    // Ignorar erros de comentários
                    error_log("Aviso ao criar comentário: " . $e->getMessage());
                }
            }
        }
        
        $pdo->commit();
        
        if (!empty($tabelas_criadas)) {
            $mensagem = 'Tabelas criadas com sucesso: ' . implode(', ', $tabelas_criadas);
        } else {
            $mensagem = 'Todas as tabelas já existem no banco de dados.';
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = 'Erro ao criar tabelas: ' . $e->getMessage();
        error_log("Erro ao criar tabelas: " . $e->getMessage());
    }
}

// Verificar tabelas existentes e faltantes
try {
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name");
    $tabelas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $tabelas_referenciadas = [
        'fornecedores', 'cotacoes', 'cotacao_itens', 'contas_receber', 
        'contas_pagar', 'comissoes', 'metas_vendas', 'empresa', 
        'documentos_empresa'
    ];
    
    $tabelas_faltantes = array_diff($tabelas_referenciadas, $tabelas_existentes);
    
} catch (PDOException $e) {
    $tabelas_existentes = [];
    $tabelas_faltantes = $tabelas_referenciadas;
}

$titulo = 'Criar Tabelas Faltantes';
include '../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Criar Tabelas Faltantes</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Crie as tabelas necessárias para o funcionamento completo do sistema</p>
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
    
    <!-- Status -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Status das Tabelas</h2>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Tabelas faltantes: <span class="font-semibold text-red-600"><?= count($tabelas_faltantes) ?></span>
                </div>
                <?php if (!empty($tabelas_faltantes)): ?>
                <div class="space-y-2 mt-4">
                    <?php foreach ($tabelas_faltantes as $tabela): ?>
                    <div class="flex items-center p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-red-800 dark:text-red-200 font-medium"><?= htmlspecialchars($tabela) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-green-800 dark:text-green-200">Todas as tabelas necessárias já existem!</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Ação -->
    <?php if (!empty($tabelas_faltantes)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Criar Tabelas</h2>
        </div>
        <div class="p-6">
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Clique no botão abaixo para criar automaticamente todas as tabelas faltantes.
                O script SQL será executado e as tabelas serão criadas com suas estruturas e índices.
            </p>
            
            <form method="POST" onsubmit="return confirm('Deseja realmente criar as tabelas faltantes? Esta ação não pode ser desfeita.');">
                <button type="submit" name="criar_tabelas" 
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                    <i class="fas fa-database mr-2"></i>Criar Tabelas Faltantes
                </button>
            </form>
            
            <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <strong>Nota:</strong> Se preferir criar manualmente, execute o arquivo SQL em 
                    <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">scripts/criar_tabelas_faltantes.sql</code>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../views/layouts/_footer.php'; ?>
