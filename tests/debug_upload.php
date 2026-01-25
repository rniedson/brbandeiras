<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireLogin();
requireRole(['gestor', 'arte_finalista']); // Apenas gestores e arte-finalistas podem debugar

// Processar teste de upload se enviado
$teste_upload = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['teste_arquivo'])) {
    $teste_upload = [
        'arquivo_enviado' => !empty($_FILES['teste_arquivo']['name']),
        'nome' => $_FILES['teste_arquivo']['name'] ?? 'Nenhum',
        'tamanho' => $_FILES['teste_arquivo']['size'] ?? 0,
        'tipo' => $_FILES['teste_arquivo']['type'] ?? 'Desconhecido',
        'erro' => $_FILES['teste_arquivo']['error'] ?? -1,
        'tmp_name' => $_FILES['teste_arquivo']['tmp_name'] ?? 'Nenhum',
        'tmp_existe' => isset($_FILES['teste_arquivo']['tmp_name']) && file_exists($_FILES['teste_arquivo']['tmp_name'])
    ];
    
    // Interpretar código de erro
    $erros_upload = [
        UPLOAD_ERR_OK => 'Sem erro',
        UPLOAD_ERR_INI_SIZE => 'Arquivo excede upload_max_filesize do PHP',
        UPLOAD_ERR_FORM_SIZE => 'Arquivo excede MAX_FILE_SIZE do formulário',
        UPLOAD_ERR_PARTIAL => 'Upload parcial',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever no disco',
        UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP'
    ];
    $teste_upload['erro_descricao'] = $erros_upload[$teste_upload['erro']] ?? 'Erro desconhecido';
    
    // Tentar mover arquivo se chegou
    if ($teste_upload['erro'] === UPLOAD_ERR_OK && $teste_upload['tmp_existe']) {
        $destino = '../uploads/teste_' . uniqid() . '_' . basename($teste_upload['nome']);
        $teste_upload['move_result'] = move_uploaded_file($teste_upload['tmp_name'], $destino);
        if ($teste_upload['move_result']) {
            $teste_upload['arquivo_final'] = $destino;
            $teste_upload['arquivo_final_existe'] = file_exists($destino);
            // Limpar arquivo de teste
            @unlink($destino);
        }
    }
}

$titulo = 'Debug - Sistema de Upload';
include '../views/_header.php';
?>

