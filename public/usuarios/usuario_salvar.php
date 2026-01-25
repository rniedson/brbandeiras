<?php
session_start();
require_once '../../app/config.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Processar apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

// Dados do formulário
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$perfil = $_POST['perfil'] ?? 'vendedor';

// CORREÇÃO: Ler corretamente o valor do radio button
// Radio buttons enviam "1" ou "0", precisamos converter para boolean
$ativo = isset($_POST['ativo']) && $_POST['ativo'] == '1';

$senha = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar_senha'] ?? '';

// Verificar permissões
$e_gestor = ($_SESSION['user_perfil'] === 'gestor');
$editando_proprio = ($id == $_SESSION['user_id']);

// Validar permissões
if ($id > 0) {
    // Editando usuário existente
    if (!$e_gestor && !$editando_proprio) {
        $_SESSION['erro'] = 'Você não tem permissão para editar este usuário';
        header("Location: usuario_editar.php?id=$id");
        exit;
    }
} else {
    // Criando novo usuário - apenas gestores
    if (!$e_gestor) {
        $_SESSION['erro'] = 'Apenas gestores podem criar usuários';
        header('Location: usuarios.php');
        exit;
    }
}

// Validações básicas
if (empty($nome) || empty($email)) {
    $_SESSION['erro'] = 'Nome e e-mail são obrigatórios';
    header($id ? "Location: usuario_editar.php?id=$id" : "Location: usuario_novo.php");
    exit;
}

// Validar e-mail
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro'] = 'E-mail inválido';
    header($id ? "Location: usuario_editar.php?id=$id" : "Location: usuario_novo.php");
    exit;
}

// Validar senha se fornecida
if (!empty($senha)) {
    if (strlen($senha) < 6) {
        $_SESSION['erro'] = 'A senha deve ter no mínimo 6 caracteres';
        header($id ? "Location: usuario_editar.php?id=$id" : "Location: usuario_novo.php");
        exit;
    }
    
    if ($senha !== $confirmar_senha) {
        $_SESSION['erro'] = 'As senhas não coincidem';
        header($id ? "Location: usuario_editar.php?id=$id" : "Location: usuario_novo.php");
        exit;
    }
}

try {
    // Verificar se e-mail já existe (exceto para o próprio usuário)
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        throw new Exception('Este e-mail já está em uso');
    }
    
    // Se está editando próprio perfil e não é gestor, manter perfil e status
    if ($editando_proprio && !$e_gestor) {
        $stmt = $pdo->prepare("SELECT perfil, ativo FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $usuario_atual = $stmt->fetch();
        
        $perfil = $usuario_atual['perfil'];
        $ativo = $usuario_atual['ativo'];
    }
    
    if ($id > 0) {
        // ATUALIZAR usuário existente
        
        // Montar SQL dinamicamente
        $campos = [
            "nome = :nome",
            "email = :email",
            "telefone = :telefone",
            "perfil = :perfil",
            "ativo = :ativo"
        ];
        
        $params = [
            ':nome' => $nome,
            ':email' => $email,
            ':telefone' => $telefone,
            ':perfil' => $perfil,
            ':ativo' => $ativo ? 'true' : 'false'  // Enviar como string boolean para PostgreSQL
        ];
        
        // Adicionar senha se fornecida
        if (!empty($senha)) {
            $campos[] = "senha = :senha";
            $params[':senha'] = password_hash($senha, PASSWORD_DEFAULT);
        }
        
        // Verificar se coluna updated_at existe
        $stmt = $pdo->query("SELECT column_name FROM information_schema.columns 
                            WHERE table_name = 'usuarios' AND column_name = 'updated_at'");
        if ($stmt->fetch()) {
            $campos[] = "updated_at = CURRENT_TIMESTAMP";
        }
        
        $sql = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id = :id";
        $params[':id'] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Tentar registrar log (se a tabela existir)
        try {
            $detalhes = $editando_proprio ? "Editou próprio perfil" : "Editou usuário: $nome (ID: $id)";
            $log_sql = "INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip, created_at) 
                       VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt_log = $pdo->prepare($log_sql);
            $stmt_log->execute([
                $_SESSION['user_id'],
                'usuario_editado',
                $detalhes,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            // Ignorar erro de log
        }
        
        $_SESSION['mensagem'] = 'Usuário atualizado com sucesso!';
        
    } else {
        // CRIAR novo usuário
        
        if (empty($senha)) {
            throw new Exception('Senha é obrigatória para novo usuário');
        }
        
        // Verificar quais colunas existem
        $colunas = ['nome', 'email', 'senha', 'telefone', 'perfil', 'ativo'];
        // Enviar ativo como string boolean
        $valores = [
            $nome, 
            $email, 
            password_hash($senha, PASSWORD_DEFAULT), 
            $telefone, 
            $perfil, 
            $ativo ? 'true' : 'false'  // Converter para string boolean
        ];
        
        // Verificar se tem created_at
        $stmt = $pdo->query("SELECT column_name FROM information_schema.columns 
                            WHERE table_name = 'usuarios' AND column_name = 'created_at'");
        if ($stmt->fetch()) {
            $colunas[] = 'created_at';
            $valores[] = date('Y-m-d H:i:s');
        }
        
        $placeholders = array_fill(0, count($colunas), '?');
        $sql = "INSERT INTO usuarios (" . implode(', ', $colunas) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($valores);
        
        $novo_id = $pdo->lastInsertId();
        
        // Tentar registrar log
        try {
            $log_sql = "INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip, created_at) 
                       VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt_log = $pdo->prepare($log_sql);
            $stmt_log->execute([
                $_SESSION['user_id'],
                'usuario_criado',
                "Criou usuário: $nome (ID: $novo_id)",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            // Ignorar erro de log
        }
        
        $_SESSION['mensagem'] = 'Usuário criado com sucesso!';
    }
    
    // Redirecionar
    if ($editando_proprio && !$e_gestor) {
        header('Location: perfil.php');
    } else {
        header('Location: usuarios.php');
    }
    exit;
    
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
    
    if ($id > 0) {
        header("Location: usuario_editar.php?id=$id");
    } else {
        header("Location: usuario_novo.php");
    }
    exit;
}