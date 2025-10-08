#!/usr/bin/env php
<?php
/**
 * Type Hint Fix Script
 * Updates Cache type hints to accept Cache|CacheManager in all Phase 9/10 files
 */

$filesToFix = [
    'src/Support/HealthMonitor.php',
    'src/Support/PerformanceProfiler.php',
    'src/Support/AlertManager.php',
    'src/Support/LogAggregator.php',
    'src/Support/AnalyticsEngine.php',
    'src/Support/ReportGenerator.php',
    'src/Support/DashboardDataProvider.php',
];

$baseDir = __DIR__;
$fixed = 0;
$errors = 0;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   TYPE HINT FIX: Cache → Cache|CacheManager             ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

foreach ($filesToFix as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "⚠ SKIP: $file (not found)\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $original = $content;
    $changed = false;
    
    // Fix: Add CacheManager import if Cache import exists
    if (preg_match('/use Unified\\\\Support\\\\Cache;/', $content)) {
        if (!preg_match('/use Unified\\\\Support\\\\CacheManager;/', $content)) {
            $content = preg_replace(
                '/(use Unified\\\\Support\\\\Cache;)/',
                "$1\nuse Unified\\Support\\CacheManager;",
                $content
            );
            $changed = true;
        }
    }
    
    // Fix: Property declaration
    $content = preg_replace(
        '/private Cache \$cache;/',
        'private Cache|CacheManager $cache;',
        $content,
        -1,
        $count
    );
    if ($count > 0) $changed = true;
    
    // Fix: Constructor parameter type hint
    $content = preg_replace(
        '/Cache \$cache([,\)])/',
        'Cache|CacheManager $cache$1',
        $content,
        -1,
        $count
    );
    if ($count > 0) $changed = true;
    
    // Fix: PHPDoc @param tags
    $content = preg_replace(
        '/@param Cache \$cache/',
        '@param Cache|CacheManager $cache',
        $content,
        -1,
        $count
    );
    if ($count > 0) $changed = true;
    
    if ($changed && $content !== $original) {
        if (file_put_contents($fullPath, $content)) {
            echo "✓ FIXED: $file\n";
            $fixed++;
        } else {
            echo "✗ ERROR: $file (write failed)\n";
            $errors++;
        }
    } else {
        echo "• OK: $file (no changes needed)\n";
    }
}

echo "\n╔══════════════════════════════════════════════════════════╗\n";
echo "║                    SUMMARY                               ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo sprintf("║  Files Fixed:     %-35d║\n", $fixed);
echo sprintf("║  Errors:          %-35d║\n", $errors);
echo sprintf("║  Total Processed: %-35d║\n", count($filesToFix));
echo "╚══════════════════════════════════════════════════════════╝\n\n";

if ($errors === 0) {
    echo "✅ All type hints fixed successfully!\n";
    echo "   MetricsCollector already fixed manually\n";
    echo "   Run: php tests/quick_verify.php\n";
    exit(0);
} else {
    echo "⚠ Some files had errors. Please check permissions.\n";
    exit(1);
}
