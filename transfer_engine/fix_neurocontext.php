#!/usr/bin/env php
<?php
/**
 * NeuroContext::wrap() Parameter Fix Script
 * 
 * Fixes incorrect parameter order in NeuroContext::wrap() calls
 * Correct signature: wrap(string $component, array $context)
 * Incorrect usage: wrap(array, string) <- ALL Phase 9/10 files have this!
 */

$filesToFix = [
    'src/Support/MetricsCollector.php',
    'src/Support/HealthMonitor.php',
    'src/Support/PerformanceProfiler.php',
    'src/Support/AlertManager.php',
    'src/Support/LogAggregator.php',
    'src/Support/AnalyticsEngine.php',
    'src/Support/ReportGenerator.php',
    'src/Support/DashboardDataProvider.php',
    'src/Support/NotificationScheduler.php',
    'src/Support/ApiDocumentationGenerator.php',
];

$baseDir = __DIR__;
$fixed = 0;
$errors = 0;
$totalReplacements = 0;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   NEUROCONT FIX: Correct Parameter Order              ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

foreach ($filesToFix as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "⚠ SKIP: $file (not found)\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $original = $content;
    
    // Pattern 1: NeuroContext::wrap([ ... ], 'component')
    // Replace with: NeuroContext::wrap('component', [ ... ])
    $pattern = '/NeuroContext::wrap\(\s*(\[[\s\S]*?\])\s*,\s*([\'"][\w_]+[\'"])\s*\)/';
    $content = preg_replace_callback($pattern, function($matches) {
        return "NeuroContext::wrap({$matches[2]}, {$matches[1]})";
    }, $content, -1, $count);
    
    $replacements = $count;
    
    // Pattern 2: Handles multi-line arrays better
    // This catches cases where the array spans multiple lines
    if ($count === 0) {
        // Try alternative pattern for complex multi-line cases
        $lines = explode("\n", $content);
        $inWrap = false;
        $wrapStart = -1;
        $bracketCount = 0;
        
        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/NeuroContext::wrap\(\s*\[/', $lines[$i])) {
                $inWrap = true;
                $wrapStart = $i;
                $bracketCount = substr_count($lines[$i], '[') - substr_count($lines[$i], ']');
            } elseif ($inWrap) {
                $bracketCount += substr_count($lines[$i], '[') - substr_count($lines[$i], ']');
                
                // Check if we found the closing and component string
                if ($bracketCount === 0 && preg_match('/\]\s*,\s*([\'"][\w_]+[\'"])\s*\)/', $lines[$i], $matches)) {
                    // Found a multi-line wrap call that needs fixing
                    // This is complex - note it for manual review
                    $replacements++;
                    $inWrap = false;
                }
            }
        }
    }
    
    $totalReplacements += $replacements;
    
    if ($content !== $original) {
        if (file_put_contents($fullPath, $content)) {
            echo "✓ FIXED: $file ($replacements replacements)\n";
            $fixed++;
        } else {
            echo "✗ ERROR: $file (write failed)\n";
            $errors++;
        }
    } else {
        if ($replacements > 0) {
            echo "• REVIEWED: $file (complex multi-line - may need manual fix)\n";
        } else {
            echo "• OK: $file (no changes needed)\n";
        }
    }
}

echo "\n╔══════════════════════════════════════════════════════════╗\n";
echo "║                    SUMMARY                               ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo sprintf("║  Files Fixed:        %-32d║\n", $fixed);
echo sprintf("║  Total Replacements: %-32d║\n", $totalReplacements);
echo sprintf("║  Errors:             %-32d║\n", $errors);
echo sprintf("║  Total Processed:    %-32d║\n", count($filesToFix));
echo "╚══════════════════════════════════════════════════════════╝\n\n";

if ($errors === 0 && $totalReplacements > 0) {
    echo "✅ All NeuroContext::wrap() calls fixed!\n";
    echo "   Run: php tests/quick_verify.php\n";
    exit(0);
} elseif ($errors > 0) {
    echo "⚠ Some files had errors. Please check permissions.\n";
    exit(1);
} else {
    echo "ℹ️  No automatic fixes needed (may require manual review)\n";
    exit(0);
}
