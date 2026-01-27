<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

// Detectar se é requisição AJAX
$isAjax = isset($_POST['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Função para responder JSON
function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Função para responder erro
function errorResponse($message, $isAjax = false) {
    if ($isAjax) {
        jsonResponse(false, $message);
    } else {
        $_SESSION['erro'] = $message;
        redirect('pedido_novo.php');
    }
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Método inválido', $isAjax);
}

// Validar token CSRF
try {
    CSRF::validate($_POST['csrf_token'] ?? '');
} catch (RuntimeException $e) {
    errorResponse('Token CSRF inválido. Recarregue a página e tente novamente.', $isAjax);
}

// Determinar se é INSERT ou UPDATE
$pedido_id = $_POST['pedido_id'] ?? null;
$is_update = !empty($pedido_id);

try {
    $pdo->beginTransaction();
    
    // Se for UPDATE, verificar permissões
    if ($is_update) {
        $stmt = $pdo->prepare("
            SELECT p.*, c.nome as cliente_nome 
            FROM pedidos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pedido_id]);
        $pedido_atual = $stmt->fetch();
        
        if (!$pedido_atual) {
            throw new Exception('Pedido não encontrado');
        }
        
        // Verificar permissões
        if ($_SESSION['user_perfil'] === 'vendedor') {
            if ($pedido_atual['vendedor_id'] != $_SESSION['user_id']) {
                throw new Exception('Você não tem permissão para editar este pedido');
            }
            
            if ($pedido_atual['status'] !== 'orcamento') {
                throw new Exception('Pedidos só podem ser editados no status Orçamento');
            }
        }
    }
    
    // 1. Processar dados do cliente (apenas para novos pedidos)
    if (!$is_update) {
        $cliente_id = null;
        
        if (isset($_POST['cliente_novo']) && $_POST['cliente_novo'] == 'true') {
            // Novo cliente
            $cliente_nome = trim($_POST['cliente_nome']);
            $cliente_telefone = preg_replace('/\D/', '', $_POST['cliente_telefone']);
            $cliente_email = trim($_POST['cliente_email'] ?? '');
            $cliente_cpf_cnpj = preg_replace('/\D/', '', $_POST['cliente_cpf_cnpj'] ?? '');
            
            if (empty($cliente_nome) || empty($cliente_telefone)) {
                throw new Exception('Nome e telefone são obrigatórios para novo cliente');
            }
            
            // Verificar se cliente já existe pelo telefone
            $stmt = $pdo->prepare("SELECT id FROM clientes WHERE telefone = ?");
            $stmt->execute([$cliente_telefone]);
            $cliente_existente = $stmt->fetch();
            
            if ($cliente_existente) {
                $cliente_id = $cliente_existente['id'];
            } else {
                // Inserir novo cliente
                $stmt = $pdo->prepare("
                    INSERT INTO clientes (nome, telefone, email, cpf_cnpj, ativo) 
                    VALUES (?, ?, ?, ?, true)
                    RETURNING id
                ");
                $stmt->execute([
                    $cliente_nome,
                    $cliente_telefone,
                    $cliente_email ?: null,
                    $cliente_cpf_cnpj ?: null
                ]);
                $cliente_id = $stmt->fetchColumn();
                
                // Log
                $stmt = $pdo->prepare("
                    INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
                    VALUES (?, 'criar_cliente', ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    "Cliente criado via pedido: $cliente_nome",
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            }
        } else {
            // Cliente existente
            $cliente_id = $_POST['cliente_id'] ?? null;
            if (!$cliente_id) {
                throw new Exception('Cliente não selecionado');
            }
        }
    } else {
        // Para UPDATE, manter o cliente atual
        $cliente_id = $pedido_atual['cliente_id'];
    }
    
    // 2. Validar itens do pedido
    $items = $_POST['items'] ?? [];
    if (empty($items)) {
        throw new Exception('Nenhum item adicionado ao pedido');
    }
    
    // Processar e validar cada item
    $items_processados = [];
    $valor_total = 0;
    
    foreach ($items as $item) {
        // Validações básicas
        if (empty($item['descricao']) || trim($item['descricao']) === '') {
            continue; // Pular itens vazios
        }
        
        $quantidade = intval($item['quantidade']);
        if ($quantidade <= 0) {
            throw new Exception('Quantidade deve ser maior que zero');
        }
        
        $valor_unitario = floatval($item['valor_unitario']);
        if ($valor_unitario < 0) {
            throw new Exception('Valor unitário não pode ser negativo');
        }
        
        $valor_item = $quantidade * $valor_unitario;
        $valor_total += $valor_item;
        
        $items_processados[] = [
            'id' => !empty($item['id']) ? intval($item['id']) : null,
            'produto_id' => !empty($item['produto_id']) ? intval($item['produto_id']) : null,
            'descricao' => trim($item['descricao']),
            'quantidade' => $quantidade,
            'valor_unitario' => round($valor_unitario, 2),
            'valor_total' => round($valor_item, 2)
        ];
    }
    
    if (empty($items_processados)) {
        throw new Exception('Nenhum item válido no pedido');
    }
    
    // 3. Calcular valores finais com suporte a desconto por porcentagem
    $desconto = floatval($_POST['desconto'] ?? 0);
    $tipo_desconto = $_POST['tipo_desconto'] ?? 'valor';
    
    if ($desconto < 0) {
        throw new Exception('Desconto não pode ser negativo');
    }
    
    // VALIDAÇÃO: Verificar limite de desconto para vendedores
    if ($_SESSION['user_perfil'] === 'vendedor' && $_SESSION['user_perfil'] !== 'gestor') {
        $descontoMaximoVendedor = getDescontoMaximoVendedor();
        
        if ($tipo_desconto === 'porcentagem') {
            if ($desconto > $descontoMaximoVendedor) {
                throw new Exception("Desconto máximo permitido para vendedores é de {$descontoMaximoVendedor}%. Para descontos maiores, entre em contato com o gestor.");
            }
        } else {
            // Para desconto em valor, calcular percentual equivalente
            $percentualDesconto = $valor_total > 0 ? ($desconto / $valor_total) * 100 : 0;
            if ($percentualDesconto > $descontoMaximoVendedor) {
                throw new Exception("Desconto máximo permitido para vendedores é de {$descontoMaximoVendedor}%. O desconto informado representa " . number_format($percentualDesconto, 2, ',', '.') . "%. Para descontos maiores, entre em contato com o gestor.");
            }
        }
    }
    
    // Calcular o valor do desconto
    if ($tipo_desconto === 'porcentagem') {
        if ($desconto > 100) {
            throw new Exception('Desconto em porcentagem não pode ser maior que 100%');
        }
        $desconto_valor = $valor_total * ($desconto / 100);
    } else {
        $desconto_valor = $desconto;
        if ($desconto_valor > $valor_total) {
            throw new Exception('Desconto não pode ser maior que o valor total');
        }
    }
    
    // Arredondar valores para evitar problemas com numeric(10,2)
    $valor_total = round($valor_total, 2);
    $desconto_final = round($desconto_valor, 2);
    $valor_final = round($valor_total - $desconto_final, 2);
    
    // 4. Validar prazo de entrega
    $prazo_entrega = $_POST['prazo_entrega'] ?? null;
    if (!$prazo_entrega) {
        throw new Exception('Prazo de entrega é obrigatório');
    }
    
    $prazo_date = new DateTime($prazo_entrega);
    $hoje = new DateTime();
    $hoje->setTime(0, 0, 0);
    $prazo_date->setTime(0, 0, 0);
    
    if ($prazo_date < $hoje) {
        throw new Exception('Prazo de entrega não pode ser no passado');
    }
    
    // 5. Processar outros campos
    $observacoes = trim($_POST['observacoes'] ?? '');
    $urgente = isset($_POST['urgente']) && $_POST['urgente'] == '1' ? 'true' : 'false';
    
    if ($is_update) {
        // UPDATE - Atualizar pedido existente
        $novo_status = $_POST['status'] ?? $pedido_atual['status'];
        
        $stmt = $pdo->prepare("
            UPDATE pedidos SET
                valor_total = ?,
                desconto = ?,
                valor_final = ?,
                urgente = ?,
                prazo_entrega = ?,
                observacoes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $valor_total,
            $desconto_final,
            $valor_final,
            $urgente,
            $prazo_entrega,
            $observacoes ?: null,
            $pedido_id
        ]);
        
        // Processar itens - estratégia: remover todos e reinserir
        if ($pedido_atual['status'] === 'orcamento') {
            // Remover itens antigos
            $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE pedido_id = ?");
            $stmt->execute([$pedido_id]);
            
            // Inserir itens atualizados
            $stmt = $pdo->prepare("
                INSERT INTO pedido_itens (
                    pedido_id, produto_id, descricao, quantidade, 
                    valor_unitario, valor_total
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($items_processados as $item) {
                $stmt->execute([
                    $pedido_id,
                    $item['produto_id'],
                    $item['descricao'],
                    $item['quantidade'],
                    $item['valor_unitario'],
                    $item['valor_total']
                ]);
            }
        }
        
        $numero_pedido = $pedido_atual['numero'];
        $acao_log = 'atualizar_pedido';
        $mensagem_sucesso = "Pedido #$numero_pedido atualizado com sucesso!";
        
    } else {
        // INSERT - Criar novo pedido
        
        // Gerar número do pedido
        $ano = date('Y');
        $mes = date('m');
        $dia = date('d');
        
        // Buscar último número do dia
        $stmt = $pdo->prepare("
            SELECT numero 
            FROM pedidos 
            WHERE numero LIKE ? 
            ORDER BY numero DESC 
            LIMIT 1
        ");
        $stmt->execute(["$ano$mes$dia-%"]);
        $ultimo = $stmt->fetchColumn();
        
        if ($ultimo) {
            $sequencia = intval(substr($ultimo, -4)) + 1;
        } else {
            $sequencia = 1;
        }
        
        $numero_pedido = sprintf("%s%s%s-%04d", $ano, $mes, $dia, $sequencia);
        
        // MUDANÇA PRINCIPAL: Inserir pedido com status 'arte' em vez de 'orcamento'
        $stmt = $pdo->prepare("
            INSERT INTO pedidos (
                numero, cliente_id, vendedor_id, valor_total, 
                desconto, valor_final, status, urgente, 
                prazo_entrega, observacoes, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, 'arte', ?, ?, ?, CURRENT_TIMESTAMP
            ) RETURNING id
        ");
        
        $stmt->execute([
            $numero_pedido,
            $cliente_id,
            $_SESSION['user_id'],
            $valor_total,
            $desconto_final,
            $valor_final,
            $urgente,
            $prazo_entrega,
            $observacoes ?: null
        ]);
        
        $pedido_id = $stmt->fetchColumn();
        
        // Inserir itens do pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedido_itens (
                pedido_id, produto_id, descricao, quantidade, 
                valor_unitario, valor_total
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items_processados as $item) {
            $stmt->execute([
                $pedido_id,
                $item['produto_id'],
                $item['descricao'],
                $item['quantidade'],
                $item['valor_unitario'],
                $item['valor_total']
            ]);
        }
        
        // Registrar status inicial como 'arte' em vez de 'orcamento'
        $stmt = $pdo->prepare("
            INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id) 
            VALUES (?, 'arte', 'Pedido criado e enviado para arte-finalista', ?)
        ");
        $stmt->execute([$pedido_id, $_SESSION['user_id']]);
        
        $acao_log = 'criar_pedido';
        $mensagem_sucesso = "Pedido #$numero_pedido criado e enviado para arte-finalista!";
    }
    
    // 6. Processar arquivos enviados
    if (!empty($_FILES['arquivos']['name'][0])) {
        $upload_dir = '../uploads/pedidos/';
        
        // Criar diretório se não existir
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Inserir com os campos corretos da tabela
        $stmt = $pdo->prepare("
            INSERT INTO pedido_arquivos (
                pedido_id, 
                nome_arquivo, 
                caminho,
                tipo,
                tamanho,
                usuario_id,
                uploaded_by,
                uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'ai', 'cdr', 'psd'];
        $max_size = 25 * 1024 * 1024; // 25MB
        
        foreach ($_FILES['arquivos']['name'] as $key => $filename) {
            if (empty($filename)) continue;
            
            $file_tmp = $_FILES['arquivos']['tmp_name'][$key];
            $file_size = $_FILES['arquivos']['size'][$key];
            $file_type = $_FILES['arquivos']['type'][$key];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Validações
            if (!in_array($file_ext, $allowed_types)) {
                throw new Exception("Tipo de arquivo não permitido: $filename");
            }
            
            if ($file_size > $max_size) {
                throw new Exception("Arquivo muito grande: $filename (máx. 25MB)");
            }
            
            // Gerar nome único
            $new_filename = $pedido_id . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $stmt->execute([
                    $pedido_id,
                    $filename,
                    'uploads/pedidos/' . $new_filename,
                    $file_type,
                    $file_size,
                    $_SESSION['user_id'],
                    $_SESSION['user_id']
                ]);
            } else {
                throw new Exception("Erro ao fazer upload do arquivo: $filename");
            }
        }
    }
    
    // 7. Log do sistema
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $acao_log,
        "Pedido #$numero_pedido - Valor: " . formatarMoeda($valor_final),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    // Commit da transação
    $pdo->commit();
    
    // Responder
    if ($isAjax) {
        jsonResponse(true, $mensagem_sucesso, [
            'pedido_id' => $pedido_id,
            'numero' => $numero_pedido
        ]);
    } else {
        $_SESSION['mensagem'] = $mensagem_sucesso;
        
        if ($is_update) {
            header("Location: pedido_detalhes.php?id=$pedido_id");
        } else {
            // MUDANÇA: Redirecionar para pedido_detalhes.php em vez de orcamento_detalhes.php
            header("Location: pedido_detalhes.php?id=$pedido_id");
        }
        exit;
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Log detalhado do erro PDO
    error_log("Erro PDO ao salvar pedido: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Trace: " . $e->getTraceAsString());
    
    errorResponse('Erro ao salvar pedido. Por favor, tente novamente.', $isAjax);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Log do erro
    error_log("Erro ao salvar pedido: " . $e->getMessage());
    
    errorResponse($e->getMessage(), $isAjax);
}