<?php
// Permitir uso tanto direto quanto via include/iframe
if (!isset($pedido_id)) {
    require_once '../app/config.php';
    require_once '../app/auth.php';
    require_once '../app/functions.php';
    
    // Se acessado diretamente
    requireLogin();
    $pedido_id = $_GET['id'] ?? null;
    $modo_standalone = true;
} else {
    // Se incluído de outro arquivo
    $modo_standalone = false;
}

// Modo iframe
$modo_iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';

if (!$pedido_id) {
    echo '<div class="text-red-600 p-4">Erro: ID do orçamento não fornecido</div>';
    exit;
}

// Buscar dados do pedido/orçamento
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        c.nome as cliente_nome,
        c.nome_fantasia,
        c.cpf_cnpj,
        c.telefone as cliente_telefone,
        c.celular as cliente_celular,
        c.whatsapp as cliente_whatsapp,
        c.email as cliente_email,
        c.endereco,
        c.numero,
        c.complemento,
        c.bairro,
        c.cidade,
        c.estado,
        c.cep,
        v.nome as vendedor_nome,
        v.email as vendedor_email,
        v.telefone as vendedor_telefone
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios v ON p.vendedor_id = v.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    echo '<div class="text-red-600 p-4">Erro: Orçamento não encontrado</div>';
    exit;
}

// Buscar itens do pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pc.nome as produto_nome, pc.codigo as produto_codigo
    FROM pedido_itens pi
    LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$itens = $stmt->fetchAll();

// Calcular valores
$subtotal = $pedido['valor_total'];
$desconto = $pedido['desconto'] ?? 0;
$tipo_desconto = $pedido['tipo_desconto'] ?? 'valor';
$valor_desconto = $tipo_desconto === 'porcentagem' ? ($subtotal * $desconto / 100) : $desconto;
$frete = 0; // Pode adicionar campo de frete no banco futuramente
$total = $pedido['valor_final'];

// Determinar telefone principal do cliente
$telefone_cliente = $pedido['cliente_whatsapp'] ?: $pedido['cliente_celular'] ?: $pedido['cliente_telefone'];

// Calcular validade (7 dias a partir da criação)
$data_criacao = new DateTime($pedido['created_at']);
$data_validade = clone $data_criacao;
$data_validade->add(new DateInterval('P7D'));
$validade_expirada = $data_validade < new DateTime();

// Gerar ID único para este orçamento (para impressão isolada)
$orcamento_id = 'orc_' . uniqid();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento #<?= htmlspecialchars($pedido['numero']) ?> - BR Bandeiras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { 
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                background: white !important;
            }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            @page { 
                size: A4; 
                margin: 10mm;
            }
            /* Esconder tudo exceto este orçamento quando imprimir */
            body > *:not(#<?= $orcamento_id ?>) {
                display: none !important;
            }
            #<?= $orcamento_id ?> {
                position: static !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
            }
        }
        .header-gradient {
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 50%, #fbbf24 100%);
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(34, 197, 94, 0.03);
            z-index: -1;
            font-weight: bold;
            pointer-events: none;
        }
        .valor-destaque {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid #f59e0b;
        }
        .print-only {
            display: none;
        }
        <?php if ($modo_iframe): ?>
        body {
            background: white;
            margin: 0;
            padding: 0;
        }
        <?php endif; ?>
    </style>
