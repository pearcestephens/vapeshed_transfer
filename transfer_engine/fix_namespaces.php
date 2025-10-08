#!/usr/bin/env php
<?php
/**
 * Namespace Fix Script
 * Changes VapeshedTransfer\Support to Unified\Support in all Phase 8-10 files
 */

$filesToFix = [
    // Phase 8
    'src/Support/CacheManager.php',
    
    // Phase 9
    'src/Support/MetricsCollector.php',
    'src/Support/HealthMonitor.php',
    'src/Support/PerformanceProfiler.php',
    'src/Support/AlertManager.php',
    'src/Support/LogAggregator.php',
    
    // Phase 10
    'src/Support/AnalyticsEngine.php',
    'src/Support/ReportGenerator.php',
    'src/Support/DashboardDataProvider.php',
    'src/Support/NotificationScheduler.php',
    'src/Support/ApiDocumentationGenerator.php',
    
    // Tests
    'tests/comprehensive_phase_test.php',
    'tests/quick_verify.php',
];

$baseDir = __DIR__;
$fixed = 0;
$errors = 0;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   NAMESPACE FIX: VapeshedTransfer → Unified             ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

foreach ($filesToFix as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "⚠ SKIP: $file (not found)\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $original = $content;
    
    // Fix namespace declarations
    $content = preg_replace(
        '/namespace VapeshedTransfer\\\\Support;/',
        'namespace Unified\\Support;',
        $content
    );
    
    // Fix use statements
    $content = preg_replace(
        '/use VapeshedTransfer\\\\Support\\\\/',
        'use Unified\\Support\\',
        $content
    );
    
    // Fix class references in strings/comments
    $content = str_replace(
        'VapeshedTransfer\\Support\\',
        'Unified\\Support\\',
        $content
    );
    
    // Fix @package tags
    $content = preg_replace(
        '/@package VapeshedTransfer\\\\Support/',
        '@package Unified\\Support',
        $content
    );
    
    if ($content !== $original) {
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
    echo "✅ All namespaces fixed successfully!\n";
    echo "   Run: php tests/quick_verify.php\n";
    exit(0);
} else {
    echo "⚠ Some files had errors. Please check permissions.\n";
    exit(1);
}
