<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

// Filtros
$categoria_id = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';

// Query base
$sql = "SELECT p.*, c.nome as categoria_nome 
        FROM produtos_catalogo p
        LEFT JOIN categorias_produtos c ON p.categoria_id = c.id
        WHERE 1=1";
$params = [];

if ($categoria_id) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $categoria_id;
}

if ($busca) {
    $sql .= " AND (p.nome ILIKE ? OR p.codigo ILIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY p.nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Buscar categorias para filtro
$categorias = $pdo->query("SELECT * FROM categorias_produtos WHERE ativo = true ORDER BY nome")->fetchAll();

$titulo = 'Atualização de Preços - Catálogo';
$breadcrumb = [
    ['label' => 'Catálogo', 'url' => 'catalogo.php'],
    ['label' => 'Atualização de Preços']
];
include '../../views/layouts/_header.php';
?>

<div class="container mx-auto px-4">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Atualização Rápida de Preços</h1>
        <p class="text-gray-600 mt-2">Atualize preços de múltiplos produtos de forma rápida</p>
    </div>
    
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Buscar por nome ou código..."
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
            </div>
            
            <div class="w-48">
                <select name="categoria" onchange="this.form.submit()"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <option value="">Todas categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoria_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Filtrar
            </button>
        </form>
    </div>
    
    <!-- Ações em Massa -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-blue-800">Ações em Massa</h3>
                <p class="text-sm text-blue-700 mt-1">
                    Edite os campos desejados e clique em "Salvar Alterações" para atualizar todos de uma vez
                </p>
            </div>
            <div class="flex space-x-4">
                <button onclick="aplicarAumento()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                    Aplicar % de Aumento
                </button>
                <button onclick="exportarPrecos()" 
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                    Exportar Lista
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tabela de Produtos -->
    <form id="form-precos" method="POST" action="catalogo_precos_salvar.php">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <input type="checkbox" onchange="selecionarTodos(this)">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Custo</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Preço</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Preço Promo</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Margem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($produtos as $produto): ?>
                    <?php 
                        $custo = $produto['custo'] ?? 0;
                        $margem = $custo > 0 
                            ? round((($produto['preco'] ?? 0) - $custo) / $custo * 100, 1)
                            : 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <input type="checkbox" name="produtos[]" value="<?= $produto['id'] ?>" 
                                   class="produto-checkbox">
                        </td>
                        <td class="px-4 py-3 text-sm font-medium">
                            <?= htmlspecialchars($produto['codigo'] ?? '') ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?= htmlspecialchars($produto['nome'] ?? '') ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            <?= htmlspecialchars($produto['categoria_nome'] ?? '-') ?>
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" 
                                   name="custo[<?= $produto['id'] ?>]" 
                                   value="<?= $produto['custo'] ?? 0 ?>"
                                   step="0.01" min="0"
                                   onchange="calcularMargem(<?= $produto['id'] ?>)"
                                   class="w-24 px-2 py-1 border rounded text-center focus:outline-none focus:border-green-500">
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" 
                                   name="preco[<?= $produto['id'] ?>]" 
                                   value="<?= $produto['preco'] ?? 0 ?>"
                                   step="0.01" min="0" required
                                   onchange="calcularMargem(<?= $produto['id'] ?>)"
                                   class="w-24 px-2 py-1 border rounded text-center focus:outline-none focus:border-green-500">
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" 
                                   name="preco_promo[<?= $produto['id'] ?>]" 
                                   value="<?= $produto['preco_promocional'] ?? '' ?>"
                                   step="0.01" min="0"
                                   class="w-24 px-2 py-1 border rounded text-center focus:outline-none focus:border-green-500">
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span id="margem-<?= $produto['id'] ?>" 
                                  class="text-sm font-medium <?= $margem > 30 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $margem ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($produtos)): ?>
        <div class="text-center py-8 text-gray-500">
            Nenhum produto encontrado com os filtros aplicados.
        </div>
        <?php endif; ?>
        
        <!-- Botão Salvar -->
        <?php if (!empty($produtos)): ?>
        <div class="mt-6 flex justify-end">
            <button type="submit" 
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Salvar Alterações
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Modal de Aumento Percentual -->
<div id="modal-aumento" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Aplicar Aumento Percentual</h3>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Porcentagem de Aumento (%)
            </label>
            <input type="number" id="percentual-aumento" step="0.1" value="10"
                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Aplicar em:
            </label>
            <select id="campo-aumento" 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                <option value="preco">Preço Normal</option>
                <option value="preco_promo">Preço Promocional</option>
                <option value="custo">Custo</option>
            </select>
        </div>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" id="apenas-selecionados" checked class="mr-2">
                <span class="text-sm text-gray-700">Apenas produtos selecionados</span>
            </label>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button onclick="fecharModalAumento()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
            <button onclick="confirmarAumento()" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Aplicar
            </button>
        </div>
    </div>
</div>

<script>
function selecionarTodos(checkbox) {
    document.querySelectorAll('.produto-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function calcularMargem(produtoId) {
    const custo = parseFloat(document.querySelector(`input[name="custo[${produtoId}]"]`).value) || 0;
    const preco = parseFloat(document.querySelector(`input[name="preco[${produtoId}]"]`).value) || 0;
    
    let margem = 0;
    if (custo > 0) {
        margem = ((preco - custo) / custo) * 100;
    }
    
    const spanMargem = document.getElementById(`margem-${produtoId}`);
    spanMargem.textContent = margem.toFixed(1) + '%';
    spanMargem.className = `text-sm font-medium ${margem > 30 ? 'text-green-600' : 'text-red-600'}`;
}

function aplicarAumento() {
    document.getElementById('modal-aumento').classList.remove('hidden');
}

function fecharModalAumento() {
    document.getElementById('modal-aumento').classList.add('hidden');
}

function confirmarAumento() {
    const percentual = parseFloat(document.getElementById('percentual-aumento').value) || 0;
    const campo = document.getElementById('campo-aumento').value;
    const apenasSelecionados = document.getElementById('apenas-selecionados').checked;
    
    if (percentual === 0) {
        alert('Informe um percentual válido');
        return;
    }
    
    const fator = 1 + (percentual / 100);
    
    document.querySelectorAll('.produto-checkbox').forEach(checkbox => {
        if (!apenasSelecionados || checkbox.checked) {
            const produtoId = checkbox.value;
            const input = document.querySelector(`input[name="${campo}[${produtoId}]"]`);
            if (input) {
                const valorAtual = parseFloat(input.value) || 0;
                input.value = (valorAtual * fator).toFixed(2);
                
                if (campo !== 'custo') {
                    calcularMargem(produtoId);
                }
            }
        }
    });
    
    fecharModalAumento();
}

function exportarPrecos() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', '1');
    window.location.href = 'catalogo_precos_export.php?' + params.toString();
}

// Marcar formulário como modificado
document.getElementById('form-precos').addEventListener('change', function() {
    window.onbeforeunload = function() {
        return 'Existem alterações não salvas. Deseja realmente sair?';
    };
});

document.getElementById('form-precos').addEventListener('submit', function() {
    window.onbeforeunload = null;
});
</script>

<?php include '../../views/layouts/_footer.php'; ?>