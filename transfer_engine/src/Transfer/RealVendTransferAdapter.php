<?php
declare(strict_types=1);

namespace Unified\Transfer;

use Unified\Support\Logger;
use Unified\Integration\VendAdapter;

/**
 * RealVendTransferAdapter - Phase 11.3
 * 
 * Replaces LegacyAdapter with real Vend production data integration.
 * Generates transfer candidates using actual inventory levels, sales history,
 * and DSR calculations from the Vend database.
 * 
 * This adapter bridges the gap between VendAdapter (data layer) and
 * TransferService (business logic layer).
 * 
 * @package Unified\Transfer
 * @version 1.0.0
 * @date 2025-10-08
 */
final class RealVendTransferAdapter
{
    private const DEFAULT_DSR_LOOKBACK_DAYS = 30;
    private const DEFAULT_SAFETY_BUFFER = 3;
    private const MIN_TRANSFER_QTY = 1;
    
    public function __construct(
        private Logger $logger,
        private VendAdapter $vendAdapter
    ) {}

    /**
     * Build transfer candidates from real Vend data
     * 
     * Process:
     * 1. Get all active outlets
     * 2. Identify low stock items across all stores
     * 3. For each low stock item, find potential donor stores
     * 4. Calculate optimal transfer quantities based on DSR
     * 5. Return normalized candidate array for TransferService
     * 
     * @param array $opts Options: ['limit' => int, 'store_id' => string, 'threshold' => int]
     * @return array<int,array> Transfer candidates with normalized structure
     */
    public function candidates(array $opts = []): array
    {
        $limit = $opts['limit'] ?? 100;
        $specificStore = $opts['store_id'] ?? null;
        $threshold = $opts['threshold'] ?? 10; // Stock level threshold
        
        $this->logger->info('real_vend_adapter.candidates.start', [
            'limit' => $limit,
            'store_id' => $specificStore,
            'threshold' => $threshold
        ]);
        
        $startTime = microtime(true);
        
        // Step 1: Get all active outlets
        $outlets = $this->vendAdapter->getOutlets();
        $this->logger->info('real_vend_adapter.outlets_loaded', [
            'count' => count($outlets)
        ]);
        
        // Step 2: Get low stock items
        $lowStockItems = $this->vendAdapter->getLowStockItems($threshold);
        $this->logger->info('real_vend_adapter.low_stock_loaded', [
            'count' => count($lowStockItems)
        ]);
        
        // Step 3: Group low stock by product
        $itemsByProduct = [];
        foreach ($lowStockItems as $item) {
            $productId = $item['product_id'];
            $outletName = $item['outlet_name'];
            
            if (!isset($itemsByProduct[$productId])) {
                $itemsByProduct[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $item['product_name'],
                    'sku' => $item['sku'] ?? $productId,
                    'reorder_point' => $item['reorder_point'] ?? 0,
                    'stores' => []
                ];
            }
            
            $itemsByProduct[$productId]['stores'][$outletName] = [
                'outlet_name' => $outletName,
                'outlet_id' => $item['outlet_id'] ?? null,
                'stock' => $item['inventory_level'],
                'reorder_point' => $item['reorder_point'] ?? 0
            ];
        }
        
        $this->logger->info('real_vend_adapter.products_grouped', [
            'unique_products' => count($itemsByProduct)
        ]);
        
        // Step 4: Generate transfer candidates
        $candidates = [];
        $processed = 0;
        
        foreach ($itemsByProduct as $productId => $productData) {
            if ($processed >= $limit) break;
            
            // Get inventory across ALL stores for this product (not just low stock)
            $allStoreInventory = $this->getProductInventoryAllStores($productId, $outlets);
            
            // Find receiver stores (need stock)
            $receivers = array_filter($productData['stores'], function($store) use ($productData) {
                $deficit = $productData['reorder_point'] - $store['stock'];
                return $deficit > 0;
            });
            
            // Find donor stores (have surplus)
            $donors = array_filter($allStoreInventory, function($store) use ($productData) {
                $surplus = $store['stock'] - $productData['reorder_point'] - self::DEFAULT_SAFETY_BUFFER;
                return $surplus > 0;
            });
            
            // Match donors to receivers
            foreach ($receivers as $receiverName => $receiver) {
                foreach ($donors as $donorName => $donor) {
                    if ($donorName === $receiverName) continue; // Same store
                    
                    $deficit = $productData['reorder_point'] - $receiver['stock'];
                    $surplus = $donor['stock'] - $productData['reorder_point'] - self::DEFAULT_SAFETY_BUFFER;
                    
                    if ($surplus < self::MIN_TRANSFER_QTY) continue;
                    
                    // Calculate optimal transfer quantity
                    $transferQty = min($deficit, $surplus);
                    
                    if ($transferQty >= self::MIN_TRANSFER_QTY) {
                        // Get sales velocity for both stores
                        $donorDSR = $this->calculateDSRForStore($productId, $donor['outlet_id']);
                        $receiverDSR = $this->calculateDSRForStore($productId, $receiver['outlet_id']);
                        
                        $candidates[] = [
                            'sku' => $productData['sku'],
                            'product_id' => $productId,
                            'product_name' => $productData['product_name'],
                            'donor_outlet' => $donorName,
                            'donor_outlet_id' => $donor['outlet_id'],
                            'receiver_outlet' => $receiverName,
                            'receiver_outlet_id' => $receiver['outlet_id'],
                            'qty' => $transferQty,
                            'donor_stock' => $donor['stock'],
                            'receiver_stock' => $receiver['stock'],
                            'donor_avg_daily' => $donorDSR['daily_rate'] ?? 0.0,
                            'receiver_avg_daily' => $receiverDSR['daily_rate'] ?? 0.0,
                            'donor_dsr' => $donorDSR['dsr'] ?? null,
                            'receiver_dsr' => $receiverDSR['dsr'] ?? null,
                            'reorder_point' => $productData['reorder_point'],
                            'deficit' => $deficit,
                            'surplus' => $surplus
                        ];
                        
                        $processed++;
                        if ($processed >= $limit) break 2;
                    }
                }
            }
        }
        
        $duration = (microtime(true) - $startTime) * 1000;
        
        $this->logger->info('real_vend_adapter.candidates.complete', [
            'candidates_generated' => count($candidates),
            'duration_ms' => round($duration, 2),
            'products_processed' => count($itemsByProduct)
        ]);
        
        return $candidates;
    }
    
    /**
     * Get inventory levels for a product across all stores
     * 
     * @param string $productId Product ID
     * @param array $outlets List of all outlets
     * @return array Store inventory keyed by outlet name
     */
    private function getProductInventoryAllStores(string $productId, array $outlets): array
    {
        $inventory = [];
        
        foreach ($outlets as $outlet) {
            $outletId = $outlet['id'];
            $outletName = $outlet['name'];
            
            $items = $this->vendAdapter->getInventory($outletId, [
                'product_id' => $productId,
                'limit' => 1
            ]);
            
            if (!empty($items)) {
                $inventory[$outletName] = [
                    'outlet_id' => $outletId,
                    'outlet_name' => $outletName,
                    'stock' => $items[0]['inventory_level']
                ];
            }
        }
        
        return $inventory;
    }
    
    /**
     * Calculate DSR (Days Sales Remaining) for a product at a specific store
     * 
     * @param string $productId Product ID
     * @param string $outletId Outlet ID
     * @return array DSR data: ['dsr' => float|null, 'daily_rate' => float]
     */
    private function calculateDSRForStore(string $productId, string $outletId): array
    {
        // Get sales history for the last 30 days
        $salesHistory = $this->vendAdapter->getSalesHistory(
            $productId,
            self::DEFAULT_DSR_LOOKBACK_DAYS,
            $outletId
        );
        
        // Calculate daily sales rate
        $totalSold = 0;
        foreach ($salesHistory as $sale) {
            $totalSold += $sale['quantity'];
        }
        
        $dailyRate = $totalSold / self::DEFAULT_DSR_LOOKBACK_DAYS;
        
        // Get current stock
        $inventory = $this->vendAdapter->getInventory($outletId, [
            'product_id' => $productId,
            'limit' => 1
        ]);
        
        $currentStock = !empty($inventory) ? $inventory[0]['inventory_level'] : 0;
        
        // Calculate DSR
        $dsr = null;
        if ($dailyRate > 0) {
            $dsr = $currentStock / $dailyRate;
        }
        
        return [
            'dsr' => $dsr,
            'daily_rate' => $dailyRate,
            'current_stock' => $currentStock,
            'total_sold_30d' => $totalSold
        ];
    }
    
    /**
     * Get transfer candidates for a specific store
     * 
     * @param string $storeId Store outlet ID
     * @param array $opts Options: ['limit' => int, 'threshold' => int]
     * @return array Transfer candidates for this store
     */
    public function candidatesForStore(string $storeId, array $opts = []): array
    {
        $opts['store_id'] = $storeId;
        $allCandidates = $this->candidates($opts);
        
        // Filter to only candidates involving this store
        return array_filter($allCandidates, function($candidate) use ($storeId) {
            return $candidate['donor_outlet_id'] === $storeId 
                || $candidate['receiver_outlet_id'] === $storeId;
        });
    }
    
    /**
     * Get statistics about potential transfers
     * 
     * @return array Statistics: total_opportunities, total_units, stores_involved
     */
    public function getTransferStatistics(): array
    {
        $candidates = $this->candidates(['limit' => 1000]);
        
        $totalUnits = array_sum(array_column($candidates, 'qty'));
        
        $storesInvolved = [];
        foreach ($candidates as $candidate) {
            $storesInvolved[$candidate['donor_outlet']] = true;
            $storesInvolved[$candidate['receiver_outlet']] = true;
        }
        
        return [
            'total_opportunities' => count($candidates),
            'total_units' => $totalUnits,
            'stores_involved' => count($storesInvolved),
            'avg_transfer_qty' => count($candidates) > 0 ? $totalUnits / count($candidates) : 0
        ];
    }
}
