<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

// Incluir functions.php se existir, senão usar fallback
if (file_exists('../app/functions.php')) {
    require_once '../../app/functions.php';
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
    $pode_visualizar = false;
    
    switch ($tipo) {
        case 'arte':
            // Visualização de versões de arte
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
                    $pode_visualizar = true;
                }
            }
            break;
            
        case 'pedido':
            // Visualização de arquivos de pedido
            $stmt = $pdo->prepare("
                SELECT 
                    pa.nome_arquivo as nome,
                    COALESCE(pa.caminho_arquivo, pa.caminho) as caminho,
                    p.vendedor_id
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
                    $pode_visualizar = true;
                }
            }
            break;
            
        case 'catalogo':
            // Visualização de imagens do catálogo
            $stmt = $pdo->prepare("
                SELECT 
                    nome,
                    imagem_principal as caminho
                FROM produtos_catalogo
                WHERE id = ? AND imagem_principal IS NOT NULL
            ");
            $stmt->execute([$id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                // Todos podem visualizar catálogo
                $pode_visualizar = true;
            }
            break;
            
        default:
            die('Tipo de arquivo inválido');
    }
    
    // Verificar se arquivo foi encontrado e usuário tem permissão
    if (!$arquivo || !$pode_visualizar) {
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
    
    // Obter extensão e mime type
    $ext = strtolower(pathinfo($arquivo['nome'], PATHINFO_EXTENSION));
    $mime_type = mime_content_type($caminho_completo);
    
    // Se não conseguir determinar o MIME type, usar baseado na extensão
    if (!$mime_type) {
        $mime_types = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml'
        ];
        
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
    }
    
    // Registrar log de visualização
    try {
        if (function_exists('registrarLog')) {
            registrarLog('visualizar_arquivo', 
                "Visualização: {$arquivo['nome']} (Tipo: {$tipo}, ID: {$id})");
        }
    } catch (Exception $e) {
        // Ignorar erro de log
    }
    
    // Se for imagem ou PDF, exibir inline
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'])) {
        // Headers para visualização inline
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . basename($arquivo['nome']) . '"');
        header('Content-Length: ' . filesize($caminho_completo));
        
        // Cache para imagens
        if (strpos($mime_type, 'image/') === 0) {
            header('Cache-Control: public, max-age=86400'); // Cache por 1 dia
        } else {
            header('Cache-Control: no-cache, must-revalidate');
        }
        
        // Limpar buffer e enviar arquivo
        ob_clean();
        flush();
        readfile($caminho_completo);
        exit;
    } else {
        // Para outros tipos, redirecionar para download
        header("Location: download.php?tipo={$tipo}&id={$id}");
        exit;
    }
    
} catch (Exception $e) {
    // Log de erro
    error_log("Erro na visualização: " . $e->getMessage());
    die('Erro ao visualizar arquivo: ' . $e->getMessage());
}
?>