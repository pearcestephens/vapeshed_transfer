<?php
declare(strict_types=1);
/**
 * Health Check API Endpoint
 * Comprehensive health status for monitoring and alerting.
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Support\Api;
use Unified\Support\Config;
use Unified\Support\Pdo;

Api::initJson();
Api::applySecurityHeaders();
Api::applyCors('GET, OPTIONS');
Api::handleOptionsPreflight();
Api::requireMethod('GET');
Api::enforceGetRateLimit('health');

// Quick health check vs detailed
$detailed = isset($_GET['detailed']) && $_GET['detailed'] === '1';

$health = [
    'status' => 'healthy',
    'timestamp' => time(),
    'version' => Config::get('neuro.unified.version', 'unknown'),
    'environment' => Config::get('neuro.unified.environment', 'unknown'),
];

// Basic checks
$checks = [];

// 1. Database connectivity
try {
    $db = Pdo::instance();
    $stmt = $db->query('SELECT 1 as health');
    $result = $stmt->fetch();
    $checks['database'] = [
        'status' => 'healthy',
        'message' => 'Database connection successful',
    ];
} catch (\Throwable $e) {
    $checks['database'] = [
        'status' => 'unhealthy',
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
    ];
    $health['status'] = 'unhealthy';
}

// 2. Configuration loaded
$configCount = count(Config::all());
$checks['configuration'] = [
    'status' => $configCount > 0 ? 'healthy' : 'unhealthy',
    'message' => "Configuration loaded: $configCount keys",
];

if ($configCount === 0) {
    $health['status'] = 'degraded';
}

// 3. Storage paths writable
$storageChecks = [];
if (defined('STORAGE_PATH')) {
    $dirs = ['logs', 'cache', 'tmp', 'backups'];
    foreach ($dirs as $dir) {
        $path = STORAGE_PATH . '/' . $dir;
        $writable = is_dir($path) && is_writable($path);
        $storageChecks[$dir] = [
            'path' => $path,
            'writable' => $writable,
        ];
        
        if (!$writable && $health['status'] === 'healthy') {
            $health['status'] = 'degraded';
        }
    }
}

$checks['storage'] = [
    'status' => $health['status'] === 'unhealthy' ? 'healthy' : $health['status'],
    'directories' => $storageChecks,
];

// 4. Memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = parseMemoryLimit($memoryLimit);

$memoryPercent = $memoryLimitBytes > 0 ? ($memoryUsage / $memoryLimitBytes) * 100 : 0;

$checks['memory'] = [
    'status' => $memoryPercent > 90 ? 'degraded' : 'healthy',
    'usage_bytes' => $memoryUsage,
    'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
    'limit' => $memoryLimit,
    'usage_percent' => round($memoryPercent, 2),
];

if ($memoryPercent > 90 && $health['status'] === 'healthy') {
    $health['status'] = 'degraded';
}

// Detailed checks (if requested)
if ($detailed) {
    // 5. PHP version and extensions
    $checks['php'] = [
        'version' => PHP_VERSION,
        'extensions' => get_loaded_extensions(),
    ];
    
    // 6. System load (if available)
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $checks['system'] = [
            'load_1min' => $load[0] ?? null,
            'load_5min' => $load[1] ?? null,
            'load_15min' => $load[2] ?? null,
        ];
    }
    
    // 7. Disk space
    if (defined('STORAGE_PATH') && is_dir(STORAGE_PATH)) {
        $freeSpace = @disk_free_space(STORAGE_PATH);
        $totalSpace = @disk_total_space(STORAGE_PATH);
        
        if ($freeSpace !== false && $totalSpace !== false) {
            $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            
            $checks['disk'] = [
                'status' => $usedPercent > 90 ? 'degraded' : 'healthy',
                'free_bytes' => $freeSpace,
                'total_bytes' => $totalSpace,
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'used_percent' => round($usedPercent, 2),
            ];
            
            if ($usedPercent > 90 && $health['status'] === 'healthy') {
                $health['status'] = 'degraded';
            }
        }
    }
}

$health['checks'] = $checks;

// HTTP status code based on health
$statusCode = 200;
if ($health['status'] === 'degraded') {
    $statusCode = 200; // Still operational
} elseif ($health['status'] === 'unhealthy') {
    $statusCode = 503; // Service unavailable
}

Api::respond([
    'success' => $health['status'] !== 'unhealthy',
    'data' => $health,
], $statusCode);

/**
 * Parse memory limit string to bytes
 * 
 * @param string $limit Memory limit (e.g., "128M", "1G")
 * @return int Bytes
 */
function parseMemoryLimit(string $limit): int
{
    $limit = trim($limit);
    
    if ($limit === '-1') {
        return PHP_INT_MAX;
    }
    
    $unit = strtoupper(substr($limit, -1));
    $value = (int) substr($limit, 0, -1);
    
    switch ($unit) {
        case 'G':
            return $value * 1024 * 1024 * 1024;
        case 'M':
            return $value * 1024 * 1024;
        case 'K':
            return $value * 1024;
        default:
            return (int) $limit;
    }
}
