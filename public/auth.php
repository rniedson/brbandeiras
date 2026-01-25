<?php
require_once '../app/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, nome, email, senha, perfil FROM usuarios WHERE email = ? AND ativo = true");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_nome'] = $usuario['nome'];
            $_SESSION['user_perfil'] = $usuario['perfil'];
            
            // Log de acesso
            $stmt = $pdo->prepare("INSERT INTO logs_acesso (usuario_id, ip) VALUES (?, ?)");
            $stmt->execute([$usuario['id'], $_SERVER['REMOTE_ADDR']]);
            
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['erro'] = 'E-mail ou senha inválidos';
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['erro'] = 'Erro ao processar login';
        header('Location: index.php');
        exit;
    }
}

header('Location: index.php');
