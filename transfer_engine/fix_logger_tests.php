#!/usr/bin/env php
<?php
/**
 * Fix Logger instantiation in comprehensive test
 * Logger requires: new Logger($channel, $logFile)
 */

$file = __DIR__ . '/tests/comprehensive_phase_test.php';

if (!file_exists($file)) {
    die("Error: File not found: $file\n");
}

$content = file_get_contents($file);

// Replace all instances of new Logger(storage_path('logs'))
// with new Logger('test', storage_path('logs'))
$content = str_replace(
    "new Logger(storage_path('logs'))",
    "new Logger('test', storage_path('logs'))",
    $content
);

file_put_contents($file, $content);

echo "✅ Fixed all Logger instantiations in comprehensive_phase_test.php\n";
echo "   Changed: new Logger(storage_path('logs'))\n";
echo "   To:      new Logger('test', storage_path('logs'))\n";
