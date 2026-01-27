<?php
// ========================================
// CONFIGURAÇÕES DE SESSÃO ESTENDIDA
// Adicionar estas linhas no início do arquivo config.php
// antes de session_start()
// ========================================

// Configurar duração da sessão para 14 horas (50400 segundos)
ini_set('session.gc_maxlifetime', 50400); // 14 horas em segundos
ini_set('session.cookie_lifetime', 50400); // Cookie de sessão dura 14 horas

// Configurar parâmetros do cookie de sessão
session_set_cookie_params([
    'lifetime' => 50400,        // 14 horas
    'path' => '/',              // Disponível em todo o site
    'domain' => '',             // Usa o domínio atual
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // HTTPS apenas se disponível
    'httponly' => true,         // Não acessível via JavaScript
    'samesite' => 'Lax'         // Proteção CSRF
]);

// Configurar diretório de sessões se necessário
// ini_set('session.save_path', '/caminho/para/sessoes');

// Configurar probabilidade de garbage collection
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Iniciar a sessão após todas as configurações
session_start();

// ========================================
// FUNÇÃO AUXILIAR PARA VERIFICAR VALIDADE DA SESSÃO
// Pode ser útil adicionar ao sistema
// ========================================

function verificarSessaoValida() {
    // Verifica se a sessão existe
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Verifica se há um timestamp de última atividade
    if (isset($_SESSION['ultima_atividade'])) {
        // Calcula o tempo de inatividade
        $tempo_inativo = time() - $_SESSION['ultima_atividade'];
        
        // Se passou de 14 horas (50400 segundos), invalida a sessão
        if ($tempo_inativo > 50400) {
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Atualiza o timestamp de última atividade
    $_SESSION['ultima_atividade'] = time();
    
    return true;
}

// ========================================
// EXEMPLO DE USO NO auth.php
// ========================================

// No arquivo auth.php, após validar o login:
if ($login_valido) {
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_name'] = $usuario['nome'];
    $_SESSION['user_email'] = $usuario['email'];
    $_SESSION['department'] = $usuario['departamento'];
    $_SESSION['ultima_atividade'] = time(); // Registra o timestamp inicial
    
    // Se o usuário marcou "Lembrar de mim", você pode estender ainda mais
    if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
        // Cookie de "lembrar" por 30 dias
        setcookie('remember_token', $token_gerado, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }
}

// ========================================
// NOTAS IMPORTANTES
// ========================================

/*
1. A configuração 'secure' => true deve ser usada apenas em produção com HTTPS
   Para desenvolvimento local com HTTP, mude para false

2. O garbage collection pode ser ajustado conforme a necessidade:
   - gc_probability = 1 e gc_divisor = 100 significa 1% de chance a cada requisição
   - Para sites com muito tráfego, aumente o gc_divisor

3. Considere usar um mecanismo de "remember me" separado para login persistente
   além da sessão de 14 horas

4. Em produção, considere usar Redis ou Memcached para armazenar sessões
   ao invés do sistema de arquivos padrão do PHP

5. Para maior segurança, regenere o ID da sessão após login bem-sucedido:
   session_regenerate_id(true);
*/
?>