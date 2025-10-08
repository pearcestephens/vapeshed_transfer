<?php
declare(strict_types=1);

/**
 * Cache Performance Analysis Tool
 * 
 * Analyzes cache behavior, hit/miss rates, and performance improvements
 * Identifies optimization opportunities for VendAdapter caching strategy
 * 
 * @author VapeShed Transfer Engine
 * @date 2025-10-08
 */

require_once __DIR__ . '/../config/bootstrap.php';

use Unified\Support\Logger;
use Unified\Support\CacheManager;
use Unified\Integration\VendConnection;
use Unified\Integration\VendAdapter;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          CACHE PERFORMANCE ANALYSIS & OPTIMIZATION             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Initialize
$logger = new Logger('/tmp/vapeshed_cache_analysis.log');
$cache = new CacheManager($logger, [
    'driver' => 'file',
    'cache_dir' => '/tmp/vapeshed_cache_perf',
    'default_ttl' => 3600
]);
$vendConnection = new VendConnection($logger);
$vendAdapter = new VendAdapter($vendConnection, $logger, $cache);

// Test scenarios
$tests = [
    'outlets' => [
        'name' => 'Get Active Outlets',
        'method' => 'getOutlets',
        'params' => [],
        'iterations' => 10
    ],
    'inventory' => [
        'name' => 'Get Store Inventory (Botany)',
        'method' => 'getInventory',
        'params' => ['0a6f6e36-8b71-11eb-f3d6-40cea3d59c5a', ['limit' => 100]],
        'iterations' => 10
    ],
    'low_stock' => [
        'name' => 'Get Low Stock Items',
        'method' => 'getLowStockItems',
        'params' => [10], // threshold parameter
        'iterations' => 5
    ],
    'products' => [
        'name' => 'Get Products',
        'method' => 'getProducts',
        'params' => [['limit' => 100]],
        'iterations' => 5
    ]
];

$results = [];

foreach ($tests as $testId => $test) {
    echo "┌─ Test: {$test['name']} " . str_repeat('─', max(1, 51 - strlen($test['name']))) . "┐\n";
    
    $method = $test['method'];
    $params = $test['params'];
    $iterations = $test['iterations'];
    
    $times = [];
    $cacheStatus = [];
    
    // First call (cache miss)
    $start = microtime(true);
    call_user_func_array([$vendAdapter, $method], $params);
    $firstCallTime = (microtime(true) - $start) * 1000;
    
    echo sprintf("  First call (cache miss):    %8.2fms\n", $firstCallTime);
    
    // Subsequent calls (cache hits)
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        call_user_func_array([$vendAdapter, $method], $params);
        $times[] = (microtime(true) - $start) * 1000;
    }
    
    $avgCacheHit = array_sum($times) / count($times);
    $minTime = min($times);
    $maxTime = max($times);
    $improvement = $firstCallTime / $avgCacheHit;
    
    echo sprintf("  Cached calls (avg of %d):   %8.2fms\n", $iterations, $avgCacheHit);
    echo sprintf("  Min/Max cached:             %8.2fms / %.2fms\n", $minTime, $maxTime);
    echo sprintf("  Speed improvement:          %8.1fx\n", $improvement);
    
    $status = '✓';
    $message = 'Good';
    
    if ($improvement < 2) {
        $status = '⚠️';
        $message = 'Cache not effective (< 2x)';
    } elseif ($improvement < 5) {
        $status = '⚠️';
        $message = 'Moderate improvement (< 5x)';
    } elseif ($improvement >= 10) {
        $status = '✓';
        $message = 'Excellent performance (>= 10x)';
    } else {
        $status = '✓';
        $message = 'Good performance (5-10x)';
    }
    
    echo "  Status: {$status} {$message}\n";
    echo "└────────────────────────────────────────────────────────────────┘\n\n";
    
    $results[$testId] = [
        'name' => $test['name'],
        'first_call_ms' => $firstCallTime,
        'avg_cached_ms' => $avgCacheHit,
        'min_cached_ms' => $minTime,
        'max_cached_ms' => $maxTime,
        'improvement' => $improvement,
        'status' => $status,
        'message' => $message
    ];
}

// Cache Memory Analysis
echo "┌─ Cache Storage Analysis ──────────────────────────────────────┐\n";

