<?php
require_once '../../../app/config.php';
require_once '../../../app/auth.php';
require_once '../../../app/functions.php';

requireLogin();
requireRole(['arte_finalista', 'gestor', 'vendedor']);

// Função para ocultar nome do cliente
function formatarNomeCliente($nome, $telefone) {
    if (empty($nome)) return 'Cliente não identificado';
    
    $palavras = explode(' ', trim($nome));
    if (count($palavras) <= 1) return $nome;
    
    $primeiro_nome = $palavras[0];
    $telefone_limpo = preg_replace('/\D/', '', $telefone);
    $ultimos_digitos = substr($telefone_limpo, -4);
    
    return $primeiro_nome . ($ultimos_digitos ? ' ...' . $ultimos_digitos : '');
}

// Função para formatar tempo relativo
function tempoRelativo($datetime) {
    $agora = new DateTime();
    $data = new DateTime($datetime);
    $diff = $agora->diff($data);
    
    if ($diff->days > 30) {
        return $data->format('d/m/Y H:i');
    } elseif ($diff->days > 0) {
        return 'há ' . $diff->days . ' dia' . ($diff->days > 1 ? 's' : '');
    } elseif ($diff->h > 0) {
        return 'há ' . $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
    } elseif ($diff->i > 0) {
        return 'há ' . $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '');
    } else {
        return 'agora mesmo';
    }
}

// Função para processar observações HTML
function processarObservacoes($html) {
    // Remove tags HTML desnecessárias mas mantém quebras de linha
    $texto = strip_tags($html, '<br><ul><li><strong><b>');
    // Converte <br> em quebras de linha
    $texto = str_replace(['<br>', '<br/>', '<br />'], "\n", $texto);
    // Remove tags de lista mas mantém estrutura
    $texto = preg_replace('/<li[^>]*>/', '• ', $texto);
    $texto = str_replace(['</li>', '<ul>', '</ul>', '<strong>', '</strong>', '<b>', '</b>'], '', $texto);
    return nl2br(trim($texto));
}

// Validar ID do pedido
$pedido_id = validarPedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID de pedido inválido';
    redirect('../../dashboard/dashboard_arte_finalista.php');
}

