<?php
/**
 * Componente Reutilizável: Timeline de Arte
 * 
 * Parâmetros esperados:
 * - $pedido_id (int) - ID do pedido
 * - $versoes (array) - Array com versões de arte
 * - $pode_interagir (bool) - Se pode aprovar/reprovar
 * - $perfil_usuario (string) - Perfil do usuário atual
 * - $exibir_filtros (bool) - Se deve exibir filtros (para gestor)
 * - $compacto (bool) - Modo compacto para tabs
 */

// Validar parâmetros obrigatórios
if (!isset($pedido_id) || !isset($versoes)) {
    echo '<p class="text-red-600">Erro: Parâmetros obrigatórios não fornecidos</p>';
    return;
}

// Valores padrão
$pode_interagir = $pode_interagir ?? false;
$perfil_usuario = $perfil_usuario ?? $_SESSION['user_perfil'];
$exibir_filtros = $exibir_filtros ?? false;
$compacto = $compacto ?? false;

// Função local para processar observações HTML
if (!function_exists('processarObservacoesArte')) {
    function processarObservacoesArte($html) {
        $texto = strip_tags($html, '<br><ul><li><strong><b>');
        $texto = str_replace(['<br>', '<br/>', '<br />'], "\n", $texto);
        $texto = preg_replace('/<li[^>]*>/', '• ', $texto);
        $texto = str_replace(['</li>', '<ul>', '</ul>', '<strong>', '</strong>', '<b>', '</b>'], '', $texto);
        return nl2br(trim($texto));
    }
}
?>