$cacheDir = '/tmp/vapeshed_cache_perf';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    $totalSize = 0;
    $fileCount = count($files);
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $totalSize += filesize($file);
        }
    }
    
    echo sprintf("  Cache files:        %d\n", $fileCount);
    echo sprintf("  Total size:         %.2f KB\n", $totalSize / 1024);
    echo sprintf("  Avg file size:      %.2f KB\n", $fileCount > 0 ? ($totalSize / $fileCount) / 1024 : 0);
    
    if ($totalSize > 10 * 1024 * 1024) { // > 10MB
        echo "  ⚠️  Cache size growing large - consider cleanup strategy\n";
    } else {
        echo "  ✓ Cache size healthy\n";
    }
} else {
    echo "  Cache directory not found\n";
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

// Overall Performance Summary
echo "┌─ Performance Summary ──────────────────────────────────────────┐\n";

$allImprovements = array_column($results, 'improvement');
$avgImprovement = array_sum($allImprovements) / count($allImprovements);
$bestImprovement = max($allImprovements);
$worstImprovement = min($allImprovements);

echo sprintf("  Average improvement:   %.1fx\n", $avgImprovement);
echo sprintf("  Best improvement:      %.1fx\n", $bestImprovement);
echo sprintf("  Worst improvement:     %.1fx\n", $worstImprovement);
echo "\n";

if ($avgImprovement >= 10) {
    echo "  ✓ EXCELLENT: Cache is performing very well\n";
    echo "    No optimization needed at this time.\n";
} elseif ($avgImprovement >= 5) {
    echo "  ✓ GOOD: Cache is providing solid performance\n";
    echo "    Minor optimizations may be beneficial.\n";
} elseif ($avgImprovement >= 2) {
    echo "  ⚠️  MODERATE: Cache is working but could be better\n";
    echo "    Recommend investigating slow cached calls.\n";
} else {
    echo "  🚨 POOR: Cache is not providing expected benefits\n";
    echo "    Immediate investigation required.\n";
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

// Recommendations
echo "┌─ Optimization Recommendations ────────────────────────────────┐\n";

$recommendations = [];

// Check if any tests have poor cache performance
$poorPerformers = array_filter($results, fn($r) => $r['improvement'] < 2);
if (!empty($poorPerformers)) {
    $recommendations[] = "Investigate why these calls have poor cache performance:";
    foreach ($poorPerformers as $test) {
        $recommendations[] = "  • {$test['name']}: {$test['improvement']}x improvement";
    }
}

// Check for high variance
foreach ($results as $test) {
    $variance = $test['max_cached_ms'] - $test['min_cached_ms'];
    if ($variance > 50) { // More than 50ms variance
        $recommendations[] = "High variance in '{$test['name']}' (min: {$test['min_cached_ms']}ms, max: {$test['max_cached_ms']}ms)";
        $recommendations[] = "  Consider investigating why cached calls have inconsistent timing";
    }
}

// Cache size recommendations
if ($totalSize > 5 * 1024 * 1024) { // > 5MB
    $recommendations[] = "Cache size approaching limits - implement cleanup strategy:";
    $recommendations[] = "  • Add TTL-based expiration";
    $recommendations[] = "  • Implement LRU eviction";
    $recommendations[] = "  • Monitor cache hit/miss ratios";
}

// General best practices
if ($avgImprovement < 10) {
    $recommendations[] = "Consider these optimization strategies:";
    $recommendations[] = "  • Increase cache TTL for stable data (outlets, products)";
    $recommendations[] = "  • Add cache warming for frequently accessed data";
    $recommendations[] = "  • Implement cache preloading at application startup";
    $recommendations[] = "  • Use Redis/Memcached for better performance than file cache";
}

if (empty($recommendations)) {
    echo "  ✓ No optimization recommendations at this time.\n";
    echo "    Cache is performing optimally!\n";
} else {
    foreach ($recommendations as $idx => $rec) {
        if (strpos($rec, '•') !== false || strpos($rec, 'Consider') !== false || strpos($rec, 'Investigate') !== false) {
            echo "  {$rec}\n";
        } else {
            echo "    {$rec}\n";
        }
    }
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              CACHE ANALYSIS COMPLETE                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$logger->info('Cache performance analysis completed', [
    'avg_improvement' => $avgImprovement,
    'best_improvement' => $bestImprovement,
    'worst_improvement' => $worstImprovement,
    'cache_files' => $fileCount ?? 0,
    'cache_size_kb' => isset($totalSize) ? round($totalSize / 1024, 2) : 0
]);
