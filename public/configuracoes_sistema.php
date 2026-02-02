<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

$mensagem = null;
$erro = null;

// Diretório para backups
$backupDir = __DIR__ . '/../backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Definir grupos de tabelas disponíveis para backup/limpeza
$gruposTabelas = [
    'pedidos' => [
        'nome' => 'Pedidos',
        'descricao' => 'Pedidos, itens, arquivos e artes',
        'icone' => 'fa-file-invoice',
        'cor' => 'blue',
        'tabelas' => ['pedidos', 'pedido_itens', 'pedido_arquivos', 'pedido_arte', 'arte_versoes']
    ],
    'clientes' => [
        'nome' => 'Clientes',
        'descricao' => 'Cadastro de clientes',
        'icone' => 'fa-users',
        'cor' => 'green',
        'tabelas' => ['clientes']
    ],
    'produtos' => [
        'nome' => 'Produtos',
        'descricao' => 'Catálogo de produtos e categorias',
        'icone' => 'fa-box',
        'cor' => 'purple',
        'tabelas' => ['produtos', 'categorias_produtos']
    ],
    'estoque' => [
        'nome' => 'Estoque',
        'descricao' => 'Movimentações de estoque',
        'icone' => 'fa-warehouse',
        'cor' => 'orange',
        'tabelas' => ['estoque_movimentacoes']
    ],
    'usuarios' => [
        'nome' => 'Usuários',
        'descricao' => 'Usuários do sistema',
        'icone' => 'fa-user-shield',
        'cor' => 'indigo',
        'tabelas' => ['usuarios']
    ],
    'auditoria' => [
        'nome' => 'Auditoria',
        'descricao' => 'Logs e histórico de ações',
        'icone' => 'fa-history',
        'cor' => 'gray',
        'tabelas' => ['auditoria']
    ],
    'configuracoes' => [
        'nome' => 'Configurações',
        'descricao' => 'Configurações do sistema',
        'icone' => 'fa-cog',
        'cor' => 'yellow',
        'tabelas' => ['configuracoes']
    ]
];

