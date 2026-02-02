<?php
/**
 * Download de arquivos de backup
 * Apenas gestores podem baixar backups
 */
require_once '../app/config.php';
require_once '../app/auth.php';
require_once '../app/functions.php';

requireRole(['gestor']);

$backupDir = __DIR__ . '/../backups/';
$filename = $_GET['file'] ?? '';

// Validar filename para evitar path traversal
$filename = basename($filename);

if (empty($filename)) {
    http_response_code(400);
    die('Arquivo não especificado');
}

$filepath = $backupDir . $filename;

// Verificar se arquivo existe e está no diretório de backups
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('Arquivo não encontrado');
}

// Verificar se é um arquivo .sql
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    http_response_code(403);
    die('Tipo de arquivo não permitido');
}

// Registrar log de download
registrarLog('backup_download', "Download de backup: {$filename}");

// Enviar arquivo
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filepath);
exit;
