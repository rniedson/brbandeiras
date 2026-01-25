<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$id = $_POST['id'] ?? null;
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$ativo = isset($_POST['ativo']) ? 1 : 0;

if (!$id || !$nome) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    // Verificar se existe
    $stmt = $pdo->prepare("SELECT nome FROM grupos_clientes WHERE id = ?");
    $stmt->execute([$id]);
    $grupo = $stmt->fetch();
    
    if (!$grupo) {
        echo json_encode(['success' => false, 'message' => 'Grupo não encontrado']);
        exit;
    }
    
    // Verificar nome duplicado
    if ($nome !== $grupo['nome']) {
        $stmt = $pdo->prepare("SELECT id FROM grupos_clientes WHERE nome = ? AND id != ?");
        $stmt->execute([$nome, $id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Já existe outro grupo com este nome']);
            exit;
        }
    }
    
    // Atualizar
    $stmt = $pdo->prepare("
        UPDATE grupos_clientes 
        SET nome = ?, descricao = ?, ativo = ?
        WHERE id = ?
    ");
    $stmt->execute([$nome, $descricao ?: null, $ativo, $id]);
    
    // Log
    $mudancas = [];
    if ($grupo['nome'] != $nome) $mudancas[] = "nome";
    if ($ativo != 1) $mudancas[] = "desativado";
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'atualizar_grupo_cliente', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Atualizou grupo: $nome" . (!empty($mudancas) ? " (" . implode(", ", $mudancas) . ")" : ""),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Grupo atualizado com sucesso']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
