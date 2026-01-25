<?php
// DEBUG - Remover em produção
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();
requireRole(['arte_finalista', 'gestor']);

// DEBUG - Ver o que está chegando
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['debug'])) {
    echo '<pre>';
    echo "=== DEBUG MODE ===\n";
    echo "POST: ";
    print_r($_POST);
    echo "\nFILES: ";
    print_r($_FILES);
    echo "\nSESSION user: ";
    print_r(['id' => $_SESSION['user_id'], 'perfil' => $_SESSION['user_perfil']]);
    echo '</pre>';
    exit;
}

// PROCESSAR UPLOAD (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Inicializar array de erros para debug
    $debug_info = [];
    
    try {
        // 1. Validar pedido_id
        $pedido_id = $_POST['pedido_id'] ?? null;
        $debug_info['pedido_id'] = $pedido_id;
        
        if (!$pedido_id) {
            throw new Exception('Pedido não identificado. POST pedido_id = ' . var_export($pedido_id, true));
        }
        
        // 2. Verificar se o pedido existe
        $stmt = $pdo->prepare("
            SELECT p.id, p.numero, pa.arte_finalista_id 
            FROM pedidos p
            LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pedido_id]);
        $pedido = $stmt->fetch();
        $debug_info['pedido_encontrado'] = $pedido ? 'Sim' : 'Não';
        
        if (!$pedido) {
            throw new Exception("Pedido ID {$pedido_id} não encontrado no banco");
        }
        
        // 3. Verificar permissão (relaxada para teste)
        $debug_info['user_perfil'] = $_SESSION['user_perfil'];
        $debug_info['user_id'] = $_SESSION['user_id'];
        $debug_info['arte_finalista_id'] = $pedido['arte_finalista_id'];
        
        // Permitir gestor ou arte-finalista responsável
        $pode_enviar = false;
        if ($_SESSION['user_perfil'] === 'gestor') {
            $pode_enviar = true;
            $debug_info['permissao_razao'] = 'É gestor';
        } elseif ($_SESSION['user_perfil'] === 'arte_finalista') {
            if (!$pedido['arte_finalista_id'] || $pedido['arte_finalista_id'] == $_SESSION['user_id']) {
                $pode_enviar = true;
                $debug_info['permissao_razao'] = 'É arte-finalista responsável ou pedido sem responsável';
            }
        }
        
        if (!$pode_enviar) {
            throw new Exception('Você não tem permissão para enviar arte para este pedido. Debug: ' . json_encode($debug_info));
        }
        
        // 4. Verificar arquivo
        $debug_info['files_recebido'] = !empty($_FILES);
        $debug_info['arquivo_campo'] = isset($_FILES['arquivo']);
        
        if (!isset($_FILES['arquivo'])) {
            throw new Exception('Nenhum arquivo recebido. $_FILES = ' . json_encode($_FILES));
        }
        
        $arquivo = $_FILES['arquivo'];
        $debug_info['arquivo_erro'] = $arquivo['error'];
        $debug_info['arquivo_nome'] = $arquivo['name'];
        $debug_info['arquivo_tamanho'] = $arquivo['size'];
        
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            $erros = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'Upload parcial',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temp ausente',
                UPLOAD_ERR_CANT_WRITE => 'Erro ao gravar',
                UPLOAD_ERR_EXTENSION => 'Bloqueado por extensão'
            ];
            $erro_msg = $erros[$arquivo['error']] ?? 'Erro desconhecido';
            throw new Exception("Erro no upload: {$erro_msg} (código {$arquivo['error']})");
        }
        
        // 5. Validar arquivo
        $nome_original = $arquivo['name'];
        $tamanho = $arquivo['size'];
        $tmp_name = $arquivo['tmp_name'];
        
        $debug_info['tmp_existe'] = file_exists($tmp_name);
        
        if (!file_exists($tmp_name)) {
            throw new Exception("Arquivo temporário não existe: {$tmp_name}");
        }
        
        // Validar tamanho (50MB)
        $max_size = 50 * 1024 * 1024;
        if ($tamanho > $max_size) {
            throw new Exception("Arquivo muito grande: {$tamanho} bytes (máximo {$max_size} bytes)");
        }
        
        // Validar extensão
        $ext = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
        $extensoes_permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'ai', 'cdr', 'psd', 'eps', 'svg'];
        
        if (!in_array($ext, $extensoes_permitidas)) {
            throw new Exception("Extensão não permitida: {$ext}. Permitidas: " . implode(', ', $extensoes_permitidas));
        }
        
        // 6. Iniciar transação
        $pdo->beginTransaction();
        $debug_info['transacao_iniciada'] = true;
        
        // 7. Buscar última versão
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(versao), 0) as ultima_versao 
            FROM arte_versoes 
            WHERE pedido_id = ?
        ");
        $stmt->execute([$pedido_id]);
        $ultima_versao = $stmt->fetchColumn();
        $nova_versao = $ultima_versao + 1;
        $debug_info['nova_versao'] = $nova_versao;
        
        // 8. Criar diretório
        $upload_dir = '../uploads/arte_versoes/';
        $debug_info['upload_dir'] = $upload_dir;
        $debug_info['upload_dir_existe'] = file_exists($upload_dir);
        
        if (!file_exists($upload_dir)) {
            $debug_info['mkdir_result'] = mkdir($upload_dir, 0777, true);
            if (!file_exists($upload_dir)) {
                throw new Exception("Não foi possível criar diretório: {$upload_dir}");
            }
        }
        
        // 9. Gerar nome único e mover arquivo
        $novo_nome = $pedido_id . '_v' . $nova_versao . '_' . uniqid() . '.' . $ext;
        $caminho_completo = $upload_dir . $novo_nome;
        $caminho_relativo = 'uploads/arte_versoes/' . $novo_nome;
        
        $debug_info['destino'] = $caminho_completo;
        $debug_info['move_result'] = move_uploaded_file($tmp_name, $caminho_completo);
        
        if (!$debug_info['move_result']) {
            throw new Exception("Falha ao mover arquivo de {$tmp_name} para {$caminho_completo}");
        }
        
        // 10. Inserir no banco
        $comentario_arte = trim($_POST['comentario_arte'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO arte_versoes (
                pedido_id, versao, arquivo_nome, arquivo_caminho, 
                comentario_arte, usuario_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $debug_info['insert_params'] = [
            'pedido_id' => $pedido_id,
            'versao' => $nova_versao,
            'arquivo_nome' => $nome_original,
            'arquivo_caminho' => $caminho_relativo,
            'comentario' => $comentario_arte,
            'usuario_id' => $_SESSION['user_id']
        ];
        
        $stmt->execute([
            $pedido_id,
            $nova_versao,
            $nome_original,
            $caminho_relativo,
            $comentario_arte ?: null,
            $_SESSION['user_id']
        ]);
        
        $debug_info['insert_id'] = $pdo->lastInsertId();
        
        // 11. Atualizar arte_finalista se não estiver atribuído
        if (!$pedido['arte_finalista_id'] && $_SESSION['user_perfil'] === 'arte_finalista') {
            $stmt = $pdo->prepare("
                INSERT INTO pedido_arte (pedido_id, arte_finalista_id, created_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (pedido_id) DO UPDATE 
                SET arte_finalista_id = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$pedido_id, $_SESSION['user_id'], $_SESSION['user_id']]);
            $debug_info['arte_atribuida'] = true;
        }
        
        // 12. Registrar log
        try {
            registrarLog('arte_versao_enviada', 
                "Nova versão ({$nova_versao}) de arte enviada para pedido #{$pedido['numero']}");
        } catch (Exception $e) {
            $debug_info['log_erro'] = $e->getMessage();
        }
        
        // 13. Commit
        $pdo->commit();
        
        $_SESSION['mensagem'] = "Versão {$nova_versao} enviada com sucesso!";
        header("Location: pedido_detalhes_arte_finalista.php?id={$pedido_id}");
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Limpar arquivo se foi movido
        if (isset($caminho_completo) && file_exists($caminho_completo)) {
            @unlink($caminho_completo);
        }
        
        $_SESSION['erro'] = $e->getMessage();
        
        // Em modo debug, mostrar informações completas
        if (isset($_GET['debug']) || true) { // Sempre debug por enquanto
            echo '<div style="background: #fee; padding: 20px; margin: 20px; border: 2px solid #c00;">';
            echo '<h2>❌ Erro no Upload</h2>';
            echo '<p><strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<h3>Debug Info:</h3>';
            echo '<pre>' . htmlspecialchars(print_r($debug_info, true)) . '</pre>';
            echo '<h3>Stack Trace:</h3>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '<p><a href="arte_upload.php?pedido_id=' . $pedido_id . '">← Voltar</a></p>';
            echo '</div>';
            exit;
        }
        
        header("Location: arte_upload.php?pedido_id={$pedido_id}");
        exit;
    }
}

// MOSTRAR FORMULÁRIO (GET)
$pedido_id = $_GET['pedido_id'] ?? null;

if (!$pedido_id) {
    $_SESSION['erro'] = 'Pedido não especificado';
    header('Location: arte_finalista.php');
    exit;
}

// Buscar dados do pedido
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.nome as cliente_nome,
            pa.arte_finalista_id,
            u.nome as arte_finalista_nome,
            (SELECT MAX(versao) FROM arte_versoes WHERE pedido_id = p.id) as ultima_versao
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios u ON pa.arte_finalista_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        $_SESSION['erro'] = 'Pedido não encontrado';
        header('Location: arte_finalista.php');
        exit;
    }

    // Verificar permissão
    if ($_SESSION['user_perfil'] === 'arte_finalista' && 
        $pedido['arte_finalista_id'] && 
        $pedido['arte_finalista_id'] != $_SESSION['user_id']) {
        $_SESSION['erro'] = 'Você não é o arte-finalista responsável por este pedido';
        header('Location: arte_finalista.php');
        exit;
    }

    // Buscar itens do pedido
    $stmt = $pdo->prepare("
        SELECT pi.*, pc.nome as produto_nome
        FROM pedido_itens pi
        LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll();

    $proxima_versao = ($pedido['ultima_versao'] ?? 0) + 1;

} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao carregar pedido: ' . $e->getMessage();
    header('Location: arte_finalista.php');
    exit;
}

