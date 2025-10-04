<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

$tmpDir = defined('STORAGE_PATH') ? (STORAGE_PATH . '/tmp') : sys_get_temp_dir();
@mkdir($tmpDir, 0775, true);

$locks = glob($tmpDir . '/sse_*_*.lock') ?: [];
$global = is_array($locks) ? count($locks) : 0;

// Aggregate per-IP counts
$perIp = [];
foreach ($locks as $f) {
    if (preg_match('#/sse_([^_]+)_.+\.lock$#', $f, $m)) {
        $ip = $m[1];
        $perIp[$ip] = ($perIp[$ip] ?? 0) + 1;
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'global' => $global,
        'per_ip' => $perIp,
        'timestamp' => time()
    ]
], JSON_PRETTY_PRINT);
