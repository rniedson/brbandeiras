<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor', 'vendedor']);

// Buscar grupos com contagem de clientes
$sql = "
    SELECT g.*, 
           COUNT(DISTINCT c.id) as total_clientes,
           COUNT(DISTINCT CASE WHEN c.ativo = true THEN c.id END) as clientes_ativos
    FROM grupos_clientes g
    LEFT JOIN clientes c ON c.grupo_id = g.id
    GROUP BY g.id
    ORDER BY g.nome
";

try {
    $grupos = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    // Se a tabela não existir, criar uma lista vazia
    $grupos = [];
}

$titulo = 'Grupos de Clientes';
$breadcrumb = [
    ['label' => 'Clientes', 'url' => 'clientes/clientes.php'],
    ['label' => 'Grupos']
];
include '../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Cabeçalho -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Grupos de Clientes</h1>
            <p class="text-gray-600 mt-2">Organize seus clientes em grupos</p>
        </div>
        
        <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
        <button onclick="abrirModalNova()" 
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Novo Grupo
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Lista de Grupos -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($grupos)): ?>
        <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum grupo cadastrado</h3>
            <p class="mt-1 text-sm text-gray-500">Comece criando um novo grupo.</p>
        </div>
        <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Grupo
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Clientes
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Ações
                    </th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($grupos as $grupo): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($grupo['nome']) ?>
                            </div>
                            <?php if (!empty($grupo['descricao'])): ?>
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($grupo['descricao']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="text-sm">
                            <span class="font-medium"><?= $grupo['clientes_ativos'] ?? 0 ?></span>
                            <?php if (($grupo['total_clientes'] ?? 0) > ($grupo['clientes_ativos'] ?? 0)): ?>
                            <span class="text-gray-500">/ <?= $grupo['total_clientes'] ?? 0 ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($grupo['ativo'] ?? true): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            Ativo
                        </span>
                        <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                            Inativo
                        </span>
                        <?php endif; ?>
                    </td>
                    <?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
                    <td class="px-6 py-4 text-right text-sm font-medium">
                        <button onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($grupo)) ?>)"
                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                            Editar
                        </button>
                        
                        <?php if (($grupo['total_clientes'] ?? 0) == 0): ?>
                        <button onclick="excluirGrupo(<?= $grupo['id'] ?>, '<?= htmlspecialchars($grupo['nome']) ?>')"
                                class="text-red-600 hover:text-red-900">
                            Excluir
                        </button>
                        <?php else: ?>
                        <button onclick="alternarStatus(<?= $grupo['id'] ?>)"
                                class="text-gray-600 hover:text-gray-900">
                            <?= ($grupo['ativo'] ?? true) ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Informações -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Total de Grupos</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($grupos) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Grupos Ativos</div>
            <div class="text-2xl font-bold text-green-600">
                <?= count(array_filter($grupos, fn($g) => $g['ativo'] ?? true)) ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Total de Clientes</div>
            <div class="text-2xl font-bold text-blue-600">
                <?= array_sum(array_column($grupos, 'total_clientes')) ?>
            </div>
        </div>
    </div>
</div>

<?php if ($_SESSION['user_perfil'] === 'gestor'): ?>
<!-- Modal de Novo/Editar Grupo -->
<div id="modalGrupo" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitulo">Novo Grupo</h3>
            
            <form id="formGrupo" onsubmit="salvarGrupo(event)">
                <input type="hidden" id="grupo_id" name="id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome do Grupo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="grupo_nome"
                           name="nome" 
                           required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Descrição
                    </label>
                    <textarea id="grupo_descricao"
                              name="descricao" 
                              rows="3"
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
                </div>
                
                <div class="mb-4" id="statusField" style="display: none;">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="grupo_ativo"
                               name="ativo" 
                               value="1" 
                               checked 
                               class="mr-2">
                        <span class="text-sm text-gray-700">Grupo ativo</span>
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
    document.getElementById('modalTitulo').textContent = 'Novo Grupo';
    document.getElementById('formGrupo').reset();
    document.getElementById('grupo_id').value = '';
    document.getElementById('statusField').style.display = 'none';
    document.getElementById('modalGrupo').classList.remove('hidden');
}

function abrirModalEditar(grupo) {
    document.getElementById('modalTitulo').textContent = 'Editar Grupo';
    document.getElementById('grupo_id').value = grupo.id;
    document.getElementById('grupo_nome').value = grupo.nome;
    document.getElementById('grupo_descricao').value = grupo.descricao || '';
    document.getElementById('grupo_ativo').checked = grupo.ativo ?? true;
    document.getElementById('statusField').style.display = 'block';
    document.getElementById('modalGrupo').classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('modalGrupo').classList.add('hidden');
}

function salvarGrupo(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const id = formData.get('id');
    const url = id ? 'cliente_grupo_atualizar.php' : 'cliente_grupo_criar.php';
    
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
        if (!response.ok) {
            throw new Error('Erro na requisição: ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        try {
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
        alert('Erro ao salvar grupo: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = btnTexto;
    });
}

function alternarStatus(id) {
    if (confirm('Deseja alterar o status deste grupo?')) {
        fetch('cliente_grupo_status.php', {
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

function excluirGrupo(id, nome) {
    if (confirm(`Deseja realmente excluir o grupo "${nome}"?`)) {
        fetch('cliente_grupo_excluir.php', {
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
document.getElementById('modalGrupo').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});
</script>
<?php endif; ?>

<?php include '../views/layouts/_footer.php'; ?>
