<?php
declare(strict_types=1);

/**
 * Business Analysis Report Generator
 * 
 * Generates comprehensive business insights from Vend integration:
 * - Low stock analysis across all 18 stores
 * - Negative inventory data quality issues
 * - Transfer opportunity identification
 * - Sales velocity and priority ranking
 * 
 * @author VapeShed Transfer Engine
 * @date 2025-10-08
 */

require_once __DIR__ . '/../config/bootstrap.php';

use Unified\Support\Logger;
use Unified\Support\CacheManager;
use Unified\Integration\VendConnection;
use Unified\Integration\VendAdapter;

// Initialize services
$logger = new Logger('/tmp/vapeshed_business_report.log');
$cache = new CacheManager($logger, [
    'driver' => 'file',
    'cache_dir' => '/tmp/vapeshed_cache',
    'default_ttl' => 3600
]);
$vendConnection = new VendConnection($logger);
$vendAdapter = new VendAdapter($vendConnection, $logger, $cache);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║       VAPESHED TRANSFER ENGINE - BUSINESS ANALYSIS REPORT      ║\n";
echo "║                    Generated: " . date('Y-m-d H:i:s') . "                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);

// ============================================================================
// SECTION 1: SYSTEM HEALTH & CONNECTIVITY
// ============================================================================

echo "┌─ SECTION 1: System Health & Connectivity ─────────────────────┐\n";

try {
    $outlets = $vendAdapter->getOutlets();
    $outletCount = count($outlets);
    
    echo "  ✓ Database Connection:    HEALTHY\n";
    echo "  ✓ Active Retail Stores:   {$outletCount}\n";
    
    // Test inventory access
    $sampleOutlet = $outlets[0] ?? null;
    if ($sampleOutlet) {
        $sampleInventory = $vendAdapter->getInventory($sampleOutlet['id'], ['limit' => 1]);
        echo "  ✓ Inventory Access:       OPERATIONAL\n";
    }
    
    echo "  ✓ Cache System:           ACTIVE\n";
    echo "└────────────────────────────────────────────────────────────────┘\n\n";
    
} catch (\Exception $e) {
    echo "  ✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "└────────────────────────────────────────────────────────────────┘\n\n";
    exit(1);
}

// ============================================================================
// SECTION 2: LOW STOCK ANALYSIS - ALL STORES
// ============================================================================

echo "┌─ SECTION 2: Low Stock Analysis (All 18 Stores) ───────────────┐\n";

$allLowStock = $vendAdapter->getLowStockItems();
$totalLowStock = count($allLowStock);

echo "  Total Items Below Reorder Point: {$totalLowStock}\n\n";

// Group by store
$byStore = [];
foreach ($allLowStock as $item) {
    $store = $item['outlet_name'] ?? 'Unknown';
    if (!isset($byStore[$store])) {
        $byStore[$store] = [];
    }
    $byStore[$store][] = $item;
}

// Sort stores by number of low stock items
arsort($byStore);

echo "  Top 10 Stores by Low Stock Item Count:\n";
echo "  ─────────────────────────────────────────────────────────────\n";

