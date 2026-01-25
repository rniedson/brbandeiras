<?php
require_once '../../../app/config.php';
require_once '../../../app/auth.php';
require_once '../../../app/functions.php';

requireLogin();
requireRole(['producao', 'gestor', 'vendedor']);

$pedido_id = $_GET['id'] ?? null;
$modo_edicao = isset($_GET['editar']) && in_array($_SESSION['user_perfil'], ['gestor', 'vendedor']);
$modo_iframe = isset($_GET['iframe']);

if (!$pedido_id) {
    header('Location: producao.php');
    exit;
}

// Processar salvamento de edições (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'salvar_observacao') {
            $item_id = $_POST['item_id'];
            $observacao = $_POST['observacao'];
            
            $stmt = $pdo->prepare("UPDATE pedido_itens SET observacoes = ? WHERE id = ?");
            $stmt->execute([$observacao, $item_id]);
            
            echo json_encode(['success' => true]);
        } elseif ($_POST['action'] === 'salvar_material') {
            // Aqui você pode adicionar um campo material na tabela se necessário
            echo json_encode(['success' => true, 'message' => 'Material atualizado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Buscar dados do pedido
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        c.nome as cliente_nome,
        v.nome as vendedor_nome
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios v ON p.vendedor_id = v.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    $_SESSION['erro'] = 'Pedido não encontrado';
    header('Location: producao.php');
    exit;
}

// Formatar número da OS - apenas últimos 4 dígitos
$numero_os = substr($pedido['numero'], -4);

// Buscar itens do pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pc.nome as produto_nome, pc.categoria_id
    FROM pedido_itens pi
    LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$itens = $stmt->fetchAll();

// Buscar TODAS as artes aprovadas
$stmt = $pdo->prepare("
    SELECT * FROM arte_versoes 
    WHERE pedido_id = ? AND aprovada = true 
    ORDER BY versao DESC
");
$stmt->execute([$pedido_id]);
$artes_aprovadas = $stmt->fetchAll();

// Detectar tipo de produto
$tipo_produto = 'geral';
$tem_uniforme = false;

foreach ($itens as $item) {
    $descricao_lower = strtolower($item['descricao']);
    if (strpos($descricao_lower, 'camisa') !== false || strpos($descricao_lower, 'camiseta') !== false || 
        strpos($descricao_lower, 'short') !== false || strpos($descricao_lower, 'uniforme') !== false) {
        $tem_uniforme = true;
        $tipo_produto = 'uniforme';
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OS <?= $numero_os ?> - BR Bandeiras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { 
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .editable { border: none !important; }
            @page { 
                size: A4; 
                margin: 5mm;
            }
        }
        body { 
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .checkbox-etapa {
            width: 14px;
            height: 14px;
            border: 2px solid #000;
            display: inline-block;
            margin-right: 4px;
            vertical-align: middle;
        }
        .checkbox-etapa.checked {
            background-color: #16a34a;
            position: relative;
        }
        .checkbox-etapa.checked:after {
            content: '✓';
            position: absolute;
            color: white;
            font-weight: bold;
            font-size: 10px;
            top: -2px;
            left: 1px;
        }
        .destaque-material {
            background-color: #fef08a !important;
            border: 2px solid #ca8a04 !important;
            font-weight: bold;
        }
        .grade-table {
            font-size: 9px;
        }
        .grade-table td, .grade-table th {
            border: 1px solid #000;
            padding: 2px 4px;
            text-align: center;
        }
        .editable {
            border: 1px dashed #ccc;
            padding: 2px;
            min-height: 20px;
            cursor: text;
        }
        .editable:focus {
            border: 1px solid #3b82f6;
            outline: none;
            background-color: #eff6ff;
        }
        <?php if ($modo_iframe): ?>
        body {
            background: white;
            padding: 0;
            margin: 0;
        }
        <?php endif; ?>
    </style>
</head>
<body class="bg-white">
    
    <!-- Container Principal A4 -->
    <div class="max-w-[200mm] mx-auto bg-white p-2">
        
        <!-- Cabeçalho -->
        <div class="border-2 border-black">
            <!-- Logo e Info Empresa -->
            <div class="flex justify-between items-start p-3 border-b-2 border-black">
                <div class="flex items-center gap-4">
                    <div>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-green-600">BR</span>
                            <span class="text-3xl font-bold">BANDEIRAS</span>
                            <div class="ml-2 w-8 h-4 bg-yellow-400"></div>
                        </div>
                        <p class="text-xs text-gray-600">COMUNICAÇÃO VISUAL</p>
                    </div>
                </div>
                <div class="text-right text-xs text-gray-700">
                    <strong>BR BANDEIRAS LTDA</strong><br>
                    CNPJ: 33.867.095/0001-02<br>
                    AV. BELA VISTA, QD. 09, LT. 01 - GOIÂNIA/GO
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">OS Nº <?= htmlspecialchars($numero_os) ?></div>
                    <?php if ($pedido['urgente']): ?>
                    <div class="mt-1">
                        <span class="bg-red-600 text-white px-3 py-1 rounded text-sm font-bold animate-pulse">⚡ URGENTE</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informações do Pedido -->
            <div class="flex justify-between p-3 bg-gray-50 border-b-2 border-black text-sm">
                <div><strong>PEDIDO:</strong> <?= htmlspecialchars($pedido['numero']) ?></div>
                <div><strong>CLIENTE:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></div>
                <div><strong>VENDEDOR:</strong> <?= htmlspecialchars($pedido['vendedor_nome']) ?></div>
                <div><strong>EMISSÃO:</strong> <?= formatarData($pedido['created_at']) ?></div>
                <div><strong>ENTREGA:</strong> 
                    <span class="font-bold <?= strtotime($pedido['prazo_entrega']) < time() ? 'text-red-600' : 'text-green-600' ?>">
                        <?= formatarData($pedido['prazo_entrega']) ?>
                    </span>
                </div>
            </div>

            <!-- Etapas de Produção -->
            <div class="flex justify-between p-3 border-b-2 border-black bg-yellow-50">
                <div class="flex items-center">
                    <span class="checkbox-etapa" id="check-arte"></span>
                    <span class="text-sm font-medium mr-4">ARTE FINAL</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa" id="check-impressao"></span>
                    <span class="text-sm font-medium mr-4">IMPRESSÃO</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa" id="check-producao"></span>
                    <span class="text-sm font-medium mr-4">PRODUÇÃO</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa" id="check-conferencia"></span>
                    <span class="text-sm font-medium mr-4">CONFERÊNCIA</span>
                </div>
                <?php if (!empty($artes_aprovadas)): ?>
                <div class="text-sm">
                    <strong>ARTE:</strong> 
                    <?php 
                    $versoes = array_column($artes_aprovadas, 'versao');
                    echo 'V' . implode(', V', $versoes);
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Aviso Importante -->
            <div class="bg-yellow-200 text-center py-2 text-sm font-bold border-b-2 border-black">
                ⚠️ CONFERIR: CORES, ORTOGRAFIA, QUANTIDADE E FRENTE/VERSO
            </div>

            <!-- Tabela de Itens -->
            <div class="p-3">
                <table class="w-full border-2 border-black">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-black px-2 py-2 text-left">DESCRIÇÃO</th>
                            <th class="border border-black px-2 py-2 w-16 text-center">QTD</th>
                            <?php if ($tipo_produto === 'uniforme'): ?>
                            <th class="border border-black px-2 py-2 w-24 text-center">TAMANHOS</th>
                            <?php endif; ?>
                            <th class="border border-black px-2 py-2 w-32 text-center destaque-material">MATERIAL/TIPO</th>
                            <th class="border border-black px-2 py-2 bg-yellow-100">OBSERVAÇÕES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $item): ?>
                        <tr>
                            <td class="border border-black px-2 py-2">
                                <?= htmlspecialchars($item['descricao']) ?>
                                <?php if ($item['produto_nome']): ?>
                                    <br><span class="text-xs text-gray-600"><?= htmlspecialchars($item['produto_nome']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="border border-black px-2 py-2 text-center font-bold text-lg">
                                <?= number_format($item['quantidade'], 0) ?>
                            </td>
                            <?php if ($tipo_produto === 'uniforme'): ?>
                            <td class="border border-black px-2 py-2 text-center text-xs">
                                <div>P: ___ M: ___ G: ___</div>
                                <div>GG: ___ XG: ___</div>
                            </td>
                            <?php endif; ?>
                            <td class="border border-black px-2 py-2 text-center destaque-material">
                                <?php if ($modo_edicao): ?>
                                <div contenteditable="true" 
                                     class="editable" 
                                     data-item-id="<?= $item['id'] ?>"
                                     data-field="material">
                                <?php endif; ?>
                                <?php
                                // Detectar material do item
                                $material = 'VERIFICAR';
                                $desc_lower = strtolower($item['descricao'] . ' ' . ($item['observacoes'] ?? ''));
                                if (strpos($desc_lower, 'dryfit') !== false) $material = 'DRYFIT';
                                elseif (strpos($desc_lower, 'algodão') !== false || strpos($desc_lower, 'algodao') !== false) $material = 'ALGODÃO';
                                elseif (strpos($desc_lower, 'cetim') !== false) $material = 'CETIM';
                                elseif (strpos($desc_lower, 'lona') !== false) $material = 'LONA';
                                elseif (strpos($desc_lower, 'vinil') !== false) $material = 'VINIL';
                                elseif (strpos($desc_lower, 'adesivo') !== false) $material = 'ADESIVO';
                                echo $material;
                                ?>
                                <?php if ($modo_edicao): ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="border border-black px-2 py-2 bg-yellow-50 text-xs">
                                <?php if ($modo_edicao): ?>
                                <div contenteditable="true" 
                                     class="editable" 
                                     data-item-id="<?= $item['id'] ?>"
                                     data-field="observacao">
                                    <?= nl2br(htmlspecialchars($item['observacoes'] ?? '')) ?>
                                </div>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($item['observacoes'] ?? '')) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grade de Tamanhos para uniformes -->
            <?php if ($tipo_produto === 'uniforme'): ?>
            <div class="p-3">
                <div class="bg-black text-white text-center py-1 text-sm font-bold">GRADE DE TAMANHOS</div>
                <table class="w-full grade-table">
                    <thead>
                        <tr class="bg-gray-200">
                            <th>ITEM</th>
                            <th>PP</th>
                            <th>P</th>
                            <th>M</th>
                            <th>G</th>
                            <th>GG</th>
                            <th>XG</th>
                            <th>XGG</th>
                            <th class="bg-gray-300 font-bold">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 1;
                        foreach ($itens as $item): 
                            if (strpos(strtolower($item['descricao']), 'camis') !== false || 
                                strpos(strtolower($item['descricao']), 'short') !== false):
                        ?>
                        <tr>
                            <td class="bg-gray-100 text-xs"><?= $contador++ ?></td>
                            <td>___</td>
                            <td>___</td>
                            <td>___</td>
                            <td>___</td>
                            <td>___</td>
                            <td>___</td>
                            <td>___</td>
                            <td class="bg-gray-200 font-bold"><?= $item['quantidade'] ?></td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Área de Arte - TODAS as aprovadas -->
            <?php if (!empty($artes_aprovadas)): ?>
            <div class="p-3">
                <div class="border-2 border-black p-4 bg-gray-50">
                    <p class="text-sm font-bold mb-3 text-center">
                        ARTES FINAIS APROVADAS (<?= count($artes_aprovadas) ?> versões)
                    </p>
                    
                    <div class="grid grid-cols-<?= count($artes_aprovadas) > 1 ? '2' : '1' ?> gap-3">
                        <?php foreach ($artes_aprovadas as $arte): 
                            $ext = strtolower(pathinfo($arte['arquivo_nome'], PATHINFO_EXTENSION));
                            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        ?>
                        <div class="border border-gray-300 p-2 bg-white">
                            <p class="text-xs font-bold text-center mb-2">
                                VERSÃO <?= $arte['versao'] ?>
                            </p>
                            <div class="text-gray-500 text-xs text-center mb-2">
                                <?= htmlspecialchars($arte['arquivo_nome']) ?><br>
                                Aprovada: <?= formatarData($arte['created_at']) ?>
                            </div>
                            
                            <?php if ($is_image && file_exists('../' . $arte['arquivo_caminho'])): ?>
                                <img src="../<?= htmlspecialchars($arte['arquivo_caminho']) ?>" 
                                     alt="Arte V<?= $arte['versao'] ?>"
                                     class="max-w-full h-auto mx-auto"
                                     style="max-height: 250px;">
                            <?php else: ?>
                                <div class="p-4 bg-gray-100 text-center">
                                    <p class="text-xs text-gray-400">[ARTE VERSÃO <?= $arte['versao'] ?>]</p>
                                    <p class="text-xs text-gray-500 mt-1">Arquivo: <?= htmlspecialchars($arte['arquivo_nome']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="p-3">
                <div class="border-2 border-red-400 p-4 text-center bg-red-50">
                    <p class="text-sm font-bold text-red-600">⚠️ NENHUMA ARTE APROVADA</p>
                    <p class="text-xs text-red-500 mt-1">Verificar com arte-finalista antes de produzir</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Área de Observações de Produção -->
            <div class="p-3">
                <div class="border-2 border-black p-3">
                    <div class="text-sm font-bold mb-2">OBSERVAÇÕES DE PRODUÇÃO:</div>
                    <div style="height: 60px;"></div>
                </div>
            </div>

            <!-- Checklist de Qualidade -->
            <div class="flex justify-between p-3 border-t-2 border-black bg-gray-50">
                <div class="flex items-center">
                    <span class="checkbox-etapa"></span>
                    <span class="text-xs">Cores OK</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa"></span>
                    <span class="text-xs">Medidas OK</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa"></span>
                    <span class="text-xs">Ortografia OK</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa"></span>
                    <span class="text-xs">Material OK</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa"></span>
                    <span class="text-xs">Quantidade OK</span>
                </div>
                <div class="flex items-center">
                    <span class="checkbox-etapa"></span>
                    <span class="text-xs">Embalado</span>
                </div>
            </div>

            <!-- Rodapé -->
            <div class="flex justify-between items-center bg-green-600 text-white px-3 py-2">
                <div class="text-xs">
                    📞 (62) 3225-5794 | 📱 (62) 98300-1611
                </div>
                <div class="text-xs">
                    OS GERADA: <?= date('d/m/Y H:i') ?>
                </div>
                <div class="text-xs">
                    www.brbandeiras.com.br
                </div>
            </div>
        </div>
    </div>

    <?php if (!$modo_iframe): ?>
    <!-- Botões de Ação (não imprime e não aparece em iframe) -->
    <div class="max-w-[200mm] mx-auto mt-4 flex flex-wrap gap-3 no-print p-4">
        <button onclick="window.print()" 
                class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2 font-medium">
            🖨️ Imprimir OS
        </button>
        
        <?php if (in_array($_SESSION['user_perfil'], ['gestor', 'vendedor'])): ?>
        <button onclick="toggleEdicao()" 
                class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            ✏️ <?= $modo_edicao ? 'Visualizar' : 'Editar' ?> Campos
        </button>
        <?php endif; ?>
        
        <button onclick="marcarEtapa('arte')" 
                class="px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
            ✓ Arte OK
        </button>
        
        <button onclick="marcarEtapa('impressao')" 
                class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            ✓ Impressão OK
        </button>
        
        <button onclick="marcarEtapa('producao')" 
                class="px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
            ✓ Produção OK
        </button>
        
        <button onclick="marcarEtapa('conferencia')" 
                class="px-4 py-3 bg-green-700 text-white rounded-lg hover:bg-green-800 transition">
            ✓ Conferência OK
        </button>
        
        <button onclick="window.location.href='producao.php'" 
                class="px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            ← Voltar
        </button>
    </div>
    <?php endif; ?>

    <script>
        // Marcar etapas de produção
        function marcarEtapa(etapa) {
            const checkbox = document.getElementById('check-' + etapa);
            if (checkbox) {
                checkbox.classList.toggle('checked');
            }
        }
        
        // Toggle modo edição
        function toggleEdicao() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('editar')) {
                urlParams.delete('editar');
            } else {
                urlParams.set('editar', '1');
            }
            window.location.search = urlParams.toString();
        }
        
        // Salvar edições (se modo edição ativo)
        <?php if ($modo_edicao): ?>
        document.querySelectorAll('.editable').forEach(el => {
            el.addEventListener('blur', function() {
                const itemId = this.dataset.itemId;
                const field = this.dataset.field;
                const value = this.innerText;
                
                // Salvar via AJAX
                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=salvar_${field}&item_id=${itemId}&${field}=${encodeURIComponent(value)}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Flash de confirmação
                        this.style.backgroundColor = '#bbf7d0';
                        setTimeout(() => {
                            this.style.backgroundColor = '';
                        }, 500);
                    }
                });
            });
        });
        <?php endif; ?>
        
        // Marcar arte como OK se houver artes aprovadas
        <?php if (!empty($artes_aprovadas)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('check-arte')?.classList.add('checked');
        });
        <?php endif; ?>
    </script>

</body>
</html>