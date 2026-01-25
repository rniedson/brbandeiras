<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';

requireRole(['vendedor', 'gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: clientes.php');
    exit;
}

try {
    // Preparar dados
    $dados = [
        'tipo_pessoa' => $_POST['tipo_pessoa'] ?? 'J',
        'nome' => trim($_POST['nome']),
        'cpf_cnpj' => preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? ''),
        'telefone' => $_POST['telefone'],
        'email' => $_POST['email'] ?: null,
        'whatsapp' => $_POST['whatsapp'] ?: null,
        'cep' => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
        'endereco' => $_POST['endereco'] ?: null,
        'numero' => $_POST['numero'] ?: null,
        'complemento' => $_POST['complemento'] ?: null,
        'bairro' => $_POST['bairro'] ?: null,
        'cidade' => $_POST['cidade'] ?: null,
        'estado' => $_POST['estado'] ?: null,
        'observacoes' => $_POST['observacoes'] ?: null
    ];
    
    // Validar CPF/CNPJ único
    if ($dados['cpf_cnpj']) {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf_cnpj = ? AND ativo = true");
        $stmt->execute([$dados['cpf_cnpj']]);
        if ($stmt->fetch()) {
            $_SESSION['erro'] = 'CPF/CNPJ já cadastrado no sistema';
            header('Location: cliente_novo.php');
            exit;
        }
    }
    
    // Validar e-mail único
    if ($dados['email']) {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ? AND ativo = true");
        $stmt->execute([$dados['email']]);
        if ($stmt->fetch()) {
            $_SESSION['erro'] = 'E-mail já cadastrado no sistema';
            header('Location: cliente_novo.php');
            exit;
        }
    }
    
    // Inserir cliente
    $sql = "INSERT INTO clientes (
                tipo_pessoa, nome, cpf_cnpj, telefone, email, whatsapp,
                cep, endereco, numero, complemento, bairro, cidade, estado,
                observacoes
            ) VALUES (
                :tipo_pessoa, :nome, :cpf_cnpj, :telefone, :email, :whatsapp,
                :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado,
                :observacoes
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dados);
    
    $_SESSION['mensagem'] = 'Cliente cadastrado com sucesso!';
    header('Location: clientes.php');
    
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao cadastrar cliente: ' . $e->getMessage();
    header('Location: cliente_novo.php');
}
