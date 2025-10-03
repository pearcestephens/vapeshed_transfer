<?php
declare(strict_types=1);

namespace App\Services\Pricing;

use App\Core\Logger;
use App\Core\Database;

/**
 * Advanced Price Intelligence Engine
 * 
 * Real algorithms for competitive pricing analysis and optimization
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */
class PriceIntelligenceEngine
{
    private Logger $logger;
    private Database $db;
    private array $marketData = [];
    private array $competitorProfiles = [];
    
    public function __construct()
    {
        $this->logger = new Logger();
        $this->db = Database::getInstance();
    }
    
    /**
     * Analyze price elasticity for a product using sales data
     */
    public function analyzePriceElasticity(string $productId, int $daysPeriod = 90): array
    {
        $startTime = microtime(true);
        
        try {
            // Get historical price and sales data
            $data = $this->getHistoricalPricingData($productId, $daysPeriod);
            
            if (count($data) < 10) {
                throw new \Exception("Insufficient data for elasticity analysis (need 10+ data points)");
            }
            
            // Calculate price elasticity using percentage change method
            $elasticity = $this->calculatePriceElasticity($data);
            
            // Determine sensitivity level
            $sensitivityLevel = $this->categorizePriceSensitivity($elasticity);
            
            // Calculate optimal price range based on elasticity
            $currentPrice = $this->getCurrentPrice($productId);
            $optimalRange = $this->calculateOptimalPriceRange($currentPrice, $elasticity, $data);
            
            $executionTime = microtime(true) - $startTime;
            
            $result = [
                'product_id' => $productId,
                'elasticity_coefficient' => round($elasticity, 4),
                'sensitivity_level' => $sensitivityLevel,
                'current_price' => $currentPrice,
                'optimal_price_range' => $optimalRange,
                'confidence_score' => $this->calculateConfidenceScore($data),
                'data_points_analyzed' => count($data),
                'analysis_period_days' => $daysPeriod,
                'execution_time' => round($executionTime, 3),
                'recommendations' => $this->generatePricingRecommendations($elasticity, $currentPrice, $optimalRange)
            ];
            
            // Store analysis result
            $this->storePriceAnalysis($result);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Price elasticity analysis failed', [
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
     * Competitive price positioning analysis
     */
    public function analyzeCompetitivePosition(string $productId): array
    {
        $startTime = microtime(true);
        
        try {
            $ourPrice = $this->getCurrentPrice($productId);
            $competitorPrices = $this->getCompetitorPrices($productId);
            
            if (empty($competitorPrices)) {
                throw new \Exception("No competitor pricing data available");
            }
            
            // Calculate market position metrics
            $position = $this->calculateMarketPosition($ourPrice, $competitorPrices);
            
            // Analyze price gaps and opportunities
            $opportunities = $this->identifyPricingOpportunities($ourPrice, $competitorPrices);
            
            // Calculate competitive strength
            $competitiveStrength = $this->assessCompetitiveStrength($ourPrice, $competitorPrices);
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'product_id' => $productId,
                'our_price' => $ourPrice,
                'market_position' => $position,
                'competitive_strength' => $competitiveStrength,
                'pricing_opportunities' => $opportunities,
                'competitor_analysis' => [
                    'count' => count($competitorPrices),
                    'price_range' => [
                        'min' => min($competitorPrices),
                        'max' => max($competitorPrices),
                        'avg' => round(array_sum($competitorPrices) / count($competitorPrices), 2)
                    ]
                ],
                'execution_time' => round($executionTime, 3)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Competitive analysis failed', [
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
     * Dynamic pricing optimization based on multiple factors
     */
    public function optimizePricing(array $config = []): array
    {
        $startTime = microtime(true);
        
        try {
            $products = $this->getProductsForOptimization($config);
            $optimizedPrices = [];
            
            foreach ($products as $product) {
                $optimization = $this->optimizeProductPrice($product, $config);
                if ($optimization['recommended_price'] !== $optimization['current_price']) {
                    $optimizedPrices[] = $optimization;
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'products_analyzed' => count($products),
                'optimizations_found' => count($optimizedPrices),
                'optimized_prices' => $optimizedPrices,
                'total_revenue_impact' => $this->calculateTotalRevenueImpact($optimizedPrices),
                'execution_time' => round($executionTime, 3),
                'analysis_timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Price optimization failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    // Private calculation methods
    
    private function calculatePriceElasticity(array $data): float
    {
        $elasticities = [];
        
        for ($i = 1; $i < count($data); $i++) {
            $priceChange = ($data[$i]['price'] - $data[$i-1]['price']) / $data[$i-1]['price'];
            $quantityChange = ($data[$i]['quantity'] - $data[$i-1]['quantity']) / max($data[$i-1]['quantity'], 1);
            
            if ($priceChange != 0) {
                $elasticities[] = $quantityChange / $priceChange;
            }
        }
        
        return empty($elasticities) ? 0 : array_sum($elasticities) / count($elasticities);
    }
    
    private function categorizePriceSensitivity(float $elasticity): string
    {
        $absElasticity = abs($elasticity);
        
        if ($absElasticity > 2.0) return 'Highly Elastic';
        if ($absElasticity > 1.0) return 'Elastic';
        if ($absElasticity > 0.5) return 'Moderately Elastic';
        return 'Inelastic';
    }
    
    private function calculateOptimalPriceRange(float $currentPrice, float $elasticity, array $data): array
    {
        // Calculate revenue-maximizing price based on elasticity
        $avgQuantity = array_sum(array_column($data, 'quantity')) / count($data);
        $avgRevenue = array_sum(array_map(fn($d) => $d['price'] * $d['quantity'], $data)) / count($data);
        
        // Use calculus-based optimization for revenue maximization
        $optimalMultiplier = 1 / (1 + (1 / abs($elasticity)));
        $optimalPrice = $currentPrice * $optimalMultiplier;
        
        // Create confidence range based on data variance
        $variance = $this->calculatePriceVariance($data);
        $confidenceRange = $variance * 0.1; // 10% of variance as confidence interval
        
        return [
            'optimal_price' => round($optimalPrice, 2),
            'min_recommended' => round($optimalPrice - $confidenceRange, 2),
            'max_recommended' => round($optimalPrice + $confidenceRange, 2),
            'current_vs_optimal' => round((($optimalPrice - $currentPrice) / $currentPrice) * 100, 2)
        ];
    }
    
    private function calculateMarketPosition(float $ourPrice, array $competitorPrices): array
    {
        sort($competitorPrices);
        $totalPrices = array_merge([$ourPrice], $competitorPrices);
        sort($totalPrices);
        
        $ourRank = array_search($ourPrice, $totalPrices) + 1;
        $totalCompetitors = count($totalPrices);
        $percentile = ($ourRank / $totalCompetitors) * 100;
        
        $positionLabel = 'Middle';
        if ($percentile <= 25) $positionLabel = 'Premium';
        elseif ($percentile <= 50) $positionLabel = 'Upper Middle';
        elseif ($percentile <= 75) $positionLabel = 'Lower Middle';
        else $positionLabel = 'Budget';
        
        return [
            'rank' => $ourRank,
            'total_competitors' => $totalCompetitors,
            'percentile' => round($percentile, 1),
            'position_label' => $positionLabel,
            'price_difference_vs_avg' => round($ourPrice - (array_sum($competitorPrices) / count($competitorPrices)), 2)
        ];
    }
    
    private function identifyPricingOpportunities(float $ourPrice, array $competitorPrices): array
    {
        $opportunities = [];
        $avgCompetitorPrice = array_sum($competitorPrices) / count($competitorPrices);
        
        // Price gap analysis
        if ($ourPrice > max($competitorPrices)) {
            $opportunities[] = [
                'type' => 'price_reduction',
                'description' => 'Priced above all competitors',
                'potential_price' => max($competitorPrices) * 0.95,
                'expected_impact' => 'Higher market share'
            ];
        }
        
        if ($ourPrice < min($competitorPrices) * 0.9) {
            $opportunities[] = [
                'type' => 'price_increase',
                'description' => 'Significantly underpriced vs market',
                'potential_price' => min($competitorPrices) * 0.95,
                'expected_impact' => 'Increased profit margin'
            ];
        }
        
        // Sweet spot analysis
        $sweetSpot = $this->findPriceSweetSpot($competitorPrices);
        if (abs($ourPrice - $sweetSpot) > $sweetSpot * 0.05) {
            $opportunities[] = [
                'type' => 'sweet_spot_positioning',
                'description' => 'Optimize for competitive sweet spot',
                'potential_price' => $sweetSpot,
                'expected_impact' => 'Balanced competitiveness and profitability'
            ];
        }
        
        return $opportunities;
    }
    
    private function findPriceSweetSpot(array $competitorPrices): float
    {
        // Find the price point that captures most market opportunity
        sort($competitorPrices);
        $median = $competitorPrices[intval(count($competitorPrices) / 2)];
        $q1 = $competitorPrices[intval(count($competitorPrices) * 0.25)];
        
        // Sweet spot is between Q1 and median
        return ($q1 + $median) / 2;
    }
    
    private function optimizeProductPrice(array $product, array $config): array
    {
        $productId = $product['product_id'];
        $currentPrice = $product['current_price'];
        
        // Multi-factor optimization
        $factors = [];
        
        // Factor 1: Elasticity-based optimization
        $elasticityAnalysis = $this->analyzePriceElasticity($productId, 30);
        if (!isset($elasticityAnalysis['error'])) {
            $factors['elasticity'] = [
                'weight' => 0.4,
                'recommended_price' => $elasticityAnalysis['optimal_price_range']['optimal_price']
            ];
        }
        
        // Factor 2: Competitive positioning
        $competitiveAnalysis = $this->analyzeCompetitivePosition($productId);
        if (!isset($competitiveAnalysis['error'])) {
            $factors['competitive'] = [
                'weight' => 0.3,
                'recommended_price' => $this->extractCompetitivePrice($competitiveAnalysis)
            ];
        }
        
        // Factor 3: Inventory optimization
        $factors['inventory'] = [
            'weight' => 0.2,
            'recommended_price' => $this->calculateInventoryOptimizedPrice($product)
        ];
        
        // Factor 4: Margin requirements
        $factors['margin'] = [
            'weight' => 0.1,
            'recommended_price' => $this->calculateMarginOptimizedPrice($product, $config)
        ];
        
        // Calculate weighted optimal price
        $recommendedPrice = $this->calculateWeightedPrice($factors, $currentPrice);
        
        return [
            'product_id' => $productId,
            'current_price' => $currentPrice,
            'recommended_price' => $recommendedPrice,
            'price_change' => round($recommendedPrice - $currentPrice, 2),
            'price_change_percent' => round((($recommendedPrice - $currentPrice) / $currentPrice) * 100, 2),
            'optimization_factors' => $factors,
            'confidence_score' => $this->calculateOptimizationConfidence($factors),
            'expected_impact' => $this->estimateRevenueImpact($product, $recommendedPrice)
        ];
    }
    
    private function calculateWeightedPrice(array $factors, float $currentPrice): float
    {
        $weightedSum = 0;
        $totalWeight = 0;
        
        foreach ($factors as $factor) {
            if (isset($factor['recommended_price']) && $factor['recommended_price'] > 0) {
                $weightedSum += $factor['recommended_price'] * $factor['weight'];
                $totalWeight += $factor['weight'];
            }
        }
        
        if ($totalWeight == 0) return $currentPrice;
        
        $optimizedPrice = $weightedSum / $totalWeight;
        
        // Apply safety bounds (±25% of current price)
        $minPrice = $currentPrice * 0.75;
        $maxPrice = $currentPrice * 1.25;
        
        return round(max($minPrice, min($maxPrice, $optimizedPrice)), 2);
    }
    
    // Database helper methods
    
    private function getHistoricalPricingData(string $productId, int $days): array
    {
        $sql = "
            SELECT 
                p.price,
                s.quantity_sold as quantity,
                p.created_at as date
            FROM price_history p
            LEFT JOIN daily_sales_summary s ON s.product_id = p.product_id 
                AND DATE(s.sale_date) = DATE(p.created_at)
            WHERE p.product_id = ?
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY p.created_at ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $productId, $days);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getCurrentPrice(string $productId): float
    {
        $sql = "SELECT current_price FROM products WHERE product_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('s', $productId);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return (float)($result['current_price'] ?? 0);
    }
    
    private function getCompetitorPrices(string $productId): array
    {
        $sql = "
            SELECT price 
            FROM competitor_prices cp
            JOIN product_mappings pm ON pm.competitor_product_id = cp.competitor_product_id
            WHERE pm.our_product_id = ?
                AND cp.last_updated >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND cp.price > 0
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('s', $productId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = (float)$row['price'];
        }
        
        return $prices;
    }
    
    private function storePriceAnalysis(array $analysis): void
    {
        $sql = "
            INSERT INTO pricing_analyses (
                product_id, analysis_type, analysis_data, created_at
            ) VALUES (?, 'elasticity', ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $analysisJson = json_encode($analysis);
        $stmt->bind_param('ss', $analysis['product_id'], $analysisJson);
        $stmt->execute();
    }
}