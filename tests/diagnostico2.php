<?php
// 🔍 SIMULAÇÃO EXATA DO ARQUIVO ORIGINAL
// Testando cada bloco específico que pode causar erro 500

echo "<h1>🔍 Simulação Exata - pedido_detalhes.php</h1>";

// Setup inicial (já sabemos que funciona)
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_perfil'] = 'gestor';
}

$pedido_id = 1; // ID que sabemos que existe

echo "<h2>🧪 TESTE 1: Query Principal Exata (Linhas 17-51)</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            c.cpf_cnpj as cliente_cpf_cnpj,
            c.endereco as cliente_endereco,
            c.cidade as cliente_cidade,
            c.estado as cliente_estado,
            c.cep as cliente_cep,
            c.tipo_pessoa as cliente_tipo,
            v.nome as vendedor_nome,
            v.email as vendedor_email,
            v.telefone as vendedor_telefone,
            pa.arte_finalista_id,
            af.nome as arte_finalista_nome,
            (SELECT COUNT(*) FROM arte_versoes WHERE pedido_id = p.id) as total_versoes_arte,
            (SELECT MAX(versao) FROM arte_versoes WHERE pedido_id = p.id) as ultima_versao_arte,
            (SELECT COUNT(*) FROM pedidos WHERE cliente_id = p.cliente_id) as total_pedidos_cliente,
            (SELECT SUM(valor_final) FROM pedidos WHERE cliente_id = p.cliente_id AND status = 'entregue') as total_comprado_cliente
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios v ON p.vendedor_id = v.id
        LEFT JOIN pedido_arte pa ON pa.pedido_id = p.id
        LEFT JOIN usuarios af ON pa.arte_finalista_id = af.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    echo "✅ Query principal exata: OK<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHA 17-51: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>🧪 TESTE 2: Buscar Itens (Linhas 64-71)</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT pi.*, pc.codigo as produto_codigo, pc.nome as produto_nome, pc.imagem_principal
        FROM pedido_itens pi
        LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll();
    echo "✅ Buscar itens: OK (" . count($itens) . " itens)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 64-71: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 3: Buscar Arquivos (Linhas 73-80)</h2>";