// PROCESSAR AÇÕES AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'enviar_mensagem':
                $versao_id = intval($_POST['versao_id']);
                $mensagem = trim($_POST['mensagem']);
                
                if (empty($mensagem)) {
                    throw new Exception('Mensagem não pode estar vazia');
                }
                
                // Buscar comentário atual
                $stmt = $pdo->prepare("SELECT comentario_arte FROM arte_versoes WHERE id = ?");
                $stmt->execute([$versao_id]);
                $comentario_atual = $stmt->fetchColumn() ?: '';
                
                // Adicionar nova mensagem
                $timestamp = date('d/m H:i');
                $usuario = $_SESSION['user_name'] ?? 'Usuário';
                $novo_comentario = $comentario_atual;
                if (!empty($comentario_atual)) {
                    $novo_comentario .= "\n";
                }
                $novo_comentario .= "[{$timestamp} - {$usuario}]: {$mensagem}";
                
                // Atualizar com o novo comentário
                $stmt = $pdo->prepare("UPDATE arte_versoes SET comentario_arte = ? WHERE id = ?");
                $stmt->execute([$novo_comentario, $versao_id]);
                
                registrarLog('arte_mensagem', "Mensagem enviada na versão #$versao_id");
                echo json_encode(['success' => true]);
                break;
                
            case 'aprovar_versao':
                $versao_id = intval($_POST['versao_id']);
                $comentario = trim($_POST['comentario']) ?: 'Arte aprovada!';
                
                $pdo->beginTransaction();
                
                // Buscar comentário atual
                $stmt = $pdo->prepare("SELECT comentario_cliente FROM arte_versoes WHERE id = ?");
                $stmt->execute([$versao_id]);
                $comentario_atual = $stmt->fetchColumn() ?: '';
                
                // Adicionar aprovação ao comentário
                $timestamp = date('d/m H:i');
                $usuario = $_SESSION['user_name'] ?? 'Usuário';
                $novo_comentario = $comentario_atual;
                if (!empty($comentario_atual)) {
                    $novo_comentario .= "\n";
                }
                $novo_comentario .= "[{$timestamp} - {$usuario}] ✅ APROVADO: {$comentario}";
                
                // Atualizar status da versão
                $stmt = $pdo->prepare("
                    UPDATE arte_versoes 
                    SET aprovada = true, reprovada = false, comentario_cliente = ?
                    WHERE id = ?
                ");
                $stmt->execute([$novo_comentario, $versao_id]);
                
                // Atualizar status do pedido
                $stmt = $pdo->prepare("
                    UPDATE pedidos 
                    SET status = 'aprovado' 
                    WHERE id = (SELECT pedido_id FROM arte_versoes WHERE id = ?)
                ");
                $stmt->execute([$versao_id]);
                
                $pdo->commit();
                registrarLog('arte_aprovada', "Versão #$versao_id aprovada");
                echo json_encode(['success' => true]);
                break;
                
            case 'solicitar_ajuste':
                $versao_id = intval($_POST['versao_id']);
                $comentario = trim($_POST['comentario']);
                
                if (empty($comentario)) {
                    throw new Exception('Por favor, descreva os ajustes necessários');
                }
                
                // Buscar comentário atual - corrigido
                $stmt = $pdo->prepare("SELECT comentario_cliente FROM arte_versoes WHERE id = ?");
                $stmt->execute([$versao_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $comentario_atual = $row['comentario_cliente'] ?? '';
                
                // Adicionar solicitação de ajuste
                $timestamp = date('d/m H:i');
                $usuario = $_SESSION['user_name'] ?? 'Usuário';
                $novo_comentario = $comentario_atual;
                if (!empty($comentario_atual)) {
                    $novo_comentario .= "\n";
                }
                $novo_comentario .= "[{$timestamp} - {$usuario}] ⚠️ AJUSTES: {$comentario}";
                
                // Atualizar status da versão
                $stmt = $pdo->prepare("
                    UPDATE arte_versoes 
                    SET reprovada = true, aprovada = false, comentario_cliente = ?
                    WHERE id = ?
                ");
                $stmt->execute([$novo_comentario, $versao_id]);
                
                registrarLog('arte_ajuste', "Ajustes solicitados na versão #$versao_id");
                echo json_encode(['success' => true]);
                break;
                
            case 'reverter_aprovacao':
                $versao_id = intval($_POST['versao_id']);
                $motivo = trim($_POST['motivo']) ?: 'Aprovação revertida';
                
                $pdo->beginTransaction();
                
                // Buscar comentário atual
                $stmt = $pdo->prepare("SELECT comentario_cliente FROM arte_versoes WHERE id = ?");
                $stmt->execute([$versao_id]);
                $comentario_atual = $stmt->fetchColumn() ?: '';
                
                // Adicionar reversão ao comentário
                $timestamp = date('d/m H:i');
                $usuario = $_SESSION['user_name'] ?? 'Usuário';
                $novo_comentario = $comentario_atual;
                if (!empty($comentario_atual)) {
                    $novo_comentario .= "\n";
                }
                $novo_comentario .= "[{$timestamp} - {$usuario}] 🔄 REVERSÃO: {$motivo}";
                
                // Reverter status da versão
                $stmt = $pdo->prepare("
                    UPDATE arte_versoes 
                    SET aprovada = false, reprovada = true, comentario_cliente = ?
                    WHERE id = ?
                ");
                $stmt->execute([$novo_comentario, $versao_id]);
                
                // Atualizar status do pedido de volta para arte
                $stmt = $pdo->prepare("
                    UPDATE pedidos 
                    SET status = 'arte' 
                    WHERE id = (SELECT pedido_id FROM arte_versoes WHERE id = ?)
                ");
                $stmt->execute([$versao_id]);
                
                $pdo->commit();
                registrarLog('arte_reversao', "Aprovação revertida na versão #$versao_id");
                echo json_encode(['success' => true]);
                break;
                
            case 'pegar_os':
                $stmt = $pdo->prepare("
                    INSERT INTO pedido_arte (pedido_id, arte_finalista_id) 
                    VALUES (?, ?)
                    ON CONFLICT (pedido_id) 
                    DO UPDATE SET arte_finalista_id = ?, updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$pedido_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                
                registrarLog('arte_os_pegada', "Arte-finalista pegou OS do pedido #$pedido_id");
                echo json_encode(['success' => true]);
                break;
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Buscar dados do pedido
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.numero,
            p.prazo_entrega,
            p.status,
            p.urgente,
            p.observacoes,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.whatsapp as cliente_whatsapp,
            pa.arte_finalista_id,
            af.nome as arte_finalista_nome,
            v.nome as vendedor_nome
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios af ON pa.arte_finalista_id = af.id
        LEFT JOIN usuarios v ON p.vendedor_id = v.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar pedido: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao carregar dados do pedido';
    redirect('../../dashboard/dashboard_arte_finalista.php');
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
    redirect('../../dashboard/dashboard_arte_finalista.php');
}

// Buscar itens do pedido
try {
    $stmt = $pdo->prepare("
        SELECT 
            pi.descricao,
            pi.quantidade,
            pi.observacoes,
            pc.nome as produto_nome
        FROM pedido_itens pi
        LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar itens do pedido: " . $e->getMessage());
    $itens = [];
}

// Buscar arquivos do cliente
try {
    $stmt = $pdo->prepare("
        SELECT * FROM pedido_arquivos 
        WHERE pedido_id = ? 
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$pedido_id]);
    $arquivos_cliente = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar arquivos do cliente: " . $e->getMessage());
    $arquivos_cliente = [];
}

// Buscar todas as versões de arte
try {
    $stmt = $pdo->prepare("
        SELECT 
            av.*,
            u.nome as usuario_nome,
            u.perfil as usuario_perfil
        FROM arte_versoes av
        LEFT JOIN usuarios u ON av.usuario_id = u.id
        WHERE av.pedido_id = ?
        ORDER BY av.versao DESC
    ");
    $stmt->execute([$pedido_id]);
    $versoes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar versões de arte: " . $e->getMessage());
    $versoes = [];
}

// Calcular dias até entrega
$prazo = new DateTime($pedido['prazo_entrega']);
$hoje = new DateTime();
$diff = $hoje->diff($prazo);
$dias_restantes = $diff->invert ? -$diff->days : $diff->days;

$titulo = 'OS #' . $pedido['numero'] . ' - Arte Digital';
$breadcrumb = [
    ['label' => 'Artes', 'url' => 'dashboard_arte_finalista.php'],
    ['label' => 'OS #' . $pedido['numero']]
];

include '../../../views/layouts/_header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="/public/css/font-awesome/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="/public/css/font-awesome/all.min.css"></noscript>
    <style>
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
        }
        
        .preview-thumb {
            aspect-ratio: 1;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 0.5rem;
        }
        
        .preview-thumb:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(147, 51, 234, 0.3);
        }
        
        .bee-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .message-bubble {
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .status-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        /* Scrollbar customizado */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #9333ea;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #7c3aed;
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="max-w-7xl mx-auto p-4 lg:p-6">
    <!-- Header Roxo -->
    <div class="bg-gradient-to-r from-purple-600 to-purple-700 rounded-lg shadow-lg mb-6 p-6 text-white">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex-1">
                <h1 class="text-2xl font-bold mb-2 flex items-center gap-3">
                    <i class="fas fa-palette"></i>
                    OS #<?= htmlspecialchars($pedido['numero']) ?>
                    <?php if ($pedido['urgente']): ?>
                        <span class="px-3 py-1 bg-red-500 text-white rounded-full text-xs animate-pulse">URGENTE</span>
                    <?php endif; ?>
                </h1>
                <div class="flex flex-wrap gap-4 text-purple-100 text-sm">
                    <span class="flex items-center gap-2">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars(formatarNomeCliente($pedido['cliente_nome'], $pedido['cliente_telefone'])) ?>
                    </span>
                    <span class="flex items-center gap-2">
                        <i class="fas fa-calendar"></i>
                        Prazo: <?= formatarData($pedido['prazo_entrega']) ?>
                        <?php if ($dias_restantes < 0): ?>
                            <span class="text-red-300 font-bold">(<?= abs($dias_restantes) ?> dias atrasado)</span>
                        <?php elseif ($dias_restantes <= 3): ?>
                            <span class="text-yellow-300 font-bold">(<?= $dias_restantes ?> dias)</span>
                        <?php else: ?>
                            <span class="text-purple-200">(<?= $dias_restantes ?> dias)</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($pedido['arte_finalista_nome']): ?>
                    <span class="flex items-center gap-2">
                        <i class="fas fa-user-check"></i>
                        Arte-finalista: <?= htmlspecialchars($pedido['arte_finalista_nome']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <button onclick="window.location.href='dashboard_arte_finalista.php'" 
                    class="px-4 py-2 bg-white/20 backdrop-blur text-white rounded-lg hover:bg-white/30 transition flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </button>
        </div>
    </div>

    <!-- Grid Principal -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Coluna Esquerda (4 colunas) -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- Card: Itens do Pedido -->
            <div class="bg-white rounded-lg shadow-sm p-5">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-list text-purple-600"></i>
                    Itens para produzir arte
                </h2>
                
                <div class="space-y-3">
                    <?php foreach ($itens as $item): ?>
                    <div class="bg-purple-50 p-3 rounded-lg border border-purple-100">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 text-sm">
                                    <?= htmlspecialchars($item['descricao']) ?>
                                </p>
                                <?php if ($item['produto_nome']): ?>
                                    <p class="text-xs text-gray-600 mt-1">
                                        Produto: <?= htmlspecialchars($item['produto_nome']) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($item['observacoes']): ?>
                                    <p class="text-xs text-gray-500 mt-2 italic">
                                        <?= nl2br(htmlspecialchars($item['observacoes'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span class="text-gray-700 font-bold text-sm ml-3 bg-white px-2 py-1 rounded">
                                Qtd: <?= $item['quantidade'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Card: Observações Importantes -->
            <?php if ($pedido['observacoes']): ?>
            <div class="bg-white rounded-lg shadow-sm p-5">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    Observações importantes
                </h2>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                    <p class="text-sm font-semibold text-gray-800 mb-2">Instruções do vendedor:</p>
                    <div class="text-sm text-gray-700 space-y-1">
                        <?= processarObservacoes($pedido['observacoes']) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Card: Arquivos de Referência com Preview -->
            <?php if (!empty($arquivos_cliente)): ?>
            <div class="bg-white rounded-lg shadow-sm p-5">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-folder-open text-purple-600"></i>
                    Arquivos do cliente
                </h2>
                
                <!-- Grid de previews para imagens -->
                <div class="preview-grid mb-4">
                    <?php 
                    $tem_imagens = false;
                    foreach ($arquivos_cliente as $arquivo): 
                        $ext = strtolower(pathinfo($arquivo['nome_arquivo'], PATHINFO_EXTENSION));
                        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        
                        if ($is_image):
                            $tem_imagens = true;
                            $caminho = '../' . ($arquivo['caminho'] ?? $arquivo['caminho_arquivo']);
                    ?>
                        <img src="<?= htmlspecialchars($caminho) ?>" 
                             alt="<?= htmlspecialchars($arquivo['nome_arquivo']) ?>"
                             onclick="abrirImagemModal('<?= htmlspecialchars($caminho) ?>', '<?= htmlspecialchars($arquivo['nome_arquivo']) ?>')"
                             class="preview-thumb border-2 border-gray-200 hover:border-purple-400"
                             title="Clique para ampliar">
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <!-- Lista de todos os arquivos -->
                <div class="space-y-2 <?= $tem_imagens ? 'border-t pt-3' : '' ?>">
                    <?php foreach ($arquivos_cliente as $arquivo): 
                        $ext = strtolower(pathinfo($arquivo['nome_arquivo'], PATHINFO_EXTENSION));
                        $icone = 'file';
                        $cor_icone = 'gray-400';
                        
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $icone = 'image';
                            $cor_icone = 'purple-500';
                        } elseif ($ext === 'pdf') {
                            $icone = 'file-pdf';
                            $cor_icone = 'red-500';
                        }
                    ?>
                    <a href="download.php?tipo=pedido&id=<?= $arquivo['id'] ?>" 
                       class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition group">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-<?= $icone ?> text-<?= $cor_icone ?>"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-700 group-hover:text-purple-600">
                                    <?= htmlspecialchars($arquivo['nome_arquivo']) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= number_format(($arquivo['tamanho'] ?? 0) / 1024, 1) ?> KB • 
                                    <?= formatarDataHora($arquivo['uploaded_at']) ?>
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-download text-gray-400 group-hover:text-purple-600"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Card: Especificações -->
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-4">
                <h3 class="font-semibold text-gray-700 mb-3 text-sm">Especificações técnicas:</h3>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="bg-white p-2 rounded">
                        <span class="text-gray-500">Vendedor:</span>
                        <p class="font-semibold text-gray-700"><?= htmlspecialchars($pedido['vendedor_nome']) ?></p>
                    </div>
                    <div class="bg-white p-2 rounded">
                        <span class="text-gray-500">Resolução:</span>
                        <p class="font-semibold text-gray-700">300 DPI</p>
                    </div>
                    <div class="bg-white p-2 rounded">
                        <span class="text-gray-500">Formato:</span>
                        <p class="font-semibold text-gray-700">PDF/AI/CDR</p>
                    </div>
                    <div class="bg-white p-2 rounded">
                        <span class="text-gray-500">Cores:</span>
                        <p class="font-semibold text-gray-700">CMYK</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna Direita (8 colunas) -->
        <div class="lg:col-span-8 space-y-6">
            
            <!-- Card: Upload de Nova Versão -->
            <?php if ($pedido['arte_finalista_id'] == $_SESSION['user_id'] || $_SESSION['user_perfil'] === 'gestor'): ?>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-upload text-purple-600"></i>
                    Enviar nova versão
                </h2>
                
                <form id="formUpload" method="POST" action="arte_upload.php" enctype="multipart/form-data">
                    <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
                    
                    <div id="dropZone" 
                         class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-purple-400 transition cursor-pointer bg-gradient-to-br from-gray-50 to-white">
                        <i class="fas fa-cloud-upload-alt text-5xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600 mb-2">Arraste arquivos aqui ou clique para enviar</p>
                        <p class="text-xs text-gray-500">PDF, JPG, PNG, AI, CDR (máx. 50MB)</p>
                        <input type="file" id="fileInput" name="arquivo" class="hidden" 
                               accept=".pdf,.jpg,.jpeg,.png,.ai,.cdr,.psd,.eps,.svg">
                    </div>
                    
                    <div id="filePreview" class="hidden mt-4 p-4 bg-purple-50 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-file text-purple-600 text-2xl"></i>
                                <div>
                                    <p class="font-medium text-gray-800" id="fileName"></p>
                                    <p class="text-sm text-gray-600" id="fileSize"></p>
                                </div>
                            </div>
                            <button type="button" onclick="limparArquivo()" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Comentários sobre esta versão:
                        </label>
                        <textarea name="comentario_arte" 
                                  rows="3" 
                                  placeholder="Descreva as alterações realizadas..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"></textarea>
                    </div>
                    
                    <button type="submit" 
                            class="w-full mt-4 px-4 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg font-semibold hover:from-purple-700 hover:to-purple-800 transition flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        Enviar arte
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Card: Versões de Arte -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-history text-purple-600"></i>
                    Versões de arte
                    <?php if (count($versoes) > 0): ?>
                        <span class="text-sm text-gray-500 font-normal">(<?= count($versoes) ?> versões)</span>
                    <?php endif; ?>
                </h2>
                
                <?php if (empty($versoes)): ?>
                <!-- Estado vazio com ilustração -->
                <div class="text-center py-12">
                    <div class="bee-animation inline-block mb-6">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="50" r="25" fill="#FCD34D"/>
                            <ellipse cx="60" cy="55" rx="30" ry="20" fill="#1F2937" opacity="0.8"/>
                            <circle cx="50" cy="45" r="8" fill="white"/>
                            <circle cx="70" cy="45" r="8" fill="white"/>
                            <circle cx="50" cy="45" r="5" fill="black"/>
                            <circle cx="70" cy="45" r="5" fill="black"/>
                            <path d="M 30 50 Q 20 40 25 30" stroke="#1F2937" stroke-width="2" fill="none"/>
                            <path d="M 90 50 Q 100 40 95 30" stroke="#1F2937" stroke-width="2" fill="none"/>
                            <ellipse cx="35" cy="60" rx="15" ry="8" fill="#60A5FA" opacity="0.5"/>
                            <ellipse cx="85" cy="60" rx="15" ry="8" fill="#60A5FA" opacity="0.5"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Nenhuma arte enviada ainda</h3>
                    <p class="text-gray-500">Aguardando primeira versão do arte-finalista</p>
                </div>
                <?php else: ?>
                
                <!-- Timeline de versões -->
                <div class="space-y-4">
                    <?php foreach ($versoes as $index => $versao): 
                        $isAprovada = $versao['aprovada'];
                        $isReprovada = $versao['reprovada'];
                        $status = $isAprovada ? 'APROVADA' : ($isReprovada ? 'AJUSTES' : 'AGUARDANDO');
                        $statusColor = $isAprovada ? 'bg-green-500' : ($isReprovada ? 'bg-red-500' : 'bg-yellow-500');
                        $borderColor = $isAprovada ? 'border-green-200 bg-green-50' : ($isReprovada ? 'border-red-200 bg-red-50' : 'border-gray-200');
                        
                        $ext = strtolower(pathinfo($versao['arquivo_nome'], PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        $caminho = '../uploads/arte_versoes/' . basename($versao['arquivo_caminho']);
                    ?>
                    <div class="border rounded-lg p-4 <?= $borderColor ?> message-bubble">
                        <!-- Header da versão -->
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                    Versão <?= $versao['versao'] ?>
                                    <span class="px-2 py-1 <?= $statusColor ?> text-white text-xs rounded-full status-badge">
                                        <?= $status ?>
                                    </span>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="far fa-clock mr-1"></i>
                                    Enviada <?= tempoRelativo($versao['created_at']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="far fa-file mr-1"></i>
                                    <?= htmlspecialchars($versao['arquivo_nome']) ?>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($isImage): ?>
                                <button onclick="abrirImagemModal('<?= $caminho ?>', 'Versão <?= $versao['versao'] ?>')" 
                                        class="px-3 py-1.5 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 transition">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                                <?php endif; ?>
                                <a href="download.php?tipo=arte&id=<?= $versao['id'] ?>" 
                                   class="px-3 py-1.5 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition">
                                    <i class="fas fa-download"></i> Baixar
                                </a>
                            </div>
                        </div>
                        
                        <!-- Preview da imagem (se for imagem) -->
                        <?php if ($isImage): ?>
                        <div class="mb-3">
                            <img src="<?= $caminho ?>" 
                                 alt="Versão <?= $versao['versao'] ?>"
                                 onclick="abrirImagemModal('<?= $caminho ?>', 'Versão <?= $versao['versao'] ?>')"
                                 class="rounded-lg border cursor-pointer hover:opacity-90 transition max-h-48 mx-auto">
                        </div>
                        <?php endif; ?>
                        
                        <!-- Comentários e conversação -->
                        <div class="space-y-2 max-h-60 overflow-y-auto custom-scrollbar">
                            <?php if ($versao['comentario_arte']): ?>
                            <div class="bg-white p-3 rounded-lg border-l-4 border-purple-400">
                                <p class="text-xs font-semibold text-purple-600 mb-1">
                                    <i class="fas fa-palette mr-1"></i>
                                    Arte-finalista • <?= tempoRelativo($versao['created_at']) ?>
                                </p>
                                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($versao['comentario_arte'])) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($versao['comentario_cliente']): ?>
                            <div class="bg-white p-3 rounded-lg border-l-4 <?= $isAprovada ? 'border-green-400' : 'border-orange-400' ?>">
                                <p class="text-xs font-semibold <?= $isAprovada ? 'text-green-600' : 'text-orange-600' ?> mb-1">
                                    <i class="fas fa-user mr-1"></i>
                                    Vendedor/Cliente • Feedback
                                </p>
                                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($versao['comentario_cliente'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Área de interação -->
                        <?php if ($isAprovada && ($_SESSION['user_perfil'] === 'vendedor' || $_SESSION['user_perfil'] === 'gestor')): ?>
                        <!-- Versão aprovada - opção de reverter -->
                        <div class="mt-4 pt-4 border-t bg-green-100 -m-4 mt-4 p-4 rounded-b-lg">
                            <div class="flex items-center justify-between">
                                <p class="text-green-700 font-semibold">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Esta versão foi aprovada!
                                </p>
                                <button onclick="reverterAprovacao(<?= $versao['id'] ?>)" 
                                        class="px-3 py-1.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium transition"
                                        title="Reverter aprovação">
                                    <i class="fas fa-undo mr-1"></i> Reverter para Ajustes
                                </button>
                            </div>
                        </div>
                        <?php elseif (!$isAprovada && ($_SESSION['user_perfil'] === 'vendedor' || $_SESSION['user_perfil'] === 'gestor')): ?>
                        <!-- Versão não aprovada - opções normais -->
                        <div class="mt-4 pt-4 border-t flex gap-2">
                            <input type="text" 
                                   id="msg-<?= $versao['id'] ?>"
                                   placeholder="Digite feedback ou comentário..." 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            
                            <button onclick="aprovarVersao(<?= $versao['id'] ?>)" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium transition"
                                    title="Aprovar esta versão">
                                <i class="fas fa-check mr-1"></i> Aprovar
                            </button>
                            
                            <button onclick="solicitarAjuste(<?= $versao['id'] ?>)" 
                                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium transition"
                                    title="Solicitar ajustes">
                                <i class="fas fa-edit mr-1"></i> Ajustes
                            </button>
                        </div>
                        <?php elseif ($isAprovada): ?>
                        <!-- Versão aprovada - sem opções (outros perfis) -->
                        <div class="mt-4 pt-4 border-t bg-green-100 -m-4 mt-4 p-4 rounded-b-lg">
                            <p class="text-center text-green-700 font-semibold">
                                <i class="fas fa-check-circle mr-2"></i>
                                Esta versão foi aprovada!
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Imagem Ampliada -->
<div id="modalImagem" class="hidden fixed inset-0 bg-black bg-opacity-95 z-50 flex items-center justify-center p-4">
    <div class="relative max-w-7xl max-h-[90vh] w-full h-full flex items-center justify-center">
        <!-- Controles do modal -->
        <div class="absolute top-4 right-4 flex gap-2 z-10">
            <button onclick="rotacionarImagem()" 
                    class="p-2 bg-white/10 backdrop-blur text-white rounded-lg hover:bg-white/20 transition">
                <i class="fas fa-redo"></i>
            </button>
            <button onclick="fecharModal()" 
                    class="p-2 bg-white/10 backdrop-blur text-white rounded-lg hover:bg-white/20 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Título da imagem -->
        <div class="absolute top-4 left-4 text-white">
            <h3 id="tituloImagem" class="text-lg font-semibold"></h3>
        </div>
        
        <!-- Container da imagem -->
        <div class="relative">
            <img id="imagemModal" 
                 src="" 
                 alt="Visualização ampliada" 
                 class="max-w-full max-h-[85vh] object-contain transition-transform duration-300">
        </div>
        
        <!-- Controles de navegação (se houver múltiplas imagens) -->
        <button onclick="imagemAnterior()" 
                class="absolute left-4 p-3 bg-white/10 backdrop-blur text-white rounded-full hover:bg-white/20 transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button onclick="proximaImagem()" 
                class="absolute right-4 p-3 bg-white/10 backdrop-blur text-white rounded-full hover:bg-white/20 transition">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<script>
// Variáveis globais
let rotacaoAtual = 0;
let imagensGaleria = [];
let imagemAtualIndex = 0;

// Configurar drag and drop
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    
    // Click para selecionar arquivo
    dropZone.addEventListener('click', () => fileInput.click());
    
    // Eventos de drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-purple-400', 'bg-purple-50');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-purple-400', 'bg-purple-50');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-purple-400', 'bg-purple-50');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            mostrarPreviewArquivo(files[0]);
        }
    });
    
    // Mudança no input de arquivo
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            mostrarPreviewArquivo(this.files[0]);
        }
    });
    
    // Coletar todas as imagens da galeria
    document.querySelectorAll('.preview-thumb').forEach((img, index) => {
        imagensGaleria.push({
            src: img.src,
            titulo: img.alt
        });
    });
});

// Mostrar preview do arquivo selecionado
function mostrarPreviewArquivo(file) {
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const filePreview = document.getElementById('filePreview');
    
    fileName.textContent = file.name;
    fileSize.textContent = formatarTamanhoArquivo(file.size);
    filePreview.classList.remove('hidden');
    
    // Se for imagem, poderia mostrar preview
    if (file.type.startsWith('image/')) {
        // Implementar preview se necessário
    }
}

// Limpar arquivo selecionado
function limparArquivo() {
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreview').classList.add('hidden');
}

// Formatar tamanho de arquivo
function formatarTamanhoArquivo(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Abrir modal de imagem
function abrirImagemModal(src, titulo) {
    const modal = document.getElementById('modalImagem');
    const img = document.getElementById('imagemModal');
    const tituloEl = document.getElementById('tituloImagem');
    
    img.src = src;
    tituloEl.textContent = titulo || 'Visualização';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Resetar rotação
    rotacaoAtual = 0;
    img.style.transform = 'rotate(0deg)';
    
    // Encontrar índice da imagem atual na galeria
    imagemAtualIndex = imagensGaleria.findIndex(item => item.src === src);
}

// Fechar modal
function fecharModal() {
    document.getElementById('modalImagem').classList.add('hidden');
    document.getElementById('imagemModal').src = '';
    document.body.style.overflow = 'auto';
}

// Rotacionar imagem
function rotacionarImagem() {
    rotacaoAtual += 90;
    if (rotacaoAtual >= 360) rotacaoAtual = 0;
    document.getElementById('imagemModal').style.transform = `rotate(${rotacaoAtual}deg)`;
}

// Navegação de imagens
function imagemAnterior() {
    if (imagensGaleria.length > 0 && imagemAtualIndex > 0) {
        imagemAtualIndex--;
        const item = imagensGaleria[imagemAtualIndex];
        document.getElementById('imagemModal').src = item.src;
        document.getElementById('tituloImagem').textContent = item.titulo;
    }
}

function proximaImagem() {
    if (imagensGaleria.length > 0 && imagemAtualIndex < imagensGaleria.length - 1) {
        imagemAtualIndex++;
        const item = imagensGaleria[imagemAtualIndex];
        document.getElementById('imagemModal').src = item.src;
        document.getElementById('tituloImagem').textContent = item.titulo;
    }
}

// Aprovar versão
function aprovarVersao(versaoId) {
    const input = document.getElementById('msg-' + versaoId);
    const comentario = input.value.trim() || 'Arte aprovada!';
    
    if (confirm('Aprovar esta versão da arte?\n\nComentário: ' + comentario)) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=aprovar_versao&versao_id=' + versaoId + '&comentario=' + encodeURIComponent(comentario)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Arte aprovada com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro: ' + (data.message || 'Erro ao aprovar'));
            }
        })
        .catch(err => {
            alert('❌ Erro de conexão');
            console.error(err);
        });
    }
}

// Solicitar ajuste
function solicitarAjuste(versaoId) {
    const input = document.getElementById('msg-' + versaoId);
    let comentario = input.value.trim();
    
    if (!comentario) {
        comentario = prompt('⚠️ Descreva os ajustes necessários:');
        if (!comentario) return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=solicitar_ajuste&versao_id=' + versaoId + '&comentario=' + encodeURIComponent(comentario)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('📝 Solicitação de ajuste enviada!');
            location.reload();
        } else {
            alert('❌ Erro: ' + (data.message || 'Erro ao solicitar ajuste'));
        }
    })
    .catch(err => {
        alert('❌ Erro de conexão');
        console.error(err);
    });
}

// Reverter aprovação
function reverterAprovacao(versaoId) {
    const motivo = prompt('🔄 Por que deseja reverter a aprovação?\n\nDescreva o motivo:');
    
    if (!motivo) {
        if (motivo === null) return; // Cancelou
        alert('Por favor, informe o motivo da reversão');
        return;
    }
    
    if (confirm('Confirma reverter a aprovação desta arte?\n\nA arte voltará para ajustes.')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=reverter_aprovacao&versao_id=' + versaoId + '&motivo=' + encodeURIComponent(motivo)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('🔄 Aprovação revertida! Arte voltou para ajustes.');
                location.reload();
            } else {
                alert('❌ Erro: ' + (data.message || 'Erro ao reverter aprovação'));
            }
        })
        .catch(err => {
            alert('❌ Erro de conexão');
            console.error(err);
        });
    }
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('modalImagem');
    
    if (!modal.classList.contains('hidden')) {
        switch(e.key) {
            case 'Escape':
                fecharModal();
                break;
            case 'ArrowLeft':
                imagemAnterior();
                break;
            case 'ArrowRight':
                proximaImagem();
                break;
            case 'r':
            case 'R':
                rotacionarImagem();
                break;
        }
    }
});

// Auto-refresh para ver novas mensagens (opcional)
if (window.location.hash !== '#no-refresh') {
    setTimeout(function() {
        if (!document.hidden && !document.querySelector('#modalImagem:not(.hidden)')) {
            location.reload();
        }
    }, 60000); // Refresh a cada 60 segundos
}
</script>

</body>
</html>