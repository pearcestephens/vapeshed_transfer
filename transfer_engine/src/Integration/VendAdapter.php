<?php
declare(strict_types=1);
namespace Unified\Integration;

use Unified\Support\Logger;
use Unified\Support\CacheManager;
use Unified\Support\NeuroContext;

/**
 * VendAdapter - High-Level Vend Data Access Layer
 * 
 * Provides simplified methods for accessing Vend database with built-in
 * caching, error handling, and performance optimization.
 * 
 * Features:
 * - Inventory data retrieval
 * - Sales history analysis
 * - Outlet/store management
 * - Product catalog access
 * - Automatic caching
 * - DSR (Days Sales Remaining) calculations
 * 
 * @package Unified\Integration
 * @version 1.0.0
 * @date 2025-10-08
 */
class VendAdapter
{
    private VendConnection $connection;
    private Logger $logger;
    private CacheManager $cache;
    private NeuroContext $neuro;
    private array $config;
    
    /**
     * Create Vend adapter
     * 
     * @param VendConnection $connection Database connection
     * @param Logger $logger Logger instance
     * @param CacheManager $cache Cache manager
     * @param array $config Configuration options
     */
    public function __construct(
        VendConnection $connection,
        Logger $logger,
        CacheManager $cache,
        array $config = []
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->neuro = new NeuroContext();
        
        // Load config
        $configFile = __DIR__ . '/../../config/vend.php';
        if (file_exists($configFile)) {
            $loadedConfig = require $configFile;
            $this->config = array_merge($loadedConfig, $config);
        } else {
            $this->config = $config;
        }
        
        $this->logger->info('vend.adapter.initialized');
    }
    
    /**
     * Get inventory levels for a specific outlet
     * 
     * @param string $outletId Outlet ID
     * @param array $filters Optional filters (product_id, sku, etc.)
     * @return array Inventory records
     */
    public function getInventory(string $outletId, array $filters = []): array
    {
        $cacheKey = "inventory:{$outletId}:" . md5(json_encode($filters));
        
        return $this->cache->remember($cacheKey, 300, function() use ($outletId, $filters) {
            $startTime = microtime(true);
            
            $query = "
                SELECT 
                    i.product_id,
                    i.outlet_id,
                    i.inventory_level,
                    i.reorder_point,
                    i.reorder_amount,
                    p.sku,
                    p.name as product_name,
                    p.active,
                    p.price_including_tax as retail_price
                FROM {$this->config['tables']['inventory']} i
                INNER JOIN {$this->config['tables']['products']} p ON i.product_id = p.id
                WHERE i.outlet_id = :outlet_id
                    AND p.active = 1
            ";
            
            $params = ['outlet_id' => $outletId];
            
            // Add filters
            if (!empty($filters['product_id'])) {
                $query .= " AND i.product_id = :product_id";
                $params['product_id'] = $filters['product_id'];
            }
            
            if (!empty($filters['sku'])) {
                $query .= " AND p.sku = :sku";
                $params['sku'] = $filters['sku'];
            }
            
            if (!empty($filters['low_stock_only'])) {
                $query .= " AND i.inventory_level <= i.reorder_point";
            }
            
            $query .= " ORDER BY p.name ASC";
            
            $results = $this->connection->query($query, $params);
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('vend.inventory.retrieved', [
                'outlet_id' => $outletId,
                'record_count' => count($results),
                'duration_ms' => round($duration, 2),
                'filters' => $filters,
            ]);
            
            return $results;
        });
    }
    