</head>
<body class="<?= $modo_iframe ? 'bg-white' : 'bg-gray-50' ?>">
    
    <!-- Container Principal do Orçamento com ID único -->
    <div id="<?= $orcamento_id ?>" class="orcamento-container">
        
        <!-- Marca d'água sutil -->
        <div class="watermark">
            <?= $pedido['status'] === 'orcamento' ? 'ORÇAMENTO' : 'PEDIDO' ?>
        </div>

        <!-- Container A4 -->
        <div class="max-w-[210mm] mx-auto bg-white <?= !$modo_iframe ? 'shadow-lg' : '' ?>">
            
            <!-- Cabeçalho -->
            <div class="header-gradient p-6 text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center mb-2">
                            <span class="text-4xl font-bold">BR</span>
                            <span class="text-4xl font-bold ml-1">BANDEIRAS</span>
                            <div class="ml-3 w-10 h-5 bg-yellow-400 rounded"></div>
                        </div>
                        <p class="text-sm opacity-90">Comunicação Visual de Qualidade</p>
                        <div class="mt-3 text-xs opacity-80">
                            <p>CNPJ: 33.867.095/0001-02</p>
                            <p>Av. Bela Vista, Qd. 09, Lt. 01, nº 1.145</p>
                            <p>Goiânia/GO - CEP: 74853-410</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="bg-white/20 backdrop-blur rounded-lg p-3">
                            <p class="text-2xl font-bold">
                                <?= $pedido['status'] === 'orcamento' ? 'ORÇAMENTO' : 'PEDIDO' ?>
                            </p>
                            <p class="text-3xl mt-1">#<?= htmlspecialchars($pedido['numero']) ?></p>
                            <p class="text-sm mt-2 opacity-90">Data: <?= formatarData($pedido['created_at']) ?></p>
                            <?php if ($pedido['status'] === 'orcamento'): ?>
                            <p class="text-sm font-bold <?= $validade_expirada ? 'text-red-300' : 'text-yellow-300' ?>">
                                <?= $validade_expirada ? 'Validade expirada' : 'Validade: ' . $data_validade->format('d/m/Y') ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados do Cliente e Vendedor -->
            <div class="px-6 py-4 bg-gray-50 border-b-2 border-gray-200">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-bold text-gray-700 mb-2 uppercase">Cliente</h3>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                        <?php if ($pedido['nome_fantasia']): ?>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($pedido['nome_fantasia']) ?></p>
                        <?php endif; ?>
                        <?php if ($pedido['cpf_cnpj']): ?>
                            <p class="text-sm text-gray-600">
                                <?= strlen($pedido['cpf_cnpj']) > 11 ? 'CNPJ' : 'CPF' ?>: 
                                <?= htmlspecialchars(formatarCpfCnpj($pedido['cpf_cnpj'])) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($pedido['cliente_email']): ?>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($pedido['cliente_email']) ?></p>
                        <?php endif; ?>
                        <?php if ($telefone_cliente): ?>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars(formatarTelefone($telefone_cliente)) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-700 mb-2 uppercase">Atendimento</h3>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($pedido['vendedor_nome']) ?></p>
                        <p class="text-sm text-gray-600">Consultor de Vendas</p>
                        <?php if ($pedido['vendedor_email']): ?>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($pedido['vendedor_email']) ?></p>
                        <?php endif; ?>
                        <?php if ($pedido['vendedor_telefone']): ?>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars(formatarTelefone($pedido['vendedor_telefone'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Apresentação -->
            <?php if ($pedido['status'] === 'orcamento'): ?>
            <div class="px-6 py-4">
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                    <p class="text-gray-700">
                        Prezado(a) cliente, é um prazer apresentar nossa proposta comercial. 
                        A <strong>BR Bandeiras</strong> possui mais de 10 anos de experiência no mercado, 
                        oferecendo produtos de alta qualidade com garantia e excelência no atendimento.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabela de Produtos -->
            <div class="px-6 pb-4">
                <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                    <span class="bg-green-600 text-white px-2 py-1 rounded text-xs mr-2">PRODUTOS</span>
                    Itens do <?= $pedido['status'] === 'orcamento' ? 'Orçamento' : 'Pedido' ?>
                </h3>
                
                <table class="w-full border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Descrição</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase">Qtd</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Valor Unit.</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $contador = 1;
                        foreach ($itens as $item): 
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm"><?= str_pad($contador++, 2, '0', STR_PAD_LEFT) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">
                                    <?= htmlspecialchars($item['descricao']) ?>
                                </div>
                                <?php if ($item['observacoes']): ?>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars($item['observacoes']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold"><?= number_format($item['quantidade'], 0) ?></td>
                            <td class="px-4 py-3 text-right"><?= formatarMoeda($item['valor_unitario']) ?></td>
                            <td class="px-4 py-3 text-right font-semibold"><?= formatarMoeda($item['valor_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Valores e Condições -->
            <div class="px-6 pb-4">
                <div class="grid grid-cols-2 gap-6">
                    <!-- Resumo de Valores -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-700 mb-3 uppercase">Investimento</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-semibold"><?= formatarMoeda($subtotal) ?></span>
                            </div>
                            <?php if ($valor_desconto > 0): ?>
                            <div class="flex justify-between mb-2 text-green-600">
                                <span>Desconto<?= $tipo_desconto === 'porcentagem' ? " ({$desconto}%)" : '' ?>:</span>
                                <span class="font-semibold">- <?= formatarMoeda($valor_desconto) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($frete > 0): ?>
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Frete:</span>
                                <span class="font-semibold"><?= formatarMoeda($frete) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="valor-destaque p-3 rounded mt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-gray-800">TOTAL:</span>
                                    <span class="text-2xl font-bold text-green-600"><?= formatarMoeda($total) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Condições Comerciais -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-700 mb-3 uppercase">Condições Comerciais</h3>
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="mb-3">
                                <p class="font-semibold text-gray-700 mb-1">💳 Formas de Pagamento:</p>
                                <ul class="text-sm text-gray-600 ml-4 space-y-1">
                                    <li>• À vista: 5% desconto adicional</li>
                                    <li>• 50% entrada + 50% na entrega</li>
                                    <li>• Parcelado em até 3x no cartão</li>
                                    <li>• PIX com desconto especial</li>
                                </ul>
                            </div>
                            <div class="mb-3">
                                <p class="font-semibold text-gray-700 mb-1">📅 Prazo de Entrega:</p>
                                <p class="text-sm text-gray-600 ml-4">
                                    <?php
                                    $prazo = new DateTime($pedido['prazo_entrega']);
                                    $dias = $data_criacao->diff($prazo)->days;
                                    echo "{$dias} dias úteis após aprovação";
                                    ?>
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-700 mb-1">✅ Garantia:</p>
                                <p class="text-sm text-gray-600 ml-4">6 meses contra defeitos de fabricação</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Observações -->
            <?php if ($pedido['observacoes'] || $pedido['status'] === 'orcamento'): ?>
            <div class="px-6 pb-4">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-2">📌 Observações Importantes:</h3>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <?php if ($pedido['status'] === 'orcamento'): ?>
                        <li>• Este orçamento tem validade de 7 dias a partir da data de emissão</li>
                        <li>• Produtos personalizados necessitam aprovação de arte antes da produção</li>
                        <li>• Prazo de entrega inicia após confirmação do pagamento e aprovação da arte</li>
                        <?php endif; ?>
                        <?php if ($pedido['observacoes']): ?>
                        <li><?= nl2br(htmlspecialchars($pedido['observacoes'])) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Por que escolher BR Bandeiras -->
            <div class="px-6 pb-4">
                <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-3">🏆 Por que escolher a BR Bandeiras?</h3>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-2xl mb-1">✨</div>
                            <p class="text-xs font-semibold text-gray-700">Qualidade Premium</p>
                            <p class="text-xs text-gray-600">Materiais de primeira linha</p>
                        </div>
                        <div>
                            <div class="text-2xl mb-1">⚡</div>
                            <p class="text-xs font-semibold text-gray-700">Entrega Rápida</p>
                            <p class="text-xs text-gray-600">Cumprimos prazos</p>
                        </div>
                        <div>
                            <div class="text-2xl mb-1">🤝</div>
                            <p class="text-xs font-semibold text-gray-700">Suporte Total</p>
                            <p class="text-xs text-gray-600">Do pedido à entrega</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Área de Aprovação (apenas para orçamento) -->
            <?php if ($pedido['status'] === 'orcamento' && !$validade_expirada): ?>
            <div class="mx-6 pb-4 no-print">
                <div class="border-2 border-dashed border-green-400 rounded-lg p-6 text-center bg-green-50">
                    <h3 class="text-lg font-bold text-green-700 mb-2">✅ APROVAR ORÇAMENTO</h3>
                    <p class="text-sm text-gray-700 mb-4">
                        Para aprovar este orçamento e iniciar a produção, entre em contato:
                    </p>
                    <div class="flex justify-center gap-4">
                        <?php if ($telefone_cliente): ?>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $telefone_cliente) ?>?text=Olá! Gostaria de aprovar o orçamento #<?= $pedido['numero'] ?>" 
                           class="bg-green-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-green-700 inline-flex items-center">
                            📱 WhatsApp
                        </a>
                        <?php endif; ?>
                        <a href="mailto:<?= htmlspecialchars($pedido['vendedor_email']) ?>?subject=Aprovação Orçamento #<?= $pedido['numero'] ?>" 
                           class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 inline-flex items-center">
                            ✉️ E-mail
                        </a>
                        <button class="bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-700">
                            📞 (62) 3300-1611
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Área de Assinatura (aparece na impressão) -->
            <div class="px-6 pb-6 print-only">
                <div class="grid grid-cols-2 gap-8 mt-8">
                    <div class="text-center">
                        <div class="border-t-2 border-gray-400 mb-2"></div>
                        <p class="font-semibold"><?= htmlspecialchars($pedido['vendedor_nome']) ?></p>
                        <p class="text-xs text-gray-600">Consultor de Vendas - BR Bandeiras</p>
                    </div>
                    <div class="text-center">
                        <div class="border-t-2 border-gray-400 mb-2"></div>
                        <p class="font-semibold">Cliente</p>
                        <p class="text-xs text-gray-600">Assinatura de Aprovação</p>
                    </div>
                </div>
            </div>

            <!-- Rodapé -->
            <div class="bg-gray-800 text-white text-center py-4 text-xs">
                <div class="flex justify-center items-center gap-6 mb-2">
                    <span>📧 vendas@brbandeiras.com.br</span>
                    <span>🌐 www.brbandeiras.com.br</span>
                    <span>📍 Goiânia - GO</span>
                </div>
                <p class="text-gray-400">
                    Documento gerado em <?= date('d/m/Y \à\s H:i') ?> | 
                    <?php if ($pedido['status'] === 'orcamento'): ?>
                        <?= $validade_expirada ? 'Validade expirada' : 'Válido até ' . $data_validade->format('d/m/Y') ?>
                    <?php else: ?>
                        Pedido confirmado
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Botões de Ação (não imprimem) -->
        <?php if (!$modo_iframe): ?>
        <div class="max-w-[210mm] mx-auto mt-6 flex justify-center gap-3 no-print">
            <button onclick="imprimirOrcamento_<?= $pedido_id ?>()" 
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold">
                🖨️ Imprimir/PDF
            </button>
            <?php if ($telefone_cliente): ?>
            <button onclick="enviarWhatsApp_<?= $pedido_id ?>()" 
                    class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-semibold">
                📱 Enviar WhatsApp
            </button>
            <?php endif; ?>
            <button onclick="enviarEmail_<?= $pedido_id ?>()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">
                ✉️ Enviar E-mail
            </button>
            <button onclick="copiarLink_<?= $pedido_id ?>()" 
                    class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-semibold">
                🔗 Copiar Link
            </button>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Função para imprimir apenas este orçamento
        function imprimirOrcamento_<?= $pedido_id ?>() {
            // Se estiver em iframe, chama a função do parent
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'print',
                    orcamento_id: '<?= $orcamento_id ?>'
                }, '*');
            } else {
                window.print();
            }
        }

        function enviarWhatsApp_<?= $pedido_id ?>() {
            const numero = '55<?= preg_replace("/\D/", "", $telefone_cliente) ?>';
            const mensagem = 'Olá! Segue o link do <?= $pedido['status'] === 'orcamento' ? 'orçamento' : 'pedido' ?> #<?= $pedido['numero'] ?>:\n' +
                           '<?= $_SERVER['HTTP_HOST'] ?>/orcamento.php?id=<?= $pedido_id ?>\n\n' +
                           'Valor total: <?= formatarMoeda($total) ?>\n' +
                           <?php if ($pedido['status'] === 'orcamento'): ?>
                           'Validade: <?= $data_validade->format('d/m/Y') ?>';
                           <?php else: ?>
                           'Status: Confirmado';
                           <?php endif; ?>
            window.open(`https://wa.me/${numero}?text=${encodeURIComponent(mensagem)}`, '_blank');
        }

        function enviarEmail_<?= $pedido_id ?>() {
            const assunto = '<?= $pedido['status'] === 'orcamento' ? 'Orçamento' : 'Pedido' ?> BR Bandeiras #<?= $pedido['numero'] ?>';
            const corpo = 'Prezado(a) <?= htmlspecialchars($pedido['cliente_nome']) ?>,\n\n' +
                        'Segue o link do seu <?= $pedido['status'] === 'orcamento' ? 'orçamento' : 'pedido' ?>:\n' +
                        '<?= $_SERVER['HTTP_HOST'] ?>/orcamento.php?id=<?= $pedido_id ?>\n\n' +
                        'Valor total: <?= formatarMoeda($total) ?>\n' +
                        <?php if ($pedido['status'] === 'orcamento'): ?>
                        'Validade: <?= $data_validade->format('d/m/Y') ?>\n\n' +
                        <?php else: ?>
                        'Status: Confirmado\n\n' +
                        <?php endif; ?>
                        'Atenciosamente,\n' +
                        '<?= htmlspecialchars($pedido['vendedor_nome']) ?>\n' +
                        'BR Bandeiras';
            window.location.href = `mailto:<?= htmlspecialchars($pedido['cliente_email']) ?>?subject=${encodeURIComponent(assunto)}&body=${encodeURIComponent(corpo)}`;
        }

        function copiarLink_<?= $pedido_id ?>() {
            const link = '<?= $_SERVER['HTTP_HOST'] ?>/orcamento.php?id=<?= $pedido_id ?>';
            navigator.clipboard.writeText(link).then(() => {
                alert('Link copiado para a área de transferência!');
            });
        }

        // Listener para mensagens do iframe (se incluído)
        if (window.parent !== window) {
            window.addEventListener('message', function(e) {
                if (e.data.action === 'print' && e.data.orcamento_id === '<?= $orcamento_id ?>') {
                    window.print();
                }
            });
        }
    </script>

</body>
</html>