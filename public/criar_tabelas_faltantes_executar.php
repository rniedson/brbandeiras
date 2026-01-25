<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$tabelas_criadas = [];
$erros = [];

try {
    // Ler o arquivo SQL otimizado
    $sql_file = __DIR__ . '/../scripts/criar_tabelas_faltantes_otimizado.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception('Arquivo SQL não encontrado: ' . $sql_file);
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Remover comentários de linha (-- comentário)
    $lines = explode("\n", $sql_content);
    $clean_lines = [];
    foreach ($lines as $line) {
        // Remover comentários de linha
        $line = preg_replace('/--.*$/', '', $line);
        $clean_lines[] = $line;
    }
    $sql_content = implode("\n", $clean_lines);
    
    // Remover comentários de bloco (/* comentário */)
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
    
    // Dividir em comandos SQL (separados por ;)
    // Usar método mais simples e robusto
    $commands = [];
    $current = '';
    $in_string = false;
    $quote_char = '';
    
    for ($i = 0; $i < strlen($sql_content); $i++) {
        $char = $sql_content[$i];
        $next_char = ($i < strlen($sql_content) - 1) ? $sql_content[$i + 1] : '';
        
        if (!$in_string && ($char === '"' || $char === "'")) {
            $in_string = true;
            $quote_char = $char;
            $current .= $char;
        } elseif ($in_string && $char === $quote_char && $sql_content[$i-1] !== '\\') {
            $in_string = false;
            $quote_char = '';
            $current .= $char;
        } elseif (!$in_string && $char === ';') {
            $cmd = trim($current);
            if (!empty($cmd) && strlen($cmd) > 10) {
                $commands[] = $cmd;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }
    
    // Adicionar último comando se não terminou com ;
    if (!empty(trim($current))) {
        $commands[] = trim($current);
    }
    
    $pdo->beginTransaction();
    
    foreach ($commands as $idx => $command) {
        $command = trim($command);
        if (empty($command) || strlen($command) < 10) continue;
        
        try {
            // CREATE TABLE
            if (preg_match('/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $command, $matches)) {
                $tabela_nome = $matches[2];
                $pdo->exec($command);
                $tabelas_criadas[] = $tabela_nome;
            }
            // CREATE INDEX
            elseif (preg_match('/CREATE\s+(OR\s+REPLACE\s+)?(UNIQUE\s+)?INDEX/i', $command)) {
                $pdo->exec($command);
            }
            // CREATE FUNCTION
            elseif (preg_match('/CREATE\s+OR\s+REPLACE\s+FUNCTION/i', $command)) {
                $pdo->exec($command);
            }
            // CREATE TRIGGER
            elseif (preg_match('/CREATE\s+TRIGGER/i', $command)) {
                $pdo->exec($command);
            }
            // ALTER TABLE (constraints)
            elseif (preg_match('/ALTER\s+TABLE/i', $command)) {
                $pdo->exec($command);
            }
            // INSERT
            elseif (preg_match('/INSERT\s+INTO/i', $command)) {
                $pdo->exec($command);
            }
            // COMMENT
            elseif (preg_match('/COMMENT\s+ON/i', $command)) {
                $pdo->exec($command);
            }
        } catch (PDOException $e) {
            $error_msg = $e->getMessage();
            
            // Ignorar erros de "já existe"
            if (strpos($error_msg, 'already exists') !== false ||
                strpos($error_msg, 'duplicate') !== false ||
                strpos($error_msg, 'violates unique constraint') !== false) {
                // Ignorar silenciosamente
                continue;
            }
            
            // Log outros erros mas não interrompe
            $erros[] = [
                'comando' => substr($command, 0, 100) . '...',
                'erro' => $error_msg
            ];
            error_log("Erro ao executar comando SQL: " . $error_msg);
        }
    }
    
    $pdo->commit();
    
    // Verificar quais tabelas foram realmente criadas
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_type = 'BASE TABLE'
        AND table_name IN ('fornecedores', 'cotacoes', 'cotacao_itens', 'contas_receber', 
                           'contas_pagar', 'comissoes', 'metas_vendas', 'empresa', 
                           'documentos_empresa')
        ORDER BY table_name
    ");
    $tabelas_verificadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'message' => 'Script executado com sucesso',
        'tabelas_criadas' => $tabelas_verificadas,
        'total_criadas' => count($tabelas_verificadas),
        'total_necessarias' => 9,
        'erros' => $erros
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao executar script: ' . $e->getMessage(),
        'erros' => $erros
    ]);
    error_log("Erro ao criar tabelas: " . $e->getMessage());
}
