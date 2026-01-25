<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$vendedor_id = !empty($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : null;
$periodo_tipo = $_POST['periodo_tipo'] ?? '';
$periodo_referencia = $_POST['periodo_referencia'] ?? '';
$valor_meta = floatval($_POST['valor_meta'] ?? 0);

// Validações
if (empty($periodo_tipo) || !in_array($periodo_tipo, ['mes', 'trimestre', 'ano'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de período inválido']);
    exit;
}

if (empty($periodo_referencia)) {
    echo json_encode(['success' => false, 'message' => 'Período de referência é obrigatório']);
    exit;
}

if ($valor_meta <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valor da meta deve ser maior que zero']);
    exit;
}

// Validar vendedor se fornecido
if ($vendedor_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND perfil = 'vendedor'");
        $stmt->execute([$vendedor_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Vendedor inválido']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro ao validar vendedor: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao validar vendedor']);
        exit;
    }
}

try {
    // Verificar se já existe meta para o mesmo período e vendedor
    $sql_check = "
        SELECT id FROM metas_vendas 
        WHERE periodo_tipo = ? 
        AND periodo_referencia = ? 
        AND (vendedor_id = ? OR (vendedor_id IS NULL AND ? IS NULL))
        AND status = 'ativa'
    ";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$periodo_tipo, $periodo_referencia, $vendedor_id, $vendedor_id]);
    
    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma meta ativa para este período e vendedor']);
        exit;
    }
    
    // Inserir nova meta
    $sql = "
        INSERT INTO metas_vendas (vendedor_id, periodo_tipo, periodo_referencia, valor_meta, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vendedor_id, $periodo_tipo, $periodo_referencia, $valor_meta]);
    
    echo json_encode(['success' => true, 'message' => 'Meta criada com sucesso']);
    
} catch (PDOException $e) {
    error_log("Erro ao salvar meta: " . $e->getMessage());
    
    // Se a tabela não existir, retornar erro específico
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'A tabela metas_vendas não existe no banco de dados. É necessário criar a tabela primeiro.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar meta: ' . $e->getMessage()]);
    }
}