$titulo = 'Enviar Arte - Pedido #' . $pedido['numero'];
$breadcrumb = [
    ['label' => 'Arte-Finalista', 'url' => 'arte_finalista.php'],
    ['label' => 'Pedido #' . $pedido['numero'], 'url' => 'pedido_detalhes_arte_finalista.php?id=' . $pedido_id],
    ['label' => 'Nova Versão']
];

include '../views/_header.php';
?>

<div class="max-w-4xl mx-auto p-4 lg:p-6">
    <!-- Debug Mode Notice -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-4">
        <p class="font-bold">🐛 Modo Debug Ativado</p>
        <p class="text-sm">O formulário enviará para: arte_upload.php?debug=1</p>
    </div>
    <?php endif; ?>

    <!-- Mensagens de erro/sucesso -->
    <?php if (isset($_SESSION['erro'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
        <p class="font-bold">Erro!</p>
        <p><?= htmlspecialchars($_SESSION['erro']) ?></p>
    </div>
    <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <!-- Cabeçalho -->
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-lg shadow-lg mb-6 p-6">
        <h1 class="text-2xl font-bold text-white mb-2">
            Enviar Nova Versão de Arte
        </h1>
        <p class="text-purple-100">
            Pedido #<?= htmlspecialchars($pedido['numero']) ?> - Versão <?= $proxima_versao ?>
        </p>
    </div>

    <!-- Informações do Pedido -->
    <div class="bg-white rounded-lg shadow mb-6 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informações do Pedido</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <p class="text-sm text-gray-600">Cliente</p>
                <p class="font-medium"><?= htmlspecialchars($pedido['cliente_nome']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Prazo de Entrega</p>
                <p class="font-medium <?= strtotime($pedido['prazo_entrega']) < time() ? 'text-red-600' : '' ?>">
                    <?= formatarData($pedido['prazo_entrega']) ?>
                    <?php if ($pedido['urgente']): ?>
                        <span class="ml-2 px-2 py-1 bg-red-100 text-red-600 text-xs rounded-full">URGENTE</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Itens do Pedido -->
        <div class="border-t pt-4">
            <h3 class="text-sm font-medium text-gray-700 mb-2">Itens do Pedido:</h3>
            <div class="space-y-2">
                <?php foreach ($itens as $item): ?>
                <div class="flex justify-between items-start text-sm">
                    <div class="flex-1">
                        <p class="font-medium"><?= htmlspecialchars($item['descricao']) ?></p>
                        <?php if ($item['observacoes']): ?>
                            <p class="text-gray-600 text-xs mt-1"><?= htmlspecialchars($item['observacoes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="text-gray-600 ml-4">Qtd: <?= $item['quantidade'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Formulário de Upload -->
    <form method="POST" 
          action="arte_upload.php<?= isset($_GET['debug']) ? '?debug=1' : '' ?>" 
          enctype="multipart/form-data" 
          class="bg-white rounded-lg shadow p-6"
          onsubmit="return validarFormulario()">
        
        <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
        
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Upload da Arte - Versão <?= $proxima_versao ?></h2>
        
        <!-- Campo de arquivo -->
        <div class="mb-6">
            <label for="arquivo" class="block text-sm font-medium text-gray-700 mb-2">
                Arquivo da Arte <span class="text-red-500">*</span>
            </label>
            <input type="file" 
                   id="arquivo"
                   name="arquivo" 
                   required
                   accept=".pdf,.jpg,.jpeg,.png,.ai,.cdr,.psd,.eps,.svg"
                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-purple-500"
                   onchange="mostrarInfoArquivo(this)">
            <p class="text-xs text-gray-500 mt-1">
                Formatos: PDF, JPG, PNG, AI, CDR, PSD, EPS, SVG (máx. 50MB)
            </p>
            <div id="info-arquivo" class="mt-2"></div>
        </div>

        <!-- Comentário -->
        <div class="mb-6">
            <label for="comentario_arte" class="block text-sm font-medium text-gray-700 mb-2">
                Comentários sobre esta versão
            </label>
            <textarea name="comentario_arte" 
                      id="comentario_arte"
                      rows="4"
                      placeholder="Descreva as alterações realizadas, observações técnicas, etc..."
                      class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-purple-500"></textarea>
            <p class="text-xs text-gray-500 mt-1">Este comentário será visível para o cliente na aprovação</p>
        </div>

        <!-- Botões -->
        <div class="flex justify-between items-center pt-4 border-t">
            <a href="pedido_detalhes_arte_finalista.php?id=<?= $pedido_id ?>" 
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                Cancelar
            </a>
            <button type="submit" 
                    id="btn-enviar"
                    class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Enviar Versão <?= $proxima_versao ?>
                </span>
            </button>
        </div>
    </form>

    <!-- Link para debug -->
    <div class="mt-4 text-center">
        <?php if (!isset($_GET['debug'])): ?>
        <a href="?pedido_id=<?= $pedido_id ?>&debug=1" class="text-sm text-gray-500 hover:text-gray-700">
            Ativar modo debug
        </a>
        <?php else: ?>
        <a href="?pedido_id=<?= $pedido_id ?>" class="text-sm text-gray-500 hover:text-gray-700">
            Desativar modo debug
        </a>
        <?php endif; ?>
    </div>
</div>

<script>
function mostrarInfoArquivo(input) {
    const infoDiv = document.getElementById('info-arquivo');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const tamanhoMB = (file.size / 1024 / 1024).toFixed(2);
        const maxMB = 50;
        
        let html = '<div class="p-3 rounded ';
        
        if (tamanhoMB > maxMB) {
            html += 'bg-red-100 text-red-700">';
            html += `<strong>⚠️ Arquivo muito grande!</strong><br>`;
            html += `Tamanho: ${tamanhoMB} MB (máximo: ${maxMB} MB)`;
        } else {
            html += 'bg-green-100 text-green-700">';
            html += `<strong>✅ Arquivo selecionado:</strong><br>`;
            html += `Nome: ${file.name}<br>`;
            html += `Tamanho: ${tamanhoMB} MB<br>`;
            html += `Tipo: ${file.type || 'Desconhecido'}`;
        }
        
        html += '</div>';
        infoDiv.innerHTML = html;
    }
}

function validarFormulario() {
    const arquivo = document.getElementById('arquivo');
    const btnEnviar = document.getElementById('btn-enviar');
    
    if (!arquivo.files || !arquivo.files[0]) {
        alert('Por favor, selecione um arquivo!');
        return false;
    }
    
    const file = arquivo.files[0];
    const tamanhoMB = file.size / 1024 / 1024;
    
    if (tamanhoMB > 50) {
        alert('Arquivo muito grande! Máximo permitido: 50MB');
        return false;
    }
    
    // Desabilitar botão e mostrar loading
    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<span class="flex items-center gap-2">Enviando... ⏳</span>';
    
    return true;
}

// Debug info no console
console.log('Formulário de upload carregado');
console.log('Pedido ID:', <?= $pedido_id ?>);
console.log('Próxima versão:', <?= $proxima_versao ?>);
</script>

<?php include '../views/_footer.php'; ?>