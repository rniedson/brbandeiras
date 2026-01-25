<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

// Buscar categorias com contagem de produtos
$sql = "
    SELECT c.*, 
           COUNT(DISTINCT p.id) as total_produtos,
           COUNT(DISTINCT CASE WHEN p.ativo = true THEN p.id END) as produtos_ativos
    FROM categorias_produtos c
    LEFT JOIN produtos_catalogo p ON c.id = p.categoria_id
    GROUP BY c.id
    ORDER BY c.nome
";

$categorias = $pdo->query($sql)->fetchAll();

$titulo = 'Categorias de Produtos';
$breadcrumb = [
    ['label' => 'Catálogo', 'url' => 'catalogo.php'],
    ['label' => 'Categorias']
];
include '../../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Cabeçalho -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Categorias de Produtos</h1>
            <p class="text-gray-600 mt-2">Gerencie as categorias do catálogo</p>
        </div>
        
        <button onclick="abrirModalNova()" 
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nova Categoria
        </button>
    </div>
    
    <!-- Lista de Categorias -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($categorias)): ?>
        <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhuma categoria cadastrada</h3>
            <p class="mt-1 text-sm text-gray-500">Comece criando uma nova categoria.</p>
        </div>
        <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Categoria
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Produtos
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Ações
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($categorias as $categoria): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($categoria['nome']) ?>
                            </div>
                            <?php if ($categoria['descricao']): ?>
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($categoria['descricao']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="text-sm">
                            <span class="font-medium"><?= $categoria['produtos_ativos'] ?></span>
                            <?php if ($categoria['total_produtos'] > $categoria['produtos_ativos']): ?>
                            <span class="text-gray-500">/ <?= $categoria['total_produtos'] ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($categoria['ativo']): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            Ativa
                        </span>
                        <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                            Inativa
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right text-sm font-medium">
                        <button onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($categoria)) ?>)"
                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                            Editar
                        </button>
                        
                        <?php if ($categoria['total_produtos'] == 0): ?>
                        <button onclick="excluirCategoria(<?= $categoria['id'] ?>, '<?= htmlspecialchars($categoria['nome']) ?>')"
                                class="text-red-600 hover:text-red-900">
                            Excluir
                        </button>
                        <?php else: ?>
                        <button onclick="alternarStatus(<?= $categoria['id'] ?>)"
                                class="text-gray-600 hover:text-gray-900">
                            <?= $categoria['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Informações -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Total de Categorias</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($categorias) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Categorias Ativas</div>
            <div class="text-2xl font-bold text-green-600">
                <?= count(array_filter($categorias, fn($c) => $c['ativo'])) ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Total de Produtos</div>
            <div class="text-2xl font-bold text-blue-600">
                <?= array_sum(array_column($categorias, 'total_produtos')) ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Nova/Editar Categoria -->
<div id="modalCategoria" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitulo">Nova Categoria</h3>
            
            <form id="formCategoria" onsubmit="salvarCategoria(event)">
                <input type="hidden" id="categoria_id" name="id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome da Categoria <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="categoria_nome"
                           name="nome" 
                           required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Descrição
                    </label>
                    <textarea id="categoria_descricao"
                              name="descricao" 
                              rows="3"
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
                </div>
                
                <div class="mb-4" id="statusField" style="display: none;">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="categoria_ativo"
                               name="ativo" 
                               value="1" 
                               checked 
                               class="mr-2">
                        <span class="text-sm text-gray-700">Categoria ativa</span>
                    </label>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" 
                            onclick="fecharModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalNova() {
    document.getElementById('modalTitulo').textContent = 'Nova Categoria';
    document.getElementById('formCategoria').reset();
    document.getElementById('categoria_id').value = '';
    document.getElementById('statusField').style.display = 'none';
    document.getElementById('modalCategoria').classList.remove('hidden');
}

function abrirModalEditar(categoria) {
    document.getElementById('modalTitulo').textContent = 'Editar Categoria';
    document.getElementById('categoria_id').value = categoria.id;
    document.getElementById('categoria_nome').value = categoria.nome;
    document.getElementById('categoria_descricao').value = categoria.descricao || '';
    document.getElementById('categoria_ativo').checked = categoria.ativo;
    document.getElementById('statusField').style.display = 'block';
    document.getElementById('modalCategoria').classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('modalCategoria').classList.add('hidden');
}

function salvarCategoria(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const id = formData.get('id');
    const url = id ? 'categoria_produto_atualizar.php' : 'categoria_produto_criar.php';
    
    // Mostrar loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const btnTexto = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Salvando...';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Verificar se a resposta é ok
        if (!response.ok) {
            throw new Error('Erro na requisição: ' + response.status);
        }
        return response.text(); // Primeiro pegar como texto
    })
    .then(text => {
        try {
            // Tentar fazer parse do JSON
            return JSON.parse(text);
        } catch (e) {
            console.error('Resposta recebida:', text);
            throw new Error('Resposta inválida do servidor');
        }
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        alert('Erro ao salvar categoria: ' + error.message);
    })
    .finally(() => {
        // Restaurar botão
        submitBtn.disabled = false;
        submitBtn.textContent = btnTexto;
    });
}

function alternarStatus(id) {
    if (confirm('Deseja alterar o status desta categoria?')) {
        fetch('categoria_produto_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        });
    }
}

function excluirCategoria(id, nome) {
    if (confirm(`Deseja realmente excluir a categoria "${nome}"?`)) {
        fetch('categoria_produto_excluir.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        });
    }
}

// Fechar modal ao clicar fora
document.getElementById('modalCategoria').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});
</script>

<?php include '../../views/layouts/_footer.php'; ?>