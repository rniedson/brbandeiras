<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo_csv'])) {
    header('Location: catalogo_importar.php');
    exit;
}

set_time_limit(300); // 5 minutos para processar

try {
    // Validar arquivo
    $arquivo = $_FILES['arquivo_csv'];
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload do arquivo');
    }
    
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, ['csv'])) {
        throw new Exception('Formato de arquivo não suportado. Use CSV.');
    }
    
    // Configurações
    $atualizar_existentes = isset($_POST['atualizar_existentes']);
    $ativar_produtos = isset($_POST['ativar_produtos']);
    
    // Ler CSV
    $handle = fopen($arquivo['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Não foi possível abrir o arquivo');
    }
    
    // Detectar e remover BOM se presente
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
    
    // Ler cabeçalho
    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception('Arquivo CSV vazio ou inválido');
    }
    
    // Limpar cabeçalhos
    $headers = array_map('trim', $headers);
    
    // Validar colunas obrigatórias
    $colunas_obrigatorias = ['codigo', 'nome', 'categoria', 'preco'];
    foreach ($colunas_obrigatorias as $coluna) {
        if (!in_array($coluna, $headers)) {
            throw new Exception("Coluna obrigatória '$coluna' não encontrada no CSV");
        }
    }
    
    // Mapear índices das colunas
    $indices = array_flip($headers);
    
    // Verificar categorias existentes
    $stmt = $pdo->query("SELECT id FROM categorias_produtos WHERE ativo = true");
    $categorias_validas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Processar produtos
    $pdo->beginTransaction();
    
    $linha_numero = 2; // Começa em 2 (1 é o cabeçalho)
    $importados = 0;
    $atualizados = 0;
    $erros = [];
    
    while (($linha = fgetcsv($handle)) !== false) {
        try {
            // Verificar se a linha tem o número correto de colunas
            if (count($linha) !== count($headers)) {
                $erros[] = "Linha $linha_numero: Número incorreto de colunas";
                $linha_numero++;
                continue;
            }
            
            // Extrair dados (apenas colunas que existem na tabela)
            $codigo = trim($linha[$indices['codigo']] ?? '');
            $nome = trim($linha[$indices['nome']] ?? '');
            $categoria_id = intval($linha[$indices['categoria']] ?? 0);
            $preco = floatval($linha[$indices['preco']] ?? 0);
            $descricao = isset($indices['descricao']) ? trim($linha[$indices['descricao']] ?? '') : null;
            
            // Validações
            if (empty($codigo)) {
                $erros[] = "Linha $linha_numero: Código não pode estar vazio";
                $linha_numero++;
                continue;
            }
            
            if (empty($nome)) {
                $erros[] = "Linha $linha_numero: Nome não pode estar vazio";
                $linha_numero++;
                continue;
            }
            
            if ($preco <= 0) {
                $erros[] = "Linha $linha_numero: Preço deve ser maior que zero";
                $linha_numero++;
                continue;
            }
            
            // Validar categoria
            if (!in_array($categoria_id, $categorias_validas)) {
                $erros[] = "Linha $linha_numero: Categoria ID $categoria_id não existe ou está inativa";
                $linha_numero++;
                continue;
            }
            
            // Verificar se produto existe
            $stmt = $pdo->prepare("SELECT id FROM produtos_catalogo WHERE codigo = ?");
            $stmt->execute([$codigo]);
            $produto_existente = $stmt->fetch();
            
            if ($produto_existente) {
                if (!$atualizar_existentes) {
                    $linha_numero++;
                    continue;
                }
                
                // Atualizar produto existente (usando apenas colunas existentes)
                $stmt = $pdo->prepare("
                    UPDATE produtos_catalogo SET 
                        nome = ?,
                        categoria_id = ?,
                        preco = ?,
                        descricao = ?,
                        ativo = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $nome,
                    $categoria_id,
                    $preco,
                    $descricao,
                    $ativar_produtos ? true : false,
                    $produto_existente['id']
                ]);
                
                $atualizados++;
                
            } else {
                // Inserir novo produto (usando apenas colunas existentes)
                $stmt = $pdo->prepare("
                    INSERT INTO produtos_catalogo (
                        codigo, nome, categoria_id, preco, descricao, ativo
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $codigo,
                    $nome,
                    $categoria_id,
                    $preco,
                    $descricao,
                    $ativar_produtos ? true : false
                ]);
                
                $importados++;
            }
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'duplicate key') !== false) {
                $erros[] = "Linha $linha_numero: Código '$codigo' duplicado";
            } else {
                $erros[] = "Linha $linha_numero: Erro no banco de dados - " . $e->getMessage();
            }
        } catch (Exception $e) {
            $erros[] = "Linha $linha_numero: " . $e->getMessage();
        }
        
        $linha_numero++;
    }
    
    fclose($handle);
    
    // Log da importação
    registrarLog('importacao_catalogo', 
        "Importados: $importados, Atualizados: $atualizados, Erros: " . count($erros)
    );
    
    $pdo->commit();
    
    // Preparar mensagem
    $mensagem = "Importação concluída! ";
    
    if ($importados > 0) {
        $mensagem .= "$importados produtos novos importados. ";
    }
    
    if ($atualizados > 0) {
        $mensagem .= "$atualizados produtos atualizados. ";
    }
    
    if (count($erros) > 0) {
        $mensagem .= count($erros) . " linhas com erros.";
        
        // Salvar log de erros detalhado
        $log_erros = "Erros de importação - " . date('Y-m-d H:i:s') . "\n";
        $log_erros .= "Arquivo: " . $arquivo['name'] . "\n\n";
        $log_erros .= implode("\n", $erros);
        
        $log_file = '../logs/importacao_erros_' . date('Ymd_His') . '.txt';
        file_put_contents($log_file, $log_erros);
        
        $_SESSION['arquivo_erros'] = basename($log_file);
    }
    
    if ($importados == 0 && $atualizados == 0) {
        $_SESSION['erro'] = "Nenhum produto foi importado ou atualizado. Verifique o arquivo.";
    } else {
        $_SESSION['mensagem'] = $mensagem;
    }
    
    header('Location: catalogo.php');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Erro na importação: ' . $e->getMessage());
    $_SESSION['erro'] = 'Erro na importação: ' . $e->getMessage();
    header('Location: catalogo_importar.php');
}
?>