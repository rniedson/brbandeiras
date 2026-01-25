<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

// Verificar se é arte-finalista
if (!in_array($_SESSION['user_perfil'], ['gestor', 'arte_finalista'])) {
    $_SESSION['erro'] = 'Acesso negado';
    header('Location: index.php');
    exit;
}

$pedido_id = $_GET['id'] ?? null;

if (!$pedido_id) {
    header('Location: arte_finalista.php');
    exit;
}

// Buscar pedido com todas as informações
$stmt = $pdo->prepare("
    SELECT p.*, 
           c.nome as cliente_nome,
           c.telefone as cliente_telefone,
           u.nome as vendedor_nome,
           pa.arte_finalista_id,
           af.nome as arte_finalista_nome
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    LEFT JOIN pedido_arte pa ON p.id = pa.pedido_id
    LEFT JOIN usuarios af ON pa.arte_finalista_id = af.id
    WHERE p.id = ? AND p.status IN ('arte', 'producao')
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    $_SESSION['erro'] = 'Pedido não encontrado';
    header('Location: arte_finalista.php');
    exit;
}

// Buscar itens
$stmt = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
$itens = $stmt->fetchAll();

// Buscar versões
$stmt = $pdo->prepare("
    SELECT av.*, u.nome as usuario_nome
    FROM arte_versoes av
    LEFT JOIN usuarios u ON av.usuario_id = u.id
    WHERE av.pedido_id = ?
    ORDER BY av.versao DESC
");
$stmt->execute([$pedido_id]);
$versoes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OS #<?= $pedido['numero'] ?> - BR Bandeiras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #000; }
    </style>
</head>
<body class="bg-black text-white min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-900 to-green-800 border-b-4 border-yellow-500 px-8 py-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-12 bg-yellow-500"></div>
                    <h1 class="text-4xl font-bold text-white">BR BANDEIRAS</h1>
                </div>
                <div class="h-12 w-px bg-green-700"></div>
                <h2 class="text-2xl text-gray-200">Arte-Finalista</h2>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-yellow-400" id="clock">
                    <?= date('H:i') ?>
                </div>
                <div class="text-lg text-gray-300">
                    Olá, <?= htmlspecialchars($_SESSION['user_nome']) ?>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-grow p-8">
        <div class="bg-gray-900 rounded-lg p-6">
            <!-- Cabeçalho -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-white mb-1">OS #<?= $pedido['numero'] ?></h3>
                    <p class="text-gray-300"><?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                </div>
                <a href="arte_finalista.php" class="text-gray-400 hover:text-white">
                    ← Voltar
                </a>
            </div>

            <!-- Informações -->
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-800 p-4 rounded-lg">
                    <h4 class="text-gray-400 text-sm mb-2">Produtos</h4>
                    <div class="text-white">
                        <?php foreach ($itens as $item): ?>
                        <p><?= htmlspecialchars($item['descricao']) ?> (<?= $item['quantidade'] ?> un)</p>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg">
                    <h4 class="text-gray-400 text-sm mb-2">Prazo</h4>
                    <p class="<?= $pedido['urgente'] ? 'text-red-500 font-bold' : 'text-white' ?>">
                        <?= date('d/m/Y', strtotime($pedido['prazo_entrega'])) ?>
                        <?php if ($pedido['urgente']): ?>
                        <span class="ml-2 text-xs">(URGENTE)</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg">
                    <h4 class="text-gray-400 text-sm mb-2">Vendedor</h4>
                    <p class="text-white flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <?= htmlspecialchars($pedido['vendedor_nome']) ?>
                    </p>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg">
                    <h4 class="text-gray-400 text-sm mb-2">Observações</h4>
                    <p class="text-white text-sm"><?= htmlspecialchars($pedido['observacoes'] ?: 'Sem observações') ?></p>
                </div>
            </div>

            <!-- Histórico de Versões -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-xl font-bold text-white">Histórico de Versões</h4>
                    <?php if (!$pedido['arte_finalista_id'] || $pedido['arte_finalista_id'] == $_SESSION['user_id'] || $_SESSION['user_perfil'] === 'gestor'): ?>
                    <button onclick="showUploadModal()"
                            class="bg-yellow-500 hover:bg-yellow-600 text-green-900 font-bold px-4 py-2 rounded-lg flex items-center gap-2 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Nova Versão
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($versoes)): ?>
                <div class="bg-gray-800 rounded-lg p-8 text-center">
                    <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-gray-400">Nenhuma arte enviada ainda</p>
                    <?php if (!$pedido['arte_finalista_id'] && $_SESSION['user_perfil'] === 'arte_finalista'): ?>
                    <button onclick="pegarOS(<?= $pedido_id ?>)"
                            class="mt-4 text-yellow-500 hover:text-yellow-400">
                        Pegar esta OS e enviar primeira versão →
                    </button>
                    <?php elseif ($pedido['arte_finalista_id'] == $_SESSION['user_id']): ?>
                    <button onclick="showUploadModal()"
                            class="mt-4 text-yellow-500 hover:text-yellow-400">
                        Enviar primeira versão →
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($versoes as $versao): ?>
                    <div class="bg-gray-800 rounded-lg p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="text-white font-semibold">Versão <?= $versao['versao'] ?></span>
                                    <?php
                                    $status_class = '';
                                    $status_icon = '';
                                    $status_text = '';
                                    
                                    switch ($versao['status']) {
                                        case 'pendente':
                                            $status_class = 'bg-yellow-500';
                                            $status_icon = '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                                            $status_text = 'Aguardando';
                                            break;
                                        case 'aprovado':
                                            $status_class = 'bg-green-600';
                                            $status_icon = '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                                            $status_text = 'Aprovado';
                                            break;
                                        case 'reprovado':
                                            $status_class = 'bg-red-600';
                                            $status_icon = '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                                            $status_text = 'Reprovado';
                                            break;
                                    }
                                    ?>
                                    <div class="inline-flex items-center gap-1 px-2 py-1 rounded-full <?= $status_class ?>">
                                        <?= $status_icon ?>
                                        <span class="text-xs text-white font-semibold"><?= $status_text ?></span>
                                    </div>
                                    <span class="text-gray-400 text-sm"><?= date('d/m/Y H:i', strtotime($versao['created_at'])) ?></span>
                                </div>
                                <p class="text-gray-300 text-sm mb-1"><?= htmlspecialchars($versao['arquivo_nome']) ?></p>
                            </div>
                            <div class="flex gap-2">
                                <a href="<?= $versao['arquivo_caminho'] ?>" target="_blank"
                                   class="text-green-400 hover:text-green-300 p-2" title="Visualizar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                                <a href="download.php?tipo=arte&id=<?= $versao['id'] ?>"
                                   class="text-blue-400 hover:text-blue-300 p-2" title="Download">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <?php if ($versao['comentario_arte']): ?>
                        <div class="bg-gray-700 rounded p-3 mb-2">
                            <p class="text-xs text-gray-400 mb-1">Comentário do Arte-finalista:</p>
                            <p class="text-white text-sm"><?= htmlspecialchars($versao['comentario_arte']) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($versao['comentario_cliente']): ?>
                        <div class="rounded p-3 <?= $versao['status'] === 'aprovado' ? 'bg-green-900' : 'bg-red-900' ?>">
                            <p class="text-xs text-gray-300 mb-1">Feedback do Cliente:</p>
                            <p class="text-white text-sm"><?= htmlspecialchars($versao['comentario_cliente']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal de Upload -->
    <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-900 rounded-lg shadow-2xl max-w-2xl w-full mx-4">
            <div class="bg-gradient-to-r from-green-900 to-green-800 p-6 rounded-t-lg border-b-4 border-yellow-500">
                <h3 class="text-2xl font-bold text-white">
                    Enviar Arte - OS #<?= $pedido['numero'] ?>
                </h3>
            </div>

            <form id="uploadForm" action="arte_finalista_upload.php" method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
                
                <div class="mb-4">
                    <p class="text-gray-300 mb-2"><?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                    <p class="text-gray-400">
                        <?php foreach ($itens as $item): ?>
                        <?= htmlspecialchars($item['descricao']) ?> (<?= $item['quantidade'] ?> un)<br>
                        <?php endforeach; ?>
                    </p>
                </div>

                <!-- Upload Area -->
                <div class="mb-6">
                    <label class="block text-gray-400 mb-2">Arquivo da Arte</label>
                    <div class="border-2 border-dashed border-gray-600 rounded-lg p-8 text-center hover:border-green-500 transition-colors" id="dropZone">
                        <div id="fileInfo" class="hidden">
                            <svg class="w-12 h-12 text-green-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="font-semibold text-green-400" id="fileName"></p>
                            <p class="text-sm text-gray-400" id="fileSize"></p>
                        </div>
                        <div id="uploadPrompt">
                            <svg class="w-12 h-12 text-gray-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-gray-400">Arraste o arquivo ou clique para selecionar</p>
                            <p class="text-sm text-gray-500 mt-2">PDF, JPG, PNG até 50MB</p>
                        </div>
                        <input type="file" name="arquivo" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" class="hidden" required>
                    </div>
                </div>

                <!-- Comentário -->
                <div class="mb-6">
                    <label class="block text-gray-400 mb-2">Comentário sobre a arte</label>
                    <textarea name="comentario_arte"
                              class="w-full bg-gray-800 text-white p-3 rounded-lg border border-gray-700 focus:border-green-500 focus:outline-none"
                              rows="3"
                              placeholder="Descreva as alterações realizadas..."></textarea>
                </div>

                <!-- Botões -->
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="hideUploadModal()"
                            class="px-6 py-3 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="bg-yellow-500 hover:bg-yellow-600 text-green-900 font-bold px-6 py-3 rounded-lg flex items-center gap-2 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Enviar Arte
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-green-900 to-green-800 border-t-2 border-yellow-500 px-8 py-4">
        <div class="text-center text-sm text-gray-300">
            <svg class="w-4 h-4 inline-block mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
            </svg>
            Módulo Arte-Finalista • Upload de artes • Histórico de versões • Aprovações
        </div>
    </footer>

    <script>
    // Atualizar relógio
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        document.getElementById('clock').textContent = timeStr;
    }
    setInterval(updateClock, 1000);

    // Modal functions
    function showUploadModal() {
        document.getElementById('uploadModal').style.display = 'flex';
    }

    function hideUploadModal() {
        document.getElementById('uploadModal').style.display = 'none';
        document.getElementById('fileInput').value = '';
        document.getElementById('fileInfo').classList.add('hidden');
        document.getElementById('uploadPrompt').classList.remove('hidden');
    }

    // File upload handling
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-green-500');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-green-500');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-green-500');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            displayFileInfo(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            displayFileInfo(e.target.files[0]);
        }
    });

    function displayFileInfo(file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        document.getElementById('fileInfo').classList.remove('hidden');
        document.getElementById('uploadPrompt').classList.add('hidden');
    }

    // Pegar OS
    function pegarOS(id) {
        if (confirm('Deseja pegar esta OS?')) {
            fetch('arte_finalista_pegar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'pedido_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
        }
    }

    // Close modal on outside click
    document.getElementById('uploadModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideUploadModal();
        }
    });
    </script>
</body>
</html>