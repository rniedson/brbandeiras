<?php
// IMPORTANTE: Carregar ajax_helper ANTES de config.php
require_once '../../app/ajax_helper.php';
AjaxResponse::init();

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    AjaxResponse::error('Não autenticado', 401);
}

// Verificar permissões
if (!hasRole(['producao', 'gestor'])) {
    AjaxResponse::error('Sem permissão para esta ação', 403);
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AjaxResponse::error('Método não permitido', 405);
}

try {
    // Verificar conexão com banco
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['pedido_id']) || !isset($input['novo_status'])) {
        throw new Exception('Dados incompletos');
    }
    
    $pedidoId = (int)$input['pedido_id'];
    $novoStatus = $input['novo_status'];
    $statusAnterior = $input['status_anterior'] ?? null;
    
    // Validar status permitidos
    $statusPermitidos = ['aprovado', 'producao', 'finalizado'];
    if (!in_array($novoStatus, $statusPermitidos)) {
        throw new Exception('Status inválido');
    }
    
    $pdo->beginTransaction();
    
    // Verificar se o pedido existe e obter dados atuais
    $stmt = $pdo->prepare("
        SELECT p.*, pc.id as checklist_id,
               COALESCE(pc.corte, false) as corte,
               COALESCE(pc.costura, false) as costura,
               COALESCE(pc.acabamento, false) as acabamento,
               COALESCE(pc.qualidade, false) as qualidade
        FROM pedidos p
        LEFT JOIN producao_checklist pc ON p.id = pc.pedido_id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
    
    $dadosResposta = [];
    $agora = new DateTime();
    
    // Lógica específica por transição de status
    switch ($novoStatus) {
        case 'producao':
            if ($statusAnterior === 'aprovado') {
                // Criar checklist se não existir
                if (!$pedido['checklist_id']) {
                    $stmt = $pdo->prepare("
                        INSERT INTO producao_checklist (
                            pedido_id, responsavel_id, iniciado_em
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$pedidoId, $_SESSION['usuario_id'], $agora->format('Y-m-d H:i:s')]);
                }
                
                // Atualizar pedido
                $stmt = $pdo->prepare("
                    UPDATE pedidos 
                    SET status = ?, 
                        responsavel_producao_id = ?,
                        iniciado_producao_em = ?
                    WHERE id = ?
                ");
                $stmt->execute([$novoStatus, $_SESSION['usuario_id'], $agora->format('Y-m-d H:i:s'), $pedidoId]);
                
                $dadosResposta = [
                    'responsavel_producao_id' => $_SESSION['usuario_id'],
                    'responsavel_nome' => $_SESSION['usuario_nome'],
                    'iniciado_producao_em' => $agora->format('Y-m-d H:i:s'),
                    'progresso_checklist' => 0,
                    'corte' => false,
                    'costura' => false,
                    'acabamento' => false,
                    'qualidade' => false
                ];
            }
            break;
            
        case 'finalizado':
            if ($statusAnterior === 'producao') {
                // Verificar se o checklist está completo
                $progressoAtual = ($pedido['corte'] ? 1 : 0) + 
                                 ($pedido['costura'] ? 1 : 0) + 
                                 ($pedido['acabamento'] ? 1 : 0) + 
                                 ($pedido['qualidade'] ? 1 : 0);
                
                if ($progressoAtual < 4) {
                    throw new Exception('Complete todo o checklist antes de finalizar');
                }
                
                // Finalizar checklist
                $stmt = $pdo->prepare("
                    UPDATE producao_checklist 
                    SET finalizado_em = ? 
                    WHERE pedido_id = ?
                ");
                $stmt->execute([$agora->format('Y-m-d H:i:s'), $pedidoId]);
                
                // Atualizar pedido
                $stmt = $pdo->prepare("
                    UPDATE pedidos 
                    SET status = ?, finalizado_producao_em = ?
                    WHERE id = ?
                ");
                $stmt->execute([$novoStatus, $agora->format('Y-m-d H:i:s'), $pedidoId]);
                
                // Calcular tempo total
                if ($pedido['iniciado_producao_em']) {
                    $inicio = new DateTime($pedido['iniciado_producao_em']);
                    $tempoMinutos = ($agora->getTimestamp() - $inicio->getTimestamp()) / 60;
                    
                    $dadosResposta['tempo_producao_minutos'] = round($tempoMinutos);
                }
                
                $dadosResposta['finalizado_producao_em'] = $agora->format('Y-m-d H:i:s');
            }
            break;
            
        case 'aprovado':
            if ($statusAnterior === 'producao') {
                // Voltar para fila - resetar dados de produção
                $stmt = $pdo->prepare("
                    UPDATE pedidos 
                    SET status = ?, 
                        responsavel_producao_id = NULL,
                        iniciado_producao_em = NULL,
                        finalizado_producao_em = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$novoStatus, $pedidoId]);
                
                // Resetar checklist
                $stmt = $pdo->prepare("
                    UPDATE producao_checklist 
                    SET corte = false, 
                        costura = false, 
                        acabamento = false, 
                        qualidade = false,
                        finalizado_em = NULL,
                        responsavel_id = NULL
                    WHERE pedido_id = ?
                ");
                $stmt->execute([$pedidoId]);
                
                $dadosResposta = [
                    'responsavel_producao_id' => null,
                    'responsavel_nome' => null,
                    'iniciado_producao_em' => null,
                    'progresso_checklist' => 0,
                    'corte' => false,
                    'costura' => false,
                    'acabamento' => false,
                    'qualidade' => false
                ];
            }
            break;
    }
    
    // Registrar log de mudança de status
    $stmt = $pdo->prepare("
        INSERT INTO producao_status (pedido_id, status, usuario_id, observacoes)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $pedidoId, 
        $novoStatus, 
        $_SESSION['usuario_id'],
        "Status alterado de '$statusAnterior' para '$novoStatus'"
    ]);
    
    // Log do sistema
    registrarLog(
        'atualizar_status_producao',
        "Pedido #$pedidoId: status alterado de '$statusAnterior' para '$novoStatus'"
    );
    
    $pdo->commit();
    
    AjaxResponse::success($dadosResposta, 'Status atualizado com sucesso');
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    AjaxResponse::error('Erro de banco de dados: ' . $e->getMessage());
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    AjaxResponse::error($e->getMessage());
}