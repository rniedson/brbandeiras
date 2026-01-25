<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

// Filtros
$busca = $_GET['busca'] ?? '';
$pedido_id = $_GET['pedido_id'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Base da query
$where = ['1=1'];
$params = [];

// Filtro por perfil
if ($_SESSION['user_perfil'] === 'vendedor') {
    $where[] = "p.vendedor_id = ?";
    $params[] = $_SESSION['user_id'];
}

// Filtro por pedido
if ($pedido_id) {
    $where[] = "pa.pedido_id = ?";
    $params[] = $pedido_id;
}

// Filtro de busca
if ($busca) {
    $where[] = "(p.numero ILIKE ? OR c.nome ILIKE ? OR pa.nome_arquivo ILIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_merge($params, [$buscaParam, $buscaParam, $buscaParam]);
}

// Filtro de datas
if ($data_inicio && $data_fim) {
    $where[] = "DATE(pa.created_at) BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

$whereClause = implode(' AND ', $where);

// Buscar arquivos
$sql = "
    SELECT pa.*, 
           p.numero as pedido_numero,
           p.status as pedido_status,
           c.nome as cliente_nome,
           u.nome as usuario_nome
    FROM pedido_arquivos pa
    INNER JOIN pedidos p ON pa.pedido_id = p.id
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON pa.usuario_id = u.id
    WHERE $whereClause
    ORDER BY pa.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stats = [
    'total_arquivos' => count($arquivos),
    'total_tamanho' => 0,
    'tipos' => []
];

foreach ($arquivos as $arquivo) {
    // Calcular tamanho total
    $caminho_completo = '../' . $arquivo['caminho_arquivo'];
    if (file_exists($caminho_completo)) {
        $stats['total_tamanho'] += filesize($caminho_completo);
    }
    
    // Contar tipos
    $ext = strtolower(pathinfo($arquivo['nome_arquivo'], PATHINFO_EXTENSION));
    if (!isset($stats['tipos'][$ext])) {
        $stats['tipos'][$ext] = 0;
    }
    $stats['tipos'][$ext]++;
}

// Buscar pedidos para filtro
$pedidos = [];
if ($_SESSION['user_perfil'] === 'gestor') {
    $pedidos = $pdo->query("
        SELECT p.id, p.numero, c.nome as cliente_nome 
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        ORDER BY p.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT p.id, p.numero, c.nome as cliente_nome 
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.vendedor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$titulo = 'Artes e Arquivos';
$breadcrumb = [
    ['label' => 'Artes']
];
include '../views/_header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Artes e Arquivos</h1>
            <p class="text-gray-600 mt-2">Gerencie os arquivos de arte dos pedidos</p>
        </div>
        
        <button onclick="abrirModalUpload()" 
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            Enviar Arquivo
        </button>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Total de Arquivos</p>
                <p class="text-xl font-bold"><?= $stats['total_arquivos'] ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Tamanho Total</p>
                <p class="text-xl font-bold"><?= formatarTamanho($stats['total_tamanho']) ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Imagens</p>
                <p class="text-xl font-bold">
                    <?= ($stats['tipos']['jpg'] ?? 0) + ($stats['tipos']['jpeg'] ?? 0) + ($stats['tipos']['png'] ?? 0) ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-500">Documentos</p>
                <p class="text-xl font-bold">
                    <?= ($stats['tipos']['pdf'] ?? 0) + ($stats['tipos']['doc'] ?? 0) + ($stats['tipos']['docx'] ?? 0) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Buscar por pedido, cliente ou arquivo..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <select name="pedido_id" class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todos os pedidos</option>
                    <?php foreach ($pedidos as $pedido): ?>
                    <option value="<?= $pedido['id'] ?>" <?= $pedido_id == $pedido['id'] ? 'selected' : '' ?>>
                        #<?= htmlspecialchars($pedido['numero']) ?> - <?= htmlspecialchars($pedido['cliente_nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <input type="date" name="data_inicio" value="<?= $data_inicio ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div>
                <input type="date" name="data_fim" value="<?= $data_fim ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Filtrar
            </button>
        </form>
    </div>
</div>

<!-- Lista de Arquivos -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($arquivos)): ?>
    <div class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum arquivo encontrado</h3>
        <p class="mt-1 text-sm text-gray-500">Comece enviando um arquivo de arte.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
        <?php foreach ($arquivos as $arquivo): ?>
        <?php 
            $ext = strtolower(pathinfo($arquivo['nome_arquivo'], PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
            $caminho_completo = '../' . $arquivo['caminho_arquivo'];
            $tamanho = file_exists($caminho_completo) ? filesize($caminho_completo) : 0;
        ?>
        <div class="bg-gray-50 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
            <!-- Preview -->
            <div class="h-48 bg-gray-200 relative overflow-hidden">
                <?php if ($isImage && file_exists($caminho_completo)): ?>
                <img src="<?= $arquivo['caminho_arquivo'] ?>" 
                     alt="<?= htmlspecialchars($arquivo['nome_arquivo']) ?>"
                     class="w-full h-full object-cover cursor-pointer"
                     onclick="abrirModalImagem('<?= $arquivo['caminho_arquivo'] ?>', '<?= htmlspecialchars($arquivo['nome_arquivo']) ?>')">
                <?php else: ?>
                <div class="h-full flex items-center justify-center">
                    <?php if ($ext === 'pdf'): ?>
                    <svg class="w-16 h-16 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-5L9 2H4z" clip-rule="evenodd"/>
                    </svg>
                    <?php elseif (in_array($ext, ['doc', 'docx'])): ?>
                    <svg class="w-16 h-16 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-5L9 2H4z" clip-rule="evenodd"/>
                    </svg>
                    <?php else: ?>
                    <svg class="w-16 h-16 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-5L9 2H4z" clip-rule="evenodd"/>
                    </svg>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Badge do tipo -->
                <span class="absolute top-2 right-2 px-2 py-1 bg-black bg-opacity-75 text-white text-xs rounded">
                    <?= strtoupper($ext) ?>
                </span>
            </div>
            
            <!-- Informações -->
            <div class="p-4">
                <h3 class="font-medium text-gray-900 truncate" title="<?= htmlspecialchars($arquivo['nome_arquivo']) ?>">
                    <?= htmlspecialchars($arquivo['nome_arquivo']) ?>
                </h3>
                
                <div class="mt-2 space-y-1 text-sm text-gray-500">
                    <p>
                        <span class="font-medium">Pedido:</span> 
                        <a href="pedido_detalhes.php?id=<?= $arquivo['pedido_id'] ?>" 
                           class="text-blue-600 hover:text-blue-800">
                            #<?= htmlspecialchars($arquivo['pedido_numero']) ?>
                        </a>
                    </p>
                    <p>
                        <span class="font-medium">Cliente:</span> 
                        <?= htmlspecialchars($arquivo['cliente_nome']) ?>
                    </p>
                    <p>
                        <span class="font-medium">Tamanho:</span> 
                        <?= formatarTamanho($tamanho) ?>
                    </p>
                    <p>
                        <span class="font-medium">Enviado em:</span> 
                        <?= formatarDataHora($arquivo['created_at']) ?>
                    </p>
                    <?php if ($arquivo['usuario_nome']): ?>
                    <p>
                        <span class="font-medium">Por:</span> 
                        <?= htmlspecialchars($arquivo['usuario_nome']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- Ações -->
                <div class="mt-4 flex gap-2">
                    <a href="download.php?tipo=pedido&id=<?= $arquivo['id'] ?>" 
                       class="flex-1 px-3 py-2 bg-blue-600 text-white text-center rounded hover:bg-blue-700 text-sm">
                        Baixar
                    </a>
                    
                    <?php if ($_SESSION['user_perfil'] === 'gestor' || $arquivo['usuario_id'] == $_SESSION['user_id']): ?>
                    <button onclick="excluirArquivo(<?= $arquivo['id'] ?>, '<?= htmlspecialchars($arquivo['nome_arquivo']) ?>')"
                            class="px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Upload -->
<div id="modalUpload" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Enviar Arquivo</h3>
            
            <form id="formUpload" action="arte_upload.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Pedido <span class="text-red-500">*</span>
                    </label>
                    <select name="pedido_id" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Selecione um pedido...</option>
                        <?php foreach ($pedidos as $pedido): ?>
                        <option value="<?= $pedido['id'] ?>">
                            #<?= htmlspecialchars($pedido['numero']) ?> - <?= htmlspecialchars($pedido['cliente_nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Arquivo <span class="text-red-500">*</span>
                    </label>
                    <input type="file" 
                           name="arquivo" 
                           required
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.ai,.cdr,.psd"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-1">
                        Formatos aceitos: PDF, JPG, PNG, DOC, DOCX, AI, CDR, PSD (máx. 50MB)
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Descrição
                    </label>
                    <textarea name="descricao" 
                              rows="3"
                              placeholder="Informações sobre o arquivo..."
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" 
                            onclick="fecharModalUpload()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
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

<!-- Modal de Visualização de Imagem -->
<div id="modalImagem" class="fixed inset-0 bg-black bg-opacity-90 hidden z-50" onclick="fecharModalImagem()">
    <div class="h-full flex items-center justify-center p-4">
        <img id="imagemModal" src="" alt="" class="max-w-full max-h-full">
        
        <button onclick="fecharModalImagem()" 
                class="absolute top-4 right-4 text-white hover:text-gray-300">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

<script>
function abrirModalUpload() {
    document.getElementById('modalUpload').classList.remove('hidden');
}

function fecharModalUpload() {
    document.getElementById('modalUpload').classList.add('hidden');
}

function abrirModalImagem(src, alt) {
    document.getElementById('imagemModal').src = src;
    document.getElementById('imagemModal').alt = alt;
    document.getElementById('modalImagem').classList.remove('hidden');
}

function fecharModalImagem() {
    document.getElementById('modalImagem').classList.add('hidden');
}

function excluirArquivo(id, nome) {
    if (confirm(`Deseja realmente excluir o arquivo "${nome}"?`)) {
        fetch('arte_excluir.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro ao excluir arquivo');
            console.error(error);
        });
    }
}

// Fechar modais ao clicar fora
document.getElementById('modalUpload').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalUpload();
    }
});

// Prevenir fechamento ao clicar na imagem
document.getElementById('imagemModal').addEventListener('click', function(e) {
    e.stopPropagation();
});
</script>

<?php include '../views/_footer.php'; ?>