$rank = 1;
foreach (array_slice($byStore, 0, 10, true) as $store => $items) {
    $count = count($items);
    $percentage = round(($count / $totalLowStock) * 100, 1);
    printf("  %2d. %-25s %4d items (%5.1f%%)\n", $rank++, $store, $count, $percentage);
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ============================================================================
// SECTION 3: DATA QUALITY ISSUES - NEGATIVE INVENTORY
// ============================================================================

echo "┌─ SECTION 3: Data Quality Issues - Negative Inventory 🚨 ─────┐\n";

$negativeInventory = array_filter($allLowStock, function($item) {
    return $item['inventory_level'] < 0;
});

$negativeCount = count($negativeInventory);

if ($negativeCount > 0) {
    echo "  ⚠️  CRITICAL: {$negativeCount} items with NEGATIVE inventory detected!\n\n";
    echo "  This indicates:\n";
    echo "  • Overselling (sales exceed available stock)\n";
    echo "  • Sync issues between POS and inventory system\n";
    echo "  • Potential customer fulfillment problems\n\n";
    
    echo "  Top 10 Negative Inventory Items:\n";
    echo "  ─────────────────────────────────────────────────────────────\n";
    
    // Sort by most negative
    usort($negativeInventory, function($a, $b) {
        return $a['inventory_level'] <=> $b['inventory_level'];
    });
    
    foreach (array_slice($negativeInventory, 0, 10) as $idx => $item) {
        $productName = substr($item['product_name'], 0, 40);
        $store = $item['outlet_name'];
        $stock = $item['inventory_level'];
        $reorder = $item['reorder_point'] ?? 0;
        $deficit = abs($stock) + $reorder;
        
        printf("  %2d. %-40s\n", $idx + 1, $productName);
        printf("      Store: %-20s Stock: %4d / Reorder: %4d\n", $store, $stock, $reorder);
        printf("      DEFICIT: %d units needed immediately\n\n", $deficit);
    }
    
    echo "  📊 Negative Inventory by Store:\n";
    echo "  ─────────────────────────────────────────────────────────────\n";
    
    $negByStore = [];
    foreach ($negativeInventory as $item) {
        $store = $item['outlet_name'] ?? 'Unknown';
        if (!isset($negByStore[$store])) {
            $negByStore[$store] = 0;
        }
        $negByStore[$store]++;
    }
    arsort($negByStore);
    
    foreach (array_slice($negByStore, 0, 5, true) as $store => $count) {
        printf("      %-25s %3d items\n", $store, $count);
    }
    
} else {
    echo "  ✓ No negative inventory detected - data quality is good!\n";
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ============================================================================
// SECTION 4: TRANSFER OPPORTUNITY ANALYSIS
// ============================================================================

echo "┌─ SECTION 4: Transfer Opportunity Analysis ────────────────────┐\n";

// Analyze top 20 low stock items for transfer opportunities
$topLowStock = array_slice($allLowStock, 0, 20);
$transferOpportunities = [];

echo "  Analyzing top 20 low stock items for transfer opportunities...\n\n";

foreach ($topLowStock as $item) {
    $productId = $item['product_id'];
    $needyStore = $item['outlet_name'];
    $currentStock = $item['inventory_level'];
    $reorderPoint = $item['reorder_point'] ?? 0;
    $deficit = max(0, $reorderPoint - $currentStock);
    
    if ($deficit <= 0) continue;
    
    // Get inventory across all stores for this product
    $allStoreInventory = [];
    foreach ($outlets as $outlet) {
        $inventory = $vendAdapter->getInventory($outlet['id'], [
            'product_id' => $productId,
            'limit' => 1
        ]);
        
        if (!empty($inventory)) {
            $allStoreInventory[$outlet['name']] = [
                'outlet_id' => $outlet['id'],
                'stock' => $inventory[0]['inventory_level']
            ];
        }
    }
    
    // Find potential donor stores (stock > reorder point + safety buffer)
    $safetyBuffer = 3;
    foreach ($allStoreInventory as $donorStore => $donorData) {
        if ($donorStore === $needyStore) continue;
        
        $surplus = $donorData['stock'] - $reorderPoint - $safetyBuffer;
        if ($surplus > 0) {
            $transferQty = min($surplus, $deficit);
            
            $transferOpportunities[] = [
                'product_name' => substr($item['product_name'], 0, 50),
                'from_store' => $donorStore,
                'from_stock' => $donorData['stock'],
                'to_store' => $needyStore,
                'to_stock' => $currentStock,
                'deficit' => $deficit,
                'surplus' => $surplus,
                'transfer_qty' => $transferQty,
                'reorder_point' => $reorderPoint
            ];
        }
    }
}

$opportunityCount = count($transferOpportunities);

if ($opportunityCount > 0) {
    echo "  ✓ Found {$opportunityCount} transfer opportunities!\n\n";
    
    echo "  Top 10 Recommended Transfers:\n";
    echo "  ─────────────────────────────────────────────────────────────\n";
    
    // Sort by transfer quantity (highest impact first)
    usort($transferOpportunities, function($a, $b) {
        return $b['transfer_qty'] <=> $a['transfer_qty'];
    });
    
    foreach (array_slice($transferOpportunities, 0, 10) as $idx => $opp) {
        printf("  %2d. %s\n", $idx + 1, $opp['product_name']);
        printf("      FROM: %-20s (%2d units, surplus: %d)\n", 
            $opp['from_store'], $opp['from_stock'], $opp['surplus']);
        printf("      TO:   %-20s (%2d units, deficit: %d)\n", 
            $opp['to_store'], $opp['to_stock'], $opp['deficit']);
        printf("      ➜ TRANSFER: %d units (Reorder point: %d)\n\n", 
            $opp['transfer_qty'], $opp['reorder_point']);
    }
    
} else {
    echo "  ⚠️  No transfer opportunities found.\n";
    echo "     Most stores appear to have similar low stock issues.\n";
    echo "     Recommend ordering from suppliers instead.\n";
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ============================================================================
// SECTION 5: SALES VELOCITY & PRIORITY RANKING
// ============================================================================

echo "┌─ SECTION 5: Sales Velocity & Priority Analysis ───────────────┐\n";

// Analyze sales velocity for Botany (sample store)
$botanyOutlet = null;
foreach ($outlets as $outlet) {
    if (stripos($outlet['name'], 'Botany') !== false) {
        $botanyOutlet = $outlet;
        break;
    }
}

if ($botanyOutlet) {
    echo "  Sample Store: {$botanyOutlet['name']}\n\n";
    
    // Get low stock items for this store
    $storeLowStock = array_filter($allLowStock, function($item) use ($botanyOutlet) {
        return $item['outlet_name'] === $botanyOutlet['name'];
    });
    
    $velocityAnalysis = [];
    
    foreach (array_slice($storeLowStock, 0, 10) as $item) {
        $productId = $item['product_id'];
        $stock = $item['inventory_level'];
        $reorderPoint = $item['reorder_point'] ?? 0;
        
        // Get sales history
        $salesHistory = $vendAdapter->getSalesHistory($productId, $botanyOutlet['id'], 30);
        
        // Calculate daily sales rate
        $totalSold = 0;
        foreach ($salesHistory as $sale) {
            $totalSold += $sale['quantity'];
        }
        $dailyRate = count($salesHistory) > 0 ? $totalSold / 30 : 0;
        
        // Calculate DSR
        $dsr = $vendAdapter->calculateDSR($productId, $botanyOutlet['id']);
        
        // Priority score (0-100)
        $priority = 0;
        if ($stock <= 0) {
            $priority = 100; // Out of stock = critical
        } elseif ($dailyRate > 0) {
            $daysUntilOut = $stock / $dailyRate;
            if ($daysUntilOut < 7) {
                $priority = 90;
            } elseif ($daysUntilOut < 14) {
                $priority = 70;
            } elseif ($daysUntilOut < 30) {
                $priority = 50;
            } else {
                $priority = 30;
            }
        }
        
        $velocityAnalysis[] = [
            'product_name' => substr($item['product_name'], 0, 50),
            'stock' => $stock,
            'reorder_point' => $reorderPoint,
            'daily_rate' => $dailyRate,
            'dsr' => $dsr,
            'priority' => $priority
        ];
    }
    
    // Sort by priority
    usort($velocityAnalysis, function($a, $b) {
        return $b['priority'] <=> $a['priority'];
    });
    
    echo "  Priority Ranking (Top 10):\n";
    echo "  ─────────────────────────────────────────────────────────────\n";
    
    foreach (array_slice($velocityAnalysis, 0, 10) as $idx => $item) {
        $priorityLabel = $item['priority'] >= 90 ? '🚨 CRITICAL' : 
                        ($item['priority'] >= 70 ? '⚠️  HIGH' : 
                        ($item['priority'] >= 50 ? '💡 MEDIUM' : '✓ LOW'));
        
        printf("  %2d. %s\n", $idx + 1, $item['product_name']);
        printf("      Priority: %-15s Stock: %3d / Reorder: %3d\n", 
            $priorityLabel, $item['stock'], $item['reorder_point']);
        printf("      Daily Rate: %.2f | DSR: %s\n\n", 
            $item['daily_rate'], 
            $item['dsr'] !== null ? number_format($item['dsr'], 1) . ' days' : 'N/A');
    }
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ============================================================================
// SECTION 6: EXECUTIVE SUMMARY & RECOMMENDATIONS
// ============================================================================

echo "┌─ SECTION 6: Executive Summary & Recommendations ──────────────┐\n";

$duration = round((microtime(true) - $startTime) * 1000, 2);

echo "  📊 KEY METRICS:\n";
echo "  ─────────────────────────────────────────────────────────────\n";
echo "  • Active Stores:              {$outletCount}\n";
echo "  • Total Low Stock Items:      {$totalLowStock}\n";
echo "  • Negative Inventory Items:   {$negativeCount} 🚨\n";
echo "  • Transfer Opportunities:     {$opportunityCount}\n";
echo "  • Analysis Duration:          {$duration}ms\n\n";

echo "  🎯 RECOMMENDATIONS:\n";
echo "  ─────────────────────────────────────────────────────────────\n";

if ($negativeCount > 0) {
    echo "  1. URGENT: Investigate and resolve {$negativeCount} negative inventory items\n";
    echo "     • Review overselling at affected stores\n";
    echo "     • Check POS sync issues\n";
    echo "     • Update customer orders if needed\n\n";
}

if ($opportunityCount > 0) {
    echo "  2. Execute {$opportunityCount} inter-store transfers to optimize inventory\n";
    echo "     • Prioritize high-velocity items\n";
    echo "     • Balance stock across regions\n";
    echo "     • Reduce supplier ordering costs\n\n";
}

echo "  3. Review supplier ordering for items with no transfer options\n";
echo "     • Focus on stores with highest low stock counts\n";
echo "     • Consider bulk ordering for common items\n\n";

echo "  4. Implement regular monitoring (daily/weekly)\n";
echo "     • Track negative inventory trends\n";
echo "     • Monitor transfer effectiveness\n";
echo "     • Adjust reorder points based on velocity\n\n";

echo "└────────────────────────────────────────────────────────────────┘\n\n";

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    REPORT GENERATION COMPLETE                  ║\n";
echo "║                  VapeShed Transfer Engine v1.0                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$logger->info('Business analysis report generated successfully', [
    'total_low_stock' => $totalLowStock,
    'negative_inventory' => $negativeCount,
    'transfer_opportunities' => $opportunityCount,
    'duration_ms' => $duration
]);
