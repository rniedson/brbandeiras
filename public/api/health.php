<?php
/**
 * Health Check Endpoint
 * 
 * Endpoint para verificar saúde do sistema, útil para monitoramento.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once '../../app/config.php';

header('Content-Type: application/json');

function checkDatabase(): array {
    try {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT 1');
        return [
            'status' => 'ok',
            'response_time_ms' => 0 // Poderia medir tempo real
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

function checkCache(): array {
    if (class_exists('Cache')) {
        return [
            'status' => Cache::isApcuAvailable() ? 'ok' : 'unavailable',
            'apcu_available' => Cache::isApcuAvailable()
        ];
    }
    return [
        'status' => 'unavailable',
        'message' => 'Cache class not found'
    ];
}

function checkDiskSpace(): array {
    $freeBytes = disk_free_space(__DIR__);
    $totalBytes = disk_total_space(__DIR__);
    $usedPercent = $totalBytes > 0 ? (1 - ($freeBytes / $totalBytes)) * 100 : 0;
    
    return [
        'status' => $usedPercent > 90 ? 'warning' : 'ok',
        'free_bytes' => $freeBytes,
        'total_bytes' => $totalBytes,
        'used_percent' => round($usedPercent, 2)
    ];
}

$health = [
    'status' => 'ok',
    'timestamp' => time(),
    'datetime' => date('Y-m-d H:i:s'),
    'checks' => [
        'database' => checkDatabase(),
        'cache' => checkCache(),
        'disk_space' => checkDiskSpace()
    ]
];

// Se algum check crítico falhou, mudar status geral
if ($health['checks']['database']['status'] !== 'ok') {
    $health['status'] = 'error';
    http_response_code(503);
} elseif ($health['checks']['disk_space']['status'] === 'warning') {
    $health['status'] = 'warning';
}

echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