<div class="arte-timeline-component" data-pedido="<?= $pedido_id ?>">
    
    <?php if ($exibir_filtros): ?>
    <!-- Filtros (apenas para gestor) -->
    <div class="mb-4 p-3 bg-gray-50 rounded-lg flex flex-wrap gap-3">
        <input type="date" 
               id="filterDate"
               class="px-3 py-2 border rounded-lg text-sm"
               placeholder="Filtrar por data">
        
        <select id="filterUser" class="px-3 py-2 border rounded-lg text-sm">
            <option value="">Todos os responsáveis</option>
            <?php 
            $usuarios_unicos = array_unique(array_column($versoes, 'usuario_nome'));
            foreach ($usuarios_unicos as $usuario): 
            ?>
            <option value="<?= htmlspecialchars($usuario) ?>"><?= htmlspecialchars($usuario) ?></option>
            <?php endforeach; ?>
        </select>
        
        <button onclick="limparFiltrosArte()" 
                class="px-3 py-2 bg-gray-200 rounded-lg text-sm hover:bg-gray-300">
            Limpar Filtros
        </button>
    </div>
    <?php endif; ?>
    
    <?php if (empty($versoes)): ?>
    <!-- Estado vazio -->
    <div class="text-center py-12">
        <?php if (!$compacto): ?>
        <div class="inline-block mb-6" style="animation: float 3s ease-in-out infinite;">
            <svg width="120" height="120" viewBox="0 0 120 120">
                <circle cx="60" cy="50" r="25" fill="#FCD34D"/>
                <ellipse cx="60" cy="55" rx="30" ry="20" fill="#1F2937" opacity="0.8"/>
                <circle cx="50" cy="45" r="8" fill="white"/>
                <circle cx="70" cy="45" r="8" fill="white"/>
                <circle cx="50" cy="45" r="5" fill="black"/>
                <circle cx="70" cy="45" r="5" fill="black"/>
                <path d="M 30 50 Q 20 40 25 30" stroke="#1F2937" stroke-width="2" fill="none"/>
                <path d="M 90 50 Q 100 40 95 30" stroke="#1F2937" stroke-width="2" fill="none"/>
                <ellipse cx="35" cy="60" rx="15" ry="8" fill="#60A5FA" opacity="0.5"/>
                <ellipse cx="85" cy="60" rx="15" ry="8" fill="#60A5FA" opacity="0.5"/>
            </svg>
        </div>
        <?php endif; ?>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Nenhuma arte enviada ainda</h3>
        <p class="text-gray-500">Aguardando primeira versão do arte-finalista</p>
    </div>
    
    <?php else: ?>
    <!-- Lista de versões -->
    <div class="space-y-4" id="listaVersoes">
        <?php foreach ($versoes as $versao): 
            $isAprovada = $versao['aprovada'] ?? false;
            $isReprovada = $versao['reprovada'] ?? false;
            $status = $isAprovada ? 'APROVADA' : ($isReprovada ? 'AJUSTES' : 'AGUARDANDO');
            $statusColor = $isAprovada ? 'bg-green-500' : ($isReprovada ? 'bg-red-500' : 'bg-yellow-500');
            $borderColor = $isAprovada ? 'border-green-200 bg-green-50' : ($isReprovada ? 'border-red-200 bg-red-50' : 'border-gray-200');
            
            $ext = strtolower(pathinfo($versao['arquivo_nome'], PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $caminho = '../uploads/arte_versoes/' . basename($versao['arquivo_caminho']);
        ?>
        <div class="versao-card border rounded-lg p-4 <?= $borderColor ?> transition-all duration-300 hover:shadow-md"
             data-usuario="<?= htmlspecialchars($versao['usuario_nome'] ?? '') ?>"
             data-data="<?= date('Y-m-d', strtotime($versao['created_at'])) ?>">
            
            <!-- Header da versão -->
            <div class="flex justify-between items-start mb-3">
                <div class="flex-1">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2">
                        <span class="text-lg">Versão <?= $versao['versao'] ?></span>
                        <span class="px-2 py-1 <?= $statusColor ?> text-white text-xs rounded-full">
                            <?= $status ?>
                        </span>
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="far fa-clock mr-1"></i>
                        <?= formatarDataHora($versao['created_at']) ?>
                        <?php if (isset($versao['usuario_nome'])): ?>
                        • Por <?= htmlspecialchars($versao['usuario_nome']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="far fa-file mr-1"></i>
                        <?= htmlspecialchars($versao['arquivo_nome']) ?>
                    </p>
                </div>
                
                <!-- Botões de ação -->
                <div class="flex gap-2 flex-shrink-0">
                    <?php if ($isImage && !$compacto): ?>
                    <button onclick="visualizarImagemArte('<?= htmlspecialchars($caminho) ?>', 'Versão <?= $versao['versao'] ?>')" 
                            class="px-3 py-1.5 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 transition">
                        <i class="fas fa-eye"></i> Ver
                    </button>
                    <?php endif; ?>
                    <a href="../../utils/download.php?tipo=arte&id=<?= $versao['id'] ?>" 
                       class="px-3 py-1.5 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 transition inline-flex items-center">
                        <i class="fas fa-download mr-1"></i> Baixar
                    </a>
                </div>
            </div>
            
            <!-- Preview da imagem (se não for modo compacto) -->
            <?php if ($isImage && !$compacto): ?>
            <div class="mb-3">
                <img src="<?= htmlspecialchars($caminho) ?>" 
                     alt="Versão <?= $versao['versao'] ?>"
                     onclick="visualizarImagemArte('<?= htmlspecialchars($caminho) ?>', 'Versão <?= $versao['versao'] ?>')"
                     class="rounded-lg border cursor-pointer hover:opacity-90 transition max-h-48 mx-auto"
                     onerror="this.style.display='none'">
            </div>
            <?php endif; ?>
            
            <!-- Comentários e conversação -->
            <div class="space-y-2 max-h-60 overflow-y-auto custom-scrollbar">
                <?php if (!empty($versao['comentario_arte'])): ?>
                <div class="bg-white p-3 rounded-lg border-l-4 border-purple-400">
                    <p class="text-xs font-semibold text-purple-600 mb-1">
                        <i class="fas fa-palette mr-1"></i>
                        Arte-finalista
                    </p>
                    <div class="text-sm text-gray-700">
                        <?= processarObservacoesArte($versao['comentario_arte']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($versao['comentario_cliente'])): ?>
                <div class="bg-white p-3 rounded-lg border-l-4 <?= $isAprovada ? 'border-green-400' : 'border-orange-400' ?>">
                    <p class="text-xs font-semibold <?= $isAprovada ? 'text-green-600' : 'text-orange-600' ?> mb-1">
                        <i class="fas fa-user mr-1"></i>
                        Vendedor/Cliente
                    </p>
                    <div class="text-sm text-gray-700">
                        <?= processarObservacoesArte($versao['comentario_cliente']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Área de interação (se permitido) -->
            <?php if ($pode_interagir): ?>
                <?php if ($isAprovada && in_array($perfil_usuario, ['vendedor', 'gestor'])): ?>
                <!-- Versão aprovada - opção de reverter -->
                <div class="mt-4 pt-4 border-t bg-green-100 -m-4 mt-4 p-4 rounded-b-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-green-700 font-semibold">
                            <i class="fas fa-check-circle mr-2"></i>
                            Esta versão foi aprovada!
                        </p>
                        <button onclick="reverterAprovacaoArte(<?= $versao['id'] ?>)" 
                                class="px-3 py-1.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium transition">
                            <i class="fas fa-undo mr-1"></i> Reverter para Ajustes
                        </button>
                    </div>
                </div>
                
                <?php elseif (!$isAprovada && in_array($perfil_usuario, ['vendedor', 'gestor'])): ?>
                <!-- Versão não aprovada - opções normais -->
                <div class="mt-4 pt-4 border-t flex gap-2">
                    <input type="text" 
                           id="msg-arte-<?= $versao['id'] ?>"
                           placeholder="Digite feedback ou comentário..." 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                    
                    <button onclick="aprovarVersaoArte(<?= $versao['id'] ?>)" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium transition">
                        <i class="fas fa-check mr-1"></i> Aprovar
                    </button>
                    
                    <button onclick="solicitarAjusteArte(<?= $versao['id'] ?>)" 
                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium transition">
                        <i class="fas fa-edit mr-1"></i> Ajustes
                    </button>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Imagem (se não existir na página) -->
<?php if (!$compacto): ?>
<div id="modalImagemArte" class="hidden fixed inset-0 bg-black bg-opacity-95 z-50 flex items-center justify-center p-4">
    <div class="relative max-w-7xl max-h-[90vh] w-full h-full flex items-center justify-center">
        <div class="absolute top-4 right-4 flex gap-2 z-10">
            <button onclick="rotacionarImagemArte()" 
                    class="p-2 bg-white/10 backdrop-blur text-white rounded-lg hover:bg-white/20 transition">
                <i class="fas fa-redo"></i>
            </button>
            <button onclick="fecharModalArte()" 
                    class="p-2 bg-white/10 backdrop-blur text-white rounded-lg hover:bg-white/20 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="absolute top-4 left-4 text-white">
            <h3 id="tituloImagemArte" class="text-lg font-semibold"></h3>
        </div>
        
        <div class="relative">
            <img id="imagemModalArte" 
                 src="" 
                 alt="Visualização ampliada" 
                 class="max-w-full max-h-[85vh] object-contain transition-transform duration-300">
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Variáveis do componente
let rotacaoAtualArte = 0;

// Filtros (se habilitado)
<?php if ($exibir_filtros): ?>
function limparFiltrosArte() {
    document.getElementById('filterDate').value = '';
    document.getElementById('filterUser').value = '';
    aplicarFiltrosArte();
}

function aplicarFiltrosArte() {
    const filterDate = document.getElementById('filterDate').value;
    const filterUser = document.getElementById('filterUser').value;
    
    document.querySelectorAll('.versao-card').forEach(card => {
        let mostrar = true;
        
        if (filterDate && card.dataset.data !== filterDate) {
            mostrar = false;
        }
        
        if (filterUser && card.dataset.usuario !== filterUser) {
            mostrar = false;
        }
        
        card.style.display = mostrar ? 'block' : 'none';
    });
}

// Event listeners para filtros
document.getElementById('filterDate')?.addEventListener('change', aplicarFiltrosArte);
document.getElementById('filterUser')?.addEventListener('change', aplicarFiltrosArte);
<?php endif; ?>

// Funções de visualização
function visualizarImagemArte(src, titulo) {
    const modal = document.getElementById('modalImagemArte');
    if (!modal) return;
    
    document.getElementById('imagemModalArte').src = src;
    document.getElementById('tituloImagemArte').textContent = titulo || 'Visualização';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    rotacaoAtualArte = 0;
    document.getElementById('imagemModalArte').style.transform = 'rotate(0deg)';
}

function fecharModalArte() {
    const modal = document.getElementById('modalImagemArte');
    if (!modal) return;
    
    modal.classList.add('hidden');
    document.getElementById('imagemModalArte').src = '';
    document.body.style.overflow = 'auto';
}

function rotacionarImagemArte() {
    rotacaoAtualArte += 90;
    if (rotacaoAtualArte >= 360) rotacaoAtualArte = 0;
    document.getElementById('imagemModalArte').style.transform = `rotate(${rotacaoAtualArte}deg)`;
}

// Funções de interação
function aprovarVersaoArte(versaoId) {
    const input = document.getElementById('msg-arte-' + versaoId);
    const comentario = input ? input.value.trim() : 'Arte aprovada!';
    
    if (confirm('Aprovar esta versão da arte?\n\nComentário: ' + comentario)) {
        // Fazer requisição AJAX
        fetch('pedido_detalhes_arte_finalista.php?id=<?= $pedido_id ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=aprovar_versao&versao_id=' + versaoId + '&comentario=' + encodeURIComponent(comentario)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Arte aprovada com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro: ' + (data.message || 'Erro ao aprovar'));
            }
        })
        .catch(err => {
            alert('❌ Erro de conexão');
            console.error(err);
        });
    }
}

function solicitarAjusteArte(versaoId) {
    const input = document.getElementById('msg-arte-' + versaoId);
    let comentario = input ? input.value.trim() : '';
    
    if (!comentario) {
        comentario = prompt('⚠️ Descreva os ajustes necessários:');
        if (!comentario) return;
    }
    
    fetch('pedido_detalhes_arte_finalista.php?id=<?= $pedido_id ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=solicitar_ajuste&versao_id=' + versaoId + '&comentario=' + encodeURIComponent(comentario)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('📝 Solicitação de ajuste enviada!');
            location.reload();
        } else {
            alert('❌ Erro: ' + (data.message || 'Erro ao solicitar ajuste'));
        }
    })
    .catch(err => {
        alert('❌ Erro de conexão');
        console.error(err);
    });
}

function reverterAprovacaoArte(versaoId) {
    const motivo = prompt('🔄 Por que deseja reverter a aprovação?\n\nDescreva o motivo:');
    
    if (!motivo) {
        if (motivo === null) return;
        alert('Por favor, informe o motivo da reversão');
        return;
    }
    
    if (confirm('Confirma reverter a aprovação desta arte?\n\nA arte voltará para ajustes.')) {
        fetch('pedido_detalhes_arte_finalista.php?id=<?= $pedido_id ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=reverter_aprovacao&versao_id=' + versaoId + '&motivo=' + encodeURIComponent(motivo)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('🔄 Aprovação revertida! Arte voltou para ajustes.');
                location.reload();
            } else {
                alert('❌ Erro: ' + (data.message || 'Erro ao reverter aprovação'));
            }
        })
        .catch(err => {
            alert('❌ Erro de conexão');
            console.error(err);
        });
    }
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('modalImagemArte');
    
    if (modal && !modal.classList.contains('hidden')) {
        switch(e.key) {
            case 'Escape':
                fecharModalArte();
                break;
            case 'r':
            case 'R':
                rotacionarImagemArte();
                break;
        }
    }
});
</script>

<style>
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #9333ea;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #7c3aed;
}
</style>