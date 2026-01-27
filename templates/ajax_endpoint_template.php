<?php
/**
 * Template para Endpoints AJAX
 * 
 * Este arquivo serve como referência/template para criar novos endpoints AJAX
 * seguindo as boas práticas para evitar ERR_EMPTY_RESPONSE
 * 
 * COPIAR este arquivo e adaptar conforme necessário
 */

// 1. IMPORTANTE: Carregar ajax_helper ANTES de qualquer coisa
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

// 2. Carregar configurações e autenticação
require_once '../../app/config.php';
require_once '../../app/auth.php';

// 3. Verificar autenticação
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

// 4. Verificar método HTTP se necessário
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('Método não permitido', 405);
}

// 5. Verificar permissões/roles se necessário
// requireRole(['gestor', 'admin']); // Descomente se necessário

try {
    // 6. Validar entrada
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['campo_obrigatorio'])) {
        AjaxResponse::error('Dados incompletos', 400);
    }
    
    // 7. Validar conexão com banco
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    // 8. Processar lógica do endpoint
    $campo = $input['campo_obrigatorio'];
    
    // Exemplo de query
    $stmt = $pdo->prepare("SELECT * FROM tabela WHERE campo = ?");
    $stmt->execute([$campo]);
    $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 9. Retornar sucesso
    AjaxResponse::success($resultado, 'Operação realizada com sucesso');
    
} catch (PDOException $e) {
    // Erro de banco de dados
    AjaxResponse::error('Erro de conexão com banco de dados');
    
} catch (Exception $e) {
    // Outros erros
    AjaxResponse::error($e->getMessage());
}

/**
 * EXEMPLOS DE USO:
 * 
 * // Resposta de sucesso simples
 * AjaxResponse::success(['id' => 123]);
 * 
 * // Resposta de sucesso com mensagem
 * AjaxResponse::success($dados, 'Dados carregados com sucesso');
 * 
 * // Resposta de erro
 * AjaxResponse::error('Erro ao processar', 500);
 * 
 * // Resposta de erro de autenticação
 * AjaxResponse::error('Não autenticado', 401);
 * 
 * // Resposta JSON customizada
 * AjaxResponse::json([
 *     'success' => true,
 *     'custom_field' => 'valor'
 * ]);
 */
