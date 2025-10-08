#!/usr/bin/env php
<?php
/**
 * Quick test to verify CacheManager flush() fix
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../config/bootstrap.php';

use Unified\Support\Cache;
use Unified\Support\CacheManager;
use Unified\Support\Logger;

// Create test cache directory
$testCacheDir = sys_get_temp_dir() . '/vapeshed_test_cache_' . uniqid();
@mkdir($testCacheDir, 0775, true);

echo "🧪 Testing CacheManager flush() fix\n";
echo "Cache directory: $testCacheDir\n\n";

try {
    // Initialize logger and cache manager
    $logger = new Logger('test_cache', null); // Channel name + no log file (output to stdout)
    $cache = new CacheManager($logger, [
        'driver' => 'file',
        'cache_dir' => $testCacheDir,
        'default_ttl' => 3600,
    ]);
    
    echo "✓ CacheManager initialized\n";
    
    // Test 1: Set tagged value
    echo "\n📝 Test 1: Set tagged value\n";
    $cache->tags(['test'])->set('tagged_key', 'tagged_value');
    echo "   Set: tagged_key = 'tagged_value' with tag 'test'\n";
    
    // Test 2: Get tagged value (should work)
    $value = $cache->tags(['test'])->get('tagged_key');
    echo "   Get: tagged_key = " . var_export($value, true) . "\n";
    
    if ($value === 'tagged_value') {
        echo "   ✓ Get works correctly\n";
    } else {
        echo "   ✗ Get failed! Expected 'tagged_value', got: " . var_export($value, true) . "\n";
        exit(1);
    }
    
    // Test 3: Flush tags
    echo "\n🗑️  Test 3: Flush tags\n";
    $cache->tags(['test'])->flush();
    echo "   Flushed tag 'test'\n";
    
    // Test 4: Get after flush (should return null)
    $value = $cache->tags(['test'])->get('tagged_key');
    echo "   Get after flush: tagged_key = " . var_export($value, true) . "\n";
    
    if ($value === null) {
        echo "   ✓ Flush works correctly! Value was deleted.\n";
    } else {
        echo "   ✗ Flush FAILED! Expected null, got: " . var_export($value, true) . "\n";
        exit(1);
    }
    
    echo "\n✅ ALL TESTS PASSED! flush() fix is working.\n";
    
} catch (Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
} finally {
    // Cleanup
    if (is_dir($testCacheDir)) {
        $files = glob($testCacheDir . '/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($testCacheDir);
        echo "\n🧹 Cleaned up test cache directory\n";
    }
}

exit(0);
