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

// Optional status classification using configured caps
try {
    $maxGlobal = (int) \Unified\Support\Config::get('neuro.unified.sse.max_global', 200);
    $maxPerIp  = (int) \Unified\Support\Config::get('neuro.unified.sse.max_per_ip', 3);
} catch (\Throwable $e) {
    $maxGlobal = 200; $maxPerIp = 3;
}

$reasons = [];
$status = 'green';
if ($global >= $maxGlobal) {
    $status = 'red';
    $reasons[] = 'global_over_capacity';
} elseif ($global >= max(1, (int) floor($maxGlobal * 0.9))) {
    $status = 'yellow';
    $reasons[] = 'global_near_capacity';
}
foreach ($perIp as $ip => $count) {
    if ($count >= $maxPerIp) { $status = $status === 'red' ? 'red' : 'yellow'; $reasons[] = 'ip_near_or_over_capacity:' . $ip; }
}

echo json_encode([
    'success' => true,
    'data' => [
        'global' => $global,
        'per_ip' => $perIp,
        'caps' => [ 'max_global' => $maxGlobal, 'max_per_ip' => $maxPerIp ],
        'status' => $status,
        'reasons' => $reasons,
        'timestamp' => time()
    ]
], JSON_PRETTY_PRINT);
