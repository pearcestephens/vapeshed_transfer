<?php

/**
 * Pattern Recognition Service
 * 
 * AI-powered pattern recognition for identifying trends, correlations, and insights
 * in transfer data. Provides optimization recommendations based on discovered patterns.
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

/**
 * Pattern Recognition Service
 * 
 * Recognizes patterns in:
 * - Transfer routes (popular routes, optimal times)
 * - Product movements (fast movers, slow movers)
 * - Seasonal trends (holiday patterns, weekly cycles)
 * - Store relationships (hub stores, satellite stores)
 * - User behavior (efficiency metrics, approval patterns)
 */
class PatternRecognition
{
    private Database $db;
    private Logger $logger;
    private Cache $cache;
    
    /**
     * Minimum support threshold for pattern mining
     */
    private const MIN_SUPPORT = 0.1; // 10%
    
    /**
     * Minimum confidence threshold for association rules
     */
    private const MIN_CONFIDENCE = 0.5; // 50%
    
    /**
     * Cache TTL (1 hour)
     */
    private const CACHE_TTL = 3600;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('pattern_recognition');
        $this->cache = new Cache();
    }
    
    /**
     * Analyze transfer patterns
     * 
     * @param int $days Analysis period in days
     * @return array Pattern analysis results
     */
    public function analyzeTransferPatterns(int $days = 90): array
    {
        $cacheKey = "patterns:transfers:days:{$days}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        try {
            $results = [
                'route_patterns' => $this->findRoutePatterns($days),
                'temporal_patterns' => $this->findTemporalPatterns($days),
                'product_patterns' => $this->findProductPatterns($days),
                'store_relationships' => $this->findStoreRelationships($days),
                'efficiency_patterns' => $this->findEfficiencyPatterns($days),
                'recommendations' => []
            ];
            
            // Generate recommendations based on patterns
            $results['recommendations'] = $this->generateRecommendations($results);
            
            // Add metadata
            $results['metadata'] = [
                'analysis_period_days' => $days,
                'generated_at' => date('Y-m-d H:i:s'),
                'total_patterns_found' => 
                    count($results['route_patterns']) +
                    count($results['temporal_patterns']) +
                    count($results['product_patterns'])
            ];
            
            $this->cache->put($cacheKey, $results, self::CACHE_TTL);
            
            $this->logger->info("Pattern analysis completed", [
                'patterns_found' => $results['metadata']['total_patterns_found']
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logger->error("Pattern analysis failed", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Find route patterns (most common transfer routes)
     * 
     * @param int $days Analysis period
     * @return array Route patterns
     */
    private function findRoutePatterns(int $days): array
    {
        $sql = "
            SELECT 
                t.from_store_id,
                t.to_store_id,
                fs.name AS from_store_name,
                ts.name AS to_store_name,
                COUNT(*) AS transfer_count,
                AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.approved_at)) AS avg_approval_hours,
                SUM(ti.quantity) AS total_quantity,
                SUM(ti.quantity * ti.unit_price) AS total_value
            FROM transfers t
            JOIN stores fs ON fs.store_id = t.from_store_id
            JOIN stores ts ON ts.store_id = t.to_store_id
            LEFT JOIN transfer_items ti ON ti.transfer_id = t.transfer_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND t.status IN ('approved', 'completed')
            GROUP BY t.from_store_id, t.to_store_id
            HAVING transfer_count >= 3
            ORDER BY transfer_count DESC
            LIMIT 20
        ";
        
        $routes = $this->db->query($sql, ['days' => $days]);
        
        $patterns = [];
        foreach ($routes as $route) {
            $patterns[] = [
                'from_store' => $route['from_store_name'],
                'to_store' => $route['to_store_name'],
                'frequency' => (int) $route['transfer_count'],
                'avg_approval_time_hours' => round((float) $route['avg_approval_hours'], 1),
                'total_quantity' => (float) $route['total_quantity'],
                'total_value' => round((float) $route['total_value'], 2),
                'pattern_type' => $this->classifyRoutePattern($route)
            ];
        }
        
        return $patterns;
    }
    
    /**
     * Classify route pattern type
     * 
     * @param array $route Route data
     * @return string Pattern classification
     */
    private function classifyRoutePattern(array $route): string
    {
        $frequency = (int) $route['transfer_count'];
        
        if ($frequency >= 20) return 'high_frequency';
        if ($frequency >= 10) return 'regular';
        if ($frequency >= 5) return 'occasional';
        return 'rare';
    }
    
    /**
     * Find temporal patterns (time-based patterns)
     * 
     * @param int $days Analysis period
     * @return array Temporal patterns
     */
    private function findTemporalPatterns(int $days): array
    {
        // Hour of day distribution
        $hourSql = "
            SELECT 
                HOUR(created_at) AS hour,
                COUNT(*) AS transfer_count,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, approved_at)) AS avg_approval_minutes
            FROM transfers
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND status IN ('approved', 'completed')
            GROUP BY HOUR(created_at)
            ORDER BY transfer_count DESC
        ";
        
        $hourData = $this->db->query($hourSql, ['days' => $days]);
        
        // Day of week distribution
        $daySql = "
            SELECT 
                DAYNAME(created_at) AS day_name,
                DAYOFWEEK(created_at) AS day_num,
                COUNT(*) AS transfer_count,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, approved_at)) AS avg_approval_minutes
            FROM transfers
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND status IN ('approved', 'completed')
            GROUP BY day_name, day_num
            ORDER BY day_num
        ";
        
        $dayData = $this->db->query($daySql, ['days' => $days]);
        
        // Month distribution
        $monthSql = "
            SELECT 
                MONTHNAME(created_at) AS month_name,
                MONTH(created_at) AS month_num,
                COUNT(*) AS transfer_count
            FROM transfers
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND status IN ('approved', 'completed')
            GROUP BY month_name, month_num
            ORDER BY month_num
        ";
        
        $monthData = $this->db->query($monthSql, ['days' => $days]);
        
        return [
            'peak_hours' => $this->findPeakHours($hourData),
            'peak_days' => $this->findPeakDays($dayData),
            'seasonal_trends' => $this->findSeasonalTrends($monthData),
            'hour_distribution' => $hourData,
            'day_distribution' => $dayData,
            'month_distribution' => $monthData
        ];
    }
    
    /**
     * Find peak hours
     * 
     * @param array $hourData Hour distribution data
     * @return array Peak hours
     */
    private function findPeakHours(array $hourData): array
    {
        if (empty($hourData)) return [];
        
        $totalTransfers = array_sum(array_column($hourData, 'transfer_count'));
        $avgPerHour = $totalTransfers / count($hourData);
        
        $peakHours = [];
        foreach ($hourData as $hour) {
            if ($hour['transfer_count'] >= $avgPerHour * 1.5) {
                $peakHours[] = [
                    'hour' => (int) $hour['hour'],
                    'transfer_count' => (int) $hour['transfer_count'],
                    'percentage_of_total' => round(($hour['transfer_count'] / $totalTransfers) * 100, 1),
                    'avg_approval_minutes' => round((float) $hour['avg_approval_minutes'], 1)
                ];
            }
        }
        
        return $peakHours;
    }
    
    /**
     * Find peak days
     * 
     * @param array $dayData Day distribution data
     * @return array Peak days
     */
    private function findPeakDays(array $dayData): array
    {
        if (empty($dayData)) return [];
        
        $totalTransfers = array_sum(array_column($dayData, 'transfer_count'));
        $avgPerDay = $totalTransfers / count($dayData);
        
        $peakDays = [];
        foreach ($dayData as $day) {
            if ($day['transfer_count'] >= $avgPerDay * 1.2) {
                $peakDays[] = [
                    'day' => $day['day_name'],
                    'transfer_count' => (int) $day['transfer_count'],
                    'percentage_of_total' => round(($day['transfer_count'] / $totalTransfers) * 100, 1),
                    'avg_approval_minutes' => round((float) $day['avg_approval_minutes'], 1)
                ];
            }
        }
        
        return $peakDays;
    }
    
    /**
     * Find seasonal trends
     * 
     * @param array $monthData Month distribution data
     * @return array Seasonal insights
     */
    private function findSeasonalTrends(array $monthData): array
    {
        if (count($monthData) < 3) {
            return ['insufficient_data' => true];
        }
        
        $counts = array_column($monthData, 'transfer_count');
        $avgCount = array_sum($counts) / count($counts);
        
        $trends = [];
        foreach ($monthData as $month) {
            $variance = (($month['transfer_count'] - $avgCount) / $avgCount) * 100;
            
            if (abs($variance) >= 20) {
                $trends[] = [
                    'month' => $month['month_name'],
                    'transfer_count' => (int) $month['transfer_count'],
                    'variance_from_average' => round($variance, 1),
                    'trend' => $variance > 0 ? 'above_average' : 'below_average'
                ];
            }
        }
        
        return $trends;
    }
    
    /**
     * Find product patterns
     * 
     * @param int $days Analysis period
     * @return array Product patterns
     */
    private function findProductPatterns(int $days): array
    {
        $sql = "
            SELECT 
                p.product_id,
                p.name,
                p.sku,
                p.category,
                COUNT(DISTINCT ti.transfer_id) AS transfer_count,
                SUM(ti.quantity) AS total_quantity,
                AVG(ti.quantity) AS avg_quantity_per_transfer,
                COUNT(DISTINCT t.from_store_id) AS unique_from_stores,
                COUNT(DISTINCT t.to_store_id) AS unique_to_stores
            FROM transfer_items ti
            JOIN transfers t ON t.transfer_id = ti.transfer_id
            JOIN products p ON p.product_id = ti.product_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND t.status IN ('approved', 'completed')
            GROUP BY p.product_id
            HAVING transfer_count >= 3
            ORDER BY total_quantity DESC
            LIMIT 50
        ";
        
        $products = $this->db->query($sql, ['days' => $days]);
        
        $patterns = [];
        foreach ($products as $product) {
            $classification = $this->classifyProduct($product);
            
            $patterns[] = [
                'product_name' => $product['name'],
                'sku' => $product['sku'],
                'category' => $product['category'],
                'transfer_frequency' => (int) $product['transfer_count'],
                'total_quantity_transferred' => (float) $product['total_quantity'],
                'avg_quantity_per_transfer' => round((float) $product['avg_quantity_per_transfer'], 2),
                'distribution_breadth' => [
                    'unique_source_stores' => (int) $product['unique_from_stores'],
                    'unique_destination_stores' => (int) $product['unique_to_stores']
                ],
                'classification' => $classification
            ];
        }
        
        return $patterns;
    }
    
    /**
     * Classify product based on transfer patterns
     * 
     * @param array $product Product data
     * @return string Product classification
     */
    private function classifyProduct(array $product): string
    {
        $transferCount = (int) $product['transfer_count'];
        $totalQuantity = (float) $product['total_quantity'];
        
        if ($transferCount >= 20 && $totalQuantity >= 100) {
            return 'high_velocity'; // Fast-moving product
        }
        
        if ($transferCount >= 10 || $totalQuantity >= 50) {
            return 'medium_velocity'; // Regular mover
        }
        
        return 'low_velocity'; // Slow mover
    }
    
    /**
     * Find store relationships (hub/satellite patterns)
     * 
     * @param int $days Analysis period
     * @return array Store relationship insights
     */
    private function findStoreRelationships(int $days): array
    {
        // Outbound transfers (from each store)
        $outboundSql = "
            SELECT 
                s.store_id,
                s.name,
                COUNT(*) AS outbound_count,
                SUM(ti.quantity) AS outbound_quantity
            FROM transfers t
            JOIN stores s ON s.store_id = t.from_store_id
            LEFT JOIN transfer_items ti ON ti.transfer_id = t.transfer_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND t.status IN ('approved', 'completed')
            GROUP BY s.store_id
        ";
        
        $outbound = $this->db->query($outboundSql, ['days' => $days]);
        
        // Inbound transfers (to each store)
        $inboundSql = "
            SELECT 
                s.store_id,
                s.name,
                COUNT(*) AS inbound_count,
                SUM(ti.quantity) AS inbound_quantity
            FROM transfers t
            JOIN stores s ON s.store_id = t.to_store_id
            LEFT JOIN transfer_items ti ON ti.transfer_id = t.transfer_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND t.status IN ('approved', 'completed')
            GROUP BY s.store_id
        ";
        
        $inbound = $this->db->query($inboundSql, ['days' => $days]);
        
        // Combine and classify
        $storeData = [];
        
        foreach ($outbound as $store) {
            $storeId = $store['store_id'];
            $storeData[$storeId] = [
                'store_name' => $store['name'],
                'outbound_transfers' => (int) $store['outbound_count'],
                'outbound_quantity' => (float) $store['outbound_quantity'],
                'inbound_transfers' => 0,
                'inbound_quantity' => 0
            ];
        }
        
        foreach ($inbound as $store) {
            $storeId = $store['store_id'];
            if (!isset($storeData[$storeId])) {
                $storeData[$storeId] = [
                    'store_name' => $store['name'],
                    'outbound_transfers' => 0,
                    'outbound_quantity' => 0,
                    'inbound_transfers' => (int) $store['inbound_count'],
                    'inbound_quantity' => (float) $store['inbound_quantity']
                ];
            } else {
                $storeData[$storeId]['inbound_transfers'] = (int) $store['inbound_count'];
                $storeData[$storeId]['inbound_quantity'] = (float) $store['inbound_quantity'];
            }
        }
        
        // Classify stores
        $relationships = [];
        foreach ($storeData as $storeId => $data) {
            $data['net_flow'] = $data['inbound_quantity'] - $data['outbound_quantity'];
            $data['role'] = $this->classifyStoreRole($data);
            
            $relationships[] = $data;
        }
        
        // Sort by total activity
        usort($relationships, function($a, $b) {
            $aTotal = $a['inbound_transfers'] + $a['outbound_transfers'];
            $bTotal = $b['inbound_transfers'] + $b['outbound_transfers'];
            return $bTotal - $aTotal;
        });
        
        return $relationships;
    }
    
    /**
     * Classify store role based on transfer patterns
     * 
     * @param array $data Store transfer data
     * @return string Store role
     */
    private function classifyStoreRole(array $data): string
    {
        $outbound = $data['outbound_transfers'];
        $inbound = $data['inbound_transfers'];
        $total = $outbound + $inbound;
        
        if ($total === 0) return 'inactive';
        
        $outboundRatio = $outbound / $total;
        
        if ($outboundRatio >= 0.7) {
            return 'hub'; // Primarily distributes to others
        }
        
        if ($outboundRatio <= 0.3) {
            return 'receiver'; // Primarily receives from others
        }
        
        return 'balanced'; // Both sends and receives
    }
    
    /**
     * Find efficiency patterns
     * 
     * @param int $days Analysis period
     * @return array Efficiency insights
     */
    private function findEfficiencyPatterns(int $days): array
    {
        $sql = "
            SELECT 
                u.user_id,
                u.username,
                COUNT(*) AS transfers_created,
                AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.approved_at)) AS avg_approval_hours,
                AVG(ti.item_count) AS avg_items_per_transfer,
                SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
            FROM transfers t
            JOIN users u ON u.user_id = t.created_by
            LEFT JOIN (
                SELECT transfer_id, COUNT(*) AS item_count
                FROM transfer_items
                GROUP BY transfer_id
            ) ti ON ti.transfer_id = t.transfer_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY u.user_id
            HAVING transfers_created >= 5
            ORDER BY transfers_created DESC
        ";
        
        $users = $this->db->query($sql, ['days' => $days]);
        
        $patterns = [];
        foreach ($users as $user) {
            $cancellationRate = ($user['cancelled_count'] / $user['transfers_created']) * 100;
            
            $patterns[] = [
                'username' => $user['username'],
                'transfers_created' => (int) $user['transfers_created'],
                'avg_approval_time_hours' => round((float) $user['avg_approval_hours'], 1),
                'avg_items_per_transfer' => round((float) $user['avg_items_per_transfer'], 1),
                'cancellation_rate' => round($cancellationRate, 1),
                'efficiency_score' => $this->calculateEfficiencyScore($user)
            ];
        }
        
        return $patterns;
    }
    
    /**
     * Calculate user efficiency score
     * 
     * @param array $user User data
     * @return int Efficiency score (0-100)
     */
    private function calculateEfficiencyScore(array $user): int
    {
        $transfersCreated = (int) $user['transfers_created'];
        $avgApprovalHours = (float) $user['avg_approval_hours'];
        $cancelledCount = (int) $user['cancelled_count'];
        
        $cancellationRate = ($cancelledCount / $transfersCreated) * 100;
        
        // Base score on volume
        $volumeScore = min(50, $transfersCreated * 2);
        
        // Penalty for slow approvals (over 24 hours)
        $speedScore = max(0, 30 - ($avgApprovalHours - 24));
        
        // Penalty for cancellations
        $qualityScore = max(0, 20 - ($cancellationRate * 2));
        
        return (int) min(100, $volumeScore + $speedScore + $qualityScore);
    }
    
    /**
     * Generate recommendations based on patterns
     * 
     * @param array $patterns All detected patterns
     * @return array Recommendations
     */
    private function generateRecommendations(array $patterns): array
    {
        $recommendations = [];
        
        // Route optimization recommendations
        if (!empty($patterns['route_patterns'])) {
            $highFrequencyRoutes = array_filter($patterns['route_patterns'], function($route) {
                return $route['pattern_type'] === 'high_frequency';
            });
            
            if (!empty($highFrequencyRoutes)) {
                $recommendations[] = [
                    'type' => 'route_optimization',
                    'priority' => 'high',
                    'title' => 'Optimize High-Frequency Routes',
                    'description' => sprintf(
                        'Found %d high-frequency transfer routes. Consider setting up automatic reorder points for these routes.',
                        count($highFrequencyRoutes)
                    ),
                    'affected_routes' => count($highFrequencyRoutes)
                ];
            }
        }
        
        // Temporal optimization recommendations
        if (!empty($patterns['temporal_patterns']['peak_hours'])) {
            $recommendations[] = [
                'type' => 'temporal_optimization',
                'priority' => 'medium',
                'title' => 'Schedule Transfers During Off-Peak Hours',
                'description' => sprintf(
                    'Peak hours: %s. Consider scheduling non-urgent transfers during off-peak times for faster processing.',
                    implode(', ', array_map(fn($h) => $h['hour'] . ':00', $patterns['temporal_patterns']['peak_hours']))
                ),
                'peak_hours' => array_column($patterns['temporal_patterns']['peak_hours'], 'hour')
            ];
        }
        
        // Store relationship recommendations
        if (!empty($patterns['store_relationships'])) {
            $hubs = array_filter($patterns['store_relationships'], function($store) {
                return $store['role'] === 'hub';
            });
            
            if (!empty($hubs)) {
                $recommendations[] = [
                    'type' => 'hub_optimization',
                    'priority' => 'high',
                    'title' => 'Optimize Hub Store Inventory',
                    'description' => sprintf(
                        'Identified %d hub stores. Ensure these stores maintain adequate inventory levels to support distribution.',
                        count($hubs)
                    ),
                    'hub_stores' => array_column($hubs, 'store_name')
                ];
            }
        }
        
        // Product recommendations
        if (!empty($patterns['product_patterns'])) {
            $highVelocity = array_filter($patterns['product_patterns'], function($product) {
                return $product['classification'] === 'high_velocity';
            });
            
            if (!empty($highVelocity)) {
                $recommendations[] = [
                    'type' => 'inventory_optimization',
                    'priority' => 'high',
                    'title' => 'Monitor High-Velocity Products',
                    'description' => sprintf(
                        'Found %d high-velocity products. Consider implementing automatic reordering for these items.',
                        count($highVelocity)
                    ),
                    'product_count' => count($highVelocity)
                ];
            }
        }
        
        return $recommendations;
    }
}
