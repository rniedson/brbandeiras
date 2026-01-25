<?php
/**
 * PEDIDO_ATUALIZAR.PHP - PROCESSAMENTO DE ATUALIZAÇÃO DO PEDIDO
 * Sistema completo com validações, logs e tratamento de erros
 */

require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireLogin();

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erro'] = 'Método inválido';
    header('Location: pedidos.php');
    exit;
}

// Obter ID do pedido
$pedido_id = $_POST['pedido_id'] ?? null;

if (!$pedido_id || !is_numeric($pedido_id)) {
    $_SESSION['erro'] = 'ID do pedido inválido';
    header('Location: pedidos.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // =====================================
    // 1. BUSCAR PEDIDO ATUAL PARA COMPARAÇÃO
    // =====================================
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome as cliente_nome
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido_atual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido_atual) {
        throw new Exception('Pedido não encontrado');
    }
    
    // =====================================
    // 2. VERIFICAR PERMISSÕES
    // =====================================
    $pode_editar = false;
    if ($_SESSION['user_perfil'] === 'gestor') {
        $pode_editar = true;
    } elseif ($_SESSION['user_perfil'] === 'vendedor' && $pedido_atual['vendedor_id'] == $_SESSION['user_id']) {
        if (in_array($pedido_atual['status'], ['orcamento', 'aprovado'])) {
            $pode_editar = true;
        }
    }
    
    if (!$pode_editar) {
        throw new Exception('Você não tem permissão para editar este pedido');
    }
    
    // =====================================
    // 3. PREPARAR ARRAYS DE MUDANÇAS PARA LOG
    // =====================================
    $mudancas = [];
    $mudancas_detalhadas = [];
    
    // =====================================
    // 4. PROCESSAR MUDANÇA DE CLIENTE
    // =====================================
    $cliente_id = $_POST['cliente_id'] ?? $pedido_atual['cliente_id'];
    
    if ($cliente_id != $pedido_atual['cliente_id']) {
        // Buscar nome do novo cliente
        $stmt = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $novo_cliente = $stmt->fetch();
        
        if (!$novo_cliente) {
            throw new Exception('Cliente selecionado não existe');
        }
        
        $mudancas[] = "Cliente alterado";
        $mudancas_detalhadas[] = sprintf(
            "Cliente: %s → %s",
            $pedido_atual['cliente_nome'],
            $novo_cliente['nome']
        );
    }
    
    // =====================================
    // 5. PROCESSAR ITENS REMOVIDOS
    // =====================================
    $items_removidos = $_POST['items_removidos'] ?? [];
    $total_removidos = 0;
    
    if (!empty($items_removidos)) {
        foreach ($items_removidos as $item_id) {
            if (!is_numeric($item_id)) continue;
            
            // Buscar dados do item antes de remover
            $stmt = $pdo->prepare("
                SELECT descricao, quantidade, valor_unitario 
                FROM pedido_itens 
                WHERE id = ? AND pedido_id = ?
            ");
            $stmt->execute([$item_id, $pedido_id]);
            $item = $stmt->fetch();
            
            if ($item) {
                // Remover item
                $stmt = $pdo->prepare("DELETE FROM pedido_itens WHERE id = ? AND pedido_id = ?");
                $stmt->execute([$item_id, $pedido_id]);
                
                $total_removidos++;
                $mudancas_detalhadas[] = sprintf(
                    "Item removido: %s (Qtd: %d, Valor: %s)",
                    substr($item['descricao'], 0, 50),
                    $item['quantidade'],
                    formatarMoeda($item['valor_unitario'])
                );
            }
        }
        
        if ($total_removidos > 0) {
            $mudancas[] = "$total_removidos item(ns) removido(s)";
        }
    }
    
    // =====================================
    // 6. PROCESSAR ITENS DO PEDIDO
    // =====================================
    $items = $_POST['items'] ?? [];
    $valor_total = 0;
    $itens_alterados = 0;
    $itens_novos = 0;
    
    foreach ($items as $item_data) {
        // Validar dados do item
        $item_id = $item_data['id'] ?? null;
        $produto_id = $item_data['produto_id'] ?? null;
        $descricao = trim($item_data['descricao'] ?? '');
        $quantidade = intval($item_data['quantidade'] ?? 0);
        $valor_unitario = floatval($item_data['valor_unitario'] ?? 0);
        $valor_item = $quantidade * $valor_unitario;
        
        // Validações básicas
        if (empty($descricao)) {
            throw new Exception('Todos os itens devem ter descrição');
        }
        if ($quantidade <= 0) {
            throw new Exception('Quantidade deve ser maior que zero');
        }
        if ($valor_unitario < 0) {
            throw new Exception('Valor unitário não pode ser negativo');
        }
        
        if ($item_id && is_numeric($item_id)) {
            // =====================================
            // ATUALIZAR ITEM EXISTENTE
            // =====================================
            $stmt = $pdo->prepare("
                SELECT * FROM pedido_itens 
                WHERE id = ? AND pedido_id = ?
            ");
            $stmt->execute([$item_id, $pedido_id]);
            $item_atual = $stmt->fetch();
            
            if ($item_atual) {
                // Verificar se houve alteração
                $alterado = false;
                $alteracoes = [];
                
                if ($item_atual['descricao'] != $descricao) {
                    $alterado = true;
                    $alteracoes[] = "descrição alterada";
                }
                if ($item_atual['quantidade'] != $quantidade) {
                    $alterado = true;
                    $alteracoes[] = sprintf("Qtd: %d → %d", $item_atual['quantidade'], $quantidade);
                }
                if (abs($item_atual['valor_unitario'] - $valor_unitario) > 0.01) {
                    $alterado = true;
                    $alteracoes[] = sprintf(
                        "Valor: %s → %s",
                        formatarMoeda($item_atual['valor_unitario']),
                        formatarMoeda($valor_unitario)
                    );
                }
                
                if ($alterado) {
                    $stmt = $pdo->prepare("
                        UPDATE pedido_itens 
                        SET descricao = ?, 
                            quantidade = ?, 
                            valor_unitario = ?, 
                            valor_total = ?,
                            produto_id = ?
                        WHERE id = ? AND pedido_id = ?
                    ");
                    $stmt->execute([
                        $descricao,
                        $quantidade,
                        $valor_unitario,
                        $valor_item,
                        $produto_id ?: null,
                        $item_id,
                        $pedido_id
                    ]);
                    
                    $itens_alterados++;
                    
                    if (!empty($alteracoes)) {
                        $mudancas_detalhadas[] = sprintf(
                            "Item alterado: %s (%s)",
                            substr($descricao, 0, 30),
                            implode(', ', $alteracoes)
                        );
                    }
                }
            }
        } else {
            // =====================================
            // INSERIR NOVO ITEM
            // =====================================
            $stmt = $pdo->prepare("
                INSERT INTO pedido_itens (
                    pedido_id, produto_id, descricao, 
                    quantidade, valor_unitario, valor_total
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $pedido_id,
                $produto_id ?: null,
                $descricao,
                $quantidade,
                $valor_unitario,
                $valor_item
            ]);
            
            $itens_novos++;
            $mudancas_detalhadas[] = sprintf(
                "Novo item: %s (Qtd: %d, Valor: %s)",
                substr($descricao, 0, 50),
                $quantidade,
                formatarMoeda($valor_unitario)
            );
        }
        
        $valor_total += $valor_item;
    }
    
    // Adicionar resumo de alterações
    if ($itens_alterados > 0) {
        $mudancas[] = "$itens_alterados item(ns) alterado(s)";
    }
    if ($itens_novos > 0) {
        $mudancas[] = "$itens_novos item(ns) adicionado(s)";
    }
    
    // =====================================
    // 7. PROCESSAR VALORES E DADOS DO PEDIDO
    // =====================================
    
    // Validar desconto
    $desconto = floatval($_POST['desconto'] ?? 0);
    if ($desconto < 0) {
        throw new Exception('Desconto não pode ser negativo');
    }
    if ($desconto > $valor_total) {
        throw new Exception('Desconto não pode ser maior que o valor total');
    }
    
    $valor_final = $valor_total - $desconto;
    
    // Verificar mudanças nos valores
    if (abs($pedido_atual['valor_total'] - $valor_total) > 0.01) {
        $mudancas[] = "Valor total alterado";
        $mudancas_detalhadas[] = sprintf(
            "Subtotal: %s → %s",
            formatarMoeda($pedido_atual['valor_total']),
            formatarMoeda($valor_total)
        );
    }
    
    if (abs($pedido_atual['desconto'] - $desconto) > 0.01) {
        $mudancas[] = "Desconto alterado";
        $mudancas_detalhadas[] = sprintf(
            "Desconto: %s → %s",
            formatarMoeda($pedido_atual['desconto']),
            formatarMoeda($desconto)
        );
    }
    
    // Validar prazo
    $prazo_entrega = $_POST['prazo_entrega'] ?? null;
    if (!$prazo_entrega) {
        throw new Exception('Prazo de entrega é obrigatório');
    }
    
    if ($pedido_atual['prazo_entrega'] != $prazo_entrega) {
        $mudancas[] = "Prazo alterado";
        $mudancas_detalhadas[] = sprintf(
            "Prazo: %s → %s",
            formatarData($pedido_atual['prazo_entrega']),
            formatarData($prazo_entrega)
        );
    }
    
    // Outros campos
    $observacoes = trim($_POST['observacoes'] ?? '');
    $urgente = isset($_POST['urgente']) && $_POST['urgente'] == '1';
    
    if (($pedido_atual['observacoes'] ?? '') != $observacoes) {
        $mudancas[] = "Observações alteradas";
    }
    
    if (($pedido_atual['urgente'] ?? false) != $urgente) {
        $mudancas[] = $urgente ? "Marcado como URGENTE" : "Removida marcação de URGENTE";
    }
    
    // =====================================
    // 8. VERIFICAR E PROCESSAR CAMPOS OPCIONAIS
    // =====================================
    
    // Verificar se as colunas de pagamento existem
    $checkColumns = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'pedidos' 
        AND column_name IN ('forma_pagamento', 'condicoes_pagamento')
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $forma_pagamento = null;
    $condicoes_pagamento = null;
    
    if (in_array('forma_pagamento', $checkColumns)) {
        $forma_pagamento = $_POST['forma_pagamento'] ?? null;
        
        if (($pedido_atual['forma_pagamento'] ?? '') != $forma_pagamento) {
            $mudancas[] = "Forma de pagamento alterada";
            if ($forma_pagamento) {
                $mudancas_detalhadas[] = sprintf(
                    "Pagamento: %s → %s",
                    $pedido_atual['forma_pagamento'] ?? 'Não definido',
                    $forma_pagamento
                );
            }
        }
    }
    
    if (in_array('condicoes_pagamento', $checkColumns)) {
        $condicoes_pagamento = trim($_POST['condicoes_pagamento'] ?? '');
        
        if (($pedido_atual['condicoes_pagamento'] ?? '') != $condicoes_pagamento) {
            $mudancas[] = "Condições de pagamento alteradas";
        }
    }
    
    // =====================================
    // 9. ATUALIZAR PEDIDO NO BANCO
    // =====================================
    
    // Montar query dinamicamente baseada nas colunas existentes
    $updateFields = [
        'cliente_id = ?',
        'valor_total = ?',
        'desconto = ?',
        'valor_final = ?',
        'prazo_entrega = ?',
        'observacoes = ?',
        'urgente = ?',
        'updated_at = CURRENT_TIMESTAMP'
    ];
    
    $updateValues = [
        $cliente_id,
        $valor_total,
        $desconto,
        $valor_final,
        $prazo_entrega,
        $observacoes ?: null,
        $urgente
    ];
    
    // Adicionar campos opcionais se existirem
    if (in_array('forma_pagamento', $checkColumns)) {
        $updateFields[] = 'forma_pagamento = ?';
        $updateValues[] = $forma_pagamento ?: null;
    }
    
    if (in_array('condicoes_pagamento', $checkColumns)) {
        $updateFields[] = 'condicoes_pagamento = ?';
        $updateValues[] = $condicoes_pagamento ?: null;
    }
    
    // Adicionar ID do pedido ao final
    $updateValues[] = $pedido_id;
    
    $sql = "UPDATE pedidos SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateValues);
    
    // =====================================
    // 10. PROCESSAR ARQUIVOS (SE HOUVER)
    // =====================================
    
    // Remover arquivos marcados para exclusão
    $remover_arquivos = $_POST['remover_arquivos'] ?? [];
    if (!empty($remover_arquivos)) {
        foreach ($remover_arquivos as $arquivo_id) {
            if (!is_numeric($arquivo_id)) continue;
            
            // Buscar arquivo para remover fisicamente
            $checkArquivosColumns = $pdo->query("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'pedido_arquivos' 
                AND column_name IN ('caminho', 'caminho_arquivo')
            ")->fetchAll(PDO::FETCH_COLUMN);
            
            $caminhoColumn = in_array('caminho_arquivo', $checkArquivosColumns) 
                ? 'caminho_arquivo' 
                : 'caminho';
            
            $stmt = $pdo->prepare("
                SELECT nome_arquivo, {$caminhoColumn} as caminho 
                FROM pedido_arquivos 
                WHERE id = ? AND pedido_id = ?
            ");
            $stmt->execute([$arquivo_id, $pedido_id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                // Remover arquivo físico
                $caminho_completo = '../' . $arquivo['caminho'];
                if (file_exists($caminho_completo)) {
                    @unlink($caminho_completo);
                }
                
                // Remover do banco
                $stmt = $pdo->prepare("DELETE FROM pedido_arquivos WHERE id = ?");
                $stmt->execute([$arquivo_id]);
                
                $mudancas[] = "Arquivo removido";
                $mudancas_detalhadas[] = "Arquivo removido: " . $arquivo['nome_arquivo'];
            }
        }
    }
    
    // Upload de novos arquivos
    if (!empty($_FILES['arquivos']['name'][0])) {
        $upload_dir = '../uploads/pedidos/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Verificar qual coluna usar para caminho
        $checkArquivosColumns = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'pedido_arquivos' 
            AND column_name IN ('caminho', 'caminho_arquivo')
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $caminhoColumn = in_array('caminho_arquivo', $checkArquivosColumns) 
            ? 'caminho_arquivo' 
            : 'caminho';
        
        $sql = "INSERT INTO pedido_arquivos (pedido_id, nome_arquivo, {$caminhoColumn}";
        
        // Verificar se tem coluna usuario_id
        $hasUserColumn = $pdo->query("
            SELECT 1 FROM information_schema.columns 
            WHERE table_name = 'pedido_arquivos' AND column_name = 'usuario_id'
        ")->fetch();
        
        if ($hasUserColumn) {
            $sql .= ", usuario_id) VALUES (?, ?, ?, ?)";
        } else {
            $sql .= ") VALUES (?, ?, ?)";
        }
        
        $stmt = $pdo->prepare($sql);
        
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
        $max_size = 10 * 1024 * 1024; // 10MB
        $arquivos_adicionados = 0;
        
        foreach ($_FILES['arquivos']['name'] as $key => $filename) {
            if (empty($filename)) continue;
            
            $file_tmp = $_FILES['arquivos']['tmp_name'][$key];
            $file_size = $_FILES['arquivos']['size'][$key];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Validações
            if (!in_array($file_ext, $allowed_types)) {
                continue; // Pular arquivo inválido
            }
            
            if ($file_size > $max_size) {
                continue; // Pular arquivo muito grande
            }
            
            // Gerar nome único
            $new_filename = $pedido_id . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $values = [
                    $pedido_id,
                    $filename,
                    'uploads/pedidos/' . $new_filename
                ];
                
                if ($hasUserColumn) {
                    $values[] = $_SESSION['user_id'];
                }
                
                $stmt->execute($values);
                $arquivos_adicionados++;
                $mudancas_detalhadas[] = "Arquivo adicionado: $filename";
            }
        }
        
        if ($arquivos_adicionados > 0) {
            $mudancas[] = "$arquivos_adicionados arquivo(s) adicionado(s)";
        }
    }
    
    // =====================================
    // 11. REGISTRAR NO HISTÓRICO DE PRODUÇÃO
    // =====================================
    
    if (!empty($mudancas)) {
        $observacao_historico = "Pedido editado";
        $observacao_historico .= " - " . implode(", ", $mudancas);
        
        if (!empty($mudancas_detalhadas)) {
            $observacao_historico .= "\n\nDetalhes das alterações:\n• ";
            $observacao_historico .= implode("\n• ", array_slice($mudancas_detalhadas, 0, 10));
            
            if (count($mudancas_detalhadas) > 10) {
                $observacao_historico .= "\n• ... e mais " . (count($mudancas_detalhadas) - 10) . " alterações";
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO producao_status (pedido_id, status, observacoes, usuario_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $pedido_id,
            $pedido_atual['status'],
            $observacao_historico,
            $_SESSION['user_id']
        ]);
    }
    
    // =====================================
    // 12. REGISTRAR LOG DO SISTEMA
    // =====================================
    
    $detalhes_log = "Pedido #{$pedido_atual['numero']} atualizado";
    if (!empty($mudancas)) {
        $detalhes_log .= " - " . implode(", ", array_slice($mudancas, 0, 3));
        if (count($mudancas) > 3) {
            $detalhes_log .= " e mais " . (count($mudancas) - 3) . " alterações";
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip) 
        VALUES (?, 'editar_pedido', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $detalhes_log,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    // =====================================
    // 13. COMMIT DA TRANSAÇÃO
    // =====================================
    
    $pdo->commit();
    
    // Preparar mensagem de sucesso
    if (!empty($mudancas)) {
        $_SESSION['mensagem'] = sprintf(
            "Pedido #%s atualizado com sucesso! (%d alterações realizadas)",
            $pedido_atual['numero'],
            count($mudancas)
        );
    } else {
        $_SESSION['mensagem'] = "Pedido #{$pedido_atual['numero']} salvo (sem alterações)";
    }
    
    // Redirecionar para detalhes do pedido
    header("Location: pedido_detalhes.php?id=$pedido_id");
    exit;
    
} catch (Exception $e) {
    // Rollback em caso de erro
    $pdo->rollBack();
    
    // Log do erro
    error_log("Erro ao atualizar pedido #$pedido_id: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Mensagem de erro para o usuário
    $_SESSION['erro'] = 'Erro ao atualizar pedido: ' . $e->getMessage();
    
    // Salvar dados do formulário para não perder o trabalho
    $_SESSION['form_data'] = $_POST;
    
    // Redirecionar de volta para edição
    header("Location: pedido_editar.php?id=$pedido_id");
    exit;
}
?>