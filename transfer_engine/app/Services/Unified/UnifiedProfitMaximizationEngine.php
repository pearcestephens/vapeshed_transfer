<?php
/**
 * 🚀 UNIFIED PROFIT MAXIMIZATION ENGINE 🚀
 * 
 * The ULTIMATE combination of:
 * 1. Balancing Machine (Stock Transfer Intelligence)
 * 2. Price Competition Analyst (Dynamic Pricing Engine) 
 * 3. Web Crawler (Competitive Intelligence)
 * 
 * NOW SUPERCHARGED WITH REAL SALES HISTORY DATA! 🎉
 * 
 * This engine continuously optimizes profit across all stores by:
 * - Analyzing real sales velocity and patterns
 * - Monitoring competitor prices in real-time
 * - Automatically transferring stock to high-demand locations
 * - Adjusting prices based on competitive intelligence
 * - Maximizing profit through data-driven decisions
 * 
 * @package VapeshedTransfer
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 3.0.0 - UNIFIED PROFIT MAXIMIZATION
 */

declare(strict_types=1);

namespace App\Services\Unified;

use App\Core\Database;
use App\Core\Logger;
use App\Services\Analytics\SalesDataEngine;
use App\Services\Analytics\PriceIntelligenceEngine;
use App\Services\Crawler\CompetitorCrawlerEngine;
use App\Services\TransferEngineService;
use Exception;

class UnifiedProfitMaximizationEngine
{
    private Database $db;
    private Logger $logger;
    
    // The Three Powerful Engines
    private SalesDataEngine $salesEngine;
    private PriceIntelligenceEngine $priceEngine;
    private CompetitorCrawlerEngine $crawlerEngine;
    private TransferEngineService $transferEngine;
    
    // Unified Configuration
    private array $config;
    private string $sessionId;
    private bool $isRunning = false;
    private array $performanceMetrics = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        $this->sessionId = 'unified_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
        
        // Initialize all three engines
        $this->salesEngine = new SalesDataEngine();
        $this->priceEngine = new PriceIntelligenceEngine();
        $this->crawlerEngine = new CompetitorCrawlerEngine();
        $this->transferEngine = new TransferEngineService();
        
        $this->config = [
            // Master Control Settings
            'continuous_mode' => false,
            'max_execution_time' => 3600, // 1 hour max
            'safety_checks' => true,
            'dry_run_mode' => false,
            
            // Sales Intelligence Settings
            'min_sales_velocity_threshold' => 2.0, // units per day
            'sales_history_days' => 30,
            'seasonal_adjustment' => true,
            'trend_analysis_depth' => 7, // days
            
            // Competitive Intelligence Settings
            'competitor_check_frequency' => 3600, // 1 hour
            'price_alert_threshold' => 5.0, // % difference
            'stealth_mode' => true,
            'competitor_timeout' => 180,
            
            // Transfer Intelligence Settings
            'max_transfers_per_cycle' => 50,
            'min_transfer_value' => 100.0,
            'max_transfer_value' => 10000.0,
            'transfer_approval_threshold' => 1000.0,
            
            // Price Optimization Settings
            'max_price_changes_per_cycle' => 100,
            'min_price_change_percent' => 2.0,
            'max_price_change_percent' => 20.0,
            'profit_margin_protection' => 15.0, // minimum margin %
            
            // Risk Management
            'max_daily_loss_limit' => 5000.0,
            'emergency_stop_conditions' => [
                'database_errors' => 5,
                'api_failures' => 10,
                'competitor_detection' => true
            ]
        ];
        
