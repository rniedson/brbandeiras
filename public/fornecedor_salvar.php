<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['producao', 'gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: fornecedores.php');
    exit;
}

try {
    // Preparar dados
    $dados = [
        'tipo_pessoa' => $_POST['tipo_pessoa'] ?? 'J',
        'nome' => trim($_POST['nome']),
        'nome_fantasia' => trim($_POST['nome_fantasia'] ?? ''),
        'cpf_cnpj' => preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? ''),
        'telefone' => $_POST['telefone'] ?? null,
        'celular' => $_POST['celular'] ?? null,
        'email' => $_POST['email'] ?: null,
        'whatsapp' => $_POST['whatsapp'] ?: null,
        'cep' => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
        'endereco' => $_POST['endereco'] ?: null,
        'numero' => $_POST['numero'] ?: null,
        'complemento' => $_POST['complemento'] ?: null,
        'bairro' => $_POST['bairro'] ?: null,
        'cidade' => $_POST['cidade'] ?: null,
        'estado' => $_POST['estado'] ?: null,
        'contato_principal' => $_POST['contato_principal'] ?: null,
        'site' => $_POST['site'] ?: null,
        'observacoes' => $_POST['observacoes'] ?: null
    ];
    
    // Validar CPF/CNPJ único
    if ($dados['cpf_cnpj']) {
        $stmt = $pdo->prepare("SELECT id FROM fornecedores WHERE cpf_cnpj = ?");
        $stmt->execute([$dados['cpf_cnpj']]);
        if ($stmt->fetch()) {
            $_SESSION['erro'] = 'CPF/CNPJ já cadastrado no sistema';
            header('Location: fornecedor_novo.php');
            exit;
        }
    }
    
    // Validar e-mail único
    if ($dados['email']) {
        $stmt = $pdo->prepare("SELECT id FROM fornecedores WHERE email = ?");
        $stmt->execute([$dados['email']]);
        if ($stmt->fetch()) {
            $_SESSION['erro'] = 'E-mail já cadastrado no sistema';
            header('Location: fornecedor_novo.php');
            exit;
        }
    }
    
    // Inserir fornecedor
    $sql = "INSERT INTO fornecedores (
                tipo_pessoa, nome, nome_fantasia, cpf_cnpj, telefone, celular, 
                email, whatsapp, cep, endereco, numero, complemento, bairro, 
                cidade, estado, contato_principal, site, observacoes
            ) VALUES (
                :tipo_pessoa, :nome, :nome_fantasia, :cpf_cnpj, :telefone, :celular,
                :email, :whatsapp, :cep, :endereco, :numero, :complemento, :bairro,
                :cidade, :estado, :contato_principal, :site, :observacoes
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dados);
    
    // Log
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'criar_fornecedor', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Criou fornecedor: {$dados['nome']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $_SESSION['mensagem'] = 'Fornecedor cadastrado com sucesso!';
    header('Location: fornecedores.php');
    
} catch (PDOException $e) {
    // Se a tabela não existir, mostrar mensagem amigável
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        $_SESSION['erro'] = 'A tabela de fornecedores ainda não foi criada no banco de dados.';
    } else {
        $_SESSION['erro'] = 'Erro ao cadastrar fornecedor: ' . $e->getMessage();
    }
    header('Location: fornecedor_novo.php');
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao cadastrar fornecedor: ' . $e->getMessage();
    header('Location: fornecedor_novo.php');
}
