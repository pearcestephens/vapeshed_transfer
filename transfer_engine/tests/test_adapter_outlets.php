#!/usr/bin/env php
<?php
/**
 * Direct VendAdapter outlet test
 */

require_once __DIR__ . '/../config/bootstrap.php';

use Unified\Support\Logger;
use Unified\Support\CacheManager;
use Unified\Integration\VendConnection;
use Unified\Integration\VendAdapter;

echo "Testing VendAdapter getOutlets()...\n\n";

try {
    $logger = new Logger('test', '/tmp');
    $cache = new CacheManager($logger);
    $conn = new VendConnection($logger);
    $adapter = new VendAdapter($conn, $logger, $cache);
    
    echo "Getting outlets...\n";
    $outlets = $adapter->getOutlets(true);
    
    echo "Found: " . count($outlets) . " outlets\n\n";
    
    if (count($outlets) > 0) {
        foreach ($outlets as $outlet) {
            echo "- {$outlet['name']} ({$outlet['outlet_code']})\n";
        }
    } else {
        echo "NO OUTLETS FOUND!\n";
        echo "This suggests a problem with the query or caching.\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
