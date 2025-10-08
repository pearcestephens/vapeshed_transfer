#!/usr/bin/env php
<?php
/**
 * Generate Daily Transfer Report
 * 
 * Creates a formatted report of transfer recommendations for inventory manager
 * Can be emailed or saved to file for review
 * 
 * Usage: php bin/generate_daily_report.php [--email] [--save]
 */
declare(strict_types=1);

require __DIR__ . '/../transfer_engine/config/bootstrap.php';

use Unified\Integration\{VendConnection, VendAdapter};
use Unified\Support\{Logger, CacheManager};

// Parse arguments
$sendEmail = in_array('--email', $argv ?? []);
$saveToFile = in_array('--save', $argv ?? []);

// Initialize components
$logger = new Logger(__DIR__ . '/../transfer_engine/logs/');
$cache = new CacheManager(['enabled' => true, 'ttl' => 300]);
$vendConnection = new VendConnection(require __DIR__ . '/../transfer_engine/config/vend.php');
$vendAdapter = new VendAdapter($vendConnection, $logger, $cache);

// Load pilot configuration
$pilotConfigPath = __DIR__ . '/../transfer_engine/config/pilot_stores.php';
$pilotConfig = file_exists($pilotConfigPath) ? require $pilotConfigPath : ['pilot_enabled' => false];

if (!$pilotConfig['pilot_enabled']) {
    echo "Pilot mode not enabled. Exiting.\n";
    exit(0);
}

$reportDate = date('Y-m-d');
$report = [];
$report[] = "╔══════════════════════════════════════════════════════════════╗";
$report[] = "║         DAILY TRANSFER RECOMMENDATIONS REPORT                ║";
$report[] = "║         Date: {$reportDate}                                  ║";
$report[] = "╚══════════════════════════════════════════════════════════════╝";
$report[] = "";

$totalLowStock = 0;
$criticalItems = 0;

foreach ($pilotConfig['pilot_stores'] as $outletId) {
    try {
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
            continue;
        }
        
        $report[] = "┌────────────────────────────────────────────────────────────┐";
        $report[] = "│ STORE: " . str_pad($store['name'], 52) . "│";
        $report[] = "└────────────────────────────────────────────────────────────┘";
        $report[] = "";
        
        // Get low stock items
        $lowStock = $vendAdapter->getLowStockItems($outletId, 20);
        $totalLowStock += count($lowStock);
        
        if (count($lowStock) === 0) {
            $report[] = "  ✓ No low stock items - inventory levels healthy";
            $report[] = "";
            continue;
        }
        
        $report[] = "  Low Stock Items: " . count($lowStock);
        $report[] = "";
        $report[] = "  Top Priority Items:";
        $report[] = "  " . str_repeat("─", 56);
        
        foreach (array_slice($lowStock, 0, 10) as $idx => $item) {
            $productName = substr($item['product_name'], 0, 45);
            $stock = $item['inventory_level'];
            $reorder = $item['reorder_point'] ?? 0;
            $deficit = $reorder - $stock;
            
            if ($stock <= 0) {
                $criticalItems++;
                $priority = "🚨 CRITICAL";
            } elseif ($deficit > 5) {
                $priority = "⚠️  HIGH";
            } else {
                $priority = "💡 MEDIUM";
            }
            
            $report[] = "  " . ($idx + 1) . ". {$priority} {$productName}";
            $report[] = "     Stock: {$stock} / Reorder: {$reorder} (Need: {$deficit} units)";
            $report[] = "";
        }
        
        if (count($lowStock) > 10) {
            $remaining = count($lowStock) - 10;
            $report[] = "  ... and {$remaining} more items below reorder point";
            $report[] = "";
        }
        
    } catch (\Exception $e) {
        $report[] = "  ✗ Error processing store: {$e->getMessage()}";
        $report[] = "";
    }
}

$report[] = "╔══════════════════════════════════════════════════════════════╗";
$report[] = "║                      SUMMARY                                 ║";
$report[] = "╚══════════════════════════════════════════════════════════════╝";
$report[] = "  Total low stock items: {$totalLowStock}";
$report[] = "  Critical items (zero/negative): {$criticalItems}";
$report[] = "";
$report[] = "ACTION REQUIRED:";
$report[] = "  1. Review critical items and arrange emergency restocking";
$report[] = "  2. Check other stores for surplus inventory to transfer";
$report[] = "  3. Contact suppliers for urgent replenishment if needed";
$report[] = "";
$report[] = "Generated: " . date('Y-m-d H:i:s');
$report[] = "";

$reportText = implode("\n", $report);

// Output to console
echo $reportText;

// Save to file if requested
if ($saveToFile) {
    $filename = __DIR__ . '/../transfer_engine/logs/daily_report_' . $reportDate . '.txt';
    file_put_contents($filename, $reportText);
    echo "Report saved to: {$filename}\n";
}

// Send email if requested
if ($sendEmail && isset($pilotConfig['notification_email'])) {
    $to = $pilotConfig['notification_email'];
    $subject = "Daily Transfer Recommendations - {$reportDate}";
    $headers = "From: noreply@vapeshed.co.nz\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    if (mail($to, $subject, $reportText, $headers)) {
        echo "Report emailed to: {$to}\n";
    } else {
        echo "Failed to send email to: {$to}\n";
    }
}

exit(0);
