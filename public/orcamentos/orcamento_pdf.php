<?php
// Desabilitar exibição de erros para não corromper o PDF
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log de erros em arquivo
ini_set('log_errors', 1);
ini_set('error_log', '../logs/pdf_errors.log');

require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

// Verificar se as constantes estão definidas
if (!defined('NOME_EMPRESA')) {
    define('NOME_EMPRESA', 'BR Bandeiras');
    define('CNPJ_EMPRESA', '00.000.000/0001-00');
    define('ENDERECO_EMPRESA', 'Rua Exemplo, 123 - Centro - Cidade/UF');
    define('TELEFONE_EMPRESA', '(62) 0000-0000');
    define('EMAIL_EMPRESA', 'contato@brbandeiras.com.br');
}

try {
    // Permitir acesso por token ou login
    $id = $_GET['id'] ?? null;
    $token = $_GET['token'] ?? null;
    
    if (!$id) {
        throw new Exception('Orçamento não informado');
    }
    
    // Se não tem token, requer login
    if (!$token) {
        requireLogin();
    }
    
    // Buscar orçamento
    $stmt = $pdo->prepare("
        SELECT p.*, 
               c.nome as cliente_nome, 
               c.telefone as cliente_telefone,
               c.email as cliente_email,
               c.cpf_cnpj as cliente_cpf_cnpj,
               c.endereco as cliente_endereco,
               u.nome as vendedor_nome,
               u.email as vendedor_email,
               u.telefone as vendedor_telefone
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.vendedor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
    
    // Validar token se fornecido
    if ($token && $token !== md5($id . $orcamento['created_at'])) {
        throw new Exception('Token inválido');
    }
    
    // Se tem login, verificar permissão
    if (!$token && isset($_SESSION['user_perfil'])) {
        if ($_SESSION['user_perfil'] === 'vendedor' && $orcamento['vendedor_id'] != $_SESSION['user_id']) {
            throw new Exception('Sem permissão para visualizar este orçamento');
        }
    }
    
    // Buscar itens
    $stmt = $pdo->prepare("
        SELECT * FROM pedido_itens 
        WHERE pedido_id = ? 
        ORDER BY id
    ");
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Função auxiliar para formatar CPF/CNPJ se não existir
    if (!function_exists('formatarCpfCnpj')) {
        function formatarCpfCnpj($documento) {
            $documento = preg_replace('/\D/', '', $documento);
            
            if (strlen($documento) == 11) {
                return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento);
            } elseif (strlen($documento) == 14) {
                return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento);
            }
            
            return $documento;
        }
    }
    
    // Começar output buffering para capturar o HTML
    ob_start();
    
} catch (Exception $e) {
    // Em caso de erro, mostrar uma página de erro simples
    die("
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Erro</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 50px; text-align: center; }
                .error { color: red; margin: 20px 0; }
            </style>
        </head>
        <body>
            <h1>Erro ao gerar PDF</h1>
            <p class='error'>{$e->getMessage()}</p>
            <p><a href='orcamentos.php'>Voltar</a></p>
        </body>
        </html>
    ");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Orçamento #<?= htmlspecialchars($orcamento['numero']) ?></title>
    <style>
        @page {
            margin: 20mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Cabeçalho */
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #22c55e;
        }
        
        .header-content {
            display: table;
            width: 100%;
        }
        
        .logo {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        
        .logo h1 {
            color: #22c55e;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .empresa-info {
            font-size: 11px;
            color: #666;
            line-height: 1.4;
        }
        
        .orcamento-info {
            display: table-cell;
            width: 40%;
            text-align: right;
            vertical-align: top;
        }
        
        .orcamento-numero {
            font-size: 18px;
            font-weight: bold;
            color: #22c55e;
            margin-bottom: 5px;
        }
        
        /* Dados do Cliente */
        .cliente-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .cliente-titulo {
            font-weight: bold;
            margin-bottom: 10px;
            color: #22c55e;
        }
        
        /* Tabela de Itens */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background: #22c55e;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: normal;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Totais */
        .totais {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .total-linha {
            margin-bottom: 5px;
            overflow: hidden;
        }
        
        .total-label {
            float: left;
            width: 70%;
            text-align: right;
            padding-right: 20px;
        }
        
        .total-valor {
            float: left;
            width: 30%;
            text-align: right;
        }
        
        .total-linha.total {
            font-size: 16px;
            font-weight: bold;
            color: #22c55e;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
        }
        
        /* Informações Adicionais */
        .info-adicional {
            margin-bottom: 20px;
        }
        
        .info-adicional h3 {
            color: #22c55e;
            margin-bottom: 10px;
        }
        
        .condicoes {
            margin-top: 20px;
            padding: 10px;
            background: #fffbeb;
            border: 1px solid #fbbf24;
            border-radius: 5px;
        }
        
        .condicoes ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        /* Rodapé */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 11px;
            color: #666;
        }
        
        .assinatura {
            margin-top: 50px;
            overflow: hidden;
        }
        
        .assinatura-campo {
            float: left;
            width: 45%;
            text-align: center;
        }
        
        .assinatura-campo:last-child {
            float: right;
        }
        
        .assinatura-linha {
            border-top: 1px solid #333;
            margin-bottom: 5px;
            width: 200px;
            margin: 0 auto 5px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .container {
                width: 100%;
                max-width: none;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Cabeçalho -->
        <div class="header">
            <div class="header-content">
                <div class="logo">
                    <h1><?= NOME_EMPRESA ?></h1>
                    <div class="empresa-info">
                        <?= ENDERECO_EMPRESA ?><br>
                        Telefone: <?= TELEFONE_EMPRESA ?><br>
                        E-mail: <?= EMAIL_EMPRESA ?><br>
                        CNPJ: <?= CNPJ_EMPRESA ?>
                    </div>
                </div>
                
                <div class="orcamento-info">
                    <div class="orcamento-numero">ORÇAMENTO #<?= htmlspecialchars($orcamento['numero']) ?></div>
                    <div>Data: <?= formatarData($orcamento['created_at']) ?></div>
                    <div>Validade: 7 dias</div>
                    <?php if ($orcamento['urgente']): ?>
                    <div style="color: red; font-weight: bold;">URGENTE</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Dados do Cliente -->
        <div class="cliente-box">
            <div class="cliente-titulo">DADOS DO CLIENTE</div>
            <div>
                <strong><?= htmlspecialchars($orcamento['cliente_nome'] ?? 'Não informado') ?></strong><br>
                <?php if (!empty($orcamento['cliente_cpf_cnpj'])): ?>
                CPF/CNPJ: <?= formatarCpfCnpj($orcamento['cliente_cpf_cnpj']) ?><br>
                <?php endif; ?>
                Telefone: <?= htmlspecialchars($orcamento['cliente_telefone'] ?? 'Não informado') ?><br>
                <?php if (!empty($orcamento['cliente_email'])): ?>
                E-mail: <?= htmlspecialchars($orcamento['cliente_email']) ?><br>
                <?php endif; ?>
                <?php if (!empty($orcamento['cliente_endereco'])): ?>
                Endereço: <?= htmlspecialchars($orcamento['cliente_endereco']) ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabela de Itens -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%">Descrição</th>
                    <th style="width: 15%" class="text-center">Quantidade</th>
                    <th style="width: 17.5%" class="text-right">Valor Unit.</th>
                    <th style="width: 17.5%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['descricao']) ?></td>
                    <td class="text-center"><?= number_format($item['quantidade'], 0, ',', '.') ?></td>
                    <td class="text-right"><?= formatarMoeda($item['valor_unitario']) ?></td>
                    <td class="text-right"><?= formatarMoeda($item['valor_total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Totais -->
        <div class="totais">
            <div class="total-linha">
                <span class="total-label">Subtotal:</span>
                <span class="total-valor"><?= formatarMoeda($orcamento['valor_total']) ?></span>
            </div>
            
            <?php if ($orcamento['desconto'] > 0): ?>
            <div class="total-linha">
                <span class="total-label">Desconto:</span>
                <span class="total-valor">- <?= formatarMoeda($orcamento['desconto']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-linha total">
                <span class="total-label">TOTAL:</span>
                <span class="total-valor"><?= formatarMoeda($orcamento['valor_final']) ?></span>
            </div>
        </div>
        
        <!-- Informações Adicionais -->
        <div class="info-adicional">
            <h3>INFORMAÇÕES IMPORTANTES</h3>
            <p><strong>Prazo de Entrega:</strong> <?= formatarData($orcamento['prazo_entrega']) ?></p>
            
            <?php if (!empty($orcamento['observacoes'])): ?>
            <p style="margin-top: 10px;">
                <strong>Observações:</strong><br>
                <?= nl2br(htmlspecialchars($orcamento['observacoes'])) ?>
            </p>
            <?php endif; ?>
            
            <div class="condicoes">
                <strong>Condições Gerais:</strong>
                <ul>
                    <li>Este orçamento tem validade de 7 dias</li>
                    <li>Pagamento conforme acordado</li>
                    <li>Preços sujeitos a alteração após o prazo de validade</li>
                    <li>Prazo de entrega começa a contar após aprovação</li>
                </ul>
            </div>
        </div>
        
        <!-- Assinaturas -->
        <div class="assinatura">
            <div class="assinatura-campo">
                <div class="assinatura-linha"></div>
                <div><?= htmlspecialchars($orcamento['vendedor_nome'] ?? 'Vendedor') ?></div>
                <div style="font-size: 10px;">Vendedor</div>
            </div>
            
            <div class="assinatura-campo">
                <div class="assinatura-linha"></div>
                <div>Cliente</div>
                <div style="font-size: 10px;">Assinatura</div>
            </div>
        </div>
        
        <!-- Rodapé -->
        <div class="footer">
            <p>Para aprovar este orçamento, entre em contato conosco:</p>
            <p>
                <?php if (!empty($orcamento['vendedor_telefone'])): ?>
                Tel: <?= htmlspecialchars($orcamento['vendedor_telefone']) ?> | 
                <?php endif; ?>
                <?php if (!empty($orcamento['vendedor_email'])): ?>
                E-mail: <?= htmlspecialchars($orcamento['vendedor_email']) ?>
                <?php else: ?>
                E-mail: <?= EMAIL_EMPRESA ?>
                <?php endif; ?>
            </p>
            <p style="margin-top: 10px;">
                Documento gerado em <?= date('d/m/Y H:i') ?>
            </p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">
            Imprimir / Salvar PDF
        </button>
    </div>
</body>
</html>
<?php
// Capturar o HTML gerado
$html = ob_get_clean();

// Enviar headers apropriados
header('Content-Type: text/html; charset=UTF-8');

// Mostrar o HTML
echo $html;
?>