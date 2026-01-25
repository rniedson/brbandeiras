<?php
require_once '../app/config.php';
require_once '../app/functions.php';

session_start();

// Verificar se o modo "Ver Como" está ativo
if (!isset($_SESSION['ver_como_ativo']) || !$_SESSION['ver_como_ativo']) {
    header('Location: dashboard.php');
    exit;
}

// Registrar no log antes de desativar
$usuario_visualizado = $_SESSION['ver_como_usuario'];
$gestor_original = $_SESSION['ver_como_original'];

registrarLog(
    'ver_como_desativado',
    "Gestor #{$gestor_original['user_id']} ({$gestor_original['user_nome']}) " .
    "desativou modo 'Ver Como' do usuário #{$usuario_visualizado['id']} ({$usuario_visualizado['nome']})"
);

// Restaurar sessão original do gestor
$_SESSION['user_id'] = $gestor_original['user_id'];
$_SESSION['user_nome'] = $gestor_original['user_nome'];
$_SESSION['user_email'] = $gestor_original['user_email'];
$_SESSION['user_perfil'] = $gestor_original['user_perfil'];

// Limpar variáveis do modo "Ver Como"
unset($_SESSION['ver_como_ativo']);
unset($_SESSION['ver_como_usuario']);
unset($_SESSION['ver_como_original']);

// Definir mensagem de sucesso
$_SESSION['mensagem'] = 'Modo "Ver Como" desativado. Você voltou à sua conta de gestor.';

// Redirecionar para o dashboard
header('Location: dashboard.php');
exit;
?>