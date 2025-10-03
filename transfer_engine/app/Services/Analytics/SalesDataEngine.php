<?php
declare(strict_types=1);

namespace App\Services\Analytics;

use App\Core\Logger;
use App\Core\Database;

/**
 * Sales Data Analytics Engine
 * 
 * Real sales data analysis with machine learning patterns
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */
class SalesDataEngine
{
    private Logger $logger;
    private Database $db;
    private array $salesCache = [];
    private array $trendCache = [];
    
    public function __construct()
    {
        $this->logger = new Logger();
        $this->db = Database::getInstance();
    }
    
    /**
     * Deep sales pattern analysis across all outlets
     */
    public function analyzeSalesPatterns(array $config = []): array
    {
        $startTime = microtime(true);
        
        try {
            $days = $config['analysis_days'] ?? 90;
            $outlets = $config['outlets'] ?? $this->getAllActiveOutlets();
            
            $patterns = [];
            $salesMetrics = [];
            
            foreach ($outlets as $outlet) {
                $outletId = $outlet['outlet_id'];
                
                // Get comprehensive sales data
                $salesData = $this->getOutletSalesHistory($outletId, $days);
                
                if (empty($salesData)) {
                    continue;
                }
                
                // Analyze patterns for this outlet
                $outletPatterns = $this->analyzeOutletPatterns($outletId, $salesData);
                $patterns[$outletId] = $outletPatterns;
                
                // Calculate key metrics
                $salesMetrics[$outletId] = $this->calculateSalesMetrics($salesData);
            }
            
            // Cross-outlet trend analysis
            $globalTrends = $this->analyzeGlobalTrends($patterns, $salesMetrics);
            
            // Product performance ranking
            $productRankings = $this->rankProductPerformance($salesMetrics);
            
            // Seasonal and cyclical patterns
            $seasonalPatterns = $this->detectSeasonalPatterns($patterns);
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'analysis_summary' => [
                    'outlets_analyzed' => count($patterns),
                    'total_sales_records' => array_sum(array_map(fn($m) => $m['total_transactions'], $salesMetrics)),
                    'analysis_period_days' => $days,
                    'execution_time' => round($executionTime, 3)
                ],
                'outlet_patterns' => $patterns,
                'sales_metrics' => $salesMetrics,
                'global_trends' => $globalTrends,
                'product_rankings' => $productRankings,
                'seasonal_patterns' => $seasonalPatterns,
                'recommendations' => $this->generateAnalyticsRecommendations($patterns, $salesMetrics, $globalTrends)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Sales pattern analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    /**
     * Real-time demand forecasting using historical sales
     */
    public function forecastDemand(string $productId, array $outlets, int $forecastDays = 30): array
    {
        $startTime = microtime(true);
        
        try {
            $forecasts = [];
            
            foreach ($outlets as $outlet) {
                $outletId = $outlet['outlet_id'];
                
                // Get historical sales for this product at this outlet
                $salesHistory = $this->getProductSalesHistory($productId, $outletId, 180); // 6 months
                
                if (count($salesHistory) < 14) {
                    // Not enough data for reliable forecasting
                    $forecasts[$outletId] = [
                        'forecast_demand' => 0,
                        'confidence' => 0,
                        'method' => 'insufficient_data',
                        'data_points' => count($salesHistory)
                    ];
                    continue;
                }
                
                // Multiple forecasting methods
                $forecasts[$outletId] = $this->calculateDemandForecast($salesHistory, $forecastDays);
            }
            
            // Calculate total demand across all outlets
            $totalForecast = array_sum(array_column($forecasts, 'forecast_demand'));
            $avgConfidence = array_sum(array_column($forecasts, 'confidence')) / count($forecasts);
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'product_id' => $productId,
                'forecast_period_days' => $forecastDays,
                'total_forecast_demand' => round($totalForecast, 2),
                'average_confidence' => round($avgConfidence, 3),
                'outlet_forecasts' => $forecasts,
                'forecast_metadata' => [
                    'analysis_timestamp' => date('Y-m-d H:i:s'),
                    'outlets_analyzed' => count($outlets),
                    'execution_time' => round($executionTime, 3)
                ]
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Demand forecasting failed', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    /**
     * Velocity-based transfer optimization
     */
    public function optimizeTransfersByVelocity(array $products, array $outlets): array
    {
        $startTime = microtime(true);
        
        try {
            $optimizations = [];
            
            foreach ($products as $product) {
                $productId = $product['product_id'];
                $warehouseStock = $product['warehouse_stock'] ?? 0;
                
                if ($warehouseStock <= 0) {
                    continue;
                }
                
                // Calculate velocity-based allocation
                $velocityAnalysis = $this->analyzeProductVelocity($productId, $outlets);
                
                if ($velocityAnalysis['total_velocity'] <= 0) {
                    continue;
                }
                
                // Smart allocation based on velocity patterns
                $allocation = $this->calculateVelocityAllocation(
                    $productId,
                    $warehouseStock,
                    $velocityAnalysis,
                    $outlets
                );
                
                if (!empty($allocation['transfers'])) {
                    $optimizations[] = [
                        'product_id' => $productId,
                        'warehouse_stock' => $warehouseStock,
                        'velocity_analysis' => $velocityAnalysis,
                        'recommended_transfers' => $allocation['transfers'],
                        'allocation_summary' => $allocation['summary']
                    ];
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'products_analyzed' => count($products),
                'transfer_opportunities' => count($optimizations),
                'optimizations' => $optimizations,
                'execution_time' => round($executionTime, 3)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Velocity optimization failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    // Private analysis methods
    
    private function analyzeOutletPatterns(string $outletId, array $salesData): array
    {
        $patterns = [];
        
        // Daily sales patterns
        $patterns['daily_patterns'] = $this->analyzeDailyPatterns($salesData);
        
        // Weekly patterns
        $patterns['weekly_patterns'] = $this->analyzeWeeklyPatterns($salesData);
        
        // Product category patterns
        $patterns['category_patterns'] = $this->analyzeCategoryPatterns($salesData);
        
        // Velocity trends
        $patterns['velocity_trends'] = $this->analyzeVelocityTrends($salesData);
        
        // Stock-out impact analysis
        $patterns['stockout_impact'] = $this->analyzeStockoutImpact($outletId, $salesData);
        
        return $patterns;
    }
    
    private function analyzeDailyPatterns(array $salesData): array
    {
        $dailyStats = [];
        
        foreach ($salesData as $sale) {
            $dayOfWeek = date('N', strtotime($sale['sale_date'])); // 1=Monday, 7=Sunday
            $hour = (int)date('H', strtotime($sale['sale_timestamp']));
            
            if (!isset($dailyStats[$dayOfWeek])) {
                $dailyStats[$dayOfWeek] = ['total_sales' => 0, 'transaction_count' => 0, 'hourly' => []];
            }
            
            $dailyStats[$dayOfWeek]['total_sales'] += (float)$sale['total_amount'];
            $dailyStats[$dayOfWeek]['transaction_count']++;
            
            if (!isset($dailyStats[$dayOfWeek]['hourly'][$hour])) {
                $dailyStats[$dayOfWeek]['hourly'][$hour] = 0;
            }
            $dailyStats[$dayOfWeek]['hourly'][$hour]++;
        }
        
        // Calculate averages and identify peak times
        $dayNames = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $patterns = [];
        
        foreach ($dailyStats as $day => $stats) {
            $avgSale = $stats['total_sales'] / max($stats['transaction_count'], 1);
            $peakHour = array_search(max($stats['hourly']), $stats['hourly']);
            
            $patterns[$dayNames[$day]] = [
                'average_sale_value' => round($avgSale, 2),
                'transaction_count' => $stats['transaction_count'],
                'peak_hour' => $peakHour,
                'hourly_distribution' => $stats['hourly']
            ];
        }
        
        return $patterns;
    }
    
    private function analyzeVelocityTrends(array $salesData): array
    {
        // Group sales by week and calculate velocity trends
        $weeklyVelocity = [];
        
        foreach ($salesData as $sale) {
            $week = date('Y-W', strtotime($sale['sale_date']));
            
            if (!isset($weeklyVelocity[$week])) {
                $weeklyVelocity[$week] = ['quantity' => 0, 'revenue' => 0];
            }
            
            $weeklyVelocity[$week]['quantity'] += (int)$sale['quantity'];
            $weeklyVelocity[$week]['revenue'] += (float)$sale['total_amount'];
        }
        
        // Calculate trend direction
        $weeks = array_keys($weeklyVelocity);
        sort($weeks);
        
        if (count($weeks) < 4) {
            return ['trend' => 'insufficient_data', 'weeks_analyzed' => count($weeks)];
        }
        
        // Simple linear regression for trend detection
        $quantityTrend = $this->calculateTrend(array_column($weeklyVelocity, 'quantity'));
        $revenueTrend = $this->calculateTrend(array_column($weeklyVelocity, 'revenue'));
        
        return [
            'quantity_trend' => $quantityTrend,
            'revenue_trend' => $revenueTrend,
            'weeks_analyzed' => count($weeks),
            'weekly_data' => $weeklyVelocity
        ];
    }
    
    private function calculateDemandForecast(array $salesHistory, int $forecastDays): array
    {
        // Multiple forecasting methods - use the best one based on data characteristics
        
        // Method 1: Moving average (simple but reliable)
        $movingAvg = $this->calculateMovingAverageForecast($salesHistory, $forecastDays);
        
        // Method 2: Exponential smoothing (good for trending data)
        $exponentialSmoothing = $this->calculateExponentialSmoothingForecast($salesHistory, $forecastDays);
        
        // Method 3: Linear regression (good for clear trends)
        $linearTrend = $this->calculateLinearTrendForecast($salesHistory, $forecastDays);
        
        // Choose best method based on historical accuracy
        $bestMethod = $this->selectBestForecastMethod($salesHistory, [
            'moving_average' => $movingAvg,
            'exponential_smoothing' => $exponentialSmoothing,
            'linear_trend' => $linearTrend
        ]);
        
        return $bestMethod;
    }
    
    private function calculateMovingAverageForecast(array $salesHistory, int $forecastDays): array
    {
        // Use last 30 days for moving average
        $recentSales = array_slice($salesHistory, -30);
        $avgDailyDemand = array_sum(array_column($recentSales, 'quantity')) / count($recentSales);
        
        $forecast = $avgDailyDemand * $forecastDays;
        $confidence = min(0.95, count($recentSales) / 30); // Higher confidence with more data
        
        return [
            'forecast_demand' => round($forecast, 2),
            'confidence' => round($confidence, 3),
            'method' => 'moving_average',
            'daily_average' => round($avgDailyDemand, 2),
            'data_points' => count($recentSales)
        ];
    }
    
    private function calculateExponentialSmoothingForecast(array $salesHistory, int $forecastDays): array
    {
        $alpha = 0.3; // Smoothing factor
        $smoothedValues = [];
        
        $smoothedValues[0] = (float)$salesHistory[0]['quantity'];
        
        for ($i = 1; $i < count($salesHistory); $i++) {
            $smoothedValues[$i] = $alpha * (float)$salesHistory[$i]['quantity'] + (1 - $alpha) * $smoothedValues[$i-1];
        }
        
        $lastSmoothed = end($smoothedValues);
        $forecast = $lastSmoothed * $forecastDays;
        
        // Calculate confidence based on prediction error
        $confidence = $this->calculateForecastConfidence($salesHistory, $smoothedValues);
        
        return [
            'forecast_demand' => round($forecast, 2),
            'confidence' => round($confidence, 3),
            'method' => 'exponential_smoothing',
            'daily_forecast' => round($lastSmoothed, 2),
            'data_points' => count($salesHistory)
        ];
    }
    
    private function analyzeProductVelocity(string $productId, array $outlets): array
    {
        $velocityData = [];
        $totalVelocity = 0;
        
        foreach ($outlets as $outlet) {
            $outletId = $outlet['outlet_id'];
            
            // Get last 30 days of sales for this product at this outlet
            $recentSales = $this->getProductSalesHistory($productId, $outletId, 30);
            
            $velocity = 0;
            if (!empty($recentSales)) {
                $totalQuantity = array_sum(array_column($recentSales, 'quantity'));
                $velocity = $totalQuantity / 30; // Daily velocity
            }
            
            $velocityData[$outletId] = [
                'outlet_name' => $outlet['name'] ?? $outletId,
                'daily_velocity' => round($velocity, 2),
                'monthly_velocity' => round($velocity * 30, 2),
                'current_stock' => $this->getCurrentStock($productId, $outletId),
                'days_of_stock' => $velocity > 0 ? round($this->getCurrentStock($productId, $outletId) / $velocity, 1) : 999
            ];
            
            $totalVelocity += $velocity;
        }
        
        return [
            'total_velocity' => round($totalVelocity, 2),
            'outlet_velocities' => $velocityData,
            'velocity_distribution' => $this->calculateVelocityDistribution($velocityData)
        ];
    }
    
    // Database helper methods
    
    private function getOutletSalesHistory(string $outletId, int $days): array
    {
        $sql = "
            SELECT 
                s.sale_date,
                s.sale_timestamp,
                s.product_id,
                s.quantity,
                s.unit_price,
                s.total_amount,
                p.category_id,
                p.brand
            FROM sales_transactions s
            LEFT JOIN products p ON p.product_id = s.product_id
            WHERE s.outlet_id = ?
                AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND s.quantity > 0
            ORDER BY s.sale_timestamp ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $outletId, $days);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getProductSalesHistory(string $productId, string $outletId, int $days): array
    {
        $sql = "
            SELECT 
                sale_date,
                quantity,
                unit_price,
                total_amount
            FROM sales_transactions
            WHERE product_id = ? 
                AND outlet_id = ?
                AND sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND quantity > 0
            ORDER BY sale_date ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ssi', $productId, $outletId, $days);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getCurrentStock(string $productId, string $outletId): int
    {
        $sql = "
            SELECT current_stock 
            FROM outlet_inventory 
            WHERE product_id = ? AND outlet_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ss', $productId, $outletId);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['current_stock'] ?? 0);
    }
    
    private function getAllActiveOutlets(): array
    {
        $sql = "
            SELECT outlet_id, name, store_code
            FROM outlets 
            WHERE deleted_at IS NULL 
                AND is_active = 1
                AND is_warehouse = 0
            ORDER BY name
        ";
        
        $result = $this->db->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}