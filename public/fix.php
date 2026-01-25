<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireLogin();

$pedido_id = $_GET['pedido_id'] ?? 4;

// Buscar vers?es de arte
$stmt = $pdo->prepare("
    SELECT * FROM arte_versoes 
    WHERE pedido_id = ? 
    ORDER BY versao DESC
");
$stmt->execute([$pedido_id]);
$versoes = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste de Caminhos de Imagem</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .status-ok {
            color: green;
            font-weight: bold;
        }
        .status-erro {
            color: red;
            font-weight: bold;
        }
        .preview {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #ddd;
            cursor: pointer;
        }
        .code {
            background: #f4f4f4;
            padding: 5px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>? Teste de Caminhos de Imagem - Pedido #<?= $pedido_id ?></h1>
        
        <?php if (empty($versoes)): ?>
            <p style="color: red;">? Nenhuma vers?o de arte encontrada para o pedido #<?= $pedido_id ?></p>
        <?php else: ?>
            
            <div class="test-section">
                <h3>? Diret$)A(.rio Atual:</h3>
                <p class="code"><?= getcwd() ?></p>
                
                <h3>? Estrutura de Pastas:</h3>
                <pre class="code">
/var/www/html/br-bandeiras/
$)A)@)$)$ app/
$)A)&   )@)$)$ config.php
$)A)&   )8)$)$ functions.php
$)A)@)$)$ public/
$)A)&   )@)$)$ pedido_detalhes_arte_finalista.php (voc(: est(" aqui)
$)A)&   )8)$)$ test_image_path.php (este arquivo)
$)A)8)$)$ uploads/
    $)A)8)$)$ arte_versoes/
        $)A)8)$)$ (arquivos de imagem)
                </pre>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Vers?o</th>
                        <th>Nome Arquivo</th>
                        <th>Caminho no Banco</th>
                        <th>Testes de Caminho</th>
                        <th>Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($versoes as $versao): ?>
                    <tr>
                        <td><?= $versao['versao'] ?></td>
                        <td><?= htmlspecialchars($versao['arquivo_nome']) ?></td>
                        <td class="code"><?= htmlspecialchars($versao['arquivo_caminho']) ?></td>
                        <td>
                            <?php
                            // Testar diferentes caminhos poss$)A(*veis
                            $testes = [
                                'Caminho original' => $versao['arquivo_caminho'],
                                '../' . $versao['arquivo_caminho'] => '../' . $versao['arquivo_caminho'],
                                '../../' . $versao['arquivo_caminho'] => '../../' . $versao['arquivo_caminho'],
                                '../public/' . $versao['arquivo_caminho'] => '../public/' . $versao['arquivo_caminho'],
                                '/var/www/html/br-bandeiras/' . $versao['arquivo_caminho'] => '/var/www/html/br-bandeiras/' . $versao['arquivo_caminho']
                            ];
                            
                            $caminho_correto = null;
                            foreach ($testes as $descricao => $caminho) {
                                $existe = file_exists($caminho);
                                $classe = $existe ? 'status-ok' : 'status-erro';
                                $status = $existe ? '? EXISTE' : '? N?O EXISTE';
                                
                                echo "<div>";
                                echo "<span class='code'>{$caminho}</span><br>";
                                echo "<span class='{$classe}'>{$status}</span>";
                                
                                if ($existe && !$caminho_correto) {
                                    $caminho_correto = $caminho;
                                    echo " <strong>$)A!{ USE ESTE!</strong>";
                                }
                                echo "</div><br>";
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($caminho_correto): ?>
                                <?php
                                $ext = strtolower(pathinfo($versao['arquivo_nome'], PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                ?>
                                    <img src="<?= htmlspecialchars($caminho_correto) ?>" 
                                         class="preview" 
                                         onclick="window.open(this.src)"
                                         title="Clique para abrir">
                                    <br>
                                    <small>Clique para testar</small>
                                <?php else: ?>
                                    <span class="code"><?= $ext ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status-erro">Arquivo n?o encontrado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="test-section" style="background: #e8f5e9; border-color: #4caf50;">
                <h3>? Solu??o Recomendada:</h3>
                <p>No arquivo <code>pedido_detalhes_arte_finalista.php</code>, use este c$)A(.digo para o bot?o visualizar:</p>
                <pre class="code" style="background: white; padding: 10px;">
&lt;button onclick="visualizarArte('&lt;?= htmlspecialchars($versao['arquivo_caminho']) ?&gt;')"&gt;
    Visualizar
&lt;/button&gt;

&lt;script&gt;
function visualizarArte(caminho) {
    // Adicionar ../ ao caminho se necess$)A("rio
    if (caminho.startsWith('uploads/')) {
        caminho = '../' + caminho;
    }
    document.getElementById('imagemModal').src = caminho;
    document.getElementById('modalVisualizacao').classList.remove('hidden');
}
&lt;/script&gt;
                </pre>
            </div>
            
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="pedido_detalhes_arte_finalista.php?id=<?= $pedido_id ?>" 
               style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
                $)A!{ Voltar para Detalhes do Pedido
            </a>
        </div>
    </div>
</body>
</html>