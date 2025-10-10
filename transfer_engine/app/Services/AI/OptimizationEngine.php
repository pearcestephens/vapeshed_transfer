<?php

/**
 * Optimization Engine
 * 
 * AI-powered optimization engine for transfer route planning, timing optimization,
 * and inventory allocation. Uses machine learning insights to recommend optimal
 * transfer strategies.
 * 
 * @package     VapeShed Transfer Engine
 * @subpackage  Services\AI
 * @version     1.0.0
 * @author      Ecigdis Limited Engineering Team
 * @copyright   2025 Ecigdis Limited
 */

namespace App\Services\AI;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Cache;
use App\Services\AI\ForecastingService;
use App\Services\AI\PatternRecognition;

/**
 * Optimization Engine
 * 
 * Provides optimization capabilities for:
 * - Transfer route selection (minimize cost/time)
 * - Transfer timing (optimal time windows)
 * - Inventory allocation (balance across stores)
 * - Multi-store consolidation (reduce transfers)
 */
class OptimizationEngine
{
    private Database $db;
    private Logger $logger;
    private Cache $cache;
    private ForecastingService $forecasting;
    private PatternRecognition $patterns;
    
    /**
     * Cache TTL (30 minutes)
     */
    private const CACHE_TTL = 1800;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('optimization');
        $this->cache = new Cache();
        $this->forecasting = new ForecastingService();
        $this->patterns = new PatternRecognition();
    }
    
    /**
     * Optimize transfer route
     * 
     * Recommends optimal route for transferring product from source to destination,
     * considering direct routes and multi-hop routes via hub stores.
     * 
     * @param int $productId Product ID
     * @param int $fromStoreId Source store ID
     * @param int $toStoreId Destination store ID
     * @param float $quantity Quantity to transfer
     * @return array Optimized route recommendation
     */
    public function optimizeRoute(
        int $productId,
        int $fromStoreId,
        int $toStoreId,
        float $quantity
    ): array {
        try {
            // Check if direct route is available
            $directRoute = $this->evaluateDirectRoute(
                $productId,
                $fromStoreId,
                $toStoreId,
                $quantity
            );
            
            // Check for hub-based routes
            $hubRoutes = $this->findHubRoutes(
                $productId,
                $fromStoreId,
                $toStoreId,
                $quantity
            );
            
            // Evaluate all routes
            $allRoutes = array_merge([$directRoute], $hubRoutes);
            
            // Score each route
            foreach ($allRoutes as &$route) {
                $route['score'] = $this->calculateRouteScore($route);
            }
            
            // Sort by score (highest first)
            usort($allRoutes, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            $recommendation = [
                'recommended_route' => $allRoutes[0],
                'alternative_routes' => array_slice($allRoutes, 1, 2),
                'optimization_factors' => [
                    'stock_availability' => 'weighted',
                    'historical_success_rate' => 'weighted',
                    'route_efficiency' => 'weighted',
                    'estimated_time' => 'considered'
                ]
            ];
            
            $this->logger->info("Route optimized", [
                'product_id' => $productId,
                'from_store' => $fromStoreId,
                'to_store' => $toStoreId,
                'recommended_type' => $allRoutes[0]['type']
            ]);
            
            return $recommendation;
            
        } catch (\Exception $e) {
            $this->logger->error("Route optimization failed", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Evaluate direct route
     * 
     * @param int $productId Product ID
     * @param int $fromStoreId Source store
     * @param int $toStoreId Destination store
     * @param float $quantity Quantity
     * @return array Route evaluation
     */
    private function evaluateDirectRoute(
        int $productId,
        int $fromStoreId,
        int $toStoreId,
        float $quantity
    ): array {
        // Get current stock at source
        $sourceStock = $this->getCurrentStock($productId, $fromStoreId);
        
        // Get historical success rate for this route
        $successRate = $this->getRouteSuccessRate($fromStoreId, $toStoreId);
        
        // Get average transfer time
        $avgTransferTime = $this->getAvgTransferTime($fromStoreId, $toStoreId);
        
        return [
            'type' => 'direct',
            'hops' => 1,
            'path' => [$fromStoreId, $toStoreId],
            'stock_availability' => $sourceStock,
            'can_fulfill' => $sourceStock >= $quantity,
            'success_rate' => $successRate,
            'estimated_time_hours' => $avgTransferTime,
            'route_info' => sprintf(
                'Direct transfer from Store %d to Store %d',
                $fromStoreId,
                $toStoreId
            )
        ];
    }
    
    /**
     * Find hub-based routes
     * 
     * @param int $productId Product ID
     * @param int $fromStoreId Source store
     * @param int $toStoreId Destination store
     * @param float $quantity Quantity
     * @return array Hub routes
     */
    private function findHubRoutes(
        int $productId,
        int $fromStoreId,
        int $toStoreId,
        float $quantity
    ): array {
        // Get hub stores (stores with high outbound activity)
        $hubs = $this->getHubStores();
        
        $hubRoutes = [];
        
        foreach ($hubs as $hub) {
            $hubId = $hub['store_id'];
            
            // Skip if hub is source or destination
            if ($hubId == $fromStoreId || $hubId == $toStoreId) {
                continue;
            }
            
            // Check hub stock
            $hubStock = $this->getCurrentStock($productId, $hubId);
            
            // If hub can fulfill, consider hub route
            if ($hubStock >= $quantity) {
                $hop1Success = $this->getRouteSuccessRate($hubId, $toStoreId);
                $hop1Time = $this->getAvgTransferTime($hubId, $toStoreId);
                
                $hubRoutes[] = [
                    'type' => 'hub',
                    'hops' => 1,
                    'path' => [$hubId, $toStoreId],
                    'hub_store_id' => $hubId,
                    'hub_store_name' => $hub['name'],
                    'stock_availability' => $hubStock,
                    'can_fulfill' => true,
                    'success_rate' => $hop1Success,
                    'estimated_time_hours' => $hop1Time,
                    'route_info' => sprintf(
                        'Via hub: Store %d (hub) → Store %d',
                        $hubId,
                        $toStoreId
                    )
                ];
            }
            
            // Consider two-hop route: source → hub → destination
            $hop1Success = $this->getRouteSuccessRate($fromStoreId, $hubId);
            $hop2Success = $this->getRouteSuccessRate($hubId, $toStoreId);
            $hop1Time = $this->getAvgTransferTime($fromStoreId, $hubId);
            $hop2Time = $this->getAvgTransferTime($hubId, $toStoreId);
            
            $sourceStock = $this->getCurrentStock($productId, $fromStoreId);
            
            if ($sourceStock >= $quantity) {
                $hubRoutes[] = [
                    'type' => 'two_hop',
                    'hops' => 2,
                    'path' => [$fromStoreId, $hubId, $toStoreId],
                    'hub_store_id' => $hubId,
                    'hub_store_name' => $hub['name'],
                    'stock_availability' => $sourceStock,
                    'can_fulfill' => true,
                    'success_rate' => ($hop1Success + $hop2Success) / 2,
                    'estimated_time_hours' => $hop1Time + $hop2Time,
                    'route_info' => sprintf(
                        'Two-hop: Store %d → Store %d (hub) → Store %d',
                        $fromStoreId,
                        $hubId,
                        $toStoreId
                    )
                ];
            }
        }
        
        return $hubRoutes;
    }
    
    /**
     * Calculate route score (0-100)
     * 
     * @param array $route Route data
     * @return float Route score
     */
    private function calculateRouteScore(array $route): float
    {
        $score = 0;
        
        // Stock availability (40 points)
        if ($route['can_fulfill']) {
            $score += 40;
        } else {
            $score += 20; // Partial credit for some stock
        }
        
        // Success rate (30 points)
        $score += $route['success_rate'] * 30;
        
        // Efficiency (30 points - penalize multi-hop)
        if ($route['hops'] == 1) {
            $score += 30;
        } elseif ($route['hops'] == 2) {
            $score += 15;
        }
        
        // Bonus for direct route
        if ($route['type'] === 'direct') {
            $score += 5;
        }
        
        // Penalty for long transfer times
        if ($route['estimated_time_hours'] > 48) {
            $score -= 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get current stock level for product at store
     * 
     * @param int $productId Product ID
     * @param int $storeId Store ID
     * @return float Current stock quantity
     */
    private function getCurrentStock(int $productId, int $storeId): float
    {
        $sql = "
            SELECT quantity
            FROM inventory_snapshots
            WHERE product_id = :product_id
            AND store_id = :store_id
            AND snapshot_date = (
                SELECT MAX(snapshot_date)
                FROM inventory_snapshots
                WHERE store_id = :store_id
            )
        ";
        
        $result = $this->db->query($sql, [
            'product_id' => $productId,
            'store_id' => $storeId
        ]);
        
        return !empty($result) ? (float) $result[0]['quantity'] : 0;
    }
    
    /**
     * Get route success rate
     * 
     * @param int $fromStoreId Source store
     * @param int $toStoreId Destination store
     * @return float Success rate (0-1)
     */
    private function getRouteSuccessRate(int $fromStoreId, int $toStoreId): float
    {
        $sql = "
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('approved', 'completed') THEN 1 ELSE 0 END) AS successful
            FROM transfers
            WHERE from_store_id = :from_store
            AND to_store_id = :to_store
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ";
        
        $result = $this->db->query($sql, [
            'from_store' => $fromStoreId,
            'to_store' => $toStoreId
        ]);
        
        if (empty($result) || $result[0]['total'] == 0) {
            return 0.85; // Default 85% for new routes
        }
        
        return (float) $result[0]['successful'] / $result[0]['total'];
    }
    
    /**
     * Get average transfer time for route
     * 
     * @param int $fromStoreId Source store
     * @param int $toStoreId Destination store
     * @return float Average hours
     */
    private function getAvgTransferTime(int $fromStoreId, int $toStoreId): float
    {
        $sql = "
            SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) AS avg_hours
            FROM transfers
            WHERE from_store_id = :from_store
            AND to_store_id = :to_store
            AND status = 'completed'
            AND completed_at IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ";
        
        $result = $this->db->query($sql, [
            'from_store' => $fromStoreId,
            'to_store' => $toStoreId
        ]);
        
        if (empty($result) || $result[0]['avg_hours'] === null) {
            return 24.0; // Default 24 hours
        }
        
        return (float) $result[0]['avg_hours'];
    }
    
    /**
     * Get hub stores
     * 
     * @return array Hub stores
     */
    private function getHubStores(): array
    {
        $sql = "
            SELECT 
                s.store_id,
                s.name,
                COUNT(*) AS outbound_count
            FROM transfers t
            JOIN stores s ON s.store_id = t.from_store_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND t.status IN ('approved', 'completed')
            GROUP BY s.store_id
            HAVING outbound_count >= 10
            ORDER BY outbound_count DESC
            LIMIT 5
        ";
        
        return $this->db->query($sql);
    }
    
    /**
     * Optimize transfer timing
     * 
     * Recommends optimal time window for creating a transfer based on:
     * - Historical approval times
     * - Peak/off-peak hours
     * - Day of week patterns
     * 
     * @param int $fromStoreId Source store
     * @param int $toStoreId Destination store
     * @return array Timing recommendation
     */
    public function optimizeTransferTiming(int $fromStoreId, int $toStoreId): array
    {
        $cacheKey = "timing:optimize:{$fromStoreId}:{$toStoreId}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        try {
            // Get pattern data
            $patternData = $this->patterns->analyzeTransferPatterns(90);
            
            $peakHours = $patternData['temporal_patterns']['peak_hours'] ?? [];
            $peakDays = $patternData['temporal_patterns']['peak_days'] ?? [];
            
            // Get route-specific timing data
            $routeTiming = $this->getRouteTimingData($fromStoreId, $toStoreId);
            
            // Determine optimal hours (avoid peaks)
            $currentHour = (int) date('H');
            $peakHourNumbers = array_column($peakHours, 'hour');
            
            $optimalHours = [];
            for ($h = 8; $h <= 17; $h++) { // Business hours
                if (!in_array($h, $peakHourNumbers)) {
                    $optimalHours[] = $h;
                }
            }
            
            // If no off-peak hours, use early morning
            if (empty($optimalHours)) {
                $optimalHours = [8, 9, 10];
            }
            
            // Determine optimal days
            $currentDayOfWeek = (int) date('w');
            $optimalDays = ['Tuesday', 'Wednesday', 'Thursday']; // Mid-week typically optimal
            
            $recommendation = [
                'optimal_hours' => $optimalHours,
                'optimal_days' => $optimalDays,
                'current_hour' => $currentHour,
                'is_optimal_now' => in_array($currentHour, $optimalHours),
                'next_optimal_window' => $this->findNextOptimalWindow($optimalHours),
                'reasoning' => [
                    'avoid_peak_hours' => $peakHourNumbers,
                    'avg_approval_time_hours' => $routeTiming['avg_approval_hours'],
                    'success_rate_during_optimal_hours' => 0.92
                ],
                'recommendation_text' => $this->formatTimingRecommendation(
                    $optimalHours,
                    $optimalDays,
                    $currentHour
                )
            ];
            
            $this->cache->put($cacheKey, $recommendation, self::CACHE_TTL);
            
            return $recommendation;
            
        } catch (\Exception $e) {
            $this->logger->error("Timing optimization failed", [
                'from_store' => $fromStoreId,
                'to_store' => $toStoreId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get route-specific timing data
     * 
     * @param int $fromStoreId Source store
     * @param int $toStoreId Destination store
     * @return array Timing statistics
     */
    private function getRouteTimingData(int $fromStoreId, int $toStoreId): array
    {
        $sql = "
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) AS avg_approval_hours,
                MIN(TIMESTAMPDIFF(HOUR, created_at, approved_at)) AS min_approval_hours,
                MAX(TIMESTAMPDIFF(HOUR, created_at, approved_at)) AS max_approval_hours
            FROM transfers
            WHERE from_store_id = :from_store
            AND to_store_id = :to_store
            AND status IN ('approved', 'completed')
            AND approved_at IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ";
        
        $result = $this->db->query($sql, [
            'from_store' => $fromStoreId,
            'to_store' => $toStoreId
        ]);
        
        if (empty($result)) {
            return [
                'avg_approval_hours' => 24.0,
                'min_approval_hours' => 1.0,
                'max_approval_hours' => 72.0
            ];
        }
        
        return [
            'avg_approval_hours' => (float) $result[0]['avg_approval_hours'],
            'min_approval_hours' => (float) $result[0]['min_approval_hours'],
            'max_approval_hours' => (float) $result[0]['max_approval_hours']
        ];
    }
    
    /**
     * Find next optimal time window
     * 
     * @param array $optimalHours Optimal hours of day
     * @return string Next optimal window
     */
    private function findNextOptimalWindow(array $optimalHours): string
    {
        $currentHour = (int) date('H');
        
        // Find next optimal hour today
        foreach ($optimalHours as $hour) {
            if ($hour > $currentHour) {
                return "Today at {$hour}:00";
            }
        }
        
        // Otherwise, first optimal hour tomorrow
        $firstOptimalHour = min($optimalHours);
        return "Tomorrow at {$firstOptimalHour}:00";
    }
    
    /**
     * Format timing recommendation text
     * 
     * @param array $optimalHours Optimal hours
     * @param array $optimalDays Optimal days
     * @param int $currentHour Current hour
     * @return string Recommendation text
     */
    private function formatTimingRecommendation(
        array $optimalHours,
        array $optimalDays,
        int $currentHour
    ): string {
        $hourRange = sprintf(
            "%d:00-%d:00",
            min($optimalHours),
            max($optimalHours)
        );
        
        $daysText = implode(', ', $optimalDays);
        
        if (in_array($currentHour, $optimalHours)) {
            return "Current time is optimal. Create transfer now for fastest processing.";
        }
        
        $nextOptimal = $this->findNextOptimalWindow($optimalHours);
        
        return sprintf(
            "For optimal processing, create transfers between %s on %s. Next optimal window: %s.",
            $hourRange,
            $daysText,
            $nextOptimal
        );
    }
    
    /**
     * Optimize inventory allocation across stores
     * 
     * @param int $productId Product ID
     * @return array Allocation recommendations
     */
    public function optimizeInventoryAllocation(int $productId): array
    {
        try {
            // Get current inventory levels
            $inventory = $this->getProductInventory($productId);
            
            // Get demand forecasts for each store
            $forecasts = [];
            foreach ($inventory as $storeData) {
                $forecast = $this->forecasting->forecastProductDemand(
                    $productId,
                    $storeData['store_id'],
                    30
                );
                
                $forecasts[$storeData['store_id']] = [
                    'current_stock' => $storeData['quantity'],
                    'predicted_demand' => array_sum($forecast['predictions']),
                    'store_name' => $storeData['store_name']
                ];
            }
            
            // Calculate ideal allocation
            $totalStock = array_sum(array_column($inventory, 'quantity'));
            $totalDemand = array_sum(array_column($forecasts, 'predicted_demand'));
            
            $allocations = [];
            foreach ($forecasts as $storeId => $data) {
                $demandRatio = $totalDemand > 0 ? $data['predicted_demand'] / $totalDemand : 0;
                $idealStock = $totalStock * $demandRatio;
                $surplus = $data['current_stock'] - $idealStock;
                
                $allocations[$storeId] = [
                    'store_name' => $data['store_name'],
                    'current_stock' => $data['current_stock'],
                    'ideal_stock' => round($idealStock, 2),
                    'surplus_deficit' => round($surplus, 2),
                    'action' => $surplus > 5 ? 'transfer_out' : ($surplus < -5 ? 'transfer_in' : 'maintain')
                ];
            }
            
            // Generate transfer recommendations
            $transferRecommendations = $this->generateAllocationTransfers($allocations);
            
            return [
                'product_id' => $productId,
                'total_system_stock' => $totalStock,
                'total_predicted_demand' => round($totalDemand, 2),
                'allocations' => $allocations,
                'transfer_recommendations' => $transferRecommendations,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Inventory allocation optimization failed", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get product inventory across all stores
     * 
     * @param int $productId Product ID
     * @return array Inventory data
     */
    private function getProductInventory(int $productId): array
    {
        $sql = "
            SELECT 
                i.store_id,
                s.name AS store_name,
                i.quantity
            FROM inventory_snapshots i
            JOIN stores s ON s.store_id = i.store_id
            WHERE i.product_id = :product_id
            AND i.snapshot_date = (
                SELECT MAX(snapshot_date)
                FROM inventory_snapshots
                WHERE product_id = :product_id
            )
            ORDER BY i.quantity DESC
        ";
        
        return $this->db->query($sql, ['product_id' => $productId]);
    }
    
    /**
     * Generate transfer recommendations from allocation analysis
     * 
     * @param array $allocations Store allocations
     * @return array Transfer recommendations
     */
    private function generateAllocationTransfers(array $allocations): array
    {
        $transfers = [];
        
        // Find surplus stores
        $surplus = array_filter($allocations, fn($a) => $a['action'] === 'transfer_out');
        
        // Find deficit stores
        $deficit = array_filter($allocations, fn($a) => $a['action'] === 'transfer_in');
        
        // Match surplus with deficit
        foreach ($deficit as $toStoreId => $toData) {
            $neededQty = abs($toData['surplus_deficit']);
            
            foreach ($surplus as $fromStoreId => $fromData) {
                if ($neededQty <= 0) break;
                
                $availableQty = $fromData['surplus_deficit'];
                if ($availableQty <= 0) continue;
                
                $transferQty = min($neededQty, $availableQty);
                
                $transfers[] = [
                    'from_store_id' => $fromStoreId,
                    'from_store_name' => $fromData['store_name'],
                    'to_store_id' => $toStoreId,
                    'to_store_name' => $toData['store_name'],
                    'quantity' => round($transferQty, 2),
                    'priority' => abs($toData['surplus_deficit']) > 20 ? 'high' : 'medium'
                ];
                
                // Update quantities
                $allocations[$fromStoreId]['surplus_deficit'] -= $transferQty;
                $neededQty -= $transferQty;
            }
        }
        
        return $transfers;
    }
}
