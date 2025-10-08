#!/usr/bin/env php
<?php
/**
 * Daily Transfer Calculation Runner
 * 
 * Executes transfer engine calculations for all pilot stores
 * Generates recommendations and logs results for inventory manager review
 * 
 * Usage: php bin/daily_transfer_run.php
 * Cron: 0 6 * * * cd /path/to/transfer_engine && php bin/daily_transfer_run.php >> logs/cron_$(date +\%Y\%m\%d).log 2>&1
 */
declare(strict_types=1);

require __DIR__ . '/../transfer_engine/config/bootstrap.php';

use Unified\Integration\{VendConnection, VendAdapter};
use Unified\Support\{Logger, CacheManager};

// Initialize components
$logger = new Logger(__DIR__ . '/../transfer_engine/logs/');
$cache = new CacheManager(['enabled' => true, 'ttl' => 300]);
$vendConnection = new VendConnection(require __DIR__ . '/../transfer_engine/config/vend.php');
$vendAdapter = new VendAdapter($vendConnection, $logger, $cache);

// Load pilot configuration
$pilotConfigPath = __DIR__ . '/../transfer_engine/config/pilot_stores.php';
$pilotConfig = file_exists($pilotConfigPath) ? require $pilotConfigPath : ['pilot_enabled' => false, 'pilot_stores' => []];

if (!$pilotConfig['pilot_enabled']) {
    echo "Pilot mode not enabled. Exiting.\n";
    $logger->info('Daily transfer run skipped - pilot mode disabled');
    exit(0);
}

$startTime = microtime(true);
$logger->info('Starting daily transfer calculation', [
    'pilot_stores' => count($pilotConfig['pilot_stores']),
    'date' => date('Y-m-d H:i:s'),
]);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   DAILY TRANSFER CALCULATION - PILOT MODE                    ║\n";
echo "║   Date: " . date('Y-m-d H:i:s') . "                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$stats = [
    'total_stores' => count($pilotConfig['pilot_stores']),
    'stores_processed' => 0,
    'low_stock_items' => 0,
    'transfer_opportunities' => 0,
    'errors' => 0,
];

// Process each pilot store
foreach ($pilotConfig['pilot_stores'] as $outletId) {
    try {
        echo "┌─ Processing Store: {$outletId} ────────────────────────────┐\n";
        
        // Get store details
        $outlets = $vendAdapter->getOutlets();
        $store = null;
        foreach ($outlets as $outlet) {
            if ($outlet['id'] === $outletId) {
                $store = $outlet;
                break;
            }
        }
        
        if (!$store) {
            echo "  ⚠️  Store not found in database\n";
            $logger->warning('Store not found', ['outlet_id' => $outletId]);
            $stats['errors']++;
            continue;
        }
        
        echo "  Store: {$store['name']}\n";
        
        // Get low stock items for this store
        $lowStock = $vendAdapter->getLowStockItems($outletId, 100);
        $stats['low_stock_items'] += count($lowStock);
        
        echo "  Low stock items: " . count($lowStock) . "\n";
        
        if (count($lowStock) > 0) {
            echo "  Top 5 items needing attention:\n";
            foreach (array_slice($lowStock, 0, 5) as $idx => $item) {
                $productName = substr($item['product_name'], 0, 50);
                $stock = $item['inventory_level'];
                $reorder = $item['reorder_point'] ?? 0;
                echo "    " . ($idx + 1) . ". {$productName}\n";
                echo "       Stock: {$stock} / Reorder: {$reorder}\n";
            }
        }
        
        // TODO: Generate transfer recommendations by comparing with other stores
        // For now, just identify and log the low stock items
        
        $stats['stores_processed']++;
        echo "  ✓ Store processed successfully\n";
        echo "└────────────────────────────────────────────────────────────┘\n\n";
        
        $logger->info('Processed store successfully', [
            'outlet_id' => $outletId,
            'store_name' => $store['name'],
            'low_stock_count' => count($lowStock),
        ]);
        
    } catch (\Exception $e) {
        $stats['errors']++;
        $logger->error('Failed to process store', [
            'outlet_id' => $outletId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        echo "  ✗ ERROR: {$e->getMessage()}\n";
        echo "└────────────────────────────────────────────────────────────┘\n\n";
    }
}

$duration = round((microtime(true) - $startTime) * 1000, 2);

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    SUMMARY                                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "  Total stores: {$stats['total_stores']}\n";
echo "  Processed successfully: {$stats['stores_processed']}\n";
echo "  Low stock items identified: {$stats['low_stock_items']}\n";
echo "  Transfer opportunities: {$stats['transfer_opportunities']}\n";
echo "  Errors: {$stats['errors']}\n";
echo "  Duration: {$duration}ms\n\n";

$logger->info('Daily transfer calculation complete', array_merge($stats, ['duration_ms' => $duration]));

echo "Daily transfer calculation complete.\n";
echo "Review recommendations and take action as needed.\n\n";

exit($stats['errors'] > 0 ? 1 : 0);