<div class="max-w-6xl mx-auto p-6">
    <div class="bg-red-600 text-white p-4 rounded-lg mb-6">
        <h1 class="text-2xl font-bold mb-2">🔧 Debug do Sistema de Upload</h1>
        <p>Esta página verifica todas as configurações necessárias para upload de arquivos</p>
    </div>

    <!-- 1. Configurações do PHP -->
    <div class="bg-white rounded-lg shadow mb-6 p-6">
        <h2 class="text-xl font-bold mb-4 text-gray-800">1. Configurações do PHP</h2>
        <?php
        $configs = [
            'file_uploads' => [
                'valor' => ini_get('file_uploads'),
                'esperado' => '1',
                'ok' => ini_get('file_uploads') == '1'
            ],
            'upload_max_filesize' => [
                'valor' => ini_get('upload_max_filesize'),
                'esperado' => '>= 50M',
                'ok' => intval(ini_get('upload_max_filesize')) >= 50
            ],
            'post_max_size' => [
                'valor' => ini_get('post_max_size'),
                'esperado' => '>= 50M',
                'ok' => intval(ini_get('post_max_size')) >= 50
            ],
            'max_file_uploads' => [
                'valor' => ini_get('max_file_uploads'),
                'esperado' => '>= 20',
                'ok' => intval(ini_get('max_file_uploads')) >= 20
            ],
            'memory_limit' => [
                'valor' => ini_get('memory_limit'),
                'esperado' => '>= 128M',
                'ok' => intval(ini_get('memory_limit')) >= 128
            ],
            'max_execution_time' => [
                'valor' => ini_get('max_execution_time'),
                'esperado' => '>= 30',
                'ok' => intval(ini_get('max_execution_time')) >= 30
            ],
            'max_input_time' => [
                'valor' => ini_get('max_input_time'),
                'esperado' => '>= 60',
                'ok' => intval(ini_get('max_input_time')) >= 60
            ]
        ];
        ?>
        <table class="min-w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Configuração</th>
                    <th class="text-left py-2">Valor Atual</th>
                    <th class="text-left py-2">Valor Esperado</th>
                    <th class="text-left py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configs as $key => $config): ?>
                <tr class="border-b">
                    <td class="py-2 font-mono text-sm"><?= $key ?></td>
                    <td class="py-2"><?= $config['valor'] ?: 'Não definido' ?></td>
                    <td class="py-2"><?= $config['esperado'] ?></td>
                    <td class="py-2">
                        <?php if ($config['ok']): ?>
                            <span class="text-green-600">✅ OK</span>
                        <?php else: ?>
                            <span class="text-red-600">❌ PROBLEMA</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="mt-4 p-4 bg-blue-50 rounded">
            <p class="text-sm text-blue-800">
                <strong>Pasta temporária do PHP:</strong> <?= ini_get('upload_tmp_dir') ?: sys_get_temp_dir() ?><br>
                <strong>Versão do PHP:</strong> <?= PHP_VERSION ?><br>
                <strong>SAPI:</strong> <?= PHP_SAPI ?>
            </p>
        </div>
    </div>

    <!-- 2. Permissões de Pastas -->
    <div class="bg-white rounded-lg shadow mb-6 p-6">
        <h2 class="text-xl font-bold mb-4 text-gray-800">2. Permissões de Pastas</h2>
        <?php
        $pastas = [
            '../uploads/',
            '../uploads/arte_versoes/',
            '../uploads/pedidos/',
            '../uploads/orcamentos/',
            '../uploads/catalogo/',
            '../public/uploads/',
            ini_get('upload_tmp_dir') ?: sys_get_temp_dir()
        ];
        ?>
        <table class="min-w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Pasta</th>
                    <th class="text-left py-2">Existe</th>
                    <th class="text-left py-2">Gravável</th>
                    <th class="text-left py-2">Permissões</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pastas as $pasta): ?>
                <tr class="border-b">
                    <td class="py-2 font-mono text-sm"><?= $pasta ?></td>
                    <td class="py-2">
                        <?php if (file_exists($pasta)): ?>
                            <span class="text-green-600">✅ Sim</span>
                        <?php else: ?>
                            <span class="text-red-600">❌ Não</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2">
                        <?php if (is_writable($pasta)): ?>
                            <span class="text-green-600">✅ Sim</span>
                        <?php else: ?>
                            <span class="text-red-600">❌ Não</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2">
                        <?php 
                        if (file_exists($pasta)) {
                            $perms = fileperms($pasta);
                            echo sprintf('%o', $perms & 0777);
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Tentar criar pastas que não existem
        $pastas_criar = [
            '../uploads/arte_versoes/',
            '../uploads/pedidos/',
            '../uploads/orcamentos/',
            '../uploads/catalogo/'
        ];
        
        $criacao_resultado = [];
        foreach ($pastas_criar as $pasta) {
            if (!file_exists($pasta)) {
                $criacao_resultado[$pasta] = @mkdir($pasta, 0777, true);
            }
        }
        
        if (!empty($criacao_resultado)):
        ?>
        <div class="mt-4 p-4 bg-yellow-50 rounded">
            <p class="text-sm text-yellow-800 font-semibold mb-2">Tentativa de criar pastas ausentes:</p>
            <?php foreach ($criacao_resultado as $pasta => $sucesso): ?>
                <p class="text-sm">
                    <?= $pasta ?>: 
                    <?php if ($sucesso): ?>
                        <span class="text-green-600">✅ Criada com sucesso</span>
                    <?php else: ?>
                        <span class="text-red-600">❌ Falha ao criar</span>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 3. Verificação do Banco de Dados -->
    <div class="bg-white rounded-lg shadow mb-6 p-6">
        <h2 class="text-xl font-bold mb-4 text-gray-800">3. Estrutura do Banco de Dados</h2>
        <?php
        // Verificar tabela arte_versoes
        try {
            $stmt = $pdo->query("SELECT column_name, data_type, is_nullable 
                                FROM information_schema.columns 
                                WHERE table_name = 'arte_versoes' 
                                ORDER BY ordinal_position");
            $colunas_arte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $colunas_arte = [];
        }
        
        // Verificar tabela pedido_arquivos
        try {
            $stmt = $pdo->query("SELECT column_name, data_type, is_nullable 
                                FROM information_schema.columns 
                                WHERE table_name = 'pedido_arquivos' 
                                ORDER BY ordinal_position");
            $colunas_pedido = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $colunas_pedido = [];
        }
        ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h3 class="font-semibold mb-2">Tabela: arte_versoes</h3>
                <?php if (empty($colunas_arte)): ?>
                    <p class="text-red-600">❌ Tabela não encontrada!</p>
                    <div class="mt-2 p-2 bg-gray-100 rounded">
                        <p class="text-xs font-mono">
                        CREATE TABLE arte_versoes (<br>
                        &nbsp;&nbsp;id SERIAL PRIMARY KEY,<br>
                        &nbsp;&nbsp;pedido_id INTEGER NOT NULL,<br>
                        &nbsp;&nbsp;versao INTEGER NOT NULL DEFAULT 1,<br>
                        &nbsp;&nbsp;arquivo_nome VARCHAR(255) NOT NULL,<br>
                        &nbsp;&nbsp;arquivo_caminho VARCHAR(500) NOT NULL,<br>
                        &nbsp;&nbsp;aprovada BOOLEAN DEFAULT false,<br>
                        &nbsp;&nbsp;reprovada BOOLEAN DEFAULT false,<br>
                        &nbsp;&nbsp;comentario_arte TEXT,<br>
                        &nbsp;&nbsp;comentario_cliente TEXT,<br>
                        &nbsp;&nbsp;usuario_id INTEGER,<br>
                        &nbsp;&nbsp;created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP<br>
                        );
                        </p>
                    </div>
                <?php else: ?>
                    <div class="text-sm">
                        <?php foreach ($colunas_arte as $col): ?>
                            <p class="py-1 border-b">
                                <span class="font-mono"><?= $col['column_name'] ?></span>
                                <span class="text-gray-600">(<?= $col['data_type'] ?>)</span>
                            </p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div>
                <h3 class="font-semibold mb-2">Tabela: pedido_arquivos</h3>
                <?php if (empty($colunas_pedido)): ?>
                    <p class="text-red-600">❌ Tabela não encontrada!</p>
                <?php else: ?>
                    <div class="text-sm">
                        <?php foreach ($colunas_pedido as $col): ?>
                            <p class="py-1 border-b">
                                <span class="font-mono"><?= $col['column_name'] ?></span>
                                <span class="text-gray-600">(<?= $col['data_type'] ?>)</span>
                            </p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. Teste de Upload Real -->
    <div class="bg-white rounded-lg shadow mb-6 p-6">
        <h2 class="text-xl font-bold mb-4 text-gray-800">4. Teste de Upload Real</h2>
        
        <form method="POST" enctype="multipart/form-data" class="mb-4">
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Selecione um arquivo para teste
                    </label>
                    <input type="file" 
                           name="teste_arquivo" 
                           required
                           class="w-full px-4 py-2 border rounded-lg">
                </div>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Testar Upload
                </button>
            </div>
        </form>
        
        <?php if ($teste_upload): ?>
        <div class="p-4 bg-gray-50 rounded">
            <h3 class="font-semibold mb-2">Resultado do Teste:</h3>
            <table class="min-w-full text-sm">
                <tr class="border-b">
                    <td class="py-2 font-semibold">Arquivo Recebido:</td>
                    <td class="py-2">
                        <?php if ($teste_upload['arquivo_enviado']): ?>
                            <span class="text-green-600">✅ Sim</span>
                        <?php else: ?>
                            <span class="text-red-600">❌ Não</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 font-semibold">Nome:</td>
                    <td class="py-2"><?= htmlspecialchars($teste_upload['nome']) ?></td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 font-semibold">Tamanho:</td>
                    <td class="py-2"><?= number_format($teste_upload['tamanho'] / 1024, 2) ?> KB</td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 font-semibold">Tipo MIME:</td>
                    <td class="py-2"><?= htmlspecialchars($teste_upload['tipo']) ?></td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 font-semibold">Código de Erro:</td>
                    <td class="py-2">
                        <?= $teste_upload['erro'] ?> - <?= $teste_upload['erro_descricao'] ?>
                        <?php if ($teste_upload['erro'] === 0): ?>
                            <span class="text-green-600">✅</span>
                        <?php else: ?>
                            <span class="text-red-600">❌</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 font-semibold">Arquivo Temporário:</td>
                    <td class="py-2">
                        <?= htmlspecialchars($teste_upload['tmp_name']) ?>
                        <?php if ($teste_upload['tmp_existe']): ?>
                            <span class="text-green-600">✅ Existe</span>
                        <?php else: ?>
                            <span class="text-red-600">❌ Não existe</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (isset($teste_upload['move_result'])): ?>
                <tr class="border-b">
                    <td class="py-2 font-semibold">Move Upload:</td>
                    <td class="py-2">
                        <?php if ($teste_upload['move_result']): ?>
                            <span class="text-green-600">✅ Sucesso</span>
                        <?php else: ?>
                            <span class="text-red-600">❌ Falhou</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- 5. Informações de Debug JavaScript -->
    <div class="bg-white rounded-lg shadow mb-6 p-6">
        <h2 class="text-xl font-bold mb-4 text-gray-800">5. Debug JavaScript/Formulário</h2>
        
        <form id="form-debug" method="POST" enctype="multipart/form-data">
            <input type="file" id="debug-file" name="debug_file" class="mb-4">
            <div id="debug-info" class="p-4 bg-gray-50 rounded text-sm font-mono"></div>
        </form>
        
        <script>
        document.getElementById('debug-file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const debugInfo = document.getElementById('debug-info');
            
            if (file) {
                const info = `
                    <p><strong>Nome:</strong> ${file.name}</p>
                    <p><strong>Tamanho:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    <p><strong>Tipo:</strong> ${file.type}</p>
                    <p><strong>Última Modificação:</strong> ${new Date(file.lastModified).toLocaleString()}</p>
                    <p><strong>Tamanho máximo PHP:</strong> <?= ini_get('upload_max_filesize') ?></p>
                    <p><strong>POST máximo PHP:</strong> <?= ini_get('post_max_size') ?></p>
                    <hr class="my-2">
                    <p><strong>Validação JS:</strong></p>
                    <p>- Tamanho OK: ${file.size <= 50 * 1024 * 1024 ? '✅' : '❌ (máx 50MB)'}</p>
                    <p>- Tipo permitido: ${['image/jpeg', 'image/png', 'application/pdf'].includes(file.type) ? '✅' : '⚠️'}</p>
                `;
                debugInfo.innerHTML = info;
            } else {
                debugInfo.innerHTML = '<p class="text-red-600">Nenhum arquivo selecionado</p>';
            }
        });
        
        // Verificar se FormData está disponível
        if (window.FormData) {
            console.log('✅ FormData suportado');
        } else {
            console.error('❌ FormData NÃO suportado - uploads não funcionarão!');
        }
        </script>
    </div>

    <!-- 6. Variáveis de Ambiente -->
    <div class="bg-white rounded-lg shadow mb-6 p-6">
        <h2 class="text-xl font-bold mb-4 text-gray-800">6. Variáveis $_FILES e $_POST</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h3 class="font-semibold mb-2">$_FILES (última requisição):</h3>
                <pre class="p-2 bg-gray-100 rounded text-xs overflow-auto">
<?= htmlspecialchars(print_r($_FILES, true)) ?>
                </pre>
            </div>
            
            <div>
                <h3 class="font-semibold mb-2">$_POST (última requisição):</h3>
                <pre class="p-2 bg-gray-100 rounded text-xs overflow-auto">
<?= htmlspecialchars(print_r($_POST, true)) ?>
                </pre>
            </div>
        </div>
    </div>

    <!-- 7. Soluções Comuns -->
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6">
        <h2 class="text-xl font-bold mb-4 text-yellow-800">🔧 Soluções Comuns</h2>
        
        <div class="space-y-3 text-sm">
            <div>
                <p class="font-semibold text-yellow-900">1. Se o arquivo não está sendo enviado:</p>
                <ul class="ml-4 list-disc text-yellow-700">
                    <li>Verificar se o formulário tem <code>enctype="multipart/form-data"</code></li>
                    <li>Verificar se o método é POST</li>
                    <li>Verificar se o campo file tem o atributo <code>name</code> correto</li>
                </ul>
            </div>
            
            <div>
                <p class="font-semibold text-yellow-900">2. Se o arquivo é muito grande:</p>
                <ul class="ml-4 list-disc text-yellow-700">
                    <li>Aumentar <code>upload_max_filesize</code> no php.ini</li>
                    <li>Aumentar <code>post_max_size</code> no php.ini</li>
                    <li>Reiniciar o servidor web após mudanças</li>
                </ul>
            </div>
            
            <div>
                <p class="font-semibold text-yellow-900">3. Se há erro de permissão:</p>
                <ul class="ml-4 list-disc text-yellow-700">
                    <li>Executar: <code>chmod -R 777 uploads/</code></li>
                    <li>Verificar o proprietário das pastas</li>
                    <li>Verificar SELinux ou AppArmor se estiver no Linux</li>
                </ul>
            </div>
            
            <div>
                <p class="font-semibold text-yellow-900">4. Comandos úteis para executar no servidor:</p>
                <pre class="mt-2 p-2 bg-gray-100 rounded text-xs">
# Criar pastas necessárias
mkdir -p ../uploads/arte_versoes
mkdir -p ../uploads/pedidos
mkdir -p ../uploads/orcamentos
mkdir -p ../uploads/catalogo

# Dar permissões
chmod -R 777 ../uploads

# Verificar proprietário
ls -la ../uploads/

# No php.ini ou .htaccess:
php_value upload_max_filesize 50M
php_value post_max_size 50M
php_value max_execution_time 300
php_value max_input_time 300
                </pre>
            </div>
        </div>
    </div>
</div>

<?php include '../views/_footer.php'; ?>