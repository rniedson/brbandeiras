<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: catalogo.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
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
    
    // Verificar se código já existe
    $stmt = $pdo->prepare("SELECT id FROM produtos_catalogo WHERE codigo = ?");
    $stmt->execute([$codigo]);
    if ($stmt->fetch()) {
        throw new Exception("Código '$codigo' já está em uso");
    }
    
    // Processar especificações técnicas
    $especificacoes = [];

// Adicionar especificações do formulário
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

// Adicionar custo e código de barras como metadados
$especificacoes['_metadata'] = [
    'custo' => floatval($_POST['custo'] ?? 0),
    'codigo_barras' => $_POST['codigo_barras'] ?? null
];
    // Processar imagem principal
    $imagem_principal = null;
    if (!empty($_FILES['imagem_principal']['name'])) {
        $upload_dir = UPLOAD_PATH . 'catalogo/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file = $_FILES['imagem_principal'];
        
        // Validar tipo de arquivo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP');
        }
        
        // Validar tamanho (5MB)
        if ($file['size'] > 5242880) {
            throw new Exception('Imagem muito grande. Máximo 5MB');
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
    
    // Inserir produto
    $stmt = $pdo->prepare("
        INSERT INTO produtos_catalogo (
            codigo, nome, descricao, categoria_id, preco, preco_promocional,
            unidade_venda, tempo_producao, estoque_disponivel, imagem_principal,
            especificacoes, tags, ativo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        isset($_POST['ativo']) ? 1 : 0
    ]);
    
    $produto_id = $pdo->lastInsertId();
    
    // Processar imagens adicionais
    if (!empty($_FILES['imagens_adicionais']['name'][0])) {
        $stmt_img = $pdo->prepare("
            INSERT INTO produtos_imagens (produto_id, caminho, descricao, ordem) 
            VALUES (?, ?, ?, ?)
        ");
        
        $ordem = 1;
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
    
    // Log da ação
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'criar_produto_catalogo', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Criou produto: $codigo - $nome",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "Produto '$nome' cadastrado com sucesso!";
    header('Location: catalogo_produto_detalhes.php?id=' . $produto_id);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    error_log('Erro ao salvar produto: ' . $e->getMessage());
    
    $_SESSION['erro'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    
    header('Location: catalogo_produto_novo.php');
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
