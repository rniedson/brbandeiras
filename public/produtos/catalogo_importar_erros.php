<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

$arquivo = $_GET['arquivo'] ?? $_SESSION['arquivo_erros'] ?? '';
$caminho = '../logs/' . basename($arquivo);

if (!$arquivo || !file_exists($caminho)) {
    $_SESSION['erro'] = 'Arquivo de erros não encontrado';
    header('Location: catalogo.php');
    exit;
}

$conteudo = file_get_contents($caminho);

$titulo = 'Erros de Importação';
$breadcrumb = [
    ['label' => 'Catálogo', 'url' => 'catalogo.php'],
    ['label' => 'Importar', 'url' => 'catalogo_importar.php'],
    ['label' => 'Erros']
];
include '../../views/layouts/_header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Erros de Importação</h1>
        <p class="text-gray-600 mt-2">Detalhes dos erros encontrados durante a importação</p>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="mb-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold">Log de Erros</h2>
            <a href="" 
               class="text-blue-600 hover:text-blue-800 text-sm">
                Baixar arquivo
            </a>
        </div>
        
        <pre class="bg-gray-100 p-4 rounded-lg overflow-x-auto text-sm"><?= htmlspecialchars($conteudo) ?></pre>
    </div>
    
    <div class="mt-6 flex justify-between">
        <a href="catalogo_importar.php" 
           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Nova Importação
        </a>
        <a href="catalogo.php" 
           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
            Voltar ao Catálogo
        </a>
    </div>
</div>

<?php include '../../views/layouts/_footer.php'; ?>