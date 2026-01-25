<?php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

// Processar formulário
$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = trim($_POST['nome'] ?? '');
        $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
        $cnpj = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
        $inscricao_estadual = trim($_POST['inscricao_estadual'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $celular = trim($_POST['celular'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $site = trim($_POST['site'] ?? '');
        $cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');
        
        // Validações básicas
        if (empty($nome)) {
            throw new Exception('Nome/Razão Social é obrigatório');
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido');
        }
        
        // Verificar se existe registro na tabela empresa
        $stmt_check = $pdo->query("SELECT COUNT(*) FROM empresa");
        $existe = $stmt_check->fetchColumn() > 0;
        
        if ($existe) {
            // Atualizar
            $sql = "
                UPDATE empresa SET
                    nome = ?,
                    nome_fantasia = ?,
                    cnpj = ?,
                    inscricao_estadual = ?,
                    telefone = ?,
                    celular = ?,
                    email = ?,
                    site = ?,
                    cep = ?,
                    endereco = ?,
                    numero = ?,
                    complemento = ?,
                    bairro = ?,
                    cidade = ?,
                    estado = ?,
                    observacoes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = 1
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nome, $nome_fantasia, $cnpj, $inscricao_estadual,
                $telefone, $celular, $email, $site,
                $cep, $endereco, $numero, $complemento,
                $bairro, $cidade, $estado, $observacoes
            ]);
            
            $mensagem = 'Dados da empresa atualizados com sucesso!';
        } else {
            // Inserir
            $sql = "
                INSERT INTO empresa (
                    nome, nome_fantasia, cnpj, inscricao_estadual,
                    telefone, celular, email, site,
                    cep, endereco, numero, complemento,
                    bairro, cidade, estado, observacoes,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nome, $nome_fantasia, $cnpj, $inscricao_estadual,
                $telefone, $celular, $email, $site,
                $cep, $endereco, $numero, $complemento,
                $bairro, $cidade, $estado, $observacoes
            ]);
            
            $mensagem = 'Dados da empresa cadastrados com sucesso!';
        }
        
    } catch (PDOException $e) {
        // Se a tabela não existir, mostrar aviso
        if (strpos($e->getMessage(), 'does not exist') !== false) {
            $erro = 'A tabela "empresa" não existe no banco de dados. É necessário criar a tabela primeiro.';
        } else {
            error_log("Erro ao salvar dados da empresa: " . $e->getMessage());
            $erro = 'Erro ao salvar dados: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Buscar dados da empresa
$empresa = null;
try {
    $stmt = $pdo->query("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela não existir, usar valores padrão das constantes
    if (strpos($e->getMessage(), 'does not exist') !== false) {
        $empresa = [
            'nome' => NOME_EMPRESA ?? 'BR Bandeiras',
            'nome_fantasia' => '',
            'cnpj' => CNPJ_EMPRESA ?? '',
            'inscricao_estadual' => '',
            'telefone' => TELEFONE_EMPRESA ?? '',
            'celular' => '',
            'email' => EMAIL_EMPRESA ?? '',
            'site' => '',
            'cep' => '',
            'endereco' => ENDERECO_EMPRESA ?? '',
            'numero' => '',
            'complemento' => '',
            'bairro' => '',
            'cidade' => '',
            'estado' => '',
            'observacoes' => ''
        ];
    } else {
        error_log("Erro ao buscar dados da empresa: " . $e->getMessage());
    }
}

// Se não encontrou dados, usar valores padrão
if (!$empresa) {
    $empresa = [
        'nome' => NOME_EMPRESA ?? 'BR Bandeiras',
        'nome_fantasia' => '',
        'cnpj' => CNPJ_EMPRESA ?? '',
        'inscricao_estadual' => '',
        'telefone' => TELEFONE_EMPRESA ?? '',
        'celular' => '',
        'email' => EMAIL_EMPRESA ?? '',
        'site' => '',
        'cep' => '',
        'endereco' => ENDERECO_EMPRESA ?? '',
        'numero' => '',
        'complemento' => '',
        'bairro' => '',
        'cidade' => '',
        'estado' => '',
        'observacoes' => ''
    ];
}

$titulo = 'Dados da Empresa';
$breadcrumb = [
    ['label' => 'Configurações', 'url' => '#'],
    ['label' => 'Empresa', 'url' => '#'],
    ['label' => 'Dados da Empresa']
];
include '../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Dados da Empresa</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Gerencie as informações da empresa</p>
    </div>
    
    <?php if ($mensagem): ?>
    <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="text-green-800 dark:text-green-200"><?= htmlspecialchars($mensagem) ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span class="text-red-800 dark:text-red-200"><?= htmlspecialchars($erro) ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" 
          x-data="{ 
              buscandoCep: false,
              formatarTelefone(e) {
                  let value = e.target.value.replace(/\D/g, '');
                  if (value.length <= 11) {
                      value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                  }
                  e.target.value = value;
              },
              formatarCNPJ(e) {
                  let value = e.target.value.replace(/\D/g, '');
                  value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
                  e.target.value = value;
              },
              formatarCEP(e) {
                  let value = e.target.value.replace(/\D/g, '');
                  value = value.replace(/^(\d{5})(\d{3}).*/, '$1-$2');
                  e.target.value = value;
              },
              async buscarCep() {
                  const cepInput = document.getElementById('cep');
                  const cep = cepInput.value.replace(/\D/g, '');
                  if (cep.length !== 8) return;
                  
                  this.buscandoCep = true;
                  
                  try {
                      const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                      const data = await response.json();
                      
                      if (!data.erro) {
                          document.getElementById('endereco').value = data.logradouro || '';
                          document.getElementById('bairro').value = data.bairro || '';
                          document.getElementById('cidade').value = data.localidade || '';
                          document.getElementById('estado').value = data.uf || '';
                      } else {
                          alert('CEP não encontrado');
                      }
                  } catch (error) {
                      alert('Erro ao buscar CEP');
                  }
                  
                  this.buscandoCep = false;
              }
          }">
        
        <!-- Dados Básicos -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Dados Básicos</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Razão Social <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($empresa['nome'] ?? '') ?>" required
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Nome Fantasia
                    </label>
                    <input type="text" name="nome_fantasia" value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        CNPJ
                    </label>
                    <input type="text" name="cnpj" id="cnpj" value="<?= htmlspecialchars($empresa['cnpj'] ?? '') ?>"
                           @input="formatarCNPJ($event)"
                           maxlength="18"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Inscrição Estadual
                    </label>
                    <input type="text" name="inscricao_estadual" value="<?= htmlspecialchars($empresa['inscricao_estadual'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
            </div>
        </div>
        
        <!-- Contato -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Contato</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Telefone
                    </label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>"
                           @input="formatarTelefone($event)"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Celular
                    </label>
                    <input type="text" name="celular" value="<?= htmlspecialchars($empresa['celular'] ?? '') ?>"
                           @input="formatarTelefone($event)"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Email
                    </label>
                    <input type="email" name="email" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Site
                    </label>
                    <input type="url" name="site" value="<?= htmlspecialchars($empresa['site'] ?? '') ?>"
                           placeholder="https://"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
            </div>
        </div>
        
        <!-- Endereço -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Endereço</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        CEP
                    </label>
                    <div class="flex gap-2">
                        <input type="text" name="cep" id="cep" value="<?= htmlspecialchars($empresa['cep'] ?? '') ?>"
                               @input="formatarCEP($event)"
                               @blur="buscarCep()"
                               maxlength="9"
                               class="flex-1 px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                        <button type="button" @click="buscarCep()" 
                                :disabled="buscandoCep"
                                class="px-4 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600 disabled:opacity-50">
                            <span x-show="!buscandoCep">Buscar</span>
                            <span x-show="buscandoCep">...</span>
                        </button>
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Endereço
                    </label>
                    <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($empresa['endereco'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Número
                    </label>
                    <input type="text" name="numero" value="<?= htmlspecialchars($empresa['numero'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Complemento
                    </label>
                    <input type="text" name="complemento" value="<?= htmlspecialchars($empresa['complemento'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Bairro
                    </label>
                    <input type="text" name="bairro" id="bairro" value="<?= htmlspecialchars($empresa['bairro'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Cidade
                    </label>
                    <input type="text" name="cidade" id="cidade" value="<?= htmlspecialchars($empresa['cidade'] ?? '') ?>"
                           class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Estado
                    </label>
                    <select name="estado" id="estado"
                            class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500">
                        <option value="">Selecione</option>
                        <?php
                        $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                        foreach ($estados as $uf):
                            $selected = ($empresa['estado'] ?? '') === $uf ? 'selected' : '';
                        ?>
                        <option value="<?= $uf ?>" <?= $selected ?>><?= $uf ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Observações -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Observações</h2>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Observações Gerais
                </label>
                <textarea name="observacoes" rows="4"
                          class="w-full px-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:border-green-500"><?= htmlspecialchars($empresa['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
        
        <!-- Botões -->
        <div class="flex justify-end gap-4">
            <a href="dashboard/dashboard.php" 
               class="px-6 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Salvar Dados
            </button>
        </div>
    </form>
</div>

<?php include '../views/layouts/_footer.php'; ?>
