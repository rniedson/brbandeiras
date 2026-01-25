<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: catalogo.php');
    exit;
}

$produto_id = $_POST['produto_id'] ?? null;

if (!$produto_id) {
    $_SESSION['erro'] = 'ID do produto não informado';
    header('Location: catalogo.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar produto atual
    $stmt = $pdo->prepare("SELECT * FROM produtos_catalogo WHERE id = ?");
    $stmt->execute([$produto_id]);
    $produto_atual = $stmt->fetch();
    
    if (!$produto_atual) {
        throw new Exception('Produto não encontrado');
    }
    
    // Validar dados obrigatórios
    $codigo = trim($_POST['codigo'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $categoria_id = $_POST['categoria_id'] ?? null;
    $preco = floatval($_POST['preco'] ?? 0);
    $unidade_venda = $_POST['unidade_venda'] ?? 'UN';
    $tempo_producao = intval($_POST['tempo_producao'] ?? 1);
    
    if (!$codigo || !$nome || !$categoria_id || $preco <= 0) {
        throw new Exception('Preencha todos os campos obrigatórios');
    }
    
    // Verificar se código mudou e já existe
    if ($codigo !== $produto_atual['codigo']) {
        $stmt = $pdo->prepare("SELECT id FROM produtos_catalogo WHERE codigo = ? AND id != ?");
        $stmt->execute([$codigo, $produto_id]);
        if ($stmt->fetch()) {
            throw new Exception("Código '$codigo' já está em uso por outro produto");
        }
    }
    
    // Processar especificações técnicas
    $especificacoes = [];
    if (isset($_POST['especificacoes']) && is_array($_POST['especificacoes'])) {
        foreach ($_POST['especificacoes'] as $spec) {
            if (!empty($spec['nome']) && !empty($spec['valor'])) {
                $especificacoes[] = [
                    'nome' => trim($spec['nome']),
                    'valor' => trim($spec['valor'])
                ];
            }
        }
    }
    
    // Processar imagem principal
    $imagem_principal = $produto_atual['imagem_principal'];
    $upload_dir = UPLOAD_PATH . 'catalogo/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Remover imagem principal se solicitado
    if (isset($_POST['remover_imagem_principal']) && $_POST['remover_imagem_principal'] == '1') {
        if ($imagem_principal && file_exists('../public/' . $imagem_principal)) {
            unlink('../public/' . $imagem_principal);
        }
        $imagem_principal = null;
    }
    
    // Upload de nova imagem principal
    if (!empty($_FILES['imagem_principal']['name'])) {
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file = $_FILES['imagem_principal'];
        
        // Validar tipo de arquivo
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP');
        }
        
        // Validar tamanho (5MB)
        if ($file['size'] > 5242880) {
            throw new Exception('Imagem muito grande. Máximo 5MB');
        }
        
        // Remover imagem anterior se existir
        if ($imagem_principal && file_exists('../public/' . $imagem_principal)) {
            unlink('../public/' . $imagem_principal);
        }
        
        // Gerar nome único
        $extensao = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nome_arquivo = $codigo . '_' . uniqid() . '.' . $extensao;
        $caminho_completo = $upload_dir . $nome_arquivo;
        
        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $caminho_completo)) {
            throw new Exception('Erro ao fazer upload da imagem');
        }
        
        // Redimensionar imagem se necessário
        redimensionarImagem($caminho_completo, 800, 800);
        
        $imagem_principal = 'uploads/catalogo/' . $nome_arquivo;
    }
    
    // Atualizar produto
    $stmt = $pdo->prepare("
        UPDATE produtos_catalogo SET
            codigo = ?,
            nome = ?,
            descricao = ?,
            categoria_id = ?,
            preco = ?,
            preco_promocional = ?,
            unidade_venda = ?,
            tempo_producao = ?,
            estoque_disponivel = ?,
            imagem_principal = ?,
            especificacoes = ?,
            tags = ?,
            ativo = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([
        $codigo,
        $nome,
        $_POST['descricao'] ?: null,
        $categoria_id,
        $preco,
        $_POST['preco_promocional'] ?: null,
        $unidade_venda,
        $tempo_producao,
        isset($_POST['estoque_disponivel']) ? 1 : 0,
        $imagem_principal,
        json_encode($especificacoes),
        $_POST['tags'] ?: null,
        isset($_POST['ativo']) ? 1 : 0,
        $produto_id
    ]);
    
    // Remover imagens adicionais marcadas
    if (isset($_POST['imagens_remover']) && is_array($_POST['imagens_remover'])) {
        foreach ($_POST['imagens_remover'] as $img_id) {
            if ($img_id) {
                // Buscar caminho da imagem
                $stmt = $pdo->prepare("SELECT caminho FROM produtos_imagens WHERE id = ? AND produto_id = ?");
                $stmt->execute([$img_id, $produto_id]);
                $img = $stmt->fetch();
                
                if ($img) {
                    // Remover arquivo físico
                    if (file_exists('../public/' . $img['caminho'])) {
                        unlink('../public/' . $img['caminho']);
                    }
                    
                    // Remover do banco
                    $stmt = $pdo->prepare("DELETE FROM produtos_imagens WHERE id = ?");
                    $stmt->execute([$img_id]);
                }
            }
        }
    }
    
    // Processar novas imagens adicionais
    if (!empty($_FILES['imagens_adicionais']['name'][0])) {
        // Buscar última ordem
        $stmt = $pdo->prepare("SELECT MAX(ordem) FROM produtos_imagens WHERE produto_id = ?");
        $stmt->execute([$produto_id]);
        $ordem = ($stmt->fetchColumn() ?: 0) + 1;
        
        $stmt_img = $pdo->prepare("
            INSERT INTO produtos_imagens (produto_id, caminho, descricao, ordem) 
            VALUES (?, ?, ?, ?)
        ");
        
        for ($i = 0; $i < count($_FILES['imagens_adicionais']['name']); $i++) {
            if ($_FILES['imagens_adicionais']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['imagens_adicionais']['name'][$i],
                    'type' => $_FILES['imagens_adicionais']['type'][$i],
                    'tmp_name' => $_FILES['imagens_adicionais']['tmp_name'][$i],
                    'size' => $_FILES['imagens_adicionais']['size'][$i]
                ];
                
                // Validar tipo
                if (!in_array($file['type'], $allowed_types)) {
                    continue;
                }
                
                // Validar tamanho
                if ($file['size'] > 5242880) {
                    continue;
                }
                
                // Gerar nome único
                $extensao = pathinfo($file['name'], PATHINFO_EXTENSION);
                $nome_arquivo = $codigo . '_' . $ordem . '_' . uniqid() . '.' . $extensao;
                $caminho_completo = $upload_dir . $nome_arquivo;
                
                if (move_uploaded_file($file['tmp_name'], $caminho_completo)) {
                    // Redimensionar
                    redimensionarImagem($caminho_completo, 800, 800);
                    
                    $stmt_img->execute([
                        $produto_id,
                        'uploads/catalogo/' . $nome_arquivo,
                        null,
                        $ordem
                    ]);
                    $ordem++;
                }
            }
        }
    }
    
    // Registrar mudanças no log
    $mudancas = [];
    if ($produto_atual['codigo'] != $codigo) $mudancas[] = "código: {$produto_atual['codigo']} → $codigo";
    if ($produto_atual['nome'] != $nome) $mudancas[] = "nome alterado";
    if ($produto_atual['preco'] != $preco) $mudancas[] = "preço: " . formatarMoeda($produto_atual['preco']) . " → " . formatarMoeda($preco);
    if ($produto_atual['ativo'] != (isset($_POST['ativo']) ? 1 : 0)) {
        $mudancas[] = isset($_POST['ativo']) ? "produto ativado" : "produto desativado";
    }
    
    $detalhes_log = "Atualizou produto: $codigo - $nome";
    if (!empty($mudancas)) {
        $detalhes_log .= " (" . implode(", ", $mudancas) . ")";
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'atualizar_produto_catalogo', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $detalhes_log,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Produto '$nome' atualizado com sucesso!";
    header('Location: catalogo_produto_detalhes.php?id=' . $produto_id);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    error_log('Erro ao atualizar produto: ' . $e->getMessage());
    
    $_SESSION['erro'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    
    header('Location: catalogo_produto_editar.php?id=' . $produto_id);
}

/**
 * Função para redimensionar imagem mantendo proporção
 */
function redimensionarImagem($caminho, $largura_max, $altura_max) {
    // Obter informações da imagem
    list($largura_orig, $altura_orig, $tipo) = getimagesize($caminho);
    
    // Calcular novas dimensões mantendo proporção
    $ratio = min($largura_max / $largura_orig, $altura_max / $altura_orig);
    
    // Se a imagem já é menor, não redimensionar
    if ($ratio >= 1) {
        return;
    }
    
    $nova_largura = round($largura_orig * $ratio);
    $nova_altura = round($altura_orig * $ratio);
    
    // Criar imagem baseada no tipo
    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $imagem_orig = imagecreatefromjpeg($caminho);
            break;
        case IMAGETYPE_PNG:
            $imagem_orig = imagecreatefrompng($caminho);
            break;
        case IMAGETYPE_GIF:
            $imagem_orig = imagecreatefromgif($caminho);
            break;
        case IMAGETYPE_WEBP:
            $imagem_orig = imagecreatefromwebp($caminho);
            break;
        default:
            return;
    }
    
    // Criar nova imagem
    $nova_imagem = imagecreatetruecolor($nova_largura, $nova_altura);
    
    // Preservar transparência para PNG e GIF
    if ($tipo == IMAGETYPE_PNG || $tipo == IMAGETYPE_GIF) {
        imagecolortransparent($nova_imagem, imagecolorallocatealpha($nova_imagem, 0, 0, 0, 127));
        imagealphablending($nova_imagem, false);
        imagesavealpha($nova_imagem, true);
    }
    
    // Redimensionar
    imagecopyresampled(
        $nova_imagem, $imagem_orig,
        0, 0, 0, 0,
        $nova_largura, $nova_altura,
        $largura_orig, $altura_orig
    );
    
    // Salvar imagem redimensionada
    switch ($tipo) {
        case IMAGETYPE_JPEG:
            imagejpeg($nova_imagem, $caminho, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($nova_imagem, $caminho, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($nova_imagem, $caminho);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($nova_imagem, $caminho, 85);
            break;
    }
    
    // Limpar memória
    imagedestroy($imagem_orig);
    imagedestroy($nova_imagem);
}