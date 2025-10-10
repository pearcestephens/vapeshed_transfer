<?php

/**
 * Forecasting Service
 * 
 * AI-powered predictive analytics for demand forecasting and inventory optimization.
 * Uses time series analysis, seasonal decomposition, and trend detection to predict
 * future inventory needs and optimize transfer planning.
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
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Product;
use App\Models\Store;

/**
 * Forecasting Service
 * 
 * Provides predictive analytics capabilities for inventory and transfer planning:
 * - Demand forecasting using time series analysis
 * - Seasonal pattern detection
 * - Trend analysis and extrapolation
 * - Transfer recommendations based on predictions
 * - Reorder point optimization
 */
class ForecastingService
{
    private Database $db;
    private Logger $logger;
    private Cache $cache;
    
    /**
     * Minimum data points required for reliable forecasting
     */
    private const MIN_DATA_POINTS = 30;
    
    /**
     * Default forecast horizon in days
     */
    private const DEFAULT_HORIZON = 30;
    
    /**
     * Cache TTL for forecast results (1 hour)
     */
    private const CACHE_TTL = 3600;
    
    /**
     * Seasonal period detection threshold
     */
    private const SEASONAL_THRESHOLD = 0.3;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('forecasting');
        $this->cache = new Cache();
    }
    
    /**
     * Generate demand forecast for a product at a store
     * 
     * @param int $productId Product ID
     * @param int $storeId Store ID
     * @param int $horizon Forecast horizon in days
     * @return array Forecast data with predictions and confidence intervals
     */
    public function forecastProductDemand(int $productId, int $storeId, int $horizon = self::DEFAULT_HORIZON): array
    {
        $cacheKey = "forecast:product:{$productId}:store:{$storeId}:horizon:{$horizon}";
        
        // Check cache first
        if ($cached = $this->cache->get($cacheKey)) {
            $this->logger->info("Returning cached forecast", [
                'product_id' => $productId,
                'store_id' => $storeId
            ]);
            return $cached;
        }
        
        try {
            // Get historical data
            $historicalData = $this->getHistoricalDemand($productId, $storeId);
            
            if (count($historicalData) < self::MIN_DATA_POINTS) {
                $this->logger->warning("Insufficient data for forecasting", [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'data_points' => count($historicalData)
                ]);
                
                return $this->generateSimpleForecast($historicalData, $horizon);
            }
            
            // Decompose time series
            $decomposition = $this->decomposeTimeSeries($historicalData);
            
            // Detect seasonality
            $seasonalPattern = $this->detectSeasonality($decomposition['seasonal']);
            
            // Forecast using appropriate method
            if ($seasonalPattern['is_seasonal']) {
                $forecast = $this->forecastWithSeasonality(
                    $historicalData,
                    $decomposition,
                    $seasonalPattern,
                    $horizon
                );
            } else {
                $forecast = $this->forecastTrend($historicalData, $decomposition, $horizon);
            }
            
            // Calculate confidence intervals
            $forecast['confidence_intervals'] = $this->calculateConfidenceIntervals(
                $historicalData,
                $forecast['predictions']
            );
            
            // Add metadata
            $forecast['metadata'] = [
                'product_id' => $productId,
                'store_id' => $storeId,
                'horizon_days' => $horizon,
                'data_points' => count($historicalData),
                'is_seasonal' => $seasonalPattern['is_seasonal'],
                'seasonal_period' => $seasonalPattern['period'] ?? null,
                'generated_at' => date('Y-m-d H:i:s'),
                'method' => $seasonalPattern['is_seasonal'] ? 'seasonal_decomposition' : 'trend_extrapolation'
            ];
            
            // Cache result
            $this->cache->put($cacheKey, $forecast, self::CACHE_TTL);
            
            $this->logger->info("Generated forecast", [
                'product_id' => $productId,
                'store_id' => $storeId,
                'method' => $forecast['metadata']['method']
            ]);
            
            return $forecast;
            
        } catch (\Exception $e) {
            $this->logger->error("Forecast generation failed", [
                'product_id' => $productId,
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get historical demand data for a product at a store
     * 
     * @param int $productId Product ID
     * @param int $storeId Store ID
     * @param int $days Number of days of historical data (default: 90)
     * @return array Historical demand data (date => quantity)
     */
    private function getHistoricalDemand(int $productId, int $storeId, int $days = 90): array
    {
        $sql = "
            SELECT 
                DATE(ti.created_at) AS date,
                SUM(ti.quantity) AS quantity
            FROM transfer_items ti
            JOIN transfers t ON t.transfer_id = ti.transfer_id
            WHERE ti.product_id = :product_id
            AND (t.from_store_id = :store_id OR t.to_store_id = :store_id)
            AND t.status IN ('approved', 'completed')
            AND ti.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(ti.created_at)
            ORDER BY date ASC
        ";
        
        $result = $this->db->query($sql, [
            'product_id' => $productId,
            'store_id' => $storeId,
            'days' => $days
        ]);
        
        $data = [];
        foreach ($result as $row) {
            $data[$row['date']] = (float) $row['quantity'];
        }
        
        return $data;
    }
    
    /**
     * Decompose time series into trend, seasonal, and residual components
     * 
     * @param array $data Time series data
     * @return array Decomposition components
     */
    private function decomposeTimeSeries(array $data): array
    {
        $values = array_values($data);
        $n = count($values);
        
        // Calculate trend using moving average
        $windowSize = min(7, (int)($n / 3)); // 7-day moving average or 1/3 of data
        $trend = $this->movingAverage($values, $windowSize);
        
        // Calculate detrended series
        $detrended = [];
        for ($i = 0; $i < $n; $i++) {
            $detrended[$i] = $values[$i] - $trend[$i];
        }
        
        // Estimate seasonal component
        $seasonal = $this->extractSeasonalComponent($detrended);
        
        // Calculate residual
        $residual = [];
        for ($i = 0; $i < $n; $i++) {
            $residual[$i] = $values[$i] - $trend[$i] - $seasonal[$i];
        }
        
        return [
            'original' => $values,
            'trend' => $trend,
            'seasonal' => $seasonal,
            'residual' => $residual
        ];
    }
    
    /**
     * Calculate moving average
     * 
     * @param array $values Time series values
     * @param int $windowSize Window size for moving average
     * @return array Moving average values
     */
    private function movingAverage(array $values, int $windowSize): array
    {
        $n = count($values);
        $result = [];
        
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - (int)($windowSize / 2));
            $end = min($n - 1, $i + (int)($windowSize / 2));
            
            $sum = 0;
            $count = 0;
            for ($j = $start; $j <= $end; $j++) {
                $sum += $values[$j];
                $count++;
            }
            
            $result[$i] = $sum / $count;
        }
        
        return $result;
    }
    
    /**
     * Extract seasonal component from detrended data
     * 
     * @param array $detrended Detrended time series
     * @return array Seasonal component
     */
    private function extractSeasonalComponent(array $detrended): array
    {
        $n = count($detrended);
        $seasonal = [];
        
        // Try common seasonal periods (7 days, 30 days)
        $periods = [7, 30];
        $bestPeriod = 7;
        $maxCorrelation = 0;
        
        foreach ($periods as $period) {
            if ($n < $period * 2) continue;
            
            $correlation = $this->autocorrelation($detrended, $period);
            if ($correlation > $maxCorrelation) {
                $maxCorrelation = $correlation;
                $bestPeriod = $period;
            }
        }
        
        // Extract seasonal pattern
        $seasonalPattern = array_fill(0, $bestPeriod, 0);
        $seasonalCounts = array_fill(0, $bestPeriod, 0);
        
        for ($i = 0; $i < $n; $i++) {
            $seasonalIndex = $i % $bestPeriod;
            $seasonalPattern[$seasonalIndex] += $detrended[$i];
            $seasonalCounts[$seasonalIndex]++;
        }
        
        // Average seasonal values
        for ($i = 0; $i < $bestPeriod; $i++) {
            if ($seasonalCounts[$i] > 0) {
                $seasonalPattern[$i] /= $seasonalCounts[$i];
            }
        }
        
        // Normalize (center around 0)
        $mean = array_sum($seasonalPattern) / $bestPeriod;
        $seasonalPattern = array_map(function($v) use ($mean) {
            return $v - $mean;
        }, $seasonalPattern);
        
        // Repeat pattern to match data length
        for ($i = 0; $i < $n; $i++) {
            $seasonal[$i] = $seasonalPattern[$i % $bestPeriod];
        }
        
        return $seasonal;
    }
    
    /**
     * Calculate autocorrelation at a specific lag
     * 
     * @param array $data Time series data
     * @param int $lag Lag period
     * @return float Autocorrelation coefficient
     */
    private function autocorrelation(array $data, int $lag): float
    {
        $n = count($data);
        if ($lag >= $n) return 0;
        
        $mean = array_sum($data) / $n;
        
        $numerator = 0;
        $denominator = 0;
        
        for ($i = 0; $i < $n - $lag; $i++) {
            $numerator += ($data[$i] - $mean) * ($data[$i + $lag] - $mean);
        }
        
        for ($i = 0; $i < $n; $i++) {
            $denominator += pow($data[$i] - $mean, 2);
        }
        
        return $denominator > 0 ? $numerator / $denominator : 0;
    }
    
    /**
     * Detect seasonality in time series
     * 
     * @param array $seasonal Seasonal component from decomposition
     * @return array Seasonality information
     */
    private function detectSeasonality(array $seasonal): array
    {
        $variance = $this->calculateVariance($seasonal);
        $totalVariance = $variance;
        
        // If seasonal variance is significant, we have seasonality
        $isSeasonal = $variance > self::SEASONAL_THRESHOLD;
        
        $periods = [7, 30]; // Weekly, monthly
        $detectedPeriod = null;
        $maxStrength = 0;
        
        foreach ($periods as $period) {
            if (count($seasonal) < $period * 2) continue;
            
            $strength = abs($this->autocorrelation($seasonal, $period));
            if ($strength > $maxStrength) {
                $maxStrength = $strength;
                $detectedPeriod = $period;
            }
        }
        
        return [
            'is_seasonal' => $isSeasonal,
            'period' => $detectedPeriod,
            'strength' => $maxStrength,
            'variance' => $variance
        ];
    }
    
    /**
     * Calculate variance of a dataset
     * 
     * @param array $data Data array
     * @return float Variance
     */
    private function calculateVariance(array $data): float
    {
        $n = count($data);
        if ($n < 2) return 0;
        
        $mean = array_sum($data) / $n;
        
        $sumSquares = 0;
        foreach ($data as $value) {
            $sumSquares += pow($value - $mean, 2);
        }
        
        return $sumSquares / ($n - 1);
    }
    
    /**
     * Forecast with seasonal patterns
     * 
     * @param array $historicalData Historical demand data
     * @param array $decomposition Time series decomposition
     * @param array $seasonalPattern Detected seasonal pattern
     * @param int $horizon Forecast horizon
     * @return array Forecast predictions
     */
    private function forecastWithSeasonality(
        array $historicalData,
        array $decomposition,
        array $seasonalPattern,
        int $horizon
    ): array {
        $trend = $decomposition['trend'];
        $seasonal = $decomposition['seasonal'];
        $n = count($trend);
        $period = $seasonalPattern['period'];
        
        // Extrapolate trend using linear regression
        $trendSlope = $this->calculateTrendSlope($trend);
        $lastTrend = $trend[$n - 1];
        
        // Extract seasonal pattern for one cycle
        $seasonalCycle = array_slice($seasonal, -$period);
        
        // Generate predictions
        $predictions = [];
        $dates = [];
        $lastDate = array_keys($historicalData)[count($historicalData) - 1];
        
        for ($i = 1; $i <= $horizon; $i++) {
            $trendValue = $lastTrend + ($trendSlope * $i);
            $seasonalIndex = ($n + $i - 1) % $period;
            $seasonalValue = $seasonalCycle[$seasonalIndex];
            
            $prediction = max(0, $trendValue + $seasonalValue); // Non-negative
            
            $date = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            $predictions[$date] = round($prediction, 2);
            $dates[] = $date;
        }
        
        return [
            'predictions' => $predictions,
            'dates' => $dates,
            'trend_slope' => $trendSlope,
            'seasonal_pattern' => $seasonalCycle
        ];
    }
    
    /**
     * Forecast using trend extrapolation (non-seasonal)
     * 
     * @param array $historicalData Historical demand data
     * @param array $decomposition Time series decomposition
     * @param int $horizon Forecast horizon
     * @return array Forecast predictions
     */
    private function forecastTrend(array $historicalData, array $decomposition, int $horizon): array
    {
        $trend = $decomposition['trend'];
        $n = count($trend);
        
        // Calculate trend slope using linear regression
        $trendSlope = $this->calculateTrendSlope($trend);
        $lastTrend = $trend[$n - 1];
        
        // Generate predictions
        $predictions = [];
        $dates = [];
        $lastDate = array_keys($historicalData)[count($historicalData) - 1];
        
        for ($i = 1; $i <= $horizon; $i++) {
            $prediction = max(0, $lastTrend + ($trendSlope * $i));
            
            $date = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            $predictions[$date] = round($prediction, 2);
            $dates[] = $date;
        }
        
        return [
            'predictions' => $predictions,
            'dates' => $dates,
            'trend_slope' => $trendSlope,
            'seasonal_pattern' => null
        ];
    }
    
    /**
     * Calculate trend slope using linear regression
     * 
     * @param array $trend Trend component
     * @return float Trend slope
     */
    private function calculateTrendSlope(array $trend): float
    {
        $n = count($trend);
        
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $trend[$i];
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if ($denominator == 0) return 0;
        
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        
        return $slope;
    }
    
    /**
     * Generate simple forecast (fallback for insufficient data)
     * 
     * @param array $historicalData Historical demand data
     * @param int $horizon Forecast horizon
     * @return array Simple forecast
     */
    private function generateSimpleForecast(array $historicalData, int $horizon): array
    {
        $values = array_values($historicalData);
        $average = count($values) > 0 ? array_sum($values) / count($values) : 0;
        
        $predictions = [];
        $dates = [];
        $lastDate = count($historicalData) > 0 
            ? array_keys($historicalData)[count($historicalData) - 1]
            : date('Y-m-d');
        
        for ($i = 1; $i <= $horizon; $i++) {
            $date = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            $predictions[$date] = round($average, 2);
            $dates[] = $date;
        }
        
        return [
            'predictions' => $predictions,
            'dates' => $dates,
            'trend_slope' => 0,
            'seasonal_pattern' => null,
            'confidence_intervals' => [],
            'metadata' => [
                'method' => 'simple_average',
                'data_points' => count($historicalData),
                'warning' => 'Insufficient data for advanced forecasting'
            ]
        ];
    }
    
    /**
     * Calculate confidence intervals for predictions
     * 
     * @param array $historicalData Historical data
     * @param array $predictions Forecast predictions
     * @return array Confidence intervals (lower and upper bounds)
     */
    private function calculateConfidenceIntervals(array $historicalData, array $predictions): array
    {
        $historicalValues = array_values($historicalData);
        $stdDev = $this->calculateStandardDeviation($historicalValues);
        
        // 95% confidence interval (±1.96 * std dev)
        $z = 1.96;
        
        $intervals = [];
        foreach ($predictions as $date => $prediction) {
            $intervals[$date] = [
                'lower' => max(0, round($prediction - ($z * $stdDev), 2)),
                'upper' => round($prediction + ($z * $stdDev), 2),
                'prediction' => $prediction
            ];
        }
        
        return $intervals;
    }
    
    /**
     * Calculate standard deviation
     * 
     * @param array $data Data array
     * @return float Standard deviation
     */
    private function calculateStandardDeviation(array $data): float
    {
        $variance = $this->calculateVariance($data);
        return sqrt($variance);
    }
    
    /**
     * Generate transfer recommendations based on forecasts
     * 
     * @param int $storeId Store ID
     * @param int $horizon Forecast horizon in days
     * @return array Transfer recommendations
     */
    public function generateTransferRecommendations(int $storeId, int $horizon = self::DEFAULT_HORIZON): array
    {
        $cacheKey = "recommendations:store:{$storeId}:horizon:{$horizon}";
        
        // Check cache
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        try {
            // Get current inventory levels
            $inventory = $this->getCurrentInventory($storeId);
            
            // Get all active products
            $products = $this->getActiveProducts();
            
            $recommendations = [];
            
            foreach ($products as $product) {
                $productId = $product['product_id'];
                
                // Get forecast
                $forecast = $this->forecastProductDemand($productId, $storeId, $horizon);
                
                // Calculate total predicted demand
                $totalPredictedDemand = array_sum($forecast['predictions']);
                
                // Get current stock level
                $currentStock = $inventory[$productId] ?? 0;
                
                // Calculate recommended transfer quantity
                $recommendedTransfer = max(0, $totalPredictedDemand - $currentStock);
                
                // Calculate urgency score (0-100)
                $daysUntilStockout = $currentStock > 0 
                    ? ($currentStock / ($totalPredictedDemand / $horizon))
                    : 0;
                
                $urgency = $this->calculateUrgencyScore($daysUntilStockout, $totalPredictedDemand);
                
                if ($recommendedTransfer > 0 || $urgency > 50) {
                    $recommendations[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'sku' => $product['sku'],
                        'current_stock' => $currentStock,
                        'predicted_demand' => round($totalPredictedDemand, 2),
                        'recommended_transfer' => round($recommendedTransfer, 2),
                        'days_until_stockout' => round($daysUntilStockout, 1),
                        'urgency_score' => $urgency,
                        'urgency_level' => $this->getUrgencyLevel($urgency),
                        'forecast_confidence' => $this->calculateForecastConfidence($forecast)
                    ];
                }
            }
            
            // Sort by urgency (highest first)
            usort($recommendations, function($a, $b) {
                return $b['urgency_score'] - $a['urgency_score'];
            });
            
            $result = [
                'store_id' => $storeId,
                'generated_at' => date('Y-m-d H:i:s'),
                'horizon_days' => $horizon,
                'recommendations' => $recommendations,
                'summary' => [
                    'total_recommendations' => count($recommendations),
                    'critical' => count(array_filter($recommendations, fn($r) => $r['urgency_level'] === 'critical')),
                    'high' => count(array_filter($recommendations, fn($r) => $r['urgency_level'] === 'high')),
                    'medium' => count(array_filter($recommendations, fn($r) => $r['urgency_level'] === 'medium')),
                    'low' => count(array_filter($recommendations, fn($r) => $r['urgency_level'] === 'low'))
                ]
            ];
            
            // Cache result
            $this->cache->put($cacheKey, $result, self::CACHE_TTL);
            
            $this->logger->info("Generated transfer recommendations", [
                'store_id' => $storeId,
                'total_recommendations' => count($recommendations)
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to generate recommendations", [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get current inventory levels for a store
     * 
     * @param int $storeId Store ID
     * @return array Product ID => quantity mapping
     */
    private function getCurrentInventory(int $storeId): array
    {
        $sql = "
            SELECT 
                product_id,
                SUM(quantity) AS total_quantity
            FROM inventory_snapshots
            WHERE store_id = :store_id
            AND snapshot_date = (
                SELECT MAX(snapshot_date)
                FROM inventory_snapshots
                WHERE store_id = :store_id
            )
            GROUP BY product_id
        ";
        
        $result = $this->db->query($sql, ['store_id' => $storeId]);
        
        $inventory = [];
        foreach ($result as $row) {
            $inventory[$row['product_id']] = (float) $row['total_quantity'];
        }
        
        return $inventory;
    }
    
    /**
     * Get all active products
     * 
     * @return array Active products
     */
    private function getActiveProducts(): array
    {
        $sql = "
            SELECT 
                product_id,
                name,
                sku,
                category
            FROM products
            WHERE active = 1
            ORDER BY name ASC
        ";
        
        return $this->db->query($sql);
    }
    
    /**
     * Calculate urgency score (0-100)
     * 
     * @param float $daysUntilStockout Days until stockout
     * @param float $predictedDemand Predicted demand
     * @return int Urgency score (0-100)
     */
    private function calculateUrgencyScore(float $daysUntilStockout, float $predictedDemand): int
    {
        if ($daysUntilStockout <= 0) {
            return 100; // Already out of stock
        }
        
        if ($daysUntilStockout <= 3) {
            return 90; // Critical - less than 3 days
        }
        
        if ($daysUntilStockout <= 7) {
            return 70; // High - less than a week
        }
        
        if ($daysUntilStockout <= 14) {
            return 50; // Medium - less than 2 weeks
        }
        
        if ($daysUntilStockout <= 30) {
            return 30; // Low - less than a month
        }
        
        return 10; // Very low urgency
    }
    
    /**
     * Get urgency level label
     * 
     * @param int $urgencyScore Urgency score (0-100)
     * @return string Urgency level
     */
    private function getUrgencyLevel(int $urgencyScore): string
    {
        if ($urgencyScore >= 80) return 'critical';
        if ($urgencyScore >= 60) return 'high';
        if ($urgencyScore >= 40) return 'medium';
        return 'low';
    }
    
    /**
     * Calculate forecast confidence level
     * 
     * @param array $forecast Forecast data
     * @return string Confidence level (high, medium, low)
     */
    private function calculateForecastConfidence(array $forecast): string
    {
        $dataPoints = $forecast['metadata']['data_points'] ?? 0;
        $method = $forecast['metadata']['method'] ?? 'unknown';
        
        if ($dataPoints < self::MIN_DATA_POINTS) {
            return 'low';
        }
        
        if ($method === 'seasonal_decomposition' && $dataPoints >= 60) {
            return 'high';
        }
        
        if ($dataPoints >= 45) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Clear forecast cache
     * 
     * @param int|null $productId Optional product ID to clear specific forecast
     * @param int|null $storeId Optional store ID to clear specific forecast
     * @return bool Success
     */
    public function clearCache(?int $productId = null, ?int $storeId = null): bool
    {
        if ($productId && $storeId) {
            // Clear specific forecast
            $pattern = "forecast:product:{$productId}:store:{$storeId}:*";
        } elseif ($storeId) {
            // Clear all forecasts for a store
            $pattern = "forecast:product:*:store:{$storeId}:*";
        } else {
            // Clear all forecasts
            $pattern = "forecast:*";
        }
        
        return $this->cache->deletePattern($pattern);
    }
}