// Processar ações do banco de dados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_action'])) {
    try {
        switch ($_POST['db_action']) {
            case 'export':
                // Exportar banco de dados
                $tabelasSelecionadas = $_POST['tabelas_export'] ?? [];
                
                if (empty($tabelasSelecionadas)) {
                    throw new Exception('Selecione pelo menos um grupo de tabelas para exportar');
                }
                
                // Montar lista de tabelas a partir dos grupos selecionados
                $tablesParaExportar = [];
                foreach ($tabelasSelecionadas as $grupo) {
                    if (isset($gruposTabelas[$grupo])) {
                        $tablesParaExportar = array_merge($tablesParaExportar, $gruposTabelas[$grupo]['tabelas']);
                    }
                }
                $tablesParaExportar = array_unique($tablesParaExportar);
                
                $timestamp = date('Y-m-d_H-i-s');
                $filename = "backup_brbandeiras_{$timestamp}.sql";
                $filepath = $backupDir . $filename;
                
                $sql = "-- Backup BR Bandeiras\n";
                $sql .= "-- Data: " . date('d/m/Y H:i:s') . "\n";
                $sql .= "-- Usuário: " . ($_SESSION['user_nome'] ?? 'Sistema') . "\n";
                $sql .= "-- Grupos: " . implode(', ', $tabelasSelecionadas) . "\n";
                $sql .= "-- Tabelas: " . implode(', ', $tablesParaExportar) . "\n\n";
                $sql .= "SET client_encoding = 'UTF8';\n\n";
                
                $tabelasExportadas = 0;
                $registrosExportados = 0;
                
                foreach ($tablesParaExportar as $table) {
                    // Verificar se tabela existe
                    $exists = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '{$table}')")->fetchColumn();
                    
                    if (!$exists) continue;
                    
                    $sql .= "-- Tabela: {$table}\n";
                    
                    // Obter dados
                    $rows = $pdo->query("SELECT * FROM \"{$table}\"")->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($rows)) {
                        $tabelasExportadas++;
                        $columns = array_keys($rows[0]);
                        $columnList = '"' . implode('", "', $columns) . '"';
                        
                        foreach ($rows as $row) {
                            $registrosExportados++;
                            $values = array_map(function($val) use ($pdo) {
                                if ($val === null) return 'NULL';
                                return $pdo->quote($val);
                            }, array_values($row));
                            
                            $sql .= "INSERT INTO \"{$table}\" ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $sql .= "\n";
                    } else {
                        $sql .= "-- (tabela vazia)\n\n";
                    }
                }
                
                // Salvar arquivo
                file_put_contents($filepath, $sql);
                
                // Fazer download
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                
                registrarLog('backup_exportado', "Backup exportado: {$filename} ({$tabelasExportadas} tabelas, {$registrosExportados} registros)");
                exit;
                break;
                
            case 'import':
                // Importar banco de dados
                if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Erro no upload do arquivo');
                }
                
                $file = $_FILES['backup_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if ($ext !== 'sql') {
                    throw new Exception('Apenas arquivos .sql são permitidos');
                }
                
                $sql = file_get_contents($file['tmp_name']);
                
                if (empty($sql)) {
                    throw new Exception('Arquivo de backup está vazio');
                }
                
                // Executar SQL
                $pdo->beginTransaction();
                try {
                    // Dividir em statements (simplificado - para SQL complexo, use pg_restore)
                    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
                    
                    foreach ($statements as $statement) {
                        if (!empty($statement) && !preg_match('/^--/', $statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    
                    $pdo->commit();
                    $mensagem = 'Backup importado com sucesso! ' . count($statements) . ' comandos executados.';
                    registrarLog('backup_importado', "Backup importado: {$file['name']}");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Erro ao importar: ' . $e->getMessage());
                }
                break;
                
            case 'clear':
                // Limpar banco de dados (PERIGOSO!)
                $confirmacao = $_POST['confirmacao'] ?? '';
                $tabelasSelecionadas = $_POST['tabelas_clear'] ?? [];
                
                if ($confirmacao !== 'LIMPAR TUDO') {
                    throw new Exception('Confirmação incorreta. Digite exatamente: LIMPAR TUDO');
                }
                
                if (empty($tabelasSelecionadas)) {
                    throw new Exception('Selecione pelo menos um grupo de tabelas para limpar');
                }
                
                // Montar lista de tabelas a partir dos grupos selecionados
                $tabelasParaLimpar = [];
                foreach ($tabelasSelecionadas as $grupo) {
                    if (isset($gruposTabelas[$grupo])) {
                        $tabelasParaLimpar = array_merge($tabelasParaLimpar, $gruposTabelas[$grupo]['tabelas']);
                    }
                }
                $tabelasParaLimpar = array_unique($tabelasParaLimpar);
                
                $pdo->beginTransaction();
                try {
                    // Desabilitar constraints temporariamente
                    $pdo->exec("SET session_replication_role = 'replica'");
                    
                    $tabelasLimpas = [];
                    foreach ($tabelasParaLimpar as $tabela) {
                        // Verificar se tabela existe
                        $exists = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '{$tabela}')")->fetchColumn();
                        if ($exists) {
                            $pdo->exec("TRUNCATE TABLE \"{$tabela}\" CASCADE");
                            $tabelasLimpas[] = $tabela;
                        }
                    }
                    
                    // Reabilitar constraints
                    $pdo->exec("SET session_replication_role = 'origin'");
                    
                    $pdo->commit();
                    $mensagem = 'Banco de dados limpo com sucesso! Tabelas limpas: ' . implode(', ', $tabelasLimpas);
                    registrarLog('banco_limpo', 'Tabelas limpas: ' . implode(', ', $tabelasLimpas));
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception('Erro ao limpar banco: ' . $e->getMessage());
                }
                break;
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Processar formulário de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['desconto_maximo_vendedor'])) {
    try {
        $descontoMaximo = floatval($_POST['desconto_maximo_vendedor'] ?? 2.0);
        
        if ($descontoMaximo < 0 || $descontoMaximo > 100) {
            throw new Exception('Desconto máximo deve estar entre 0% e 100%');
        }
        
        if (setConfig('desconto_maximo_vendedor', $descontoMaximo, 'decimal', 'Desconto máximo permitido para vendedores (%)')) {
            $mensagem = 'Configuração salva com sucesso!';
            registrarLog('configuracao_atualizada', "Desconto máximo vendedor alterado para {$descontoMaximo}%");
        } else {
            throw new Exception('Erro ao salvar configuração');
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Listar backups existentes
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'nome' => basename($file),
            'tamanho' => filesize($file),
            'data' => filemtime($file)
        ];
    }
    // Ordenar por data (mais recente primeiro)
    usort($backups, fn($a, $b) => $b['data'] - $a['data']);
}

// Buscar configuração atual
$descontoMaximoAtual = getDescontoMaximoVendedor();

$titulo = 'Configurações do Sistema';
include '../views/layouts/_header.php';
?>

<div class="flex-1 bg-gray-50">
    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">
                <i class="fas fa-cog mr-2"></i>
                Configurações do Sistema
            </h1>
            
            <?php if ($mensagem): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($mensagem) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($erro): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <!-- Configuração de Desconto Máximo para Vendedores -->
                <div class="border border-gray-200 rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-percent mr-2 text-blue-600"></i>
                        Limite de Desconto para Vendedores
                    </h2>
                    
                    <p class="text-sm text-gray-600 mb-4">
                        Defina o percentual máximo de desconto que os vendedores podem aplicar nos pedidos. 
                        Descontos acima deste limite só podem ser aplicados por gestores.
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Desconto Máximo (%)
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       name="desconto_maximo_vendedor" 
                                       value="<?= number_format($descontoMaximoAtual, 2, '.', '') ?>"
                                       step="0.1"
                                       min="0"
                                       max="100"
                                       required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <span class="absolute right-3 top-2 text-gray-500">%</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Valor atual: <strong><?= number_format($descontoMaximoAtual, 2, ',', '.') ?>%</strong>
                            </p>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Salvar Configuração
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Importante:</strong> Esta configuração afeta todos os vendedores do sistema. 
                            Ao tentar aplicar um desconto maior que o limite, o vendedor receberá uma mensagem de erro 
                            e precisará solicitar aprovação do gestor.
                        </p>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Gerenciamento de Banco de Dados -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6" x-data="backupManager()">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-database mr-2 text-purple-600"></i>
                Gerenciamento de Banco de Dados
            </h2>
            
            <p class="text-sm text-gray-600 mb-6">
                Faça backup dos dados do sistema, restaure backups anteriores ou limpe o banco de dados para começar do zero.
                Você pode selecionar quais dados deseja incluir em cada operação.
            </p>
            
            <!-- Botões de Ação -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- Exportar -->
                <button @click="showExportModal = true" 
                        class="flex items-center justify-center gap-3 px-6 py-4 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors">
                    <i class="fas fa-download text-xl"></i>
                    <div class="text-left">
                        <span class="block">Exportar Backup</span>
                        <span class="text-xs text-green-200">Selecionar tabelas</span>
                    </div>
                </button>
                
                <!-- Importar -->
                <button @click="showImportModal = true" 
                        class="flex items-center justify-center gap-3 px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                    <i class="fas fa-upload text-xl"></i>
                    <div class="text-left">
                        <span class="block">Importar Backup</span>
                        <span class="text-xs text-blue-200">Restaurar dados</span>
                    </div>
                </button>
                
                <!-- Limpar -->
                <button @click="showClearModal = true; confirmText = ''; clearTables = []" 
                        class="flex items-center justify-center gap-3 px-6 py-4 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors">
                    <i class="fas fa-trash-alt text-xl"></i>
                    <div class="text-left">
                        <span class="block">Limpar Banco</span>
                        <span class="text-xs text-red-200">Selecionar tabelas</span>
                    </div>
                </button>
            </div>
            
            <!-- Lista de Backups Salvos -->
            <?php if (!empty($backups)): ?>
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b">
                    <h3 class="font-semibold text-gray-700">
                        <i class="fas fa-history mr-2"></i>
                        Backups Salvos no Servidor
                    </h3>
                </div>
                <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                    <?php foreach (array_slice($backups, 0, 10) as $backup): ?>
                    <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($backup['nome']) ?></p>
                            <p class="text-xs text-gray-500">
                                <?= date('d/m/Y H:i', $backup['data']) ?> • 
                                <?= number_format($backup['tamanho'] / 1024, 1) ?> KB
                            </p>
                        </div>
                        <a href="download_backup.php?file=<?= urlencode($backup['nome']) ?>" 
                           class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-download mr-1"></i> Baixar
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-gray-500 border border-dashed border-gray-300 rounded-lg">
                <i class="fas fa-archive text-4xl mb-2 opacity-50"></i>
                <p>Nenhum backup salvo no servidor</p>
                <p class="text-xs">Exporte um backup para começar</p>
            </div>
            <?php endif; ?>
            
            <!-- Modal de Exportação -->
            <div x-show="showExportModal" 
                 x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                 @keydown.escape="showExportModal = false">
                <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 p-6" @click.away="showExportModal = false">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-download mr-2 text-green-600"></i>
                        Exportar Backup
                    </h3>
                    
                    <form method="POST" @submit="isExporting = true">
                        <input type="hidden" name="db_action" value="export">
                        
                        <p class="text-sm text-gray-600 mb-4">
                            Selecione os dados que deseja incluir no backup:
                        </p>
                        
                        <!-- Seleção de Tabelas para Export -->
                        <div class="mb-4 border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b flex items-center justify-between">
                                <span class="font-medium text-gray-700 text-sm">Grupos de Dados</span>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" 
                                           @change="toggleAll('export', $event.target.checked)"
                                           :checked="exportTables.length === <?= count($gruposTabelas) ?>"
                                           class="rounded text-green-600">
                                    <span class="text-gray-600">Selecionar todos</span>
                                </label>
                            </div>
                            <div class="p-3 space-y-2 max-h-64 overflow-y-auto">
                                <?php foreach ($gruposTabelas as $key => $grupo): ?>
                                <label class="flex items-center gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" 
                                           name="tabelas_export[]" 
                                           value="<?= $key ?>"
                                           x-model="exportTables"
                                           class="rounded text-green-600 focus:ring-green-500">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="w-8 h-8 flex items-center justify-center bg-<?= $grupo['cor'] ?>-100 text-<?= $grupo['cor'] ?>-600 rounded">
                                            <i class="fas <?= $grupo['icone'] ?> text-sm"></i>
                                        </span>
                                        <div>
                                            <span class="font-medium text-gray-800"><?= $grupo['nome'] ?></span>
                                            <p class="text-xs text-gray-500"><?= $grupo['descricao'] ?></p>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                            <p class="text-sm text-green-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                O arquivo será salvo no servidor e baixado automaticamente.
                            </p>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="button" 
                                    @click="showExportModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    :disabled="exportTables.length === 0 || isExporting"
                                    class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                                <i class="fas fa-download mr-2" :class="isExporting && 'animate-bounce'"></i>
                                <span x-text="isExporting ? 'Exportando...' : 'Exportar'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Modal de Importação -->
            <div x-show="showImportModal" 
                 x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                 @keydown.escape="showImportModal = false">
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6" @click.away="showImportModal = false">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-upload mr-2 text-blue-600"></i>
                        Importar Backup
                    </h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="db_action" value="import">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Arquivo de Backup (.sql)
                            </label>
                            <input type="file" 
                                   name="backup_file" 
                                   accept=".sql"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                            <p class="text-sm text-yellow-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Atenção:</strong> A importação pode sobrescrever dados existentes. 
                                Recomenda-se exportar um backup antes de continuar.
                            </p>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="button" 
                                    @click="showImportModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-upload mr-2"></i>
                                Importar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Modal de Limpeza -->
            <div x-show="showClearModal" 
                 x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                 @keydown.escape="showClearModal = false">
                <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 p-6" @click.away="showClearModal = false">
                    <h3 class="text-xl font-bold text-red-600 mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Limpar Banco de Dados
                    </h3>
                    
                    <form method="POST">
                        <input type="hidden" name="db_action" value="clear">
                        
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <p class="text-red-800 font-semibold mb-2">
                                <i class="fas fa-skull-crossbones mr-2"></i>
                                Esta ação é IRREVERSÍVEL!
                            </p>
                            <p class="text-sm text-red-700">
                                Os dados selecionados serão apagados permanentemente.
                            </p>
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-3">
                            Selecione os dados que deseja <strong class="text-red-600">APAGAR</strong>:
                        </p>
                        
                        <!-- Seleção de Tabelas para Limpar -->
                        <div class="mb-4 border border-red-200 rounded-lg overflow-hidden">
                            <div class="bg-red-50 px-4 py-2 border-b flex items-center justify-between">
                                <span class="font-medium text-red-700 text-sm">Grupos de Dados</span>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" 
                                           @change="toggleAll('clear', $event.target.checked)"
                                           :checked="clearTables.length === <?= count($gruposTabelas) ?>"
                                           class="rounded text-red-600">
                                    <span class="text-red-600">Selecionar todos</span>
                                </label>
                            </div>
                            <div class="p-3 space-y-2 max-h-48 overflow-y-auto">
                                <?php foreach ($gruposTabelas as $key => $grupo): ?>
                                <label class="flex items-center gap-3 p-2 rounded hover:bg-red-50 cursor-pointer">
                                    <input type="checkbox" 
                                           name="tabelas_clear[]" 
                                           value="<?= $key ?>"
                                           x-model="clearTables"
                                           class="rounded text-red-600 focus:ring-red-500">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="w-8 h-8 flex items-center justify-center bg-<?= $grupo['cor'] ?>-100 text-<?= $grupo['cor'] ?>-600 rounded">
                                            <i class="fas <?= $grupo['icone'] ?> text-sm"></i>
                                        </span>
                                        <div>
                                            <span class="font-medium text-gray-800"><?= $grupo['nome'] ?></span>
                                            <p class="text-xs text-gray-500"><?= $grupo['descricao'] ?></p>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Tabelas selecionadas -->
                        <div x-show="clearTables.length > 0" class="mb-4 p-3 bg-red-100 border border-red-200 rounded-lg">
                            <p class="text-sm text-red-800">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <strong x-text="clearTables.length"></strong> grupo(s) selecionado(s) para limpeza
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Para confirmar, digite: <strong class="text-red-600">LIMPAR TUDO</strong>
                            </label>
                            <input type="text" 
                                   name="confirmacao"
                                   x-model="confirmText"
                                   placeholder="Digite a confirmação"
                                   autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-red-500">
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="button" 
                                    @click="showClearModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    :disabled="confirmText !== 'LIMPAR TUDO' || clearTables.length === 0"
                                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                                <i class="fas fa-trash-alt mr-2"></i>
                                Limpar Selecionados
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle mr-2 text-gray-600"></i>
                Informações sobre Permissões
            </h2>
            
            <div class="space-y-4 text-sm text-gray-700">
                <div class="border-l-4 border-blue-500 pl-4">
                    <h3 class="font-semibold mb-2">Vendedores</h3>
                    <ul class="list-disc list-inside space-y-1 text-gray-600">
                        <li>Podem aplicar desconto até o limite configurado</li>
                        <li>Veem apenas seus próprios pedidos e comissões</li>
                        <li>Não têm acesso a valores de outros vendedores</li>
                    </ul>
                </div>
                
                <div class="border-l-4 border-green-500 pl-4">
                    <h3 class="font-semibold mb-2">Gestores</h3>
                    <ul class="list-disc list-inside space-y-1 text-gray-600">
                        <li>Podem aplicar qualquer desconto (sem limite)</li>
                        <li>Têm acesso a todos os pedidos e valores</li>
                        <li>Podem configurar limites de desconto</li>
                    </ul>
                </div>
                
                <div class="border-l-4 border-orange-500 pl-4">
                    <h3 class="font-semibold mb-2">Produção e Arte</h3>
                    <ul class="list-disc list-inside space-y-1 text-gray-600">
                        <li>Não veem valores financeiros dos pedidos</li>
                        <li>Focam apenas nas informações operacionais</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Registrar componente Alpine para gerenciamento de backup
document.addEventListener('alpine:init', () => {
    Alpine.data('backupManager', () => ({
        showExportModal: false,
        showImportModal: false, 
        showClearModal: false,
        confirmText: '',
        isExporting: false,
        exportTables: ['pedidos', 'clientes', 'produtos'],
        clearTables: [],
        allGroups: <?= json_encode(array_keys($gruposTabelas)) ?>,
        
        toggleAll(type, checked) {
            if (type === 'export') {
                this.exportTables = checked ? [...this.allGroups] : [];
            } else {
                this.clearTables = checked ? [...this.allGroups] : [];
            }
        }
    }));
});
</script>

<?php include '../views/layouts/_footer.php'; ?>
