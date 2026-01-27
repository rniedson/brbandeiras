<?php
/**
 * Relatórios de Auditoria
 * 
 * Gera relatórios resumidos de atividades por período.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

use App\Services\AuditoriaService;
use App\Core\ModelFactory;

requireLogin();
requireRole(['gestor', 'administrador']); // Gestores e administradores podem acessar relatórios de auditoria

// Obter service de auditoria usando ModelFactory
$service = ModelFactory::auditoriaService();

$periodo = $_GET['periodo'] ?? 'dia';
$usuarioId = isset($_GET['usuario_id']) && $_GET['usuario_id'] > 0 ? (int)$_GET['usuario_id'] : null;

$filtros = [];
if ($usuarioId) {
    $filtros['usuario_id'] = $usuarioId;
}

// Gerar relatório
$relatorio = $service->gerarRelatorio($periodo, $filtros);

// Buscar usuário se filtrado
$usuario = null;
if ($usuarioId) {
    try {
        $db = \Database::getInstance();
        $stmt = $db->query("SELECT nome, email FROM usuarios WHERE id = ?", [$usuarioId]);
        $usuario = $stmt->fetch();
    } catch (Exception $e) {
        $usuario = null;
    }
}

$titulo = 'Relatório de Auditoria';
include '../../views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Relatório de Auditoria</h1>
        <p class="text-gray-600">Resumo de atividades do sistema</p>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                <select name="periodo" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="dia" <?= $periodo === 'dia' ? 'selected' : '' ?>>Hoje</option>
                    <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Últimos 7 dias</option>
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este mês</option>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuário (opcional)</label>
                <input type="number" name="usuario_id" value="<?= $usuarioId ?? '' ?>" 
                       placeholder="ID do usuário" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                Gerar Relatório
            </button>
        </form>
    </div>

    <!-- Resumo Geral -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="text-sm text-blue-600 font-medium mb-1">Total de Atividades</div>
            <div class="text-3xl font-bold text-blue-800"><?= number_format($relatorio['total_atividades']) ?></div>
        </div>
        <div class="bg-green-50 rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="text-sm text-green-600 font-medium mb-1">Usuários Únicos</div>
            <div class="text-3xl font-bold text-green-800"><?= number_format($relatorio['total_usuarios_unicos']) ?></div>
        </div>
        <div class="bg-purple-50 rounded-lg shadow-md p-6 border-l-4 border-purple-500">
            <div class="text-sm text-purple-600 font-medium mb-1">Ações Únicas</div>
            <div class="text-3xl font-bold text-purple-800"><?= number_format($relatorio['total_acoes_unicas']) ?></div>
        </div>
        <div class="bg-orange-50 rounded-lg shadow-md p-6 border-l-4 border-orange-500">
            <div class="text-sm text-orange-600 font-medium mb-1">Período</div>
            <div class="text-lg font-bold text-orange-800 capitalize"><?= $periodo ?></div>
            <div class="text-xs text-orange-600 mt-1">
                <?= formatarData($relatorio['data_inicio'], 'd/m/Y H:i') ?> até 
                <?= formatarData($relatorio['data_fim'], 'd/m/Y H:i') ?>
            </div>
        </div>
    </div>

    <?php if ($usuario): ?>
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <h3 class="font-semibold text-gray-800">Filtrado por:</h3>
            <p class="text-gray-600"><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['email']) ?>)</p>
        </div>
    <?php endif; ?>

    <!-- Estatísticas por Data -->
    <?php if (!empty($relatorio['estatisticas_por_data'])): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Atividades por Data</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuários Únicos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações Únicas</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($relatorio['estatisticas_por_data'] as $stat): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= formatarData($stat['data'], 'd/m/Y') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($stat['total']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($stat['usuarios_unicos']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($stat['acoes_unicas']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ações Mais Frequentes -->
    <?php if (!empty($relatorio['acoes_frequentes'])): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Ações Mais Frequentes</h2>
            <div class="space-y-3">
                <?php 
                $maxTotal = max(array_column($relatorio['acoes_frequentes'], 'total'));
                foreach ($relatorio['acoes_frequentes'] as $acao): 
                    $percentual = $maxTotal > 0 ? ($acao['total'] / $maxTotal) * 100 : 0;
                ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($acao['acao']) ?></span>
                            <span class="text-sm text-gray-600">
                                <?= number_format($acao['total']) ?> ocorrências 
                                (<?= $acao['usuarios_unicos'] ?> usuários)
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $percentual ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Botão de Exportar -->
    <div class="mt-6 text-center">
        <a href="auditoria.php?<?= http_build_query($_GET) ?>" 
           class="inline-block bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
            Ver Detalhes Completos
        </a>
    </div>
</div>

<?php include '../../views/layouts/_footer.php'; ?>
