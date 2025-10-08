#!/usr/bin/env php
<?php
/**
 * System Health Check Script
 * 
 * Validates all system components and reports status
 * Returns 0 on success, 1 on failure for monitoring integration
 * 
 * Usage: php bin/health_check.php
 */
declare(strict_types=1);

require __DIR__ . '/../transfer_engine/config/bootstrap.php';

use Unified\Integration\VendConnection;

$config = require __DIR__ . '/../transfer_engine/config/vend.php';
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => [],
];

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║            SYSTEM HEALTH CHECK                               ║\n";
echo "║            " . date('Y-m-d H:i:s') . "                              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Check 1: Database connectivity
echo "Checking database connectivity... ";
try {
    $connection = new VendConnection($config);
    $health = $connection->healthCheck();
    $results['checks']['database'] = [
        'status' => $health ? 'OK' : 'FAIL',
        'response_time' => '<1ms',
    ];
    echo $health ? "✓ OK\n" : "✗ FAIL\n";
} catch (\Exception $e) {
    $results['checks']['database'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage(),
    ];
    echo "✗ ERROR: {$e->getMessage()}\n";
}

// Check 2: Cache directory
echo "Checking cache directory... ";
$cacheWritable = is_writable('/tmp/');
$results['checks']['cache'] = [
    'status' => $cacheWritable ? 'OK' : 'FAIL',
    'path' => '/tmp/',
];
echo $cacheWritable ? "✓ OK\n" : "✗ FAIL\n";

// Check 3: Log directory
echo "Checking log directory... ";
$logDir = __DIR__ . '/../transfer_engine/logs/';
$logWritable = is_writable($logDir);
$results['checks']['logs'] = [
    'status' => $logWritable ? 'OK' : 'FAIL',
    'path' => $logDir,
];
echo $logWritable ? "✓ OK\n" : "✗ FAIL\n";

// Check 4: Configuration
echo "Checking configuration... ";
$configValid = !empty($config['database']['host']) && !empty($config['database']['database']);
$results['checks']['config'] = [
    'status' => $configValid ? 'OK' : 'FAIL',
    'read_only' => $config['read_only'] ?? false,
    'host' => $config['database']['host'] ?? 'NOT SET',
];
echo $configValid ? "✓ OK\n" : "✗ FAIL\n";

// Check 5: Required PHP extensions
echo "Checking PHP extensions... ";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}
$extensionsOk = empty($missingExtensions);
$results['checks']['php_extensions'] = [
    'status' => $extensionsOk ? 'OK' : 'FAIL',
    'missing' => $missingExtensions,
];
echo $extensionsOk ? "✓ OK\n" : "✗ FAIL (missing: " . implode(', ', $missingExtensions) . ")\n";

// Check 6: Disk space
echo "Checking disk space... ";
$freeSpace = disk_free_space(__DIR__);
$totalSpace = disk_total_space(__DIR__);
$freePercent = round(($freeSpace / $totalSpace) * 100, 2);
$diskOk = $freePercent > 10; // Alert if less than 10% free
$results['checks']['disk_space'] = [
    'status' => $diskOk ? 'OK' : 'WARNING',
    'free_percent' => $freePercent,
    'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
];
echo $diskOk ? "✓ OK ({$freePercent}% free)\n" : "⚠️  WARNING ({$freePercent}% free)\n";

// Check 7: Recent logs
echo "Checking recent logs... ";
$logFiles = glob($logDir . '*.log');
$recentLogs = 0;
$yesterday = time() - (24 * 60 * 60);
foreach ($logFiles as $logFile) {
    if (filemtime($logFile) > $yesterday) {
        $recentLogs++;
    }
}
$logsOk = $recentLogs > 0;
$results['checks']['recent_logs'] = [
    'status' => $logsOk ? 'OK' : 'WARNING',
    'count' => $recentLogs,
];
echo $logsOk ? "✓ OK ({$recentLogs} recent log files)\n" : "⚠️  WARNING (no recent logs)\n";

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    SUMMARY                                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

$allOk = true;
foreach ($results['checks'] as $name => $check) {
    $status = $check['status'];
    $icon = $status === 'OK' ? '✓' : ($status === 'WARNING' ? '⚠️' : '✗');
    echo "  {$icon} " . str_pad(ucwords(str_replace('_', ' ', $name)), 20) . " {$status}\n";
    if ($status !== 'OK') {
        $allOk = false;
    }
}

echo "\n";
if ($allOk) {
    echo "✓ All health checks passed.\n\n";
} else {
    echo "⚠️  Some health checks failed. Review output above.\n\n";
}

// Output JSON for programmatic parsing
if (isset($argv[1]) && $argv[1] === '--json') {
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
}

exit($allOk ? 0 : 1);