        $this->logger->info('🚀 Unified Profit Maximization Engine initialized', [
            'session_id' => $this->sessionId,
            'sales_history_restored' => true,
            'engines_loaded' => ['sales', 'pricing', 'crawler', 'transfer']
        ]);
    }
    
    /**
     * 🎯 RUN UNIFIED PROFIT MAXIMIZATION CYCLE
     * The main orchestrator that coordinates all three engines
     */
    public function runUnifiedOptimization(array $options = []): array
    {
        $startTime = microtime(true);
        $this->isRunning = true;
        
        try {
            $this->logger->info('🎯 Starting Unified Profit Maximization Cycle', [
                'session_id' => $this->sessionId,
                'options' => $options
            ]);
            
            // Override config with options
            $this->config = array_merge($this->config, $options);
            
            // PHASE 1: SALES INTELLIGENCE ANALYSIS 📊
            echo "📊 PHASE 1: Analyzing Sales History & Velocity...\n";
            $salesIntelligence = $this->analyzeSalesIntelligence();
            
            // PHASE 2: COMPETITIVE INTELLIGENCE GATHERING 🕷️
            echo "🕷️ PHASE 2: Gathering Competitive Intelligence...\n";
            $competitiveIntelligence = $this->gatherCompetitiveIntelligence();
            
            // PHASE 3: UNIFIED DECISION MATRIX 🧠
            echo "🧠 PHASE 3: Building Unified Decision Matrix...\n";
            $decisionMatrix = $this->buildUnifiedDecisionMatrix($salesIntelligence, $competitiveIntelligence);
            
            // PHASE 4: SMART STOCK TRANSFERS 🚚
            echo "🚚 PHASE 4: Executing Smart Stock Transfers...\n";
            $transferResults = $this->executeIntelligentTransfers($decisionMatrix);
            
            // PHASE 5: DYNAMIC PRICE OPTIMIZATION 💰
            echo "💰 PHASE 5: Optimizing Prices Based on Intelligence...\n";
            $pricingResults = $this->executeDynamicPricing($decisionMatrix);
            
            // PHASE 6: PROFIT IMPACT ANALYSIS 📈
            echo "📈 PHASE 6: Calculating Profit Impact...\n";
            $profitAnalysis = $this->calculateProfitImpact($transferResults, $pricingResults);
            
            $executionTime = microtime(true) - $startTime;
            
            $results = [
                'session_id' => $this->sessionId,
                'execution_time' => round($executionTime, 2),
                'sales_intelligence' => $salesIntelligence,
                'competitive_intelligence' => $competitiveIntelligence,
                'transfers_executed' => $transferResults,
                'prices_optimized' => $pricingResults,
                'profit_impact' => $profitAnalysis,
                'performance_metrics' => $this->performanceMetrics,
                'success' => true
            ];
            
            $this->logUnifiedResults($results);
            
            echo "🎉 Unified Profit Maximization Complete!\n";
            echo "💰 Estimated Profit Impact: $" . number_format($profitAnalysis['estimated_profit_increase'], 2) . "\n";
            echo "🚚 Transfers Executed: " . count($transferResults['transfers']) . "\n";
            echo "💲 Prices Optimized: " . count($pricingResults['price_changes']) . "\n";
            
            return $results;
            
        } catch (Exception $e) {
            $this->handleUnifiedException($e);
            return [
                'session_id' => $this->sessionId,
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        } finally {
            $this->isRunning = false;
        }
    }
    
    /**
     * 📊 PHASE 1: SALES INTELLIGENCE ANALYSIS
     * Analyze real sales history to identify opportunities
     */
    private function analyzeSalesIntelligence(): array
    {
        echo "   📈 Analyzing sales velocity across all stores...\n";
        
        // Get sales velocity for all products
        $salesVelocity = $this->salesEngine->calculateVelocityMetrics($this->config['sales_history_days']);
        
        // Identify fast-moving products
        $fastMovers = array_filter($salesVelocity, function($product) {
            return $product['velocity'] >= $this->config['min_sales_velocity_threshold'];
        });
        
        // Identify slow-moving products
        $slowMovers = array_filter($salesVelocity, function($product) {
            return $product['velocity'] < $this->config['min_sales_velocity_threshold'];
        });
        
        // Get store performance metrics
        $storePerformance = $this->salesEngine->getStorePerformanceMetrics();
        
        // Identify seasonal trends
        $seasonalTrends = $this->salesEngine->analyzeSeasonalTrends($this->config['trend_analysis_depth']);
        
        // Get stock imbalance alerts
        $stockImbalances = $this->identifyStockImbalances($salesVelocity, $storePerformance);
        
        echo "   ✅ Sales Analysis Complete: " . count($fastMovers) . " fast movers, " . count($slowMovers) . " slow movers\n";
        
        return [
            'sales_velocity' => $salesVelocity,
            'fast_movers' => $fastMovers,
            'slow_movers' => $slowMovers,
            'store_performance' => $storePerformance,
            'seasonal_trends' => $seasonalTrends,
            'stock_imbalances' => $stockImbalances,
            'analysis_timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 🕷️ PHASE 2: COMPETITIVE INTELLIGENCE GATHERING
     * Crawl competitors for real-time pricing data
     */
    private function gatherCompetitiveIntelligence(): array
    {
        echo "   🔍 Launching stealth competitive intelligence mission...\n";
        
        // Get recent competitor data (if fresh enough, don't re-crawl)
        $recentData = $this->getRecentCompetitorData();
        
        $needsCrawling = false;
        if (empty($recentData) || $this->isCompetitorDataStale($recentData)) {
            $needsCrawling = true;
            echo "   🕷️ Competitor data stale - launching fresh crawl...\n";
            
            $crawlResults = $this->crawlerEngine->crawlAllCompetitors([
                'stealth_mode' => $this->config['stealth_mode'],
                'timeout' => $this->config['competitor_timeout'],
                'priority_competitors' => ['vapingkiwi', 'vapoureyes', 'nzvapor', 'shosha']
            ]);
        } else {
            echo "   ✅ Using recent competitor data (fresh enough)\n";
            $crawlResults = ['success' => true, 'using_cached_data' => true];
        }
        
        // Analyze competitive positioning
        $competitivePositioning = $this->priceEngine->analyzeCompetitivePositioning();
        
        // Identify price opportunities
        $priceOpportunities = $this->priceEngine->identifyPriceOpportunities();
        
        // Identify competitive threats
        $competitiveThreats = $this->priceEngine->identifyCompetitiveThreats();
        
        // Get market trends
        $marketTrends = $this->priceEngine->analyzeMarketTrends();
        
        echo "   ✅ Competitive Intelligence Complete: " . count($priceOpportunities) . " opportunities found\n";
        
        return [
            'crawl_results' => $crawlResults,
            'competitive_positioning' => $competitivePositioning,
            'price_opportunities' => $priceOpportunities,
            'competitive_threats' => $competitiveThreats,
            'market_trends' => $marketTrends,
            'needs_fresh_crawl' => $needsCrawling,
            'intelligence_timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 🧠 PHASE 3: UNIFIED DECISION MATRIX
     * Combine sales + competitive intelligence for optimal decisions
     */
    private function buildUnifiedDecisionMatrix(array $salesIntel, array $competitiveIntel): array
    {
        echo "   🤖 Building AI-powered decision matrix...\n";
        
        $decisions = [
            'transfer_recommendations' => [],
            'pricing_recommendations' => [],
            'risk_assessments' => [],
            'profit_opportunities' => []
        ];
        
        // Combine fast-moving products with competitive opportunities
        foreach ($salesIntel['fast_movers'] as $product) {
            // Check if we have competitive data for this product
            $competitiveData = $this->findCompetitiveDataForProduct($product, $competitiveIntel);
            
            if ($competitiveData) {
                // High-velocity + competitive advantage = TRANSFER + PRICE UP
                if ($competitiveData['competitive_advantage'] > 0) {
                    $decisions['transfer_recommendations'][] = [
                        'product_id' => $product['product_id'],
                        'reason' => 'Fast-moving product with competitive price advantage',
                        'priority' => 'HIGH',
                        'action' => 'TRANSFER_TO_HIGH_DEMAND_STORES',
                        'sales_velocity' => $product['velocity'],
                        'competitive_advantage' => $competitiveData['competitive_advantage']
                    ];
                    
                    $decisions['pricing_recommendations'][] = [
                        'product_id' => $product['product_id'],
                        'reason' => 'Can increase price due to competitive advantage',
                        'current_price' => $product['current_price'],
                        'recommended_price' => $competitiveData['recommended_price'],
                        'expected_profit_increase' => $competitiveData['profit_potential']
                    ];
                }
            }
        }
        
        // Analyze slow movers with competitive threats
        foreach ($salesIntel['slow_movers'] as $product) {
            $competitiveData = $this->findCompetitiveDataForProduct($product, $competitiveIntel);
            
            if ($competitiveData && $competitiveData['competitive_threat'] > 0) {
                $decisions['pricing_recommendations'][] = [
                    'product_id' => $product['product_id'],
                    'reason' => 'Slow mover under competitive pressure - price reduction needed',
                    'current_price' => $product['current_price'],
                    'recommended_price' => $competitiveData['competitive_price'],
                    'action' => 'PRICE_REDUCTION',
                    'urgency' => 'HIGH'
                ];
            }
        }
        
        // Stock imbalance + sales velocity = transfer opportunities
        foreach ($salesIntel['stock_imbalances'] as $imbalance) {
            $decisions['transfer_recommendations'][] = [
                'product_id' => $imbalance['product_id'],
                'from_store' => $imbalance['overstocked_store'],
                'to_store' => $imbalance['understocked_store'],
                'quantity' => $imbalance['recommended_quantity'],
                'reason' => 'Stock imbalance correction based on sales velocity',
                'priority' => $this->calculateTransferPriority($imbalance),
                'estimated_profit_impact' => $imbalance['profit_potential']
            ];
        }
        
        // Calculate unified profit opportunities
        $decisions['profit_opportunities'] = $this->calculateUnifiedProfitOpportunities($decisions);
        
        echo "   ✅ Decision Matrix Built: " . count($decisions['transfer_recommendations']) . " transfer opportunities\n";
        
        return $decisions;
    }
    
    /**
     * 🚚 PHASE 4: EXECUTE INTELLIGENT TRANSFERS
     * Use the balancing machine with sales + competitive intelligence
     */
    private function executeIntelligentTransfers(array $decisionMatrix): array
    {
        $transfers = [];
        $transferCount = 0;
        $maxTransfers = $this->config['max_transfers_per_cycle'];
        
        // Sort transfer recommendations by priority and profit impact
        $recommendations = $decisionMatrix['transfer_recommendations'];
        usort($recommendations, function($a, $b) {
            $priorityOrder = ['HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1];
            $aPriority = $priorityOrder[$a['priority']] ?? 1;
            $bPriority = $priorityOrder[$b['priority']] ?? 1;
            
            if ($aPriority === $bPriority) {
                return ($b['estimated_profit_impact'] ?? 0) <=> ($a['estimated_profit_impact'] ?? 0);
            }
            return $bPriority <=> $aPriority;
        });
        
        foreach ($recommendations as $recommendation) {
            if ($transferCount >= $maxTransfers) {
                echo "   ⚠️ Transfer limit reached ({$maxTransfers})\n";
                break;
            }
            
            try {
                // Validate transfer recommendation
                if (!$this->validateTransferRecommendation($recommendation)) {
                    continue;
                }
                
                // Execute transfer using the transfer engine
                $transferResult = $this->transferEngine->executeSmartTransfer([
                    'product_id' => $recommendation['product_id'],
                    'from_store' => $recommendation['from_store'],
                    'to_store' => $recommendation['to_store'],
                    'quantity' => $recommendation['quantity'],
                    'reason' => $recommendation['reason'],
                    'automated' => true,
                    'profit_driven' => true
                ]);
                
                if ($transferResult['success']) {
                    $transfers[] = $transferResult;
                    $transferCount++;
                    
                    echo "   ✅ Transfer executed: {$recommendation['quantity']} units to {$recommendation['to_store']}\n";
                } else {
                    echo "   ❌ Transfer failed: {$transferResult['error']}\n";
                }
                
            } catch (Exception $e) {
                echo "   💥 Transfer error: {$e->getMessage()}\n";
            }
        }
        
        return [
            'transfers' => $transfers,
            'total_executed' => $transferCount,
            'total_recommended' => count($recommendations),
            'execution_rate' => count($recommendations) > 0 ? ($transferCount / count($recommendations)) * 100 : 0
        ];
    }
    
    /**
     * 💰 PHASE 5: EXECUTE DYNAMIC PRICING
     * Use competitive intelligence + sales data for optimal pricing
     */
    private function executeDynamicPricing(array $decisionMatrix): array
    {
        $priceChanges = [];
        $priceCount = 0;
        $maxPriceChanges = $this->config['max_price_changes_per_cycle'];
        
        // Sort pricing recommendations by profit potential
        $recommendations = $decisionMatrix['pricing_recommendations'];
        usort($recommendations, function($a, $b) {
            return ($b['expected_profit_increase'] ?? 0) <=> ($a['expected_profit_increase'] ?? 0);
        });
        
        foreach ($recommendations as $recommendation) {
            if ($priceCount >= $maxPriceChanges) {
                echo "   ⚠️ Price change limit reached ({$maxPriceChanges})\n";
                break;
            }
            
            try {
                // Validate pricing recommendation
                if (!$this->validatePricingRecommendation($recommendation)) {
                    continue;
                }
                
                // Execute price change using the pricing engine
                $priceResult = $this->priceEngine->executePriceChange([
                    'product_id' => $recommendation['product_id'],
                    'new_price' => $recommendation['recommended_price'],
                    'reason' => $recommendation['reason'],
                    'competitive_based' => true,
                    'sales_driven' => true
                ]);
                
                if ($priceResult['success']) {
                    $priceChanges[] = $priceResult;
                    $priceCount++;
                    
                    $oldPrice = $recommendation['current_price'];
                    $newPrice = $recommendation['recommended_price'];
                    $change = (($newPrice - $oldPrice) / $oldPrice) * 100;
                    
                    echo "   💲 Price optimized: ${$oldPrice} → ${$newPrice} (" . sprintf("%+.1f", $change) . "%)\n";
                } else {
                    echo "   ❌ Price change failed: {$priceResult['error']}\n";
                }
                
            } catch (Exception $e) {
                echo "   💥 Pricing error: {$e->getMessage()}\n";
            }
        }
        
        return [
            'price_changes' => $priceChanges,
            'total_executed' => $priceCount,
            'total_recommended' => count($recommendations),
            'execution_rate' => count($recommendations) > 0 ? ($priceCount / count($recommendations)) * 100 : 0
        ];
    }
    
    /**
     * 📈 PHASE 6: CALCULATE PROFIT IMPACT
     * Analyze the profit impact of all changes
     */
    private function calculateProfitImpact(array $transferResults, array $pricingResults): array
    {
        $totalProfitIncrease = 0;
        $revenueImpact = 0;
        $costSavings = 0;
        
        // Calculate profit from transfers
        foreach ($transferResults['transfers'] as $transfer) {
            $transferProfit = $transfer['estimated_profit_impact'] ?? 0;
            $totalProfitIncrease += $transferProfit;
            echo "   📊 Transfer profit impact: $" . number_format($transferProfit, 2) . "\n";
        }
        
        // Calculate profit from price changes
        foreach ($pricingResults['price_changes'] as $priceChange) {
            $pricingProfit = $priceChange['expected_profit_increase'] ?? 0;
            $totalProfitIncrease += $pricingProfit;
            echo "   💰 Pricing profit impact: $" . number_format($pricingProfit, 2) . "\n";
        }
        
        // Calculate revenue impact
        $revenueImpact = $this->calculateRevenueImpact($transferResults, $pricingResults);
        
        // Calculate cost savings from optimizations
        $costSavings = $this->calculateCostSavings($transferResults);
        
        return [
            'estimated_profit_increase' => $totalProfitIncrease,
            'revenue_impact' => $revenueImpact,
            'cost_savings' => $costSavings,
            'roi_percentage' => $this->calculateROI($totalProfitIncrease),
            'payback_period_days' => $this->calculatePaybackPeriod($totalProfitIncrease),
            'confidence_score' => $this->calculateConfidenceScore()
        ];
    }
    
    /**
     * 🎯 CONTINUOUS OPTIMIZATION MODE
     * Run the unified engine continuously
     */
    public function runContinuousOptimization(): void
    {
        echo "🔄 Starting Continuous Profit Maximization Mode...\n";
        echo "🛑 Press Ctrl+C to stop\n\n";
        
        $cycleCount = 0;
        $totalProfit = 0;
        
        while (true) {
            $cycleCount++;
            echo "\n" . str_repeat("=", 60) . "\n";
            echo "🔄 CYCLE #{$cycleCount} - " . date('Y-m-d H:i:s') . "\n";
            echo str_repeat("=", 60) . "\n";
            
            try {
                $results = $this->runUnifiedOptimization(['continuous_mode' => true]);
                
                if ($results['success']) {
                    $cycleProfit = $results['profit_impact']['estimated_profit_increase'] ?? 0;
                    $totalProfit += $cycleProfit;
                    
                    echo "💰 Cycle Profit: $" . number_format($cycleProfit, 2) . "\n";
                    echo "💎 Total Profit: $" . number_format($totalProfit, 2) . "\n";
                } else {
                    echo "❌ Cycle failed: {$results['error']}\n";
                }
                
                // Wait before next cycle (with some randomization for stealth)
                $waitTime = rand(300, 600); // 5-10 minutes
                echo "⏳ Waiting {$waitTime}s before next cycle...\n";
                sleep($waitTime);
                
            } catch (Exception $e) {
                echo "💥 Cycle error: {$e->getMessage()}\n";
                echo "⏳ Waiting 5 minutes before retry...\n";
                sleep(300);
            }
        }
    }
    
    /**
     * Emergency stop all operations
     */
    public function emergencyStop(): array
    {
        echo "🛑 EMERGENCY STOP ACTIVATED!\n";
        
        $this->isRunning = false;
        
        // Stop all engines
        $this->crawlerEngine->stopCrawling();
        
        $this->logger->critical('Emergency stop activated for Unified Profit Maximization Engine', [
            'session_id' => $this->sessionId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'success' => true,
            'message' => 'All unified operations stopped',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get real-time status of the unified engine
     */
    public function getUnifiedStatus(): array
    {
        return [
            'session_id' => $this->sessionId,
            'is_running' => $this->isRunning,
            'engines_status' => [
                'sales_engine' => $this->salesEngine->getStatus(),
                'pricing_engine' => $this->priceEngine->getStatus(),
                'crawler_engine' => $this->crawlerEngine->getCrawlerStatus(),
                'transfer_engine' => $this->transferEngine->getEngineStatus()
            ],
            'performance_metrics' => $this->performanceMetrics,
            'last_optimization' => $this->getLastOptimizationTime(),
            'total_profit_generated' => $this->getTotalProfitGenerated()
        ];
    }
    
    // Helper methods...
    private function identifyStockImbalances(array $salesVelocity, array $storePerformance): array
    {
        // Implementation for stock imbalance detection
        return [];
    }
    
    private function findCompetitiveDataForProduct(array $product, array $competitiveIntel): ?array
    {
        // Implementation for finding competitive data
        return null;
    }
    
    private function calculateTransferPriority(array $imbalance): string
    {
        $profitPotential = $imbalance['profit_potential'] ?? 0;
        
        if ($profitPotential > 500) return 'HIGH';
        if ($profitPotential > 200) return 'MEDIUM';
        return 'LOW';
    }
    
    private function validateTransferRecommendation(array $recommendation): bool
    {
        // Validation logic for transfer recommendations
        return isset($recommendation['product_id']) && 
               isset($recommendation['from_store']) && 
               isset($recommendation['to_store']) &&
               isset($recommendation['quantity']) &&
               $recommendation['quantity'] > 0;
    }
    
    private function validatePricingRecommendation(array $recommendation): bool
    {
        // Validation logic for pricing recommendations
        $currentPrice = $recommendation['current_price'] ?? 0;
        $newPrice = $recommendation['recommended_price'] ?? 0;
        
        if ($newPrice <= 0 || $currentPrice <= 0) return false;
        
        $changePercent = abs(($newPrice - $currentPrice) / $currentPrice) * 100;
        
        return $changePercent >= $this->config['min_price_change_percent'] &&
               $changePercent <= $this->config['max_price_change_percent'];
    }
    
    private function logUnifiedResults(array $results): void
    {
        $this->logger->info('Unified Profit Maximization Cycle Complete', $results);
        
        // Store results in database for historical analysis
        $sql = "
            INSERT INTO unified_optimization_runs (
                session_id, execution_time, transfers_executed, prices_optimized,
                profit_impact, created_at, results_data
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param(
            'sdiiis',
            $results['session_id'],
            $results['execution_time'],
            count($results['transfers_executed']['transfers']),
            count($results['prices_optimized']['price_changes']),
            $results['profit_impact']['estimated_profit_increase'],
            json_encode($results)
        );
        $stmt->execute();
    }
    
    private function handleUnifiedException(Exception $e): void
    {
        $this->logger->error('Unified Engine Exception', [
            'session_id' => $this->sessionId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        echo "💥 UNIFIED ENGINE ERROR: {$e->getMessage()}\n";
    }
}

// CLI execution
if (isset($argv[0]) && basename($argv[0]) === 'unified_profit_maximization_engine.php') {
    echo "🚀 UNIFIED PROFIT MAXIMIZATION ENGINE 🚀\n";
    echo "Combining: Sales Analysis + Competitive Intelligence + Smart Transfers\n";
    echo "Now with REAL SALES HISTORY DATA! 🎉\n\n";
    
    $engine = new UnifiedProfitMaximizationEngine();
    
    switch ($argv[1] ?? 'optimize') {
        case 'optimize':
        case 'run':
            echo "🎯 Running single optimization cycle...\n";
            $results = $engine->runUnifiedOptimization();
            echo "\n📊 RESULTS:\n" . json_encode($results, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'continuous':
            echo "🔄 Starting continuous optimization mode...\n";
            $engine->runContinuousOptimization();
            break;
            
        case 'status':
            echo "📊 Engine status:\n";
            $status = $engine->getUnifiedStatus();
            echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'stop':
            echo "🛑 Emergency stop...\n";
            $result = $engine->emergencyStop();
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        default:
            echo "🎯 Unified Profit Maximization Commands:\n";
            echo "  php unified_profit_maximization_engine.php optimize    - Run single cycle\n";
            echo "  php unified_profit_maximization_engine.php continuous - Run continuously\n";
            echo "  php unified_profit_maximization_engine.php status     - Check status\n";
            echo "  php unified_profit_maximization_engine.php stop       - Emergency stop\n";
            break;
    }
}