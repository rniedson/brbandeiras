<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

// Listar todas as tabelas existentes no banco
try {
    $sql = "
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ";
    $stmt = $pdo->query($sql);
    $tabelas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    die("Erro ao listar tabelas: " . $e->getMessage());
}

// Tabelas referenciadas no código que criamos
$tabelas_referenciadas = [
    // Tabelas principais do sistema (já existentes)
    'usuarios',
    'clientes',
    'pedidos',
    'pedido_itens',
    'produtos_catalogo',
    'produtos_estoque',
    'categorias_produtos',
    'grupos_clientes',
    'fornecedores',
    'cotacoes',
    'cotacao_itens',
    'contas_receber',
    'contas_pagar',
    'comissoes',
    'arte_versoes',
    'pedido_arte',
    'pedido_arquivos',
    'logs_sistema',
    
    // Tabelas que criamos nas páginas recentes
    'metas_vendas',
    'empresa',
    'documentos_empresa',
];

// Comparar
$tabelas_faltantes = array_diff($tabelas_referenciadas, $tabelas_existentes);
$tabelas_nao_usadas = array_diff($tabelas_existentes, $tabelas_referenciadas);

$titulo = 'Verificação de Tabelas';
include '../views/layouts/_header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Verificação de Tabelas do Banco de Dados</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Análise das tabelas existentes e faltantes</p>
    </div>
    
    <!-- Resumo -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total de Tabelas</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-white"><?= count($tabelas_existentes) ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">No banco de dados</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Tabelas Referenciadas</div>
            <div class="text-2xl font-bold text-blue-600"><?= count($tabelas_referenciadas) ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">No código</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Tabelas Faltantes</div>
            <div class="text-2xl font-bold <?= count($tabelas_faltantes) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                <?= count($tabelas_faltantes) ?>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Precisam ser criadas</div>
        </div>
    </div>
    
    <!-- Tabelas Faltantes -->
    <?php if (!empty($tabelas_faltantes)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                ⚠️ Tabelas Faltantes (<?= count($tabelas_faltantes) ?>)
            </h2>
        </div>
        <div class="p-6">
            <div class="space-y-2">
                <?php foreach ($tabelas_faltantes as $tabela): ?>
                <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <div>
                        <span class="font-medium text-red-800 dark:text-red-200"><?= htmlspecialchars($tabela) ?></span>
                        <span class="text-sm text-red-600 dark:text-red-400 ml-2">
                            <?php
                            // Informar onde é usada
                            $usos = [];
                            if ($tabela === 'metas_vendas') $usos[] = 'metas.php';
                            if ($tabela === 'empresa') $usos[] = 'empresa.php';
                            if ($tabela === 'documentos_empresa') $usos[] = 'documentos.php';
                            if (!empty($usos)) {
                                echo '(' . implode(', ', $usos) . ')';
                            }
                            ?>
                        </span>
                    </div>
                    <span class="text-xs text-red-600 dark:text-red-400">Faltando</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6 mb-6">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-green-800 dark:text-green-200">Todas as tabelas necessárias existem!</h3>
                <p class="text-sm text-green-600 dark:text-green-400 mt-1">Não há tabelas faltantes no banco de dados.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tabelas Existentes -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                Tabelas Existentes (<?= count($tabelas_existentes) ?>)
            </h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                <?php foreach ($tabelas_existentes as $tabela): 
                    $esta_referenciada = in_array($tabela, $tabelas_referenciadas);
                    $cor = $esta_referenciada ? 'green' : 'gray';
                ?>
                <div class="flex items-center p-2 bg-<?= $cor ?>-50 dark:bg-<?= $cor ?>-900/20 border border-<?= $cor ?>-200 dark:border-<?= $cor ?>-800 rounded">
                    <span class="text-sm font-medium text-<?= $cor ?>-800 dark:text-<?= $cor ?>-200">
                        <?= htmlspecialchars($tabela) ?>
                    </span>
                    <?php if ($esta_referenciada): ?>
                    <svg class="w-4 h-4 text-<?= $cor ?>-600 dark:text-<?= $cor ?>-400 ml-auto" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Tabelas Não Referenciadas -->
    <?php if (!empty($tabelas_nao_usadas)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                Tabelas Não Referenciadas (<?= count($tabelas_nao_usadas) ?>)
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Existem no banco mas não são usadas nas páginas criadas</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                <?php foreach ($tabelas_nao_usadas as $tabela): ?>
                <div class="p-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">
                    <span class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($tabela) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Scripts SQL para Criar Tabelas Faltantes -->
    <?php if (!empty($tabelas_faltantes)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Scripts SQL para Criar Tabelas Faltantes</h2>
        </div>
        <div class="p-6">
            <div class="space-y-6">
                <?php if (in_array('metas_vendas', $tabelas_faltantes)): ?>
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">metas_vendas</h3>
                    <pre class="bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-sm"><code>CREATE TABLE IF NOT EXISTS metas_vendas (
    id SERIAL PRIMARY KEY,
    vendedor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    periodo_tipo VARCHAR(20) NOT NULL CHECK (periodo_tipo IN ('mes', 'trimestre', 'ano')),
    periodo_referencia VARCHAR(20) NOT NULL,
    valor_meta DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'ativa' CHECK (status IN ('ativa', 'concluida', 'cancelada')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(vendedor_id, periodo_tipo, periodo_referencia, status) WHERE status = 'ativa'
);

CREATE INDEX idx_metas_vendas_vendedor ON metas_vendas(vendedor_id);
CREATE INDEX idx_metas_vendas_periodo ON metas_vendas(periodo_tipo, periodo_referencia);
CREATE INDEX idx_metas_vendas_status ON metas_vendas(status);</code></pre>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('empresa', $tabelas_faltantes)): ?>
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">empresa</h3>
                    <pre class="bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-sm"><code>CREATE TABLE IF NOT EXISTS empresa (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    cnpj VARCHAR(18),
    inscricao_estadual VARCHAR(50),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    email VARCHAR(255),
    site VARCHAR(255),
    cep VARCHAR(10),
    endereco VARCHAR(255),
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO empresa (id, nome) VALUES (1, 'BR Bandeiras') ON CONFLICT (id) DO NOTHING;</code></pre>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('documentos_empresa', $tabelas_faltantes)): ?>
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">documentos_empresa</h3>
                    <pre class="bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-sm"><code>CREATE TABLE IF NOT EXISTS documentos_empresa (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    categoria VARCHAR(50) DEFAULT 'geral',
    descricao TEXT,
    arquivo_nome VARCHAR(255) NOT NULL,
    arquivo_caminho VARCHAR(500) NOT NULL,
    tamanho BIGINT NOT NULL,
    tipo VARCHAR(10) NOT NULL,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_documentos_empresa_categoria ON documentos_empresa(categoria);
CREATE INDEX idx_documentos_empresa_usuario ON documentos_empresa(usuario_id);
CREATE INDEX idx_documentos_empresa_created ON documentos_empresa(created_at);</code></pre>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../views/layouts/_footer.php'; ?>
