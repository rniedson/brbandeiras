<?php
require_once '../app/config.php';
require_once '../app/auth.php';

// Incluir functions.php se existir, senão usar fallback
if (file_exists('../app/functions.php')) {
    require_once '../app/functions.php';
} else {
    // Definir função mínima se não existir
    if (!function_exists('registrarLog')) {
        function registrarLog($acao, $detalhes) {
            // Função vazia para evitar erro
            error_log("Log: {$acao} - {$detalhes}");
        }
    }
}

requireLogin();

// Parâmetros da requisição
$tipo = $_GET['tipo'] ?? null;
$id = $_GET['id'] ?? null;

if (!$tipo || !$id) {
    die('Parâmetros inválidos');
}

try {
    $arquivo = null;
    $pode_baixar = false;
    
    switch ($tipo) {
        case 'arte':
            // Download de versões de arte
            $stmt = $pdo->prepare("
                SELECT 
                    av.arquivo_nome as nome, 
                    av.arquivo_caminho as caminho,
                    av.pedido_id,
                    p.vendedor_id,
                    pa.arte_finalista_id
                FROM arte_versoes av
                LEFT JOIN pedidos p ON av.pedido_id = p.id
                LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
                WHERE av.id = ?
            ");
            $stmt->execute([$id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                // Verificar permissões
                if ($_SESSION['user_perfil'] === 'gestor' || 
                    $_SESSION['user_id'] == $arquivo['vendedor_id'] ||
                    $_SESSION['user_id'] == $arquivo['arte_finalista_id'] ||
                    $_SESSION['user_perfil'] === 'producao') {
                    $pode_baixar = true;
                }
            }
            break;
            
        case 'pedido':
            // Download de arquivos de pedido
            $stmt = $pdo->prepare("
                SELECT 
                    pa.nome_arquivo as nome,
                    COALESCE(pa.caminho_arquivo, pa.caminho) as caminho,
                    p.vendedor_id,
                    p.cliente_id
                FROM pedido_arquivos pa
                LEFT JOIN pedidos p ON pa.pedido_id = p.id
                WHERE pa.id = ?
            ");
            $stmt->execute([$id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                // Verificar permissões
                if ($_SESSION['user_perfil'] === 'gestor' || 
                    $_SESSION['user_id'] == $arquivo['vendedor_id'] ||
                    $_SESSION['user_perfil'] === 'producao' ||
                    $_SESSION['user_perfil'] === 'arte_finalista') {
                    $pode_baixar = true;
                }
            }
            break;
            
        case 'producao':
            // Download de arquivos de produção/fechamento
            $stmt = $pdo->prepare("
                SELECT 
                    nome_arquivo as nome,
                    caminho_arquivo as caminho,
                    pedido_id
                FROM pedido_arquivos
                WHERE id = ? AND (
                    nome_arquivo LIKE '%fechamento%' OR 
                    nome_arquivo LIKE '%final%' OR 
                    descricao LIKE '%fechamento%' OR
                    descricao LIKE '%producao%'
                )
            ");
            $stmt->execute([$id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                // Produção e gestores podem baixar
                if ($_SESSION['user_perfil'] === 'gestor' || 
                    $_SESSION['user_perfil'] === 'producao') {
                    $pode_baixar = true;
                }
            }
            break;
            
        case 'catalogo':
            // Download de imagens do catálogo
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT('produto_', codigo, '.jpg') as nome,
                    imagem_principal as caminho
                FROM produtos_catalogo
                WHERE id = ? AND imagem_principal IS NOT NULL
            ");
            $stmt->execute([$id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                // Todos podem baixar imagens do catálogo
                $pode_baixar = true;
            }
            break;
            
        case 'orcamento':
            // Download de PDF de orçamento
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT('orcamento_', numero, '.pdf') as nome,
                    CONCAT('uploads/orcamentos/orcamento_', id, '.pdf') as caminho,
                    vendedor_id
                FROM pedidos
                WHERE id = ? AND status = 'orcamento'
            ");
            $stmt->execute([$id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                // Verificar permissões
                if ($_SESSION['user_perfil'] === 'gestor' || 
                    $_SESSION['user_id'] == $arquivo['vendedor_id']) {
                    $pode_baixar = true;
                }
            }
            break;
            
        default:
            die('Tipo de arquivo inválido');
    }
    
    // Verificar se arquivo foi encontrado e usuário tem permissão
    if (!$arquivo || !$pode_baixar) {
        die('Arquivo não encontrado ou sem permissão de acesso');
    }
    
    // Construir caminho completo
    $caminho_completo = '../public/' . $arquivo['caminho'];
    
    // Verificar se o arquivo existe fisicamente
    if (!file_exists($caminho_completo)) {
        // Tentar caminho alternativo
        $caminho_completo = '../' . $arquivo['caminho'];
        
        if (!file_exists($caminho_completo)) {
            die('Arquivo não encontrado no servidor');
        }
    }
    
    // Obter informações do arquivo
    $nome_arquivo = $arquivo['nome'];
    $tamanho_arquivo = filesize($caminho_completo);
    $mime_type = mime_content_type($caminho_completo);
    
    // Se não conseguir determinar o MIME type, usar baseado na extensão
    if (!$mime_type) {
        $ext = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
        $mime_types = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ai' => 'application/postscript',
            'cdr' => 'application/x-coreldraw',
            'psd' => 'image/vnd.adobe.photoshop',
            'eps' => 'application/postscript',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed'
        ];
        
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
    }
    
    // Registrar log de download
    try {
        if (function_exists('registrarLog')) {
            registrarLog('download_arquivo', 
                "Download: {$nome_arquivo} (Tipo: {$tipo}, ID: {$id})");
        }
    } catch (Exception $e) {
        // Ignorar erro de log
    }
    
    // Definir headers para download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . basename($nome_arquivo) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $tamanho_arquivo);
    
    // Limpar buffer de saída
    ob_clean();
    flush();
    
    // Enviar arquivo
    readfile($caminho_completo);
    exit;
    
} catch (Exception $e) {
    // Log de erro
    error_log("Erro no download: " . $e->getMessage());
    die('Erro ao processar download: ' . $e->getMessage());
}
?>