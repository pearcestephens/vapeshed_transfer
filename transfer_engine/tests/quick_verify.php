#!/usr/bin/env php
<?php
/**
 * Quick Test - Verify Phase 8, 9, 10 Components Load
 * 
 * This is a simplified test to verify all components can be instantiated
 * without running full integration tests.
 * 
 * @version 2.0.0
 */

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   QUICK VERIFICATION TEST - PHASES 8, 9, 10             ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Get the correct path
$baseDir = dirname(__DIR__);
$bootstrapPath = $baseDir . '/config/bootstrap.php';

echo "Base Directory: {$baseDir}\n";
echo "Bootstrap Path: {$bootstrapPath}\n";
echo "Bootstrap Exists: " . (file_exists($bootstrapPath) ? "✓ Yes" : "✗ No") . "\n\n";

if (!file_exists($bootstrapPath)) {
    die("ERROR: Bootstrap file not found!\n");
}

require_once $bootstrapPath;

echo "┌─ Loading Components ─────────────────────────────────────┐\n";

$results = [];
$passed = 0;
$failed = 0;

// Test component loading
$components = [
    'Logger' => 'Unified\Support\Logger',
    'CacheManager' => 'Unified\Support\CacheManager',
    'MetricsCollector' => 'Unified\Support\MetricsCollector',
    'HealthMonitor' => 'Unified\Support\HealthMonitor',
    'PerformanceProfiler' => 'Unified\Support\PerformanceProfiler',
    'AlertManager' => 'Unified\Support\AlertManager',
    'LogAggregator' => 'Unified\Support\LogAggregator',
    'AnalyticsEngine' => 'Unified\Support\AnalyticsEngine',
    'ReportGenerator' => 'Unified\Support\ReportGenerator',
    'DashboardDataProvider' => 'Unified\Support\DashboardDataProvider',
    'NotificationScheduler' => 'Unified\Support\NotificationScheduler',
    'ApiDocumentationGenerator' => 'Unified\Support\ApiDocumentationGenerator',
];

foreach ($components as $name => $class) {
    echo "  Testing {$name}... ";
    
    // Remove BOTH possible namespace prefixes (Unified\ or VapeshedTransfer\)
    $relativePath = str_replace(['Unified\\', 'VapeshedTransfer\\'], '', $class);
    $classFile = $baseDir . '/src/' . str_replace('\\', '/', $relativePath) . '.php';
    
    if (file_exists($classFile)) {
        if (class_exists($class)) {
            echo "✓ Class loaded\n";
            $passed++;
        } else {
            echo "✗ Class not found\n";
            $failed++;
        }
    } else {
        echo "✗ File not found: {$classFile}\n";
        $failed++;
    }
}

echo "└──────────────────────────────────────────────────────────┘\n\n";

// Test basic instantiation
echo "┌─ Testing Basic Instantiation ───────────────────────────┐\n";

try {
    echo "  Creating Logger... ";
    $logger = new Unified\Support\Logger(storage_path('logs'));
    echo "✓\n";
    $passed++;
} catch (\Exception $e) {
    echo "✗ {$e->getMessage()}\n";
    $failed++;
}

try {
    echo "  Creating CacheManager... ";
    $cache = new Unified\Support\CacheManager($logger);
    echo "✓\n";
    $passed++;
} catch (\Exception $e) {
    echo "✗ {$e->getMessage()}\n";
    $failed++;
}

try {
    echo "  Creating MetricsCollector... ";
    $metrics = new Unified\Support\MetricsCollector($logger, $cache);
    echo "✓\n";
    $passed++;
} catch (\Exception $e) {
    echo "✗ {$e->getMessage()}\n";
    $failed++;
}

try {
    echo "  Creating AnalyticsEngine... ";
    $analytics = new Unified\Support\AnalyticsEngine($logger, $metrics);
    echo "✓\n";
    $passed++;
} catch (\Exception $e) {
    echo "✗ {$e->getMessage()}\n";
    $failed++;
}

try {
    echo "  Creating ReportGenerator... ";
    $reportGen = new Unified\Support\ReportGenerator($logger, $metrics);
    echo "✓\n";
    $passed++;
} catch (\Exception $e) {
    echo "✗ {$e->getMessage()}\n";
    $failed++;
}

echo "└──────────────────────────────────────────────────────────┘\n\n";

// Summary
$total = $passed + $failed;
$passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                    SUMMARY                               ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo sprintf("║  Total Tests:     %-35d║\n", $total);
echo sprintf("║  Passed:          %-35d║\n", $passed);
echo sprintf("║  Failed:          %-35d║\n", $failed);
echo sprintf("║  Pass Rate:       %-33s  ║\n", $passRate . '%');
echo "╚══════════════════════════════════════════════════════════╝\n\n";

if ($failed > 0) {
    echo "⚠ Some tests failed. Check the output above for details.\n\n";
    exit(1);
} else {
    echo "✅ All components loaded successfully!\n\n";
    echo "Next steps:\n";
    echo "1. Run full test suite: php tests/comprehensive_phase_test.php\n";
    echo "2. Or use: ./run_comprehensive_tests.sh\n\n";
    exit(0);
}