try {
    // Verificar se a coluna usuario_id existe
    $hasUserIdColumn = $pdo->query("
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pedido_arquivos' AND column_name = 'usuario_id'
    ")->fetch();
    
    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare("
            SELECT pa.*, u.nome as usuario_nome
            FROM pedido_arquivos pa
            LEFT JOIN usuarios u ON pa.usuario_id = u.id
            WHERE pa.pedido_id = ?
            ORDER BY pa.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT pa.*, NULL as usuario_nome
            FROM pedido_arquivos pa
            WHERE pa.pedido_id = ?
            ORDER BY pa.created_at DESC
        ");
    }
    $stmt->execute([$pedido_id]);
    $arquivos = $stmt->fetchAll();
    echo "✅ Buscar arquivos: OK (" . count($arquivos) . " arquivos)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 73-80: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 4: Separar Imagens (Linhas 82-94)</h2>";
try {
    $imagens = [];
    $arquivos_outros = [];
    
    foreach ($arquivos as $arquivo) {
        $ext = strtolower(pathinfo($arquivo['nome_arquivo'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $imagens[] = $arquivo;
        } else {
            $arquivos_outros[] = $arquivo;
        }
    }
    echo "✅ Separar imagens: OK (" . count($imagens) . " imagens, " . count($arquivos_outros) . " outros)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 82-94: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 5: Histórico Status (Linhas 96-104)</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, u.nome as usuario_nome, u.perfil as usuario_perfil
        FROM producao_status ps
        LEFT JOIN usuarios u ON ps.usuario_id = u.id
        WHERE ps.pedido_id = ?
        ORDER BY ps.created_at DESC
    ");
    $stmt->execute([$pedido_id]);
    $historico = $stmt->fetchAll();
    echo "✅ Histórico status: OK (" . count($historico) . " eventos)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 96-104: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 6: Logs Sistema (Linhas 106-116)</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.nome as usuario_nome
        FROM logs_sistema l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        WHERE l.detalhes LIKE ? 
        ORDER BY l.created_at DESC
        LIMIT 20
    ");
    $stmt->execute(["%Pedido #{$pedido['numero']}%"]);
    $logs_alteracao = $stmt->fetchAll();
    echo "✅ Logs sistema: OK (" . count($logs_alteracao) . " logs)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 106-116: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 7: Versões Arte (Linhas 118-128)</h2>";
try {
    $versoes_arte = [];
    if ($pedido['arte_finalista_id']) {
        $stmt = $pdo->prepare("
            SELECT av.*, u.nome as usuario_nome
            FROM arte_versoes av
            LEFT JOIN usuarios u ON av.usuario_id = u.id
            WHERE av.pedido_id = ?
            ORDER BY av.versao DESC
        ");
        $stmt->execute([$pedido_id]);
        $versoes_arte = $stmt->fetchAll();
    }
    echo "✅ Versões arte: OK (" . count($versoes_arte) . " versões)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 118-128: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 8: Pedidos Relacionados (Linhas 130-138)</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT id, numero, status, valor_final, created_at
        FROM pedidos
        WHERE cliente_id = ? AND id != ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$pedido['cliente_id'], $pedido_id]);
    $pedidos_relacionados = $stmt->fetchAll();
    echo "✅ Pedidos relacionados: OK (" . count($pedidos_relacionados) . " relacionados)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 130-138: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 9: Cálculo de Dias (Linhas 140-144)</h2>";
try {
    $prazo = new DateTime($pedido['prazo_entrega']);
    $hoje = new DateTime();
    $diff = $hoje->diff($prazo);
    $dias_restantes = $diff->invert ? -$diff->days : $diff->days;
    echo "✅ Cálculo dias: OK ($dias_restantes dias)<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 140-144: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 10: Timeline Array (Linhas 146-165)</h2>";
try {
    $timeline_status = [
        'orcamento' => ['label' => 'Orçamento Criado', 'icon' => 'clipboard-list', 'date' => null, 'user' => null],
        'aprovado' => ['label' => 'Pedido Aprovado', 'icon' => 'check-circle', 'date' => null, 'user' => null],
        'pagamento_50' => ['label' => 'Entrada (50%)', 'icon' => 'currency-dollar', 'date' => null, 'user' => null],
        'producao' => ['label' => 'Em Produção', 'icon' => 'cog', 'date' => null, 'user' => null],
        'pagamento_100' => ['label' => 'Pagamento Final', 'icon' => 'credit-card', 'date' => null, 'user' => null],
        'pronto' => ['label' => 'Pronto para Entrega', 'icon' => 'package', 'date' => null, 'user' => null],
        'entregue' => ['label' => 'Produto Entregue', 'icon' => 'truck', 'date' => null, 'user' => null]
    ];
    
    // Preencher timeline
    foreach ($historico as $evento) {
        if (isset($timeline_status[$evento['status']])) {
            $timeline_status[$evento['status']]['date'] = $evento['created_at'];
            $timeline_status[$evento['status']]['user'] = $evento['usuario_nome'];
        }
    }
    echo "✅ Timeline array: OK<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 146-165: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 11: Função getStatusInfo (Linhas 180-195)</h2>";
try {
    function getStatusInfo($status) {
        $statusInfo = [
            'orcamento' => ['color' => 'bg-gray-600', 'text' => 'Orçamento'],
            'aprovado' => ['color' => 'bg-blue-600', 'text' => 'Aprovado'],
            'pagamento' => ['color' => 'bg-yellow-600', 'text' => 'Aguardando Pagamento'],
            'pagamento_50' => ['color' => 'bg-yellow-600', 'text' => 'Entrada 50%'],
            'pagamento_100' => ['color' => 'bg-yellow-700', 'text' => 'Pagamento Final'],
            'producao' => ['color' => 'bg-orange-600', 'text' => 'Em Produção'],
            'pronto' => ['color' => 'bg-green-600', 'text' => 'Pronto'],
            'entregue' => ['color' => 'bg-green-800', 'text' => 'Entregue'],
            'cancelado' => ['color' => 'bg-red-600', 'text' => 'Cancelado']
        ];
        return $statusInfo[$status] ?? ['color' => 'bg-gray-500', 'text' => ucfirst($status)];
    }
    
    $statusInfo = getStatusInfo($pedido['status']);
    echo "✅ Função getStatusInfo: OK - Status '{$pedido['status']}' = '{$statusInfo['text']}'<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 180-195: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 12: Verificações de Permissão (Linhas 197-206)</h2>";
try {
    $pode_editar = ($_SESSION['user_perfil'] === 'gestor' || 
                    ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] == $_SESSION['user_id'])) 
                    && in_array($pedido['status'], ['orcamento', 'aprovado']);

    $pode_aprovar = ($_SESSION['user_perfil'] === 'gestor' || 
                     ($_SESSION['user_perfil'] === 'vendedor' && $pedido['vendedor_id'] == $_SESSION['user_id'])) 
                     && $pedido['status'] === 'orcamento';

    $pode_cancelar = $_SESSION['user_perfil'] === 'gestor' && !in_array($pedido['status'], ['entregue', 'cancelado']);
    $pode_avancar_status = $_SESSION['user_perfil'] === 'gestor';
    
    echo "✅ Verificações permissão: OK<br>";
    echo "   - Pode editar: " . ($pode_editar ? 'Sim' : 'Não') . "<br>";
    echo "   - Pode aprovar: " . ($pode_aprovar ? 'Sim' : 'Não') . "<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO LINHAS 197-206: " . $e->getMessage() . "<br>";
}

echo "<h2>🧪 TESTE 13: Includes Header/Footer</h2>";
try {
    $titulo = 'Pedido #' . $pedido['numero'];
    $breadcrumb = [
        ['label' => 'Pedidos', 'url' => 'pedidos.php'],
        ['label' => 'Detalhes do Pedido']
    ];
    
    // Testar se consegue incluir header (sem output buffer)
    ob_start();
    include '../views/_header.php';
    $header_output = ob_get_clean();
    
    if (strlen($header_output) > 0) {
        echo "✅ Include header: OK (" . strlen($header_output) . " chars)<br>";
    } else {
        echo "⚠️ Include header retornou vazio<br>";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO no include header: " . $e->getMessage() . "<br>";
    echo "🎯 POSSÍVEL CAUSA DO ERRO 500: Erro no _header.php<br>";
}

echo "<h2>🧪 TESTE 14: HTML com PHP Embedded</h2>";
try {
    // Testar construção de HTML específica que pode dar problema
    $test_html = '';
    
    // Testar número formatado
    $test_html .= "Pedido #" . htmlspecialchars($pedido['numero']);
    echo "✅ htmlspecialchars numero: OK<br>";
    
    // Testar formatação de moeda
    $test_html .= formatarMoeda($pedido['valor_total']);
    echo "✅ formatarMoeda valor_total: OK<br>";
    
    // Testar data com verificação NULL
    if ($pedido['prazo_entrega']) {
        $test_html .= formatarData($pedido['prazo_entrega']);
        echo "✅ formatarData prazo: OK<br>";
    }
    
    // Testar WhatsApp link
    if ($pedido['cliente_telefone']) {
        $whatsapp_msg = "Olá! Gostaria de informações sobre o pedido #{$pedido['numero']}";
        $whatsapp_link = gerarLinkWhatsApp($pedido['cliente_telefone'], $whatsapp_msg);
        echo "✅ gerarLinkWhatsApp: OK<br>";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO na construção HTML: " . $e->getMessage() . "<br>";
    echo "🎯 POSSÍVEL CAUSA DO ERRO 500: Erro em função auxiliar ou formatação<br>";
}

echo "<h2>🧪 TESTE 15: Loop Foreach Críticos</h2>";
try {
    // Loop dos itens (linha ~340)
    foreach ($itens as $index => $item) {
        $teste = $index + 1;
        $teste .= htmlspecialchars($item['descricao']);
        $teste .= number_format($item['quantidade'], 0);
        $teste .= formatarMoeda($item['valor_unitario']);
        $teste .= formatarMoeda($item['valor_total']);
    }
    echo "✅ Loop itens: OK<br>";
    
    // Loop dos arquivos (linha ~500+)
    foreach ($arquivos as $arquivo) {
        $teste = htmlspecialchars($arquivo['nome_arquivo']);
        $teste .= formatarDataHora($arquivo['created_at']);
        if (isset($arquivo['descricao'])) {
            $teste .= htmlspecialchars($arquivo['descricao']);
        }
    }
    echo "✅ Loop arquivos: OK<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO nos loops: " . $e->getMessage() . "<br>";
    echo "🎯 POSSÍVEL CAUSA DO ERRO 500: Campo NULL acessado incorretamente<br>";
}

echo "<h2>🧪 TESTE 16: Memory Usage</h2>";
$memory_used = memory_get_usage(true);
$memory_peak = memory_get_peak_usage(true);
echo "📊 Memória atual: " . number_format($memory_used / 1024 / 1024, 2) . " MB<br>";
echo "📊 Pico de memória: " . number_format($memory_peak / 1024 / 1024, 2) . " MB<br>";

if ($memory_peak > 128 * 1024 * 1024) { // 128MB
    echo "⚠️ ALTO USO DE MEMÓRIA - Possível causa do erro 500<br>";
}

echo "<h2>🧪 TESTE 17: Testar JavaScript Específico</h2>";
try {
    // Simular construção do JavaScript problemático
    $js_vars = json_encode(['id' => $pedido_id, 'status' => $pedido['status']]);
    echo "✅ JSON encode para JS: OK<br>";
    
    // Testar construção de links dinâmicos
    $test_url = "orcamento_aprovar.php?id=" . $pedido_id;
    echo "✅ URLs dinâmicas: OK<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO no JavaScript: " . $e->getMessage() . "<br>";
}

echo "<hr><h2>🎯 DIAGNÓSTICO ESPECÍFICO</h2>";

// Se chegou até aqui sem erro, o problema é MUITO específico
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; border-left: 4px solid #0c5460;'>";
echo "<strong>CONCLUSÃO:</strong><br>";
echo "Todos os testes passaram! O erro 500 pode estar em:<br><br>";

echo "<strong>1. MEMORY LIMIT:</strong> Arquivo muito grande/complexo<br>";
echo "<strong>2. ARRAY ACCESS específico:</strong> Algum campo acessado que pode ser NULL<br>";
echo "<strong>3. HTML mal formado:</strong> Tag não fechada quebra a página<br>";
echo "<strong>4. TIMEOUT:</strong> Muitas queries demoram demais<br>";
echo "<strong>5. ERROR REPORTING:</strong> Servidor escondendo erros<br><br>";

echo "<strong>AÇÃO IMEDIATA:</strong><br>";
echo "1. Execute: <code>tail -f /var/log/apache2/error.log</code><br>";
echo "2. Acesse pedido_detalhes.php?id=1 em outra aba<br>";
echo "3. Veja o erro EXATO que aparece no log<br>";
echo "4. OU use o fix_pedido_detalhes.php como versão limpa<br>";
echo "</div>";

echo "<h2>🔧 COMPARAÇÃO DE ARQUIVOS</h2>";
if (file_exists('pedido_detalhes.php')) {
    $original_size = filesize('pedido_detalhes.php');
    $original_lines = count(file('pedido_detalhes.php'));
    
    echo "📊 Arquivo original:<br>";
    echo "   - Tamanho: " . number_format($original_size) . " bytes<br>";
    echo "   - Linhas: $original_lines<br>";
    
    if ($original_size > 50000) { // 50KB
        echo "⚠️ ARQUIVO MUITO GRANDE - Possível timeout<br>";
    }
    
    if ($original_lines > 800) {
        echo "⚠️ MUITAS LINHAS - Possível memory limit<br>";
    }
}
?>