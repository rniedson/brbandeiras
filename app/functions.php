<?php
/**
 * BR Bandeiras - Sistema de Gestão Completo
 * Arquivo de funções auxiliares consolidado
 * 
 * Este arquivo contém todas as funções utilitárias do sistema
 * organizadas por categoria para facilitar manutenção
 * 
 * @version 3.0.0
 * @date 2025-01-27
 */

// ============================================================================
// FUNÇÕES DE CONFIGURAÇÃO DO SISTEMA
// ============================================================================

/**
 * Obtém valor de uma configuração do sistema
 * @param string $chave Chave da configuração
 * @param mixed $default Valor padrão se não encontrar
 * @return mixed Valor da configuração ou default
 */
function getConfig($chave, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT valor, tipo FROM configuracoes WHERE chave = ?");
        $stmt->execute([$chave]);
        $config = $stmt->fetch();
        
        if (!$config) {
            return $default;
        }
        
        // Converter conforme o tipo
        switch ($config['tipo']) {
            case 'integer':
                return intval($config['valor']);
            case 'decimal':
            case 'float':
                return floatval($config['valor']);
            case 'boolean':
                return filter_var($config['valor'], FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($config['valor'], true);
            default:
                return $config['valor'];
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar configuração $chave: " . $e->getMessage());
        return $default;
    }
}

/**
 * Define ou atualiza uma configuração do sistema
 * @param string $chave Chave da configuração
 * @param mixed $valor Valor a ser salvo
 * @param string $tipo Tipo da configuração (string, integer, decimal, boolean, json)
 * @param string $descricao Descrição da configuração
 * @return bool Sucesso da operação
 */
function setConfig($chave, $valor, $tipo = 'string', $descricao = null) {
    global $pdo;
    try {
        // Converter valor conforme o tipo
        switch ($tipo) {
            case 'integer':
                $valor = intval($valor);
                break;
            case 'decimal':
            case 'float':
                $valor = floatval($valor);
                break;
            case 'boolean':
                $valor = $valor ? '1' : '0';
                break;
            case 'json':
                $valor = json_encode($valor);
                break;
            default:
                $valor = strval($valor);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO configuracoes (chave, valor, tipo, descricao, updated_at) 
            VALUES (?, ?, ?, ?, NOW())
            ON CONFLICT (chave) 
            DO UPDATE SET valor = EXCLUDED.valor, tipo = EXCLUDED.tipo, descricao = EXCLUDED.descricao, updated_at = NOW()
        ");
        
        return $stmt->execute([$chave, $valor, $tipo, $descricao]);
    } catch (Exception $e) {
        error_log("Erro ao salvar configuração $chave: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o desconto máximo permitido para vendedores
 * @return float Percentual máximo de desconto (padrão: 2%)
 */
function getDescontoMaximoVendedor() {
    return getConfig('desconto_maximo_vendedor', 2.0);
}

/**
 * Verifica se o usuário pode ver valores financeiros
 * @return bool True se pode ver valores
 */
function podeVerValores() {
    return $_SESSION['user_perfil'] === 'gestor' || $_SESSION['user_perfil'] === 'vendedor';
}

// ============================================================================
// CONSTANTES GLOBAIS
// ============================================================================

// Constantes com verificação para evitar redefinição (compatível com PHP 9)
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', '../uploads/');
}
if (!defined('SISTEMA_EMAIL')) {
    define('SISTEMA_EMAIL', 'naoresponda@brbandeiras.com.br');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://brbandeiras.com.br/');
}

// ============================================================================
// FUNÇÕES DE ACESSO AO BANCO DE DADOS
// ============================================================================

/**
 * Obtém instância do Database (substitui uso de global $pdo)
 * 
 * @return Database Instância singleton do Database
 * 
 * @example
 * $pdo = getDb()->getPdo();
 * $stmt = getDb()->query("SELECT * FROM pedidos WHERE id = ?", [$id]);
 */
function getDb(): Database {
    return Database::getInstance();
}

// ============================================================================
// FUNÇÕES DE FORMATAÇÃO
// ============================================================================

/**
 * Formata valor monetário para o padrão brasileiro
 * @param float $valor Valor numérico
 * @return string Valor formatado como R$ 1.234,56
 */
function formatarMoeda($valor) {
    if ($valor === null || $valor === '') return 'R$ 0,00';
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Formata data para padrão brasileiro
 * @param string $data Data no formato banco (Y-m-d ou Y-m-d H:i:s)
 * @param string $formato Formato de saída (padrão d/m/Y)
 * @return string Data formatada
 */
function formatarData($data, $formato = 'd/m/Y') {
    if (!$data || $data === '0000-00-00') return '';
    
    try {
        $dateObj = new DateTime($data);
        return $dateObj->format($formato);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Formata data e hora para padrão brasileiro
 * @param string $dataHora DateTime no formato banco
 * @param string $formato Formato de saída (padrão d/m/Y H:i)
 * @return string Data/hora formatada
 */
function formatarDataHora($dataHora, $formato = 'd/m/Y H:i') {
    if (!$dataHora || $dataHora === '0000-00-00 00:00:00') return '';
    
    try {
        $dateObj = new DateTime($dataHora);
        return $dateObj->format($formato);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Formata CPF ou CNPJ com máscara
 * @param string|null $documento Documento limpo (apenas números)
 * @return string Documento formatado ou vazio
 */
function formatarCpfCnpj($documento) {
    if ($documento === null || $documento === '') {
        return '';
    }
    $documento = preg_replace('/\D/', '', $documento);
    
    if (strlen($documento) == 11) {
        // CPF: 000.000.000-00
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento);
    } elseif (strlen($documento) == 14) {
        // CNPJ: 00.000.000/0000-00
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento);
    }
    
    return $documento;
}

/**
 * Formata telefone com máscara
 * @param string $telefone Telefone limpo (apenas números)
 * @return string Telefone formatado
 */
function formatarTelefone($telefone) {
    $telefone = preg_replace('/\D/', '', $telefone);
    
    if (strlen($telefone) == 11) {
        // Celular: (00) 90000-0000
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
    } elseif (strlen($telefone) == 10) {
        // Fixo: (00) 0000-0000
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    }
    
    return $telefone;
}

/**
 * Formata tamanho de arquivo em bytes para formato legível
 * @param int $bytes Tamanho em bytes
 * @return string Tamanho formatado (KB, MB, GB)
 */
function formatarTamanho($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// ============================================================================
// FUNÇÕES DE VALIDAÇÃO
// ============================================================================

/**
 * Valida CPF brasileiro
 * @param string $cpf CPF com ou sem formatação
 * @return bool True se válido
 */
function validarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    // Calcula os dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    
    return true;
}

/**
 * Valida CNPJ brasileiro
 * @param string $cnpj CNPJ com ou sem formatação
 * @return bool True se válido
 */
function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/\D/', '', $cnpj);
    
    if (strlen($cnpj) != 14) return false;
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) return false;
    
    // Calcula primeiro dígito verificador
    $tamanho = strlen($cnpj) - 2;
    $numeros = substr($cnpj, 0, $tamanho);
    $soma = 0;
    $pos = $tamanho - 7;
    
    for ($i = $tamanho; $i >= 1; $i--) {
        $soma += $numeros[$tamanho - $i] * $pos--;
        if ($pos < 2) $pos = 9;
    }
    
    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $cnpj[12]) return false;
    
    // Calcula segundo dígito verificador
    $tamanho = $tamanho + 1;
    $numeros = substr($cnpj, 0, $tamanho);
    $soma = 0;
    $pos = $tamanho - 7;
    
    for ($i = $tamanho; $i >= 1; $i--) {
        $soma += $numeros[$tamanho - $i] * $pos--;
        if ($pos < 2) $pos = 9;
    }
    
    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $cnpj[13]) return false;
    
    return true;
}

/**
 * Valida CPF ou CNPJ automaticamente
 * @param string $documento Documento com ou sem formatação
 * @return bool True se válido
 */
function validarCPFCNPJ($documento) {
    $documento = preg_replace('/\D/', '', $documento);
    
    if (strlen($documento) == 11) {
        return validarCPF($documento);
    } elseif (strlen($documento) == 14) {
        return validarCNPJ($documento);
    }
    
    return false;
}

/**
 * Valida endereço de e-mail
 * @param string $email E-mail a ser validado
 * @return bool True se válido
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida ID de pedido
 * @param mixed $id ID do pedido (string ou int)
 * @return int|null ID validado como inteiro ou null se inválido
 */
function validarPedidoId($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? $id : null;
}

/**
 * Valida força de senha
 * @param string $senha Senha a ser validada
 * @return array Array com pontos, nível e sugestões
 */
function validarForcaSenha($senha) {
    $pontos = 0;
    $sugestoes = [];
    
    if (strlen($senha) >= 8) {
        $pontos++;
    } else {
        $sugestoes[] = 'Use pelo menos 8 caracteres';
    }
    
    if (strlen($senha) >= 12) $pontos++;
    
    if (preg_match('/[a-z]/', $senha)) {
        $pontos++;
    } else {
        $sugestoes[] = 'Inclua letras minúsculas';
    }
    
    if (preg_match('/[A-Z]/', $senha)) {
        $pontos++;
    } else {
        $sugestoes[] = 'Inclua letras maiúsculas';
    }
    
    if (preg_match('/[0-9]/', $senha)) {
        $pontos++;
    } else {
        $sugestoes[] = 'Inclua números';
    }
    
    if (preg_match('/[^a-zA-Z0-9]/', $senha)) {
        $pontos++;
    } else {
        $sugestoes[] = 'Inclua caracteres especiais';
    }
    
    $niveis = [
        0 => ['nivel' => 'Muito fraca', 'cor' => 'red'],
        1 => ['nivel' => 'Fraca', 'cor' => 'red'],
        2 => ['nivel' => 'Razoável', 'cor' => 'yellow'],
        3 => ['nivel' => 'Boa', 'cor' => 'orange'],
        4 => ['nivel' => 'Forte', 'cor' => 'green'],
        5 => ['nivel' => 'Muito forte', 'cor' => 'green'],
        6 => ['nivel' => 'Excelente', 'cor' => 'green']
    ];
    
    $info_nivel = $niveis[$pontos] ?? $niveis[0];
    
    return [
        'pontos' => $pontos,
        'nivel' => $info_nivel['nivel'],
        'cor' => $info_nivel['cor'],
        'sugestoes' => $sugestoes,
        'porcentagem' => min(100, ($pontos / 6) * 100)
    ];
}

// ============================================================================
// FUNÇÕES DE GERAÇÃO
// ============================================================================

/**
 * Gera código único para pedido
 * @param PDO $pdo Conexão com banco
 * @param string $telefone Telefone do cliente (opcional)
 * @return string Código do pedido no formato YYYYMMDD-XXXX-TTTT
 */
function gerarCodigoPedido($pdo = null, $telefone = '') {
    if (!$pdo && isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    }
    
    $data = date('Ymd');
    
    // Extrair últimos 4 dígitos do telefone
    $telefone_limpo = preg_replace('/\D/', '', $telefone);
    $final_telefone = substr($telefone_limpo, -4);
    
    // Se não tiver 4 dígitos, preencher com zeros
    if (strlen($final_telefone) < 4) {
        $final_telefone = str_pad($final_telefone, 4, '0', STR_PAD_LEFT);
    }
    
    // Buscar último número do dia
    $stmt = $pdo->prepare("
        SELECT numero 
        FROM pedidos 
        WHERE numero LIKE ? 
        ORDER BY numero DESC 
        LIMIT 1
    ");
    $stmt->execute([$data . '-%']);
    $ultimo = $stmt->fetchColumn();
    
    if ($ultimo) {
        // Extrair sequência do número existente
        $partes = explode('-', $ultimo);
        $sequencia = isset($partes[1]) ? intval($partes[1]) + 1 : 1;
    } else {
        $sequencia = 1;
    }
    
    // Formato: YYYYMMDD-XXXX-TTTT
    return $data . '-' . str_pad($sequencia, 4, '0', STR_PAD_LEFT) . '-' . $final_telefone;
}

/**
 * Gera senha aleatória segura
 * @param int $tamanho Tamanho da senha (padrão 12)
 * @param bool $incluir_especiais Incluir caracteres especiais
 * @return string Senha gerada
 */
function gerarSenhaAleatoria($tamanho = 12, $incluir_especiais = true) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    if ($incluir_especiais) {
        $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
    }
    
    $senha = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $chars[random_int(0, $max)];
    }
    
    return $senha;
}

/**
 * Gera token único para autenticação/verificação
 * @param string $prefix Prefixo do token (opcional)
 * @return string Token gerado
 */
function gerarToken($prefix = '') {
    $token = bin2hex(random_bytes(32));
    return $prefix ? $prefix . '_' . $token : $token;
}

// ============================================================================
// FUNÇÕES DE ARQUIVO E UPLOAD
// ============================================================================

/**
 * Realiza upload seguro de arquivo
 * @param array $arquivo Array $_FILES do arquivo
 * @param string $pasta Pasta destino relativa ao UPLOAD_PATH
 * @param array $tipos_permitidos Extensões permitidas
 * @param int $tamanho_max Tamanho máximo em bytes (padrão 5MB)
 * @return array Array com sucesso/erro e detalhes
 */
function uploadArquivo($arquivo, $pasta = '', $tipos_permitidos = null, $tamanho_max = 5242880) {
    // Tipos permitidos padrão
    if ($tipos_permitidos === null) {
        $tipos_permitidos = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'txt', 'zip'];
    }
    
    // Validações iniciais
    if (!isset($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
        return ['sucesso' => false, 'erro' => 'Arquivo não foi enviado corretamente'];
    }
    
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande (limite do servidor)',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande (limite do formulário)',
            UPLOAD_ERR_PARTIAL => 'Upload foi interrompido',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
            UPLOAD_ERR_CANT_WRITE => 'Erro ao escrever arquivo',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        
        $mensagem_erro = $erros[$arquivo['error']] ?? 'Erro desconhecido no upload';
        return ['sucesso' => false, 'erro' => $mensagem_erro];
    }
    
    // Validar tamanho
    if ($arquivo['size'] > $tamanho_max) {
        return ['sucesso' => false, 'erro' => 'Arquivo muito grande. Máximo: ' . formatarTamanho($tamanho_max)];
    }
    
    // Validar tipo
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $tipos_permitidos)) {
        return ['sucesso' => false, 'erro' => 'Tipo de arquivo não permitido: .' . $extensao];
    }
    
    // Validar tipo MIME (segurança extra)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    
    $mimes_permitidos = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'zip' => 'application/zip'
    ];
    
    $mime_esperado = $mimes_permitidos[$extensao] ?? null;
    if ($mime_esperado && $mime !== $mime_esperado) {
        return ['sucesso' => false, 'erro' => 'Tipo MIME inválido para a extensão'];
    }
    
    // Criar pasta se não existir
    $caminho_pasta = UPLOAD_PATH . ($pasta ? rtrim($pasta, '/') . '/' : '');
    if (!is_dir($caminho_pasta)) {
        if (!mkdir($caminho_pasta, 0755, true)) {
            return ['sucesso' => false, 'erro' => 'Erro ao criar pasta de destino'];
        }
    }
    
    // Gerar nome único
    $nome_original = pathinfo($arquivo['name'], PATHINFO_FILENAME);
    $nome_original = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nome_original);
    $nome_unico = $nome_original . '_' . uniqid() . '.' . $extensao;
    
    $caminho_completo = $caminho_pasta . $nome_unico;
    
    // Mover arquivo
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        // Definir permissões do arquivo
        chmod($caminho_completo, 0644);
        
        return [
            'sucesso' => true,
            'arquivo' => $nome_unico,
            'caminho' => $caminho_completo,
            'tamanho' => $arquivo['size'],
            'nome_original' => $arquivo['name'],
            'extensao' => $extensao,
            'mime' => $mime
        ];
    }
    
    return ['sucesso' => false, 'erro' => 'Falha ao mover arquivo para destino'];
}

/**
 * Remove arquivo do servidor de forma segura
 * @param string $caminho_arquivo Caminho completo do arquivo
 * @return bool True se removido com sucesso
 */
function removerArquivo($caminho_arquivo) {
    if (file_exists($caminho_arquivo) && is_file($caminho_arquivo)) {
        return unlink($caminho_arquivo);
    }
    return false;
}

/**
 * Obtém informações de um arquivo
 * @param string $caminho_arquivo Caminho completo do arquivo
 * @return array|false Array com informações ou false se não existir
 */
function obterInfoArquivo($caminho_arquivo) {
    if (!file_exists($caminho_arquivo)) {
        return false;
    }
    
    $info = pathinfo($caminho_arquivo);
    $stat = stat($caminho_arquivo);
    
    return [
        'nome' => $info['basename'],
        'nome_sem_ext' => $info['filename'],
        'extensao' => $info['extension'] ?? '',
        'tamanho' => $stat['size'],
        'tamanho_formatado' => formatarTamanho($stat['size']),
        'data_modificacao' => date('d/m/Y H:i:s', $stat['mtime']),
        'data_acesso' => date('d/m/Y H:i:s', $stat['atime']),
        'eh_arquivo' => is_file($caminho_arquivo),
        'eh_legivel' => is_readable($caminho_arquivo),
        'eh_gravavel' => is_writable($caminho_arquivo)
    ];
}

// ============================================================================
// FUNÇÕES DE COMUNICAÇÃO
// ============================================================================

/**
 * Envia e-mail usando configurações do sistema
 * @param string $para Destinatário
 * @param string $assunto Assunto do e-mail
 * @param string $corpo Corpo do e-mail (HTML ou texto)
 * @param array $anexos Array de caminhos de arquivos para anexar
 * @param string $de Remetente (opcional, usa padrão do sistema)
 * @return bool True se enviado com sucesso
 */
function enviarEmail($para, $assunto, $corpo, $anexos = [], $de = null) {
    // Validar e-mail destinatário
    if (!validarEmail($para)) {
        return false;
    }
    
    $de = $de ?: SISTEMA_EMAIL;
    $boundary = uniqid();
    
    // Headers básicos
    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: multipart/mixed; boundary=\"$boundary\"",
        "From: $de",
        "Reply-To: $de",
        "X-Mailer: BR Bandeiras Sistema"
    ];
    
    // Construir corpo do e-mail
    $mensagem = "--$boundary\r\n";
    $mensagem .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mensagem .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mensagem .= $corpo . "\r\n";
    
    // Adicionar anexos
    foreach ($anexos as $anexo) {
        if (file_exists($anexo)) {
            $nome_arquivo = basename($anexo);
            $conteudo_arquivo = chunk_split(base64_encode(file_get_contents($anexo)));
            
            $mensagem .= "--$boundary\r\n";
            $mensagem .= "Content-Type: application/octet-stream; name=\"$nome_arquivo\"\r\n";
            $mensagem .= "Content-Transfer-Encoding: base64\r\n";
            $mensagem .= "Content-Disposition: attachment; filename=\"$nome_arquivo\"\r\n\r\n";
            $mensagem .= $conteudo_arquivo . "\r\n";
        }
    }
    
    $mensagem .= "--$boundary--";
    
    // Enviar e-mail
    return mail($para, $assunto, $mensagem, implode("\r\n", $headers));
}

/**
 * Gera link do WhatsApp com mensagem pré-definida
 * @param string $telefone Telefone (com ou sem formatação)
 * @param string $mensagem Mensagem a ser enviada
 * @return string URL do WhatsApp
 */
function gerarLinkWhatsApp($telefone, $mensagem = '') {
    // Limpar telefone (apenas números)
    $telefone_limpo = preg_replace('/\D/', '', $telefone);
    
    // Adicionar código do país se não tiver
    if (strlen($telefone_limpo) === 10 || strlen($telefone_limpo) === 11) {
        $telefone_limpo = '55' . $telefone_limpo;
    }
    
    // Codificar mensagem para URL
    $mensagem_codificada = urlencode($mensagem);
    
    // Retornar link do WhatsApp
    return "https://wa.me/{$telefone_limpo}" . ($mensagem ? "?text={$mensagem_codificada}" : '');
}

// ============================================================================
// FUNÇÕES DE SISTEMA E LOG
// ============================================================================

/**
 * Registra ação no log do sistema
 * @param string $acao Ação realizada
 * @param string $detalhes Detalhes da ação
 * @param int $usuario_id ID do usuário (opcional, usa sessão)
 * @return bool True se registrado com sucesso
 */
function registrarLog($acao, $detalhes = '', $usuario_id = null) {
    if (!$usuario_id) {
        $usuario_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$usuario_id) {
        return false; // Não registra log sem usuário
    }
    
    try {
        // Usar sistema de auditoria se disponível
        if (class_exists('App\Core\Auditoria')) {
            \App\Core\Auditoria::registrar($acao, $detalhes, $usuario_id);
            return true;
        }
        
        // Fallback para método antigo
        $db = getDb();
        $stmt = $db->query("
            INSERT INTO logs_sistema (usuario_id, acao, detalhes, ip, created_at) 
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ", [$usuario_id, $acao, $detalhes, $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null]);
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém informações detalhadas do status do pedido
 * @param string $status Status do pedido
 * @return array Array com cor, texto e ícone
 */


/**
 * Obtém classe CSS para cor do status (compatibilidade)
 * @param string $status Status do pedido
 * @return string Classe CSS
 */
function getStatusColor($status) {
    $info = getStatusInfo($status);
    return $info['cor'] ?? 'bg-gray-500';
}

// ============================================================================
// FUNÇÕES DE UTILIDADE
// ============================================================================

/**
 * Remove formatação de documento (CPF/CNPJ)
 * @param string $documento Documento formatado
 * @return string Documento apenas com números
 */
function limparDocumento($documento) {
    return preg_replace('/\D/', '', $documento);
}

/**
 * Converte string para slug (URL amigável)
 * @param string $texto Texto a ser convertido
 * @return string Slug gerado
 */
function gerarSlug($texto) {
    // Converter para minúsculas
    $texto = strtolower($texto);
    
    // Remover acentos
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    
    // Substituir caracteres especiais por hífen
    $texto = preg_replace('/[^a-z0-9\-]/', '-', $texto);
    
    // Remover hífens duplicados
    $texto = preg_replace('/-+/', '-', $texto);
    
    // Remover hífens do início e fim
    return trim($texto, '-');
}

/**
 * Sanitiza string para uso seguro
 * @param string $texto Texto a ser sanitizado
 * @return string Texto sanitizado
 */
function sanitizarTexto($texto) {
    // Remove tags HTML
    $texto = strip_tags($texto);
    
    // Remove caracteres especiais perigosos
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    
    return trim($texto);
}

/**
 * Calcula diferença entre datas em dias
 * @param string $data_inicial Data inicial
 * @param string $data_final Data final (opcional, usa hoje)
 * @return int Diferença em dias
 */
function calcularDiferenca($data_inicial, $data_final = null) {
    try {
        $dt1 = new DateTime($data_inicial);
        $dt2 = $data_final ? new DateTime($data_final) : new DateTime();
        
        $diff = $dt1->diff($dt2);
        return $diff->invert ? -$diff->days : $diff->days;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Verifica se data está vencida
 * @param string $data Data a ser verificada
 * @return bool True se vencida
 */
function dataVencida($data) {
    try {
        $dt = new DateTime($data);
        $hoje = new DateTime();
        return $dt < $hoje;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Gera array de opções para select de anos
 * @param int $ano_inicial Ano inicial (opcional, padrão ano atual - 10)
 * @param int $ano_final Ano final (opcional, padrão ano atual + 5)
 * @return array Array de anos
 */
function gerarOpcoesAnos($ano_inicial = null, $ano_final = null) {
    $ano_atual = (int)date('Y');
    $ano_inicial = $ano_inicial ?: ($ano_atual - 10);
    $ano_final = $ano_final ?: ($ano_atual + 5);
    
    $anos = [];
    for ($ano = $ano_inicial; $ano <= $ano_final; $ano++) {
        $anos[$ano] = $ano;
    }
    
    return $anos;
}

/**
 * Gera array de opções para select de meses
 * @return array Array de meses
 */
function gerarOpcoesMeses() {
    return [
        1 => 'Janeiro',
        2 => 'Fevereiro', 
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
}

/**
 * Redireciona para uma URL com validação
 * @param string $url URL para redirecionar (relativa ou absoluta)
 * @param int $statusCode Código HTTP de status (padrão 302)
 */
function redirect($url, $statusCode = 302) {
    // Se for URL absoluta (http/https), usar diretamente
    if (strpos($url, 'http') === 0) {
        header("Location: {$url}", true, $statusCode);
        exit;
    }
    
    // Se começar com '/', é caminho absoluto do servidor
    if (strpos($url, '/') === 0) {
        header("Location: {$url}", true, $statusCode);
        exit;
    }
    
    // Caso contrário, é caminho relativo - usar diretamente
    // O navegador resolve caminhos relativos corretamente
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * Obtém informações de status do pedido
 * @param string|null $status Status específico ou null para retornar todos
 * @return array|array[] Array de informações de status ou informações de um status específico
 */
function getStatusInfo($status = null) {
    static $status_info = [
        'orcamento' => ['cor' => 'bg-gray-600', 'texto' => 'Orçamento', 'icone' => 'clipboard-list'],
        'arte' => ['cor' => 'bg-lime-600', 'texto' => 'Arte', 'icone' => 'paint-brush'],
        'aprovado' => ['cor' => 'bg-blue-600', 'texto' => 'Aprovado', 'icone' => 'check-circle'],
        'pagamento_50' => ['cor' => 'bg-yellow-600', 'texto' => 'Entrada 50%', 'icone' => 'currency-dollar'],
        'producao' => ['cor' => 'bg-orange-600', 'texto' => 'Em Produção', 'icone' => 'cog'],
        'pagamento_100' => ['cor' => 'bg-yellow-700', 'texto' => 'Pagamento Final', 'icone' => 'credit-card'],
        'pronto' => ['cor' => 'bg-green-600', 'texto' => 'Pronto', 'icone' => 'package'],
        'entregue' => ['cor' => 'bg-green-800', 'texto' => 'Entregue', 'icone' => 'truck'],
        'cancelado' => ['cor' => 'bg-red-600', 'texto' => 'Cancelado', 'icone' => 'x-circle']
    ];
    
    if ($status === null) {
        return $status_info;
    }
    
    return $status_info[$status] ?? ['cor' => 'bg-gray-500', 'texto' => 'Desconhecido', 'icone' => 'question-mark-circle'];
}

/**
 * Processa observações HTML permitindo apenas tags seguras
 * @param string $observacoes Texto com observações (pode conter HTML)
 * @return string Texto processado com HTML seguro
 */
function processarObservacoesHTML($observacoes) {
    if (empty($observacoes)) {
        return '';
    }
    
    // Se o HTML foi escapado, decodificar primeiro
    if (strpos($observacoes, '&lt;') !== false || strpos($observacoes, '&gt;') !== false) {
        $observacoes = html_entity_decode($observacoes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Permitir apenas tags HTML seguras
    $observacoes = strip_tags($observacoes, '<b><strong><i><em><u><br><br/><p><ul><ol><li>');
    
    // Converter quebras de linha em <br> se ainda não houver tags <br>
    if (strpos($observacoes, '<br') === false) {
        $observacoes = nl2br($observacoes);
    }
    
    return $observacoes;
}

/**
 * Separa arquivos por tipo (imagens e outros)
 * @param array $arquivos Array de arquivos com campo 'nome_arquivo'
 * @return array Array com chaves 'imagens' e 'outros'
 */
function separarArquivosPorTipo($arquivos) {
    $arquivos_imagem = [];
    $arquivos_audio = [];
    $arquivos_outros = [];
    $extensoes_imagem = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    $extensoes_audio = ['mp3', 'ogg', 'opus', 'm4a', 'wav', 'aac', 'amr', 'webm'];
    
    foreach ($arquivos as $arquivo) {
        $extensao = strtolower(pathinfo($arquivo['nome_arquivo'] ?? '', PATHINFO_EXTENSION));
        if (in_array($extensao, $extensoes_imagem)) {
            $arquivos_imagem[] = $arquivo;
        } elseif (in_array($extensao, $extensoes_audio)) {
            $arquivos_audio[] = $arquivo;
        } else {
            $arquivos_outros[] = $arquivo;
        }
    }
    
    return ['imagens' => $arquivos_imagem, 'audios' => $arquivos_audio, 'outros' => $arquivos_outros];
}

// ============================================================================
// FUNÇÕES DE BANCO DE DADOS
// ============================================================================

/**
 * Verifica se uma tabela existe no banco
 * @param string $tabela Nome da tabela
 * @return bool True se existe
 */
function verificarTabelaExiste($tabela) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.tables 
            WHERE table_schema = 'public' AND table_name = ?
            LIMIT 1
        ");
        $stmt->execute([$tabela]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function verificarColunaExiste($tabela, $coluna) {
    // Cache estático para evitar múltiplas consultas ao information_schema
    static $colunaCache = [];
    $cacheKey = "{$tabela}.{$coluna}";
    
    if (isset($colunaCache[$cacheKey])) {
        return $colunaCache[$cacheKey];
    }
    
    try {
        $db = getDb();
        $stmt = $db->query("
            SELECT 1 FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = ? 
            AND column_name = ?
            LIMIT 1
        ", [$tabela, $coluna]);
        $resultado = (bool)$stmt->fetchColumn();
        
        // Armazenar no cache
        $colunaCache[$cacheKey] = $resultado;
        
        return $resultado;
    } catch (Exception $e) {
        return false;
    }
}

function getDatabaseSchema($tabela) {
    try {
        $db = getDb();
        $stmt = $db->query("
            SELECT 
                column_name, 
                data_type, 
                is_nullable, 
                column_default,
                '' as column_key
            FROM information_schema.columns 
            WHERE table_schema = 'public'
            AND table_name = ?
            ORDER BY ordinal_position
        ", [$tabela]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// ============================================================================
// FUNÇÕES DE DEBUG E DESENVOLVIMENTO
// ============================================================================

/**
 * Debug de variável com formatação HTML
 * @param mixed $var Variável a ser debugada
 * @param string $label Label para identificar
 * @param bool $die Se deve parar execução
 */
function debug($var, $label = 'Debug', $die = false) {
    if (defined('DEBUG') && DEBUG) {
        echo "<div style='background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin: 10px; font-family: monospace;'>";
        echo "<strong>$label:</strong><br>";
        echo "<pre>";
        print_r($var);
        echo "</pre>";
        echo "</div>";
        
        if ($die) {
            die();
        }
    }
}

/**
 * Log personalizado para desenvolvimento
 * @param mixed $data Dados para log
 * @param string $arquivo Nome do arquivo de log (opcional)
 */
function debugLog($data, $arquivo = 'debug.log') {
    if (defined('DEBUG') && DEBUG) {
        $timestamp = date('Y-m-d H:i:s');
        $log_data = "[$timestamp] " . print_r($data, true) . "\n";
        file_put_contents($arquivo, $log_data, FILE_APPEND | LOCK_EX);
    }
}

// ============================================================================
// FUNÇÕES DE CACHE
// ============================================================================

/**
 * Executa uma query com cache usando APCu (se disponível)
 * @param PDO $pdo Conexão PDO
 * @param string $cache_key Chave única para o cache
 * @param string $sql Query SQL a executar
 * @param array $params Parâmetros para a query (opcional)
 * @param int $ttl Tempo de vida do cache em segundos (padrão: 5 minutos)
 * @return array Resultado da query
 */
function getCachedQuery($pdo, $cache_key, $sql, $params = [], $ttl = 300) {
    // Tentar buscar do cache se APCu estiver disponível
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    // Executar query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Armazenar no cache se APCu estiver disponível
    if (function_exists('apcu_store')) {
        apcu_store($cache_key, $result, $ttl);
    }
    
    return $result;
}

/**
 * Limpa um item específico do cache
 * @param string $cache_key Chave do cache a limpar
 * @return bool Sucesso da operação
 */
function clearCache($cache_key) {
    if (function_exists('apcu_delete')) {
        return apcu_delete($cache_key);
    }
    return false;
}

/**
 * Limpa todo o cache (use com cuidado!)
 * @return bool Sucesso da operação
 */
function clearAllCache() {
    if (function_exists('apcu_clear_cache')) {
        return apcu_clear_cache();
    }
    return false;
}

// ============================================================================
// AUTO-LOAD DE VARIÁVEIS GLOBAIS (se necessário)
// ============================================================================

// Disponibilizar conexão PDO globalmente se necessário
if (isset($pdo) && !isset($GLOBALS['pdo'])) {
    $GLOBALS['pdo'] = $pdo;
}
?>