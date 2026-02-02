<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

$mensagem = '';
$erro = '';
$detalhes_importacao = [];

// Processar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_csv'])) {
    
    set_time_limit(300);
    
    try {
        $arquivo = $_FILES['arquivo_csv'];
        
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload do arquivo');
        }
        
        $handle = fopen($arquivo['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Não foi possível abrir o arquivo');
        }
        
        // Remover BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        // Ler cabeçalho
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Arquivo CSV vazio');
        }
        
        $headers = array_map('trim', $headers);
        $indices = array_flip($headers);
        
        $linha_num = 2;
        $importados = 0;
        $atualizados = 0;
        $erros = [];
        
        // Processar linha por linha
        while (($linha = fgetcsv($handle)) !== false) {
            try {
                // Validar colunas
                if (count($linha) !== count($headers)) {
                    $erros[] = "Linha $linha_num: número de colunas incorreto";
                    $linha_num++;
                    continue;
                }
                
                // Extrair dados (apenas colunas que existem na tabela)
                $codigo = trim($linha[$indices['codigo']] ?? '');
                $nome = trim($linha[$indices['nome']] ?? '');
                $categoria_id = isset($indices['categoria']) ? intval($linha[$indices['categoria']] ?? 0) : null;
                $preco = floatval(str_replace(',', '.', $linha[$indices['preco']] ?? '0'));
                $descricao = isset($indices['descricao']) ? trim($linha[$indices['descricao']] ?? '') : null;
                
                // Validações
                if (empty($codigo) || empty($nome) || $preco <= 0) {
                    $erros[] = "Linha $linha_num: dados obrigatórios faltando ou inválidos";
                    $linha_num++;
                    continue;
                }
                
                // Transação individual
                $pdo->beginTransaction();
                
                // Verificar se existe
                $stmt = $pdo->prepare("SELECT id FROM produtos_catalogo WHERE codigo = ?");
                $stmt->execute([$codigo]);
                $existe = $stmt->fetch();
                
                if ($existe) {
                    // Atualizar (usando apenas colunas existentes)
                    $stmt = $pdo->prepare("
                        UPDATE produtos_catalogo SET 
                            nome = ?, 
                            categoria_id = ?, 
                            preco = ?, 
                            descricao = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE codigo = ?
                    ");
                    $stmt->execute([$nome, $categoria_id, $preco, $descricao, $codigo]);
                    $atualizados++;
                    
                } else {
                    // Inserir novo (usando apenas colunas existentes)
                    $stmt = $pdo->prepare("
                        INSERT INTO produtos_catalogo 
                        (codigo, nome, categoria_id, preco, descricao, ativo)
                        VALUES (?, ?, ?, ?, ?, true)
                    ");
                    
                    $stmt->execute([$codigo, $nome, $categoria_id, $preco, $descricao]);
                    $importados++;
                }
                
                $pdo->commit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                
                // Mensagens específicas
                if (strpos($e->getMessage(), 'categoria_id_fkey') !== false) {
                    $erros[] = "Linha $linha_num ($codigo): Categoria ID $categoria_id não existe";
                } elseif (strpos($e->getMessage(), 'duplicate key') !== false) {
                    $erros[] = "Linha $linha_num: Código '$codigo' duplicado";
                } else {
                    $erros[] = "Linha $linha_num ($codigo): Erro no banco - " . $e->getMessage();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erros[] = "Linha $linha_num: " . $e->getMessage();
            }
            
            $linha_num++;
            
            // A cada 100 linhas, dar feedback
            if (($linha_num - 2) % 100 == 0) {
                flush();
            }
        }
        
        fclose($handle);
        
        // Log
        registrarLog('importacao_catalogo', 
            "Arquivo: {$arquivo['name']}, Total: " . ($linha_num - 2) . 
            ", Importados: $importados, Atualizados: $atualizados, Erros: " . count($erros)
        );
        
        // Resultado
        if ($importados > 0 || $atualizados > 0) {
            $mensagem = "✅ Importação concluída com sucesso! ";
            if ($importados > 0) $mensagem .= "$importados novos produtos. ";
            if ($atualizados > 0) $mensagem .= "$atualizados atualizados. ";
            if (count($erros) > 0) $mensagem .= count($erros) . " linhas com erro.";
        } else {
            $erro = "❌ Nenhum produto foi importado. Verifique os erros abaixo.";
        }
        
        $detalhes_importacao = [
            'total_linhas' => $linha_num - 2,
            'importados' => $importados,
            'atualizados' => $atualizados,
            'erros' => $erros
        ];
        
    } catch (Exception $e) {
        $erro = "Erro geral: " . $e->getMessage();
    }
}

// Buscar categorias
$categorias = $pdo->query("
    SELECT id, nome 
    FROM categorias_produtos 
    WHERE ativo = true 
    ORDER BY id
")->fetchAll();

$titulo = 'Importar Produtos';
$breadcrumb = [
    ['label' => 'Catálogo', 'url' => 'catalogo.php'],
    ['label' => 'Importar']
];
include '../../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Importar Produtos</h1>
    
    <?php if ($mensagem): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
        <?= $mensagem ?>
    </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
        <?= $erro ?>
    </div>
    <?php endif; ?>
    
    <!-- Formulário -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Selecione o arquivo CSV</h2>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <input type="file" name="arquivo_csv" accept=".csv" required
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <p class="text-xs text-gray-500 mt-1">
                    Formato: CSV com 8 colunas na ordem correta
                </p>
            </div>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                <p class="text-sm text-yellow-700">
                    <strong>Formato esperado:</strong><br>
                    codigo, nome, categoria, preco, preco_promocional, unidade_venda, tempo_producao, tags<br>
                    <span class="text-xs">• categoria = ID numérico<br>
                    • precos = usar ponto como decimal<br>
                    • tempo_producao = número inteiro de dias</span>
                </p>
            </div>
            
            <button type="submit" 
                    onclick="this.disabled=true; this.innerText='Importando...'; this.form.submit();"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Importar Produtos
            </button>
        </form>
    </div>
    
    <!-- Categorias -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">IDs de Categorias</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <?php foreach ($categorias as $cat): ?>
            <div class="flex items-center space-x-2 text-sm">
                <span class="font-mono bg-gray-100 px-2 py-1 rounded"><?= $cat['id'] ?></span>
                <span><?= htmlspecialchars($cat['nome']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Resultados -->
    <?php if (!empty($detalhes_importacao)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">Resultado da Importação</h2>
        
        <div class="grid grid-cols-4 gap-4 mb-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-600"><?= $detalhes_importacao['total_linhas'] ?></div>
                <div class="text-sm text-gray-600">Total</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600"><?= $detalhes_importacao['importados'] ?></div>
                <div class="text-sm text-gray-600">Novos</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $detalhes_importacao['atualizados'] ?></div>
                <div class="text-sm text-gray-600">Atualizados</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600"><?= count($detalhes_importacao['erros']) ?></div>
                <div class="text-sm text-gray-600">Erros</div>
            </div>
        </div>
        
        <?php if (!empty($detalhes_importacao['erros'])): ?>
        <div class="mt-4">
            <div class="flex justify-between items-center mb-2">
                <h3 class="font-semibold">Erros Encontrados:</h3>
                <button onclick="document.getElementById('erros-completos').classList.toggle('hidden')"
                        class="text-sm text-blue-600 hover:text-blue-800">
                    Ver todos
                </button>
            </div>
            
            <!-- Primeiros erros sempre visíveis -->
            <div class="bg-gray-100 p-3 rounded text-sm font-mono">
                <?php foreach (array_slice($detalhes_importacao['erros'], 0, 5) as $erro): ?>
                <div class="text-red-600 mb-1">• <?= htmlspecialchars($erro) ?></div>
                <?php endforeach; ?>
                <?php if (count($detalhes_importacao['erros']) > 5): ?>
                <div class="text-gray-600 mt-2">... e mais <?= count($detalhes_importacao['erros']) - 5 ?> erros</div>
                <?php endif; ?>
            </div>
            
            <!-- Todos os erros (oculto por padrão) -->
            <div id="erros-completos" class="hidden mt-2 bg-gray-100 p-3 rounded max-h-60 overflow-y-auto text-sm font-mono">
                <?php foreach ($detalhes_importacao['erros'] as $erro): ?>
                <div class="text-red-600 mb-1">• <?= htmlspecialchars($erro) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($importados > 0 || $atualizados > 0): ?>
        <div class="mt-4 pt-4 border-t">
            <a href="catalogo.php" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Ver Catálogo Atualizado
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../../views/layouts/_footer.php'; ?>