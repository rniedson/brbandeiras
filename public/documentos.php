<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Processar ações
$mensagem = null;
$erro = null;

// Upload de documento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    try {
        $nome = trim($_POST['nome'] ?? '');
        $categoria = trim($_POST['categoria'] ?? 'geral');
        $descricao = trim($_POST['descricao'] ?? '');
        $arquivo = $_FILES['arquivo'];
        
        if (empty($nome)) {
            throw new Exception('Nome do documento é obrigatório');
        }
        
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload do arquivo');
        }
        
        // Validar tipo de arquivo
        $tipos_permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extensao, $tipos_permitidos)) {
            throw new Exception('Tipo de arquivo não permitido. Use: ' . implode(', ', $tipos_permitidos));
        }
        
        // Validar tamanho (máximo 10MB)
        if ($arquivo['size'] > 10 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 10MB');
        }
        
        // Criar diretório se não existir
        $upload_dir = '../uploads/documentos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Gerar nome único
        $nome_arquivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $arquivo['name']);
        $caminho_arquivo = $upload_dir . $nome_arquivo;
        
        if (!move_uploaded_file($arquivo['tmp_name'], $caminho_arquivo)) {
            throw new Exception('Erro ao salvar arquivo');
        }
        
        // Salvar no banco
        $sql = "
            INSERT INTO documentos_empresa (
                nome, categoria, descricao, arquivo_nome, arquivo_caminho, 
                tamanho, tipo, usuario_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome,
            $categoria,
            $descricao,
            $arquivo['name'],
            'uploads/documentos/' . $nome_arquivo,
            $arquivo['size'],
            $extensao,
            $_SESSION['user_id']
        ]);
        
        $mensagem = 'Documento enviado com sucesso!';
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'does not exist') !== false) {
            $erro = 'A tabela "documentos_empresa" não existe no banco de dados. É necessário criar a tabela primeiro.';
        } else {
            error_log("Erro ao salvar documento: " . $e->getMessage());
            $erro = 'Erro ao salvar documento: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Excluir documento
if (isset($_GET['excluir'])) {
    try {
        $id = intval($_GET['excluir']);
        
        // Buscar documento
        $stmt = $pdo->prepare("SELECT arquivo_caminho FROM documentos_empresa WHERE id = ?");
        $stmt->execute([$id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($documento) {
            // Excluir arquivo físico
            $arquivo_fisico = '../' . $documento['arquivo_caminho'];
            if (file_exists($arquivo_fisico)) {
                unlink($arquivo_fisico);
            }
            
            // Excluir do banco
            $stmt = $pdo->prepare("DELETE FROM documentos_empresa WHERE id = ?");
            $stmt->execute([$id]);
            
            $mensagem = 'Documento excluído com sucesso!';
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao excluir documento: " . $e->getMessage());
        $erro = 'Erro ao excluir documento';
    }
}

// Filtros
$categoria_filtro = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$limite = 20;
$offset = ($pagina - 1) * $limite;

// Query base
$where = ["1=1"];
$params = [];

if ($categoria_filtro) {
    $where[] = "categoria = ?";
    $params[] = $categoria_filtro;
}

if ($busca) {
    $where[] = "(nome ILIKE ? OR descricao ILIKE ? OR arquivo_nome ILIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam]);
}

$whereClause = implode(' AND ', $where);

// Buscar documentos
try {
    $sql = "
        SELECT d.*, u.nome as usuario_nome
        FROM documentos_empresa d
        LEFT JOIN usuarios u ON d.usuario_id = u.id
        WHERE $whereClause
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params_query = array_merge($params, [intval($limite), intval($offset)]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_query);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total
    $sql_count = "SELECT COUNT(*) FROM documentos_empresa WHERE $whereClause";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $limite);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        $documentos = [];
        $total_registros = 0;
        $total_paginas = 0;
    } else {
        error_log("Erro ao buscar documentos: " . $e->getMessage());
        $documentos = [];
        $total_registros = 0;
        $total_paginas = 0;
    }
}

// Estatísticas
try {
    $sql_stats = "
        SELECT 
            COUNT(*) as total,
            COUNT(DISTINCT categoria) as total_categorias,
            SUM(tamanho) as tamanho_total
        FROM documentos_empresa
    ";
    $stats = $pdo->query($sql_stats)->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'total_categorias' => 0, 'tamanho_total' => 0];
}

// Categorias disponíveis
$categorias = [
    'geral' => 'Geral',
    'contratos' => 'Contratos',
    'certificados' => 'Certificados',
    'licencas' => 'Licenças',
    'documentos_fiscais' => 'Documentos Fiscais',
    'documentos_comerciais' => 'Documentos Comerciais',
    'outros' => 'Outros'
];

$titulo = 'Documentos da Empresa';
$breadcrumb = [
    ['label' => 'Configurações', 'url' => '#'],
    ['label' => 'Empresa', 'url' => '#'],
    ['label' => 'Documentos']
];
include '../views/layouts/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Documentos da Empresa</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Gerencie os documentos da empresa</p>
        </div>
        <button onclick="abrirModalUpload()" 
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-upload mr-2"></i>Enviar Documento
        </button>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Total de Documentos</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stats['total'] ?? 0) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Categorias</div>
        <div class="text-2xl font-bold text-blue-600"><?= number_format($stats['total_categorias'] ?? 0) ?></div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">Espaço Utilizado</div>
        <div class="text-2xl font-bold text-purple-600">
            <?= $stats['tamanho_total'] ? number_format($stats['tamanho_total'] / 1024 / 1024, 2) : '0' ?> MB
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Categoria</label>
                <select name="categoria" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $categoria_filtro === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Buscar</label>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Nome, descrição ou arquivo..."
                       class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600">
                    Filtrar
                </button>
            </div>
        </form>
    </div>
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

