<?php
// clientes_processar_lote.php
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

// Verificar autenticação
requireLogin();
requireRole(['gestor']);

// Configurar resposta JSON
header('Content-Type: application/json');

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['clientes'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Dados inválidos'
    ]);
    exit;
}

$clientes = $input['clientes'];
$opcoes = $input['opcoes'] ?? [];

$resultado = [
    'success' => true,
    'inseridos' => 0,
    'atualizados' => 0,
    'erros' => 0,
    'logs' => []
];

try {
    // Iniciar transação se não for modo teste
    if (!($opcoes['modoTeste'] ?? false)) {
        $pdo->beginTransaction();
    }
    
    foreach ($clientes as $cliente) {
        try {
            // Preparar dados
            $cpf_cnpj = $cliente['cpf_cnpj'] ?? '';
            $tipo_pessoa = $cliente['tipo_pessoa'] ?? 'J';
            $codigo_sistema = $cliente['codigo_sistema'] ?? '';
            
            // Verificar se cliente existe
            $existe = false;
            $cliente_id = null;
            
            if (!empty($codigo_sistema)) {
                $stmt = $pdo->prepare("SELECT id FROM clientes WHERE codigo_sistema = ?");
                $stmt->execute([$codigo_sistema]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $existe = true;
                    $cliente_id = $row['id'];
                }
            }
            
            if (!$existe && !empty($cpf_cnpj)) {
                $stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf_cnpj = ?");
                $stmt->execute([$cpf_cnpj]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $existe = true;
                    $cliente_id = $row['id'];
                }
            }
            
            // Preparar dados para insert/update
            $dados_db = [
                'nome' => $cliente['nome'] ?? '',
                'tipo_pessoa' => $tipo_pessoa,
                'cpf_cnpj' => $cpf_cnpj ?: null,
                'telefone' => $cliente['telefone'] ?: null,
                'email' => filter_var($cliente['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                'endereco' => $cliente['endereco'] ?: null,
                'numero' => $cliente['numero'] ?: null,
                'complemento' => $cliente['complemento'] ?: null,
                'bairro' => $cliente['bairro'] ?: null,
                'cidade' => $cliente['cidade'] ?: null,
                'estado' => strtoupper($cliente['estado'] ?? '') ?: null,
                'cep' => preg_replace('/\D/', '', $cliente['cep'] ?? '') ?: null
            ];
            
            // Adicionar campos extras se existirem na tabela
            $campos_extras = [
                'codigo_sistema' => $codigo_sistema ?: null,
                'nome_fantasia' => $cliente['nome_fantasia'] ?: null,
                'tipo_lista_precos' => $cliente['tipo_lista_precos'] ?: 'Padrão',
                'sexo' => $cliente['sexo'] ?: null,
                'rg' => $cliente['rg'] ?: null,
                'rg_expedicao' => $cliente['rg_expedicao'] ?: null,
                'rg_uf' => $cliente['rg_uf'] ?: null,
                'indicador_ie' => $cliente['indicador_ie'] ?: null,
                'inscricao_estadual' => $cliente['inscricao_estadual'] ?: null,
                'celular' => $cliente['celular'] ?: null,
                'fax' => $cliente['fax'] ?: null,
                'site' => $cliente['site'] ?: null,
                'data_nascimento' => null
            ];
            
            // Converter data de nascimento se fornecida
            if (!empty($cliente['data_nascimento'])) {
                $data = DateTime::createFromFormat('d/m/Y', $cliente['data_nascimento']);
                if ($data) {
                    $campos_extras['data_nascimento'] = $data->format('Y-m-d');
                }
            }
            
            // Verificar quais campos extras existem na tabela
            static $colunas_tabela = null;
            if ($colunas_tabela === null) {
                $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'clientes'");
                $colunas_tabela = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Adicionar apenas campos que existem
            foreach ($campos_extras as $campo => $valor) {
                if (in_array($campo, $colunas_tabela)) {
                    $dados_db[$campo] = $valor;
                }
            }
            
            if (!($opcoes['modoTeste'] ?? false)) {
                if ($existe && ($opcoes['atualizarExistentes'] ?? false)) {
                    // UPDATE
                    $set_clause = [];
                    $params = [];
                    
                    foreach ($dados_db as $campo => $valor) {
                        if ($campo !== 'nome' || !empty($valor)) { // Nome é obrigatório
                            $set_clause[] = "$campo = :$campo";
                            $params[":$campo"] = $valor;
                        }
                    }
                    
                    if (in_array('updated_at', $colunas_tabela)) {
                        $set_clause[] = "updated_at = CURRENT_TIMESTAMP";
                    }
                    
                    if (in_array('origem_dados', $colunas_tabela)) {
                        $set_clause[] = "origem_dados = 'importacao'";
                    }
                    
                    if (in_array('data_ultima_importacao', $colunas_tabela)) {
                        $set_clause[] = "data_ultima_importacao = CURRENT_TIMESTAMP";
                    }
                    
                    $sql = "UPDATE clientes SET " . implode(', ', $set_clause) . " WHERE id = :id";
                    $params[':id'] = $cliente_id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $resultado['atualizados']++;
                    $resultado['logs'][] = [
                        'type' => 'sucesso',
                        'message' => "Cliente '{$cliente['nome']}' atualizado"
                    ];
                    
                } elseif (!$existe) {
                    // INSERT
                    $dados_db['ativo'] = true;
                    
                    if (in_array('origem_dados', $colunas_tabela)) {
                        $dados_db['origem_dados'] = 'importacao';
                    }
                    
                    if (in_array('data_ultima_importacao', $colunas_tabela)) {
                        $dados_db['data_ultima_importacao'] = date('Y-m-d H:i:s');
                    }
                    
                    $campos = array_keys($dados_db);
                    $valores = array_map(function($campo) { return ":$campo"; }, $campos);
                    
                    $sql = "INSERT INTO clientes (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $valores) . ")";
                    
                    $stmt = $pdo->prepare($sql);
                    $params = [];
                    foreach ($dados_db as $campo => $valor) {
                        $params[":$campo"] = $valor;
                    }
                    $stmt->execute($params);
                    
                    $resultado['inseridos']++;
                    $resultado['logs'][] = [
                        'type' => 'sucesso',
                        'message' => "Cliente '{$cliente['nome']}' inserido"
                    ];
                }
            } else {
                // Modo teste - apenas simular
                if ($existe) {
                    $resultado['atualizados']++;
                    $resultado['logs'][] = [
                        'type' => 'info',
                        'message' => "[TESTE] Cliente '{$cliente['nome']}' seria atualizado"
                    ];
                } else {
                    $resultado['inseridos']++;
                    $resultado['logs'][] = [
                        'type' => 'info',
                        'message' => "[TESTE] Cliente '{$cliente['nome']}' seria inserido"
                    ];
                }
            }
            
        } catch (Exception $e) {
            $resultado['erros']++;
            $resultado['logs'][] = [
                'type' => 'erro',
                'message' => "Erro em '{$cliente['nome']}': " . $e->getMessage()
            ];
            
            if (!($opcoes['ignorarErros'] ?? false)) {
                throw $e;
            }
        }
    }
    
    // Commit da transação se não for modo teste
    if (!($opcoes['modoTeste'] ?? false) && $pdo->inTransaction()) {
        $pdo->commit();
    }
    
    // Registrar no log do sistema se não for modo teste
    if (!($opcoes['modoTeste'] ?? false) && function_exists('registrarLog')) {
        registrarLog('importacao_clientes_lote', 
            "Processou lote: {$resultado['inseridos']} inseridos, {$resultado['atualizados']} atualizados");
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $resultado['success'] = false;
    $resultado['message'] = $e->getMessage();
    $resultado['logs'][] = [
        'type' => 'erro',
        'message' => 'Erro geral: ' . $e->getMessage()
    ];
}

echo json_encode($resultado);