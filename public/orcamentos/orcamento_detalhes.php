<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireLogin();

// Validar ID do pedido
$pedido_id = validarPedidoId($_GET['id'] ?? null);

if (!$pedido_id) {
    $_SESSION['erro'] = 'ID do orçamento inválido';
    header('Location: orcamentos.php');
    exit;
}

// Buscar orçamento
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               c.nome as cliente_nome, 
               c.telefone as cliente_telefone,
               c.email as cliente_email,
               c.cpf_cnpj as cliente_cpf_cnpj,
               c.endereco as cliente_endereco,
               u.nome as vendedor_nome,
               u.email as vendedor_email
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE p.id = ? AND p.status = 'orcamento'
    ");
    $stmt->execute([$pedido_id]);
    $orcamento = $stmt->fetch();
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar orçamento: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao carregar dados do orçamento';
    header('Location: orcamentos.php');
    exit;
} catch (Exception $e) {
    $_SESSION['erro'] = $e->getMessage();
    header('Location: orcamentos.php');
    exit;
}

// Verificar permissão
if ($_SESSION['user_perfil'] === 'vendedor' && $orcamento['vendedor_id'] != $_SESSION['user_id']) {
    $_SESSION['erro'] = 'Você não tem permissão para visualizar este orçamento';
    header('Location: orcamentos.php');
    exit;
}

// Buscar itens
$stmt = $pdo->prepare("
    SELECT pi.*, pc.id as produto_codigo, pc.nome as produto_nome
    FROM pedido_itens pi
    LEFT JOIN produtos_catalogo pc ON pi.produto_id = pc.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$itens = $stmt->fetchAll();

// Buscar arquivos
$stmt = $pdo->prepare("SELECT * FROM pedido_arquivos WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
$arquivos = $stmt->fetchAll();

// Buscar histórico
$stmt = $pdo->prepare("
    SELECT ps.*, u.nome as usuario_nome
    FROM producao_status ps
    LEFT JOIN usuarios u ON ps.usuario_id = u.id
    WHERE ps.pedido_id = ?
    ORDER BY ps.created_at DESC
");
$stmt->execute([$pedido_id]);
$historico = $stmt->fetchAll();

$titulo = 'Orçamento #' . $orcamento['numero'];
$breadcrumb = [
    ['label' => 'Orçamentos', 'url' => 'orcamentos.php'],
    ['label' => 'Detalhes do Orçamento']
];
include '../../views/layouts/_header.php';
?>

<!-- Estilos CSS para renderização HTML e preview de imagens -->
<style>
.prose ul {
    list-style-type: disc;
    margin-left: 1.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.prose ol {
    list-style-type: decimal;
    margin-left: 1.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.prose li {
    margin-bottom: 0.25rem;
}

.prose b,
.prose strong {
    font-weight: 600;
}

.image-preview {
    cursor: pointer;
    transition: transform 0.2s;
}

.image-preview:hover {
    transform: scale(1.05);
}

@media print {
    nav, footer, .no-print { display: none !important; }
    .shadow { box-shadow: none !important; }
    button { display: none !important; }
}
</style>

<div class="max-w-7xl mx-auto">
    <!-- Cabeçalho -->
    <div class="mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    Orçamento #<?= htmlspecialchars($orcamento['numero']) ?>
                </h1>
                <p class="text-gray-600 mt-2">
                    Criado em <?= formatarDataHora($orcamento['created_at']) ?>
                </p>
            </div>
            
            <div class="flex gap-2">
                <?php if ($_SESSION['user_perfil'] === 'gestor' || $orcamento['vendedor_id'] == $_SESSION['user_id']): ?>
                <a href="pedido_editar.php?id=<?= $pedido_id ?>" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Editar
                </a>
                
                <button onclick="aprovarOrcamento()" 
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Aprovar Orçamento
                </button>
                
                <button onclick="reprovarOrcamento()" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Reprovar
                </button>
                <?php endif; ?>
                
                <a href="orcamento_pdf.php?id=<?= $pedido_id ?>" target="_blank"
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    Gerar PDF
                </a>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Coluna Principal -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Dados do Cliente -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Dados do Cliente</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Nome</p>
                        <p class="font-medium"><?= htmlspecialchars($orcamento['cliente_nome']) ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Telefone</p>
                        <p class="font-medium"><?= htmlspecialchars($orcamento['cliente_telefone']) ?></p>
                    </div>
                    
                    <?php if ($orcamento['cliente_email']): ?>
                    <div>
                        <p class="text-sm text-gray-500">E-mail</p>
                        <p class="font-medium"><?= htmlspecialchars($orcamento['cliente_email']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($orcamento['cliente_cpf_cnpj']): ?>
                    <div>
                        <p class="text-sm text-gray-500">CPF/CNPJ</p>
                        <p class="font-medium"><?= formatarCpfCnpj($orcamento['cliente_cpf_cnpj']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Itens do Orçamento -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-lg font-semibold">Itens do Orçamento</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Item
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Qtd
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Valor Unit.
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($itens as $item): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($item['descricao']) ?>
                                    </div>
                                    <?php if ($item['produto_codigo']): ?>
                                    <div class="text-xs text-gray-500">
                                        Cód: <?= htmlspecialchars($item['produto_codigo']) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center text-sm">
                                    <?= number_format($item['quantidade'], 0, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <?= formatarMoeda($item['valor_unitario']) ?>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <?= formatarMoeda($item['valor_total']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-6 py-3 text-right font-medium">Subtotal:</td>
                                <td class="px-6 py-3 text-right font-medium">
                                    <?= formatarMoeda($orcamento['valor_total']) ?>
                                </td>
                            </tr>
                            <?php if ($orcamento['desconto'] > 0): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-3 text-right font-medium">Desconto:</td>
                                <td class="px-6 py-3 text-right font-medium text-red-600">
                                    - <?= formatarMoeda($orcamento['desconto']) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="bg-gray-100">
                                <td colspan="3" class="px-6 py-3 text-right font-bold text-lg">Total:</td>
                                <td class="px-6 py-3 text-right font-bold text-lg text-green-600">
                                    <?= formatarMoeda($orcamento['valor_final']) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Observações (corrigido para renderizar HTML) -->
            <?php if ($orcamento['observacoes']): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Observações</h2>
                <div class="text-gray-700 prose max-w-none">
                    <?= $orcamento['observacoes'] ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Arquivos com preview de imagens -->
            <?php if (!empty($arquivos)): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Arquivos Anexados</h2>
                <div class="space-y-3">
                    <?php foreach ($arquivos as $arquivo): ?>
                    <?php 
                    $extensao = strtolower(pathinfo($arquivo['nome_arquivo'], PATHINFO_EXTENSION));
                    $is_image = in_array($extensao, ['jpg', 'jpeg', 'png', 'gif']);
                    ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                        <div class="flex items-center">
                            <?php if ($is_image): ?>
                                <!-- Preview de imagem -->
                                <div class="mr-3">
                                    <img src="../<?= htmlspecialchars($arquivo['caminho']) ?>" 
                                         alt="<?= htmlspecialchars($arquivo['nome_arquivo']) ?>"
                                         class="w-16 h-16 object-cover rounded image-preview"
                                         onclick="abrirImagemModal('<?= htmlspecialchars($arquivo['caminho']) ?>', '<?= htmlspecialchars($arquivo['nome_arquivo']) ?>')">
                                </div>
                            <?php else: ?>
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm font-medium"><?= htmlspecialchars($arquivo['nome_arquivo']) ?></p>
                                <?php if ($arquivo['tamanho']): ?>
                                <p class="text-xs text-gray-500">
                                    <?= number_format($arquivo['tamanho'] / 1024, 2) ?> KB
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="download.php?tipo=pedido&id=<?= $arquivo['id'] ?>" 
                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Baixar
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Coluna Lateral -->
        <div class="space-y-6">
            <!-- Informações -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Informações</h2>
                
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            Aguardando Aprovação
                        </span>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Vendedor</p>
                        <p class="font-medium"><?= htmlspecialchars($orcamento['vendedor_nome']) ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Prazo de Entrega</p>
                        <p class="font-medium"><?= formatarData($orcamento['prazo_entrega']) ?></p>
                    </div>
                    
                    <?php if ($orcamento['urgente']): ?>
                    <div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                            </svg>
                            Pedido Urgente
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Ações -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Ações</h2>
                
                <div class="space-y-2">
                    <?php if ($orcamento['cliente_email']): ?>
                    <button onclick="enviarPorEmail()" 
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Enviar por E-mail
                    </button>
                    <?php endif; ?>
                    
                    <button onclick="enviarWhatsApp()" 
                            class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Enviar por WhatsApp
                    </button>
                    
                    <button onclick="window.print()" 
                            class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        Imprimir
                    </button>
                </div>
            </div>
            
            <!-- Histórico -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Histórico</h2>
                
                <div class="space-y-3">
                    <?php foreach ($historico as $evento): ?>
                    <div class="text-sm">
                        <p class="font-medium"><?= htmlspecialchars($evento['status']) ?></p>
                        <p class="text-gray-600"><?= htmlspecialchars($evento['observacoes']) ?></p>
                        <p class="text-xs text-gray-500">
                            <?= htmlspecialchars($evento['usuario_nome']) ?> - 
                            <?= formatarDataHora($evento['created_at']) ?>
                        </p>
                    </div>
                    <div class="border-t"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Aprovação -->
<div id="modalAprovacao" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Aprovar Orçamento</h3>
            
            <form id="formAprovacao" action="orcamento_aprovar.php" method="POST">
                <input type="hidden" name="id" value="<?= $pedido_id ?>">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Observações da Aprovação
                    </label>
                    <textarea name="observacoes" rows="3"
                              placeholder="Condições especiais, observações..."
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="enviar_email" value="1" checked class="mr-2">
                        <span class="text-sm text-gray-700">Notificar cliente por e-mail</span>
                    </label>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" 
                            onclick="fecharModalAprovacao()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Aprovar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Imagem -->
<div id="modalImagem" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center" onclick="fecharImagemModal()">
    <div class="max-w-4xl max-h-full p-4">
        <img id="imagemAmpliada" src="" alt="" class="max-w-full max-h-full rounded-lg">
        <p id="nomeImagem" class="text-white text-center mt-2"></p>
    </div>
</div>

<script>
function aprovarOrcamento() {
    document.getElementById('modalAprovacao').classList.remove('hidden');
}

function fecharModalAprovacao() {
    document.getElementById('modalAprovacao').classList.add('hidden');
}

function reprovarOrcamento() {
    if (confirm('Deseja realmente reprovar este orçamento?')) {
        const motivo = prompt('Motivo da reprovação:');
        if (motivo) {
            window.location.href = 'orcamento_reprovar.php?id=<?= $pedido_id ?>&motivo=' + encodeURIComponent(motivo);
        }
    }
}

function enviarPorEmail() {
    if (confirm('Enviar orçamento por e-mail para <?= htmlspecialchars($orcamento['cliente_email']) ?>?')) {
        fetch('orcamento_enviar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=<?= $pedido_id ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Orçamento enviado com sucesso!');
            } else {
                alert('Erro ao enviar: ' + data.message);
            }
        });
    }
}

function enviarWhatsApp() {
    const telefone = '<?= preg_replace('/\D/', '', $orcamento['cliente_telefone']) ?>';
    const mensagem = encodeURIComponent(
        `Olá <?= $orcamento['cliente_nome'] ?>!\n\n` +
        `Segue o link do seu orçamento #<?= $orcamento['numero'] ?>:\n` +
        `<?= BASE_URL ?>orcamento_visualizar.php?token=<?= md5($pedido_id . $orcamento['created_at']) ?>\n\n` +
        `Valor total: <?= formatarMoeda($orcamento['valor_final']) ?>\n` +
        `Validade: <?= formatarData($orcamento['prazo_entrega']) ?>\n\n` +
        `Qualquer dúvida, estamos à disposição!`
    );
    
    window.open(`https://wa.me/55${telefone}?text=${mensagem}`, '_blank');
}

// Funções para modal de imagem
function abrirImagemModal(caminho, nome) {
    document.getElementById('imagemAmpliada').src = '../' + caminho;
    document.getElementById('nomeImagem').textContent = nome;
    document.getElementById('modalImagem').classList.remove('hidden');
}

function fecharImagemModal() {
    document.getElementById('modalImagem').classList.add('hidden');
}

// Fechar modais ao clicar fora
document.getElementById('modalAprovacao').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalAprovacao();
    }
});

// Atalho ESC para fechar modais
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalAprovacao();
        fecharImagemModal();
    }
});
</script>

<?php include '../../views/layouts/_footer.php'; ?>