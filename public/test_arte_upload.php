<?php
// TESTE SIMPLES DE UPLOAD - Coloque este arquivo na pasta public/

// Mostrar TODOS os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

$resultado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = "<h2>Resultado do Upload:</h2>";
    $resultado .= "<pre>";
    $resultado .= "POST recebido: SIM\n";
    $resultado .= "FILES array:\n" . print_r($_FILES, true) . "\n";
    
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === 0) {
        $arquivo = $_FILES['arquivo'];
        
        // Tentar salvar em pasta temporária local
        $destino = 'temp_' . time() . '_' . basename($arquivo['name']);
        
        if (move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $resultado .= "✅ Arquivo salvo como: $destino\n";
            $resultado .= "Tamanho: " . filesize($destino) . " bytes\n";
            
            // Limpar arquivo de teste
            unlink($destino);
            $resultado .= "Arquivo de teste removido.\n";
        } else {
            $resultado .= "❌ Falha ao mover arquivo\n";
        }
    } else {
        $erros = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo maior que upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo maior que MAX_FILE_SIZE do form',
            UPLOAD_ERR_PARTIAL => 'Upload parcial',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Sem pasta temp',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar',
            UPLOAD_ERR_EXTENSION => 'Bloqueado por extensão'
        ];
        $erro_codigo = $_FILES['arquivo']['error'] ?? -1;
        $resultado .= "❌ Erro no upload: " . ($erros[$erro_codigo] ?? "Código $erro_codigo") . "\n";
    }
    
    $resultado .= "</pre>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste Simples Upload</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        form {
            margin: 20px 0;
            padding: 20px;
            border: 2px dashed #4CAF50;
            border-radius: 5px;
            background: #f9fff9;
        }
        input[type="file"] {
            display: block;
            margin: 20px 0;
            padding: 10px;
            width: 100%;
            box-sizing: border-box;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .resultado {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        pre {
            background: #333;
            color: #0f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            margin: 5px;
        }
        .ok { background: #4CAF50; color: white; }
        .erro { background: #f44336; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste Ultra-Simples de Upload</h1>
        
        <div class="info">
            <h3>Informações do PHP:</h3>
            <p>
                <span class="status <?= ini_get('file_uploads') ? 'ok' : 'erro' ?>">
                    file_uploads: <?= ini_get('file_uploads') ? 'ON' : 'OFF' ?>
                </span>
                <span class="status ok">
                    upload_max_filesize: <?= ini_get('upload_max_filesize') ?>
                </span>
                <span class="status ok">
                    post_max_size: <?= ini_get('post_max_size') ?>
                </span>
                <span class="status ok">
                    max_file_uploads: <?= ini_get('max_file_uploads') ?>
                </span>
            </p>
            <p>
                <strong>Diretório atual:</strong> <?= getcwd() ?><br>
                <strong>Temp dir:</strong> <?= sys_get_temp_dir() ?><br>
                <strong>PHP Version:</strong> <?= PHP_VERSION ?>
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <h3>Selecione um arquivo para testar:</h3>
            <input type="file" name="arquivo" required onchange="mostrarInfo(this)">
            <div id="file-info"></div>
            <button type="submit">Enviar Arquivo</button>
        </form>

        <?php if ($resultado): ?>
        <div class="resultado">
            <?= $resultado ?>
        </div>
        <?php endif; ?>

        <div class="info" style="margin-top: 30px;">
            <h3>📋 Checklist de Verificação:</h3>
            <ol>
                <li>O formulário tem <code>method="POST"</code>? ✅</li>
                <li>O formulário tem <code>enctype="multipart/form-data"</code>? ✅</li>
                <li>O input file tem <code>name="arquivo"</code>? ✅</li>
                <li>PHP está configurado para aceitar uploads? <?= ini_get('file_uploads') ? '✅' : '❌' ?></li>
                <li>Limite de tamanho adequado? <?= intval(ini_get('upload_max_filesize')) >= 10 ? '✅' : '❌' ?></li>
            </ol>
        </div>

        <div class="info">
            <h3>🔍 Para testar no arte_upload.php:</h3>
            <p>Se este teste funcionar mas o arte_upload.php não, adicione isto no início do arte_upload.php:</p>
            <pre>
// DEBUG - No início do arquivo, após os requires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    die('&lt;pre&gt;FILES: ' . print_r($_FILES, true) . '&lt;/pre&gt;');
}
            </pre>
        </div>
    </div>

    <script>
    function mostrarInfo(input) {
        const info = document.getElementById('file-info');
        if (input.files && input.files[0]) {
            const file = input.files[0];
            info.innerHTML = `
                <div style="background: #e8f5e9; padding: 10px; margin: 10px 0; border-radius: 5px;">
                    <strong>Arquivo selecionado:</strong><br>
                    Nome: ${file.name}<br>
                    Tamanho: ${(file.size / 1024).toFixed(2)} KB<br>
                    Tipo: ${file.type || 'Desconhecido'}
                </div>
            `;
        }
    }
    
    // Log no console
    console.log('Upload test loaded');
    console.log('FormData supported:', typeof FormData !== 'undefined');
    </script>
</body>
</html>