<?php
require_once '../app/config.php';
require_once '../app/auth.php';

requireRole(['gestor']);

$formato = $_GET['formato'] ?? 'csv';

// CSV padrão
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_produtos_catalogo.csv"');

// UTF-8 BOM para Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Cabeçalhos
fputcsv($output, [
    'codigo',
    'nome',
    'categoria', 
    'descricao',
    'preco',
    'preco_promocional',
    'custo',
    'unidade_venda',
    'tempo_producao',
    'codigo_barras',
    'tags'
], ';');

// Exemplos
fputcsv($output, ['BAND-001', 'Bandeira do Brasil 1,5x1m', 'Bandeiras', 'Bandeira em poliéster 110g', '89.90', '', '35.00', 'UN', '3', '7891234567890', 'promocao,mais-vendido'], ';');
fputcsv($output, ['BAND-002', 'Bandeira de Goiás 2x3m', 'Bandeiras', 'Bandeira dupla face', '149.90', '129.90', '65.00', 'UN', '5', '7891234567891', ''], ';');
fputcsv($output, ['FITA-001', 'Fita para Inauguração 10cm x 50m', 'Fitas', 'Fita de cetim personalizada', '45.00', '', '18.00', 'M', '2', '7891234567892', 'lancamento'], ';');

fclose($output);
exit;