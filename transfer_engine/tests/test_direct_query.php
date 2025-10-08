#!/usr/bin/env php
<?php
/**
 * Bypass cache test
 */

require_once __DIR__ . '/../config/bootstrap.php';

use Unified\Support\Logger;
use Unified\Integration\VendConnection;

echo "Testing direct SQL query through VendConnection...\n\n";

try {
    $logger = new Logger('test', '/tmp');
    $conn = new VendConnection($logger);
    
    // Load config to get table name
    $config = require __DIR__ . '/../config/vend.php';
    $tableName = $config['tables']['outlets'];
    
    echo "Table name: {$tableName}\n\n";
    
    $sql = "SELECT 
                id,
                name,
                store_code as outlet_code,
                (deleted_at = '0000-00-00 00:00:00') as is_active,
                CONCAT_WS(', ', 
                    physical_address_1, 
                    physical_suburb, 
                    physical_city,
                    physical_postcode
                ) as address,
                physical_phone_number as phone,
                email,
                is_warehouse
            FROM {$tableName}
            WHERE deleted_at = '0000-00-00 00:00:00' AND is_warehouse = 0
            ORDER BY name ASC";
    
    echo "Executing query...\n";
    $results = $conn->query($sql, []);
    
    echo "Results: " . count($results) . " outlets\n\n";
    
    if (count($results) > 0) {
        foreach (array_slice($results, 0, 5) as $outlet) {
            echo "- {$outlet['name']} ({$outlet['outlet_code']})\n";
        }
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
