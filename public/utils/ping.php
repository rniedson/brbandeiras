<?php
require_once '../../app/config.php';

// Verificar se está logado
if (isset($_SESSION['user_id'])) {
    // Atualizar timestamp da última atividade
    $_SESSION['last_activity'] = time();
    
    // Retornar status OK
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'time' => date('H:i:s')]);
} else {
    // Sessão expirada
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'expired']);
}