    /**
     * Get sales history for a product
     * 
     * @param string $productId Product ID
     * @param int $days Number of days to look back
     * @param string|null $outletId Optional specific outlet
     * @return array Sales records
     */
    public function getSalesHistory(string $productId, int $days = 30, ?string $outletId = null): array
    {
        $cacheKey = "sales:{$productId}:{$days}:" . ($outletId ?? 'all');
        
        return $this->cache->remember($cacheKey, 300, function() use ($productId, $days, $outletId) {
            $startTime = microtime(true);
            
            $query = "
                SELECT 
                    sli.product_id,
                    s.outlet_id,
                    DATE(s.sale_date) as sale_date,
                    sli.quantity,
                    sli.price_total as total_price,
                    sli.cost_total as cost,
                    o.name as outlet_name
                FROM {$this->config['tables']['sales_line_items']} sli
                INNER JOIN {$this->config['tables']['sales']} s ON sli.sales_increment_id = s.increment_id
                INNER JOIN {$this->config['tables']['outlets']} o ON s.outlet_id = o.id
                WHERE sli.product_id = :product_id
                    AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                    AND sli.status = 'CONFIRMED'
            ";
            
            $params = [
                'product_id' => $productId,
                'days' => $days,
            ];
            
            if ($outletId !== null) {
                $query .= " AND s.outlet_id = :outlet_id";
                $params['outlet_id'] = $outletId;
            }
            
            $query .= " ORDER BY s.sale_date DESC";
            
            $results = $this->connection->query($query, $params);
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('vend.sales.retrieved', [
                'product_id' => $productId,
                'days' => $days,
                'outlet_id' => $outletId,
                'record_count' => count($results),
                'duration_ms' => round($duration, 2),
            ]);
            
            return $results;
        });
    }
    
    /**
     * Calculate Days Sales Remaining (DSR) for a product at an outlet
     * 
     * @param string $productId Product ID
     * @param string $outletId Outlet ID
     * @param int $historyDays Days of sales history to use
     * @return array DSR calculation result
     */
    public function calculateDSR(string $productId, string $outletId, int $historyDays = 30): array
    {
        $startTime = microtime(true);
        
        // Get current inventory
        $inventory = $this->getInventory($outletId, ['product_id' => $productId]);
        
        if (empty($inventory)) {
            return [
                'product_id' => $productId,
                'outlet_id' => $outletId,
                'dsr' => null,
                'current_stock' => 0,
                'daily_sales_rate' => 0,
                'error' => 'Product not found in inventory',
            ];
        }
        
        $currentStock = (float) $inventory[0]['inventory_level'];
        
        // Get sales history
        $salesHistory = $this->getSalesHistory($productId, $historyDays, $outletId);
        
        if (empty($salesHistory)) {
            return [
                'product_id' => $productId,
                'outlet_id' => $outletId,
                'dsr' => null,
                'current_stock' => $currentStock,
                'daily_sales_rate' => 0,
                'message' => 'No sales history available',
            ];
        }
        
        // Calculate total quantity sold
        $totalQuantity = array_sum(array_column($salesHistory, 'quantity'));
        
        // Calculate daily sales rate
        $dailySalesRate = $totalQuantity / $historyDays;
        
        // Calculate DSR
        $dsr = $dailySalesRate > 0 ? ($currentStock / $dailySalesRate) : null;
        
        $duration = (microtime(true) - $startTime) * 1000;
        
        $result = [
            'product_id' => $productId,
            'outlet_id' => $outletId,
            'current_stock' => $currentStock,
            'total_sales' => $totalQuantity,
            'daily_sales_rate' => round($dailySalesRate, 2),
            'dsr' => $dsr !== null ? round($dsr, 1) : null,
            'history_days' => $historyDays,
            'calculation_time_ms' => round($duration, 2),
        ];
        
        $this->logger->debug('vend.dsr.calculated', $result);
        
        return $result;
    }
    
    /**
     * Get all outlets/stores
     * 
     * @param bool $activeOnly Only return active outlets
     * @return array Outlet records
     */
    public function getOutlets(bool $activeOnly = true): array
    {
        $cacheKey = "outlets:" . ($activeOnly ? 'active' : 'all');
        
        return $this->cache->remember($cacheKey, 600, function() use ($activeOnly) {
            $query = "
                SELECT 
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
                FROM {$this->config['tables']['outlets']}
            ";
            
            if ($activeOnly) {
                // Active stores have deleted_at = '0000-00-00 00:00:00' (zero date)
                // Real timestamps mean deleted/inactive
                $query .= " WHERE deleted_at = '0000-00-00 00:00:00' AND is_warehouse = 0";
            }
            
            $query .= " ORDER BY name ASC";
            
            $results = $this->connection->query($query);
            
            // Debug logging
            $this->logger->debug('vend.outlets.query_executed', [
                'active_only' => $activeOnly,
                'result_count' => count($results),
                'sql' => $query
            ]);
            
            $this->logger->info('vend.outlets.retrieved', [
                'count' => count($results),
                'active_only' => $activeOnly,
            ]);
            
            return $results;
        });
    }
    
    /**
     * Get products with optional filters
     * 
     * @param array $filters Filters (active, sku, name, etc.)
     * @return array Product records
     */
    public function getProducts(array $filters = []): array
    {
        $cacheKey = "products:" . md5(json_encode($filters));
        
        return $this->cache->remember($cacheKey, 600, function() use ($filters) {
            $query = "
                SELECT 
                    p.id,
                    p.sku,
                    p.name,
                    p.description,
                    p.active,
                    p.price_including_tax as retail_price,
                    0 as supply_price,
                    p.brand_id,
                    p.supplier_code as supplier_id,
                    p.brand as brand_name,
                    '' as supplier_name
                FROM {$this->config['tables']['products']} p
                WHERE 1=1
            ";
            
            $params = [];
            
            // Add filters
            if (isset($filters['active'])) {
                $query .= " AND p.active = :active";
                $params['active'] = $filters['active'] ? 1 : 0;
            }
            
            if (!empty($filters['sku'])) {
                $query .= " AND p.sku = :sku";
                $params['sku'] = $filters['sku'];
            }
            
            if (!empty($filters['name'])) {
                $query .= " AND p.name LIKE :name";
                $params['name'] = '%' . $filters['name'] . '%';
            }
            
            if (!empty($filters['brand_id'])) {
                $query .= " AND p.brand_id = :brand_id";
                $params['brand_id'] = $filters['brand_id'];
            }
            
            if (!empty($filters['supplier_id'])) {
                $query .= " AND p.supplier_id = :supplier_id";
                $params['supplier_id'] = $filters['supplier_id'];
            }
            
            // Limit results if specified
            $limit = $filters['limit'] ?? 1000;
            $query .= " ORDER BY p.name ASC LIMIT :limit";
            $params['limit'] = $limit;
            
            $results = $this->connection->query($query, $params);
            
            $this->logger->info('vend.products.retrieved', [
                'count' => count($results),
                'filters' => $filters,
            ]);
            
            return $results;
        });
    }
    
    /**
     * Get low stock items across all outlets
     * 
     * @param int $threshold Percentage below reorder point (default 100%)
     * @return array Low stock items
     */
    public function getLowStockItems(int $threshold = 100): array
    {
        $cacheKey = "low_stock:{$threshold}";
        
        return $this->cache->remember($cacheKey, 180, function() use ($threshold) {
            $query = "
                SELECT 
                    i.product_id,
                    i.outlet_id,
                    i.inventory_level,
                    i.reorder_point,
                    p.sku,
                    p.name as product_name,
                    o.name as outlet_name,
                    (i.inventory_level / NULLIF(i.reorder_point, 0) * 100) as stock_percentage
                FROM {$this->config['tables']['inventory']} i
                INNER JOIN {$this->config['tables']['products']} p ON i.product_id = p.id
                INNER JOIN {$this->config['tables']['outlets']} o ON i.outlet_id = o.id
                WHERE p.active = 1
                    AND i.reorder_point > 0
                    AND i.inventory_level <= (i.reorder_point * (:threshold / 100))
                ORDER BY stock_percentage ASC, o.name ASC
            ";
            
            $results = $this->connection->query($query, ['threshold' => $threshold]);
            
            $this->logger->info('vend.low_stock.retrieved', [
                'count' => count($results),
                'threshold' => $threshold,
            ]);
            
            return $results;
        });
    }
    
    /**
     * Get adapter statistics
     * 
     * @return array Adapter stats
     */
    public function getStats(): array
    {
        return [
            'connection' => $this->connection->getStats(),
            'cache' => $this->cache->getStats(),
        ];
    }
}
