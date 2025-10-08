#!/usr/bin/env php
<?php
/**
 * Quick outlet query test
 */

require_once __DIR__ . '/../config/bootstrap.php';

use Unified\Support\Logger;
use Unified\Integration\VendConnection;

$logger = new Logger('test', storage_path('logs'));
$conn = new VendConnection($logger);

echo "Testing outlet query...\n\n";

$sql = "SELECT id, name, store_code, deleted_at, is_warehouse 
        FROM vend_outlets 
        WHERE deleted_at = '0000-00-00 00:00:00' AND is_warehouse = 0 
        LIMIT 5";

try {
    $results = $conn->query($sql, []);
    echo "Results found: " . count($results) . "\n\n";
    
    foreach ($results as $row) {
        echo "- {$row['name']} ({$row['store_code']})\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
