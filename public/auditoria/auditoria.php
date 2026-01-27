<?php
/**
 * Sistema de Auditoria - Visualização de atividades do sistema
 * 
 * Permite visualizar e filtrar todas as atividades registradas no sistema.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

use App\Services\AuditoriaService;
use App\Repositories\AuditoriaRepository;
use App\Core\ModelFactory;
use App\Core\Paginator;

requireLogin();
requireRole(['gestor', 'administrador']); // Gestores e administradores podem acessar o sistema de auditoria

// Obter service de auditoria usando ModelFactory
$service = ModelFactory::auditoriaService();

// Processar filtros
$filtros = [];
$periodo = $_GET['periodo'] ?? 'dia';

if (isset($_GET['usuario_id']) && $_GET['usuario_id'] > 0) {
    $filtros['usuario_id'] = (int)$_GET['usuario_id'];
}

if (isset($_GET['acao']) && !empty($_GET['acao'])) {
    $filtros['acao'] = $_GET['acao'];
}

if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $filtros['data_inicio'] = $_GET['data_inicio'];
}

if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $filtros['data_fim'] = $_GET['data_fim'];
}

if (isset($_GET['busca']) && !empty($_GET['busca'])) {
    $filtros['busca'] = $_GET['busca'];
}

if (isset($_GET['ip']) && !empty($_GET['ip'])) {
    $filtros['ip'] = $_GET['ip'];
}

// Paginação
$params = Paginator::parseParams($_GET);
$page = $params['page'];
$perPage = 50;

// Buscar registros
$resultado = $service->buscar($filtros, $page, $perPage);
$registros = $resultado['dados'];

// Buscar estatísticas do período
$estatisticasPeriodo = $service->buscarPorPeriodo($periodo, $filtros);

// Buscar ações mais frequentes
$acoesFrequentes = $service->buscarAcoesFrequentes(10, $filtros);

// Buscar lista de usuários para filtro
try {
    $db = \Database::getInstance();
    $stmt = $db->query("SELECT id, nome, email FROM usuarios ORDER BY nome");
    $usuarios = $stmt->fetchAll();
} catch (Exception $e) {
    $usuarios = [];
}

// Buscar lista de ações únicas para filtro
try {
    $db = \Database::getInstance();
    $stmt = $db->query("SELECT DISTINCT acao FROM logs_sistema ORDER BY acao");
    $acoesDisponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $acoesDisponiveis = [];
}

$titulo = 'Sistema de Auditoria';
include '../../views/layouts/_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Sistema de Auditoria</h1>
        <p class="text-gray-600">Visualize todas as atividades do sistema</p>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Filtros</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Período -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                <select name="periodo" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="dia" <?= $periodo === 'dia' ? 'selected' : '' ?>>Hoje</option>
                    <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Últimos 7 dias</option>
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este mês</option>
                </select>
            </div>

            <!-- Usuário -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuário</label>
                <select name="usuario_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= $usuario['id'] ?>" <?= isset($filtros['usuario_id']) && $filtros['usuario_id'] == $usuario['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($usuario['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ação -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ação</label>
                <select name="acao" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach ($acoesDisponiveis as $acao): ?>
                        <option value="<?= htmlspecialchars($acao) ?>" <?= isset($filtros['acao']) && $filtros['acao'] === $acao ? 'selected' : '' ?>>
                            <?= htmlspecialchars($acao) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Busca -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <input type="text" name="busca" value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>" 
                       placeholder="Buscar em ações e detalhes..." 
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <!-- Data Início -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                <input type="date" name="data_inicio" value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <!-- Data Fim -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <!-- IP -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">IP</label>
                <input type="text" name="ip" value="<?= htmlspecialchars($_GET['ip'] ?? '') ?>" 
                       placeholder="Ex: 192.168.1.1" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <!-- Botões -->
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Filtrar
                </button>
                <a href="auditoria.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                    Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm text-gray-600">Total de Registros</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($resultado['total']) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm text-gray-600">Página Atual</div>
            <div class="text-2xl font-bold text-gray-800"><?= $resultado['pagina'] ?> / <?= $resultado['total_paginas'] ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm text-gray-600">Registros por Página</div>
            <div class="text-2xl font-bold text-gray-800"><?= $resultado['por_pagina'] ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm text-gray-600">Ações Únicas</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($acoesDisponiveis) ?></div>
        </div>
    </div>

    <!-- Tabela de Registros -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalhes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                Nenhum registro encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $registro): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= formatarDataHora($registro['created_at']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($registro['usuario_nome'] ?? 'Sistema') ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($registro['usuario_email'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($registro['acao']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= htmlspecialchars($registro['detalhes']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($registro['ip'] ?? $registro['ip_address'] ?? 'N/A') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($resultado['total_paginas'] > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <?php
                $queryString = http_build_query(array_merge($_GET, ['page' => '']));
                echo Paginator::render($resultado, '?' . $queryString . 'page=');
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ações Mais Frequentes -->
    <?php if (!empty($acoesFrequentes)): ?>
        <div class="mt-6 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Ações Mais Frequentes</h2>
            <div class="space-y-2">
                <?php foreach ($acoesFrequentes as $acao): ?>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <span class="font-medium"><?= htmlspecialchars($acao['acao']) ?></span>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-600"><?= number_format($acao['total']) ?> ocorrências</span>
                            <span class="text-sm text-gray-500"><?= $acao['usuarios_unicos'] ?> usuários</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../views/layouts/_footer.php'; ?>