<!-- Lista de Documentos -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Documentos Cadastrados</h2>
    </div>
    
    <?php if (empty($documentos)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nenhum documento encontrado</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Envie o primeiro documento para começar.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Categoria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Arquivo</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tamanho</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Enviado por</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($documentos as $doc): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            <?= htmlspecialchars($doc['nome']) ?>
                        </div>
                        <?php if (!empty($doc['descricao'])): ?>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <?= htmlspecialchars($doc['descricao']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            <?= htmlspecialchars($categorias[$doc['categoria']] ?? ucfirst($doc['categoria'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($doc['arquivo_nome']) ?>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            .<?= strtoupper($doc['tipo'] ?? '') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm text-gray-900 dark:text-white">
                            <?= number_format(($doc['tamanho'] ?? 0) / 1024, 2) ?> KB
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= htmlspecialchars($doc['usuario_nome'] ?? 'N/A') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?= formatarData($doc['created_at'] ?? '') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end gap-2">
                            <a href="../<?= htmlspecialchars($doc['arquivo_caminho']) ?>" 
                               target="_blank"
                               class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                <i class="fas fa-download"></i> Baixar
                            </a>
                            <button onclick="excluirDocumento(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['nome'])) ?>')" 
                                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
    <div class="px-6 py-4 border-t dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Mostrando <?= count($documentos) ?> de <?= $total_registros ?> documentos
        </div>
        <div class="flex gap-2">
            <?php
            $query_params = [];
            if ($categoria_filtro) $query_params['categoria'] = $categoria_filtro;
            if ($busca) $query_params['busca'] = $busca;
            $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
            ?>
            
            <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                Anterior
            </a>
            <?php endif; ?>
            
            <?php
            $inicio = max(1, $pagina - 2);
            $fim = min($total_paginas, $pagina + 2);
            
            if ($inicio > 1): ?>
                <a href="?pagina=1<?= $query_string ?>" class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">1</a>
                <?php if ($inicio > 2): ?>
                    <span class="px-3 py-2 text-gray-400">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                <?php if ($i == $pagina): ?>
                    <span class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">
                        <?= $i ?>
                    </span>
                <?php else: ?>
                    <a href="?pagina=<?= $i ?><?= $query_string ?>" 
                       class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($fim < $total_paginas): ?>
                <?php if ($fim < $total_paginas - 1): ?>
                    <span class="px-3 py-2 text-gray-400">...</span>
                <?php endif; ?>
                <a href="?pagina=<?= $total_paginas ?><?= $query_string ?>" 
                   class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                    <?= $total_paginas ?>
                </a>
            <?php endif; ?>
            
            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?><?= $query_string ?>" 
               class="px-3 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                Próxima
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal Upload -->
<div id="modalUpload" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="fecharModalUpload()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Enviar Documento</h3>
                <button onclick="fecharModalUpload()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formUpload">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nome do Documento <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nome" required
                               class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Categoria
                        </label>
                        <select name="categoria" class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                            <?php foreach ($categorias as $key => $label): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Descrição
                        </label>
                        <textarea name="descricao" rows="3"
                                  class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Arquivo <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="arquivo" required
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"
                               class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Formatos permitidos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF (máx. 10MB)
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="fecharModalUpload()" 
                            class="px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalUpload() {
    document.getElementById('modalUpload').classList.remove('hidden');
}

function fecharModalUpload() {
    document.getElementById('modalUpload').classList.add('hidden');
    document.getElementById('formUpload').reset();
}

function excluirDocumento(id, nome) {
    if (confirm(`Deseja realmente excluir o documento "${nome}"?`)) {
        window.location.href = '?excluir=' + id;
    }
}
</script>

<?php include '../views/layouts/_footer.php'; ?>
