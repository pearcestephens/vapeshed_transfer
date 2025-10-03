<?php
declare(strict_types=1);

namespace App\Services\Automation;

use App\Core\Logger;
use App\Core\Database;
use App\Services\TransferEngineService;
use App\Services\Pricing\PriceIntelligenceEngine;
use App\Services\Analytics\SalesDataEngine;
use App\Services\Crawler\CompetitorCrawlerEngine;

/**
 * Autonomous Profit Optimization Engine
 * 
 * Continuously transfers products and adjusts prices to maximize profits
 * Includes clearance automation for slow-moving inventory
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */
class AutonomousProfitEngine
{
    private Logger $logger;
    private Database $db;
    private TransferEngineService $transferEngine;
    private PriceIntelligenceEngine $priceEngine;
    private SalesDataEngine $salesEngine;
    private CompetitorCrawlerEngine $crawlerEngine;
    
    private array $config;
    private array $runStats = [];
    private string $runId;
    
    public function __construct(array $config = [])
    {
        $this->logger = new Logger();
        $this->db = Database::getInstance();
        $this->transferEngine = new TransferEngineService();
        $this->priceEngine = new PriceIntelligenceEngine();
        $this->salesEngine = new SalesDataEngine();
        $this->crawlerEngine = new CompetitorCrawlerEngine();
        
        $this->runId = 'AUTO_' . date('YmdHis') . '_' . substr(md5(microtime()), 0, 6);
        $this->config = $this->validateConfig($config);
        
        $this->logger->info('Autonomous Profit Engine initialized', [
            'run_id' => $this->runId,
            'config' => $this->config
        ]);
    }
    
    /**
     * Main autonomous operation - runs continuously
     */
    public function runAutonomousOptimization(): array
    {
        $startTime = microtime(true);
        
        try {
            $this->logger->info('Starting autonomous profit optimization', ['run_id' => $this->runId]);
            
            // Initialize run stats
            $this->runStats = [
                'start_time' => date('Y-m-d H:i:s'),
                'products_analyzed' => 0,
                'transfers_executed' => 0,
                'price_changes' => 0,
                'clearance_items' => 0,
                'profit_impact' => 0,
                'revenue_opportunity' => 0
            ];
            
            // Step 1: Update competitor intelligence data
            $competitorIntel = $this->updateCompetitorIntelligence();
            
            // Step 2: Analyze market conditions with fresh competitor data
            $marketAnalysis = $this->analyzeMarketConditions($competitorIntel);
            
            // Step 3: Execute smart transfers based on velocity and profit margins
            $transferResults = $this->executeSmartTransfers($marketAnalysis);
            
            // Step 4: Optimize pricing for profit maximization with competitor data
            $pricingResults = $this->optimizePricingForProfit($marketAnalysis);
            
            // Step 5: Execute clearance automation for slow movers
            $clearanceResults = $this->executeClearanceAutomation($marketAnalysis);
            
            // Step 6: Analyze and report profit impact
            $profitAnalysis = $this->analyzeProfitImpact($transferResults, $pricingResults, $clearanceResults);
            
            $executionTime = microtime(true) - $startTime;
            
            $results = [
                'run_id' => $this->runId,
                'execution_time' => round($executionTime, 3),
                'competitor_intelligence' => $competitorIntel,
                'market_analysis' => $marketAnalysis,
                'transfer_results' => $transferResults,
                'pricing_results' => $pricingResults,
                'clearance_results' => $clearanceResults,
                'profit_analysis' => $profitAnalysis,
                'run_stats' => $this->runStats,
                'next_run_recommended' => $this->calculateNextRunTime(),
                'autonomous_actions' => $this->getAutonomousActionsSummary()
            ];
            
            // Store run results for history
            $this->storeRunResults($results);
            
            $this->logger->info('Autonomous optimization completed successfully', [
                'run_id' => $this->runId,
                'execution_time' => $executionTime,
                'transfers' => $this->runStats['transfers_executed'],
                'price_changes' => $this->runStats['price_changes'],
                'profit_impact' => $this->runStats['profit_impact']
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logger->error('Autonomous optimization failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime,
                'partial_stats' => $this->runStats
            ];
        }
    }
    
    /**
     * Continuous monitoring mode - runs in background
     */
    public function startContinuousMode(): void
    {
        $this->logger->info('Starting continuous autonomous mode', ['run_id' => $this->runId]);
        
        while (true) {
            try {
                // Check if kill switch is active
                if ($this->isKillSwitchActive()) {
                    $this->logger->warning('Kill switch detected - pausing autonomous mode');
                    sleep(300); // Wait 5 minutes before checking again
                    continue;
                }
                
                // Run optimization cycle
                $results = $this->runAutonomousOptimization();
                
                // Calculate sleep time based on market activity
                $sleepTime = $this->calculateOptimalSleepTime($results);
                
                $this->logger->info('Autonomous cycle completed', [
                    'run_id' => $this->runId,
                    'next_cycle_in_minutes' => round($sleepTime / 60, 1),
                    'actions_taken' => $results['run_stats']['transfers_executed'] + $results['run_stats']['price_changes']
                ]);
                
                // Sleep until next cycle
                sleep($sleepTime);
                
                // Generate new run ID for next cycle
                $this->runId = 'AUTO_' . date('YmdHis') . '_' . substr(md5(microtime()), 0, 6);
                
            } catch (\Exception $e) {
                $this->logger->error('Continuous mode error', [
                    'error' => $e->getMessage(),
                    'sleeping_for' => '10_minutes'
                ]);
                
                sleep(600); // Sleep 10 minutes on error
            }
        }
    }
    
    /**
     * Update competitor intelligence data
     */
    private function updateCompetitorIntelligence(): array
    {
        $this->logger->info('Updating competitor intelligence', ['run_id' => $this->runId]);
        
        // Check if we need to crawl (don't crawl too frequently)
        $lastCrawl = $this->getLastCrawlTime();
        $crawlFrequencyHours = $this->config['competitor_crawl_frequency_hours'] ?? 4;
        
        if (!$lastCrawl || (time() - strtotime($lastCrawl)) > ($crawlFrequencyHours * 3600)) {
            $this->logger->info('Running competitor crawl', ['last_crawl' => $lastCrawl]);
            
            // Run crawler
            $crawlResults = $this->crawlerEngine->crawlAllCompetitors();
            
            if (isset($crawlResults['error'])) {
                $this->logger->warning('Competitor crawl failed', ['error' => $crawlResults['error']]);
                // Use existing competitor data
                return $this->getExistingCompetitorData();
            }
            
            return $crawlResults;
        }
        
        // Use existing recent data
        $this->logger->info('Using existing competitor data', ['last_crawl' => $lastCrawl]);
        return $this->getExistingCompetitorData();
    }
    
    /**
     * Analyze current market conditions
     */
    private function analyzeMarketConditions(array $competitorIntel = []): array
    {
        $this->logger->info('Analyzing market conditions', ['run_id' => $this->runId]);
        
        // Get sales velocity trends
        $salesPatterns = $this->salesEngine->analyzeSalesPatterns([
            'analysis_days' => 30,
            'include_predictions' => true
        ]);
        
        // Get current inventory levels
        $inventoryLevels = $this->getInventoryLevels();
        
        // Get profit margins by product
        $profitMargins = $this->getProfitMargins();
        
        // Analyze competitor pricing impact
        $competitorAnalysis = $this->analyzeCompetitorPricingImpact($competitorIntel, $profitMargins);
        
        // Identify opportunities including competitive advantages
        $opportunities = $this->identifyMarketOpportunities($salesPatterns, $inventoryLevels, $profitMargins, $competitorAnalysis);
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'sales_patterns' => $salesPatterns,
            'inventory_levels' => $inventoryLevels,
            'profit_margins' => $profitMargins,
            'competitor_analysis' => $competitorAnalysis,
            'opportunities' => $opportunities,
            'market_sentiment' => $this->calculateMarketSentiment($salesPatterns, $competitorAnalysis)
        ];
    }
    
    /**
     * Execute smart transfers based on profit optimization
     */
    private function executeSmartTransfers(array $marketAnalysis): array
    {
        $this->logger->info('Executing smart transfers', ['run_id' => $this->runId]);
        
        $transferOpportunities = [];
        $executedTransfers = [];
        
        foreach ($marketAnalysis['opportunities']['high_velocity_low_stock'] as $opportunity) {
            $productId = $opportunity['product_id'];
            $outletId = $opportunity['outlet_id'];
            
            // Calculate optimal transfer quantity based on velocity and profit
            $transferQty = $this->calculateOptimalTransferQuantity($opportunity, $marketAnalysis);
            
            if ($transferQty > 0) {
                $transferOpportunities[] = [
                    'product_id' => $productId,
                    'outlet_id' => $outletId,
                    'quantity' => $transferQty,
                    'reason' => 'high_velocity_profit_optimization',
                    'expected_profit_increase' => $opportunity['profit_potential']
                ];
            }
        }
        
        // Execute transfers if not in dry run mode
        if (!$this->config['dry_run'] && !empty($transferOpportunities)) {
            foreach ($transferOpportunities as $transfer) {
                $result = $this->executeTransfer($transfer);
                if ($result['success']) {
                    $executedTransfers[] = $transfer;
                    $this->runStats['transfers_executed']++;
                    $this->runStats['profit_impact'] += $transfer['expected_profit_increase'];
                }
            }
        }
        
        return [
            'opportunities_identified' => count($transferOpportunities),
            'transfers_executed' => count($executedTransfers),
            'transfer_details' => $executedTransfers,
            'estimated_profit_impact' => array_sum(array_column($transferOpportunities, 'expected_profit_increase'))
        ];
    }
    
    /**
     * Optimize pricing for maximum profit
     */
    private function optimizePricingForProfit(array $marketAnalysis): array
    {
        $this->logger->info('Optimizing pricing for profit', ['run_id' => $this->runId]);
        
        $pricingOpportunities = [];
        $executedPriceChanges = [];
        
        foreach ($marketAnalysis['opportunities']['pricing_optimization'] as $opportunity) {
            $productId = $opportunity['product_id'];
            $currentPrice = $opportunity['current_price'];
            $optimalPrice = $opportunity['optimal_price'];
            
            // Only make price changes if profit increase is significant
            if ($opportunity['profit_increase'] > $this->config['min_profit_increase']) {
                $pricingOpportunities[] = [
                    'product_id' => $productId,
                    'current_price' => $currentPrice,
                    'new_price' => $optimalPrice,
                    'price_change' => $optimalPrice - $currentPrice,
                    'price_change_percent' => (($optimalPrice - $currentPrice) / $currentPrice) * 100,
                    'expected_profit_increase' => $opportunity['profit_increase'],
                    'confidence' => $opportunity['confidence']
                ];
            }
        }
        
        // Execute price changes if not in dry run mode
        if (!$this->config['dry_run'] && !empty($pricingOpportunities)) {
            foreach ($pricingOpportunities as $priceChange) {
                $result = $this->executePriceChange($priceChange);
                if ($result['success']) {
                    $executedPriceChanges[] = $priceChange;
                    $this->runStats['price_changes']++;
                    $this->runStats['profit_impact'] += $priceChange['expected_profit_increase'];
                }
            }
        }
        
        return [
            'opportunities_identified' => count($pricingOpportunities),
            'price_changes_executed' => count($executedPriceChanges),
            'price_change_details' => $executedPriceChanges,
            'estimated_profit_impact' => array_sum(array_column($pricingOpportunities, 'expected_profit_increase'))
        ];
    }
    
    /**
     * Execute clearance automation for slow-moving inventory
     */
    private function executeClearanceAutomation(array $marketAnalysis): array
    {
        $this->logger->info('Executing clearance automation', ['run_id' => $this->runId]);
        
        $clearanceOpportunities = [];
        $executedClearances = [];
        
        // Identify slow-moving inventory
        $slowMovers = $this->identifySlowMovingInventory($marketAnalysis);
        
        foreach ($slowMovers as $product) {
            $productId = $product['product_id'];
            $daysWithoutSale = $product['days_without_sale'];
            $currentStock = $product['current_stock'];
            $currentPrice = $product['current_price'];
            
            // Calculate clearance discount based on how long it's been sitting
            $discountPercent = $this->calculateClearanceDiscount($daysWithoutSale, $currentStock);
            $clearancePrice = $currentPrice * (1 - $discountPercent);
            
            // Only proceed if we can still maintain minimum margin
            $costPrice = $product['cost_price'] ?? $currentPrice * 0.6; // Assume 40% margin if cost unknown
            if ($clearancePrice > $costPrice * 1.1) { // Maintain at least 10% margin
                $clearanceOpportunities[] = [
                    'product_id' => $productId,
                    'current_price' => $currentPrice,
                    'clearance_price' => round($clearancePrice, 2),
                    'discount_percent' => round($discountPercent * 100, 1),
                    'days_without_sale' => $daysWithoutSale,
                    'current_stock' => $currentStock,
                    'reason' => $this->getClearanceReason($daysWithoutSale, $currentStock)
                ];
            }
        }
        
        // Execute clearance pricing if not in dry run mode
        if (!$this->config['dry_run'] && !empty($clearanceOpportunities)) {
            foreach ($clearanceOpportunities as $clearance) {
                $result = $this->executeClearancePricing($clearance);
                if ($result['success']) {
                    $executedClearances[] = $clearance;
                    $this->runStats['clearance_items']++;
                }
            }
        }
        
        return [
            'slow_movers_identified' => count($slowMovers),
            'clearance_opportunities' => count($clearanceOpportunities),
            'clearances_executed' => count($executedClearances),
            'clearance_details' => $executedClearances
        ];
    }
    
    /**
     * Calculate optimal transfer quantity based on velocity and profit
     */
    private function calculateOptimalTransferQuantity(array $opportunity, array $marketAnalysis): int
    {
        $dailyVelocity = $opportunity['daily_velocity'];
        $currentStock = $opportunity['current_stock'];
        $daysOfStock = $currentStock / max($dailyVelocity, 0.1);
        
        // Target 14 days of stock for high-velocity items
        $targetDaysOfStock = 14;
        $targetStock = ceil($dailyVelocity * $targetDaysOfStock);
        
        // Transfer quantity needed to reach target
        $transferQty = max(0, $targetStock - $currentStock);
        
        // Cap transfer based on warehouse availability
        $warehouseStock = $this->getWarehouseStock($opportunity['product_id']);
        $transferQty = min($transferQty, floor($warehouseStock * 0.3)); // Don't transfer more than 30% of warehouse stock
        
        return $transferQty;
    }
    
    /**
     * Calculate clearance discount based on inventory age
     */
    private function calculateClearanceDiscount(int $daysWithoutSale, int $currentStock): float
    {
        // Progressive discount based on days without sale
        if ($daysWithoutSale >= 90) {
            return 0.40; // 40% discount for 90+ days
        } elseif ($daysWithoutSale >= 60) {
            return 0.30; // 30% discount for 60-89 days
        } elseif ($daysWithoutSale >= 30) {
            return 0.20; // 20% discount for 30-59 days
        } elseif ($daysWithoutSale >= 14) {
            return 0.15; // 15% discount for 14-29 days
        }
        
        // Additional discount for high stock levels
        if ($currentStock > 50) {
            return 0.10; // 10% discount for overstocked items
        }
        
        return 0.05; // 5% minimum discount
    }
    
    /**
     * Execute actual transfer
     */
    private function executeTransfer(array $transfer): array
    {
        try {
            // Use existing transfer engine to execute
            $transferConfig = [
                'dry_run' => 0,
                'warehouse_id' => $this->config['warehouse_id'],
                'specific_products' => [$transfer['product_id']],
                'specific_outlets' => [$transfer['outlet_id']],
                'force_quantity' => $transfer['quantity']
            ];
            
            $result = $this->transferEngine->execute($transferConfig);
            
            if ($result['success']) {
                $this->logger->info('Autonomous transfer executed', [
                    'run_id' => $this->runId,
                    'product_id' => $transfer['product_id'],
                    'outlet_id' => $transfer['outlet_id'],
                    'quantity' => $transfer['quantity']
                ]);
                
                return ['success' => true, 'result' => $result];
            }
            
            return ['success' => false, 'error' => $result['error'] ?? 'Transfer failed'];
            
        } catch (\Exception $e) {
            $this->logger->error('Transfer execution failed', [
                'transfer' => $transfer,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Execute price change
     */
    private function executePriceChange(array $priceChange): array
    {
        try {
            $sql = "
                UPDATE products 
                SET current_price = ?, 
                    price_updated_at = NOW(),
                    price_updated_by = 'autonomous_engine'
                WHERE product_id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('ds', $priceChange['new_price'], $priceChange['product_id']);
            $success = $stmt->execute();
            
            if ($success) {
                // Log price change for audit trail
                $this->logPriceChange($priceChange);
                
                $this->logger->info('Autonomous price change executed', [
                    'run_id' => $this->runId,
                    'product_id' => $priceChange['product_id'],
                    'old_price' => $priceChange['current_price'],
                    'new_price' => $priceChange['new_price']
                ]);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Database update failed'];
            
        } catch (\Exception $e) {
            $this->logger->error('Price change execution failed', [
                'price_change' => $priceChange,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Helper methods for data retrieval and analysis...
    
    private function validateConfig(array $config): array
    {
        $defaults = [
            'dry_run' => false,
            'warehouse_id' => 'WAREHOUSE-001',
            'min_profit_increase' => 10.0, // Minimum $10 profit increase to trigger price change
            'max_price_change_percent' => 25.0, // Maximum 25% price change
            'clearance_enabled' => true,
            'transfer_enabled' => true,
            'pricing_enabled' => true,
            'continuous_mode' => false,
            'sleep_time_minutes' => 60, // Default 1 hour between cycles
            'kill_switch_check' => true
        ];
        
        return array_merge($defaults, $config);
    }
    
    private function isKillSwitchActive(): bool
    {
        return file_exists(STORAGE_PATH . '/KILL_SWITCH') || file_exists(STORAGE_PATH . '/AUTONOMOUS_KILL_SWITCH');
    }
    
    /**
     * Analyze competitor pricing impact on our opportunities
     */
    private function analyzeCompetitorPricingImpact(array $competitorIntel, array $profitMargins): array
    {
        $analysis = [
            'pricing_opportunities' => [],
            'competitive_threats' => [],
            'market_positioning' => [],
            'price_gaps' => []
        ];
        
        if (empty($competitorIntel['competitor_results'])) {
            return $analysis;
        }
        
        foreach ($competitorIntel['competitor_results'] as $competitorId => $crawlData) {
            if (!$crawlData['success'] || empty($crawlData['products'])) {
                continue;
            }
            
            foreach ($crawlData['products'] as $competitorProduct) {
                $ourProductId = $competitorProduct['our_product_id'];
                
                if (!$ourProductId) {
                    continue; // Skip if we can't match the product
                }
                
                $ourPrice = $this->getCurrentPrice($ourProductId);
                $competitorPrice = $competitorProduct['price'];
                $priceDiff = $ourPrice - $competitorPrice;
                $priceDiffPercent = $ourPrice > 0 ? ($priceDiff / $ourPrice) * 100 : 0;
                
                // Identify pricing opportunities
                if ($priceDiffPercent > 10) {
                    // We're significantly more expensive
                    $analysis['competitive_threats'][] = [
                        'our_product_id' => $ourProductId,
                        'competitor' => $competitorProduct['competitor_name'],
                        'our_price' => $ourPrice,
                        'competitor_price' => $competitorPrice,
                        'price_difference' => $priceDiff,
                        'price_difference_percent' => round($priceDiffPercent, 2),
                        'threat_level' => $priceDiffPercent > 20 ? 'high' : 'medium',
                        'recommended_action' => 'consider_price_reduction'
                    ];
                } elseif ($priceDiffPercent < -5) {
                    // We're significantly cheaper - opportunity to increase
                    $analysis['pricing_opportunities'][] = [
                        'our_product_id' => $ourProductId,
                        'competitor' => $competitorProduct['competitor_name'],
                        'our_price' => $ourPrice,
                        'competitor_price' => $competitorPrice,
                        'price_difference' => $priceDiff,
                        'price_difference_percent' => round($priceDiffPercent, 2),
                        'opportunity_level' => abs($priceDiffPercent) > 15 ? 'high' : 'medium',
                        'recommended_action' => 'consider_price_increase'
                    ];
                }
                
                // Market positioning analysis
                $analysis['market_positioning'][] = [
                    'our_product_id' => $ourProductId,
                    'competitor' => $competitorProduct['competitor_name'],
                    'our_price' => $ourPrice,
                    'competitor_price' => $competitorPrice,
                    'position' => $this->determineMarketPosition($priceDiffPercent)
                ];
            }
        }
        
        return $analysis;
    }
    
    /**
     * Enhanced market opportunities identification with competitor data
     */
    private function identifyMarketOpportunities(array $salesPatterns, array $inventoryLevels, array $profitMargins, array $competitorAnalysis = []): array
    {
        $opportunities = [
            'high_velocity_low_stock' => [],
            'pricing_optimization' => [],
            'competitive_advantages' => [],
            'clearance_candidates' => []
        ];
        
        // Merge competitor pricing opportunities into main opportunities
        if (!empty($competitorAnalysis['pricing_opportunities'])) {
            foreach ($competitorAnalysis['pricing_opportunities'] as $compOpp) {
                $productId = $compOpp['our_product_id'];
                $currentPrice = $compOpp['our_price'];
                $suggestedPrice = $compOpp['competitor_price'] * 0.95; // Price just below competitor
                
                // Calculate potential profit increase
                $currentMargin = $profitMargins[$productId]['margin_percent'] ?? 30;
                $newMargin = (($suggestedPrice - ($profitMargins[$productId]['cost_price'] ?? $currentPrice * 0.7)) / $suggestedPrice) * 100;
                $profitIncrease = ($suggestedPrice - $currentPrice) * ($profitMargins[$productId]['monthly_volume'] ?? 10);
                
                if ($profitIncrease > $this->config['min_profit_increase']) {
                    $opportunities['pricing_optimization'][] = [
                        'product_id' => $productId,
                        'current_price' => $currentPrice,
                        'optimal_price' => round($suggestedPrice, 2),
                        'profit_increase' => $profitIncrease,
                        'confidence' => 0.85,
                        'reason' => 'competitive_pricing_gap',
                        'competitor_reference' => $compOpp['competitor']
                    ];
                }
            }
        }
        
        // Add competitive threats requiring price reductions
        if (!empty($competitorAnalysis['competitive_threats'])) {
            foreach ($competitorAnalysis['competitive_threats'] as $threat) {
                if ($threat['threat_level'] === 'high') {
                    $productId = $threat['our_product_id'];
                    $currentPrice = $threat['our_price'];
                    $competitorPrice = $threat['competitor_price'];
                    $suggestedPrice = $competitorPrice * 1.02; // Price slightly above competitor
                    
                    // Only recommend if we can maintain minimum margin
                    $minPrice = ($profitMargins[$productId]['cost_price'] ?? $currentPrice * 0.7) * 1.1;
                    
                    if ($suggestedPrice > $minPrice) {
                        $opportunities['pricing_optimization'][] = [
                            'product_id' => $productId,
                            'current_price' => $currentPrice,
                            'optimal_price' => round($suggestedPrice, 2),
                            'profit_increase' => -abs($currentPrice - $suggestedPrice) * ($profitMargins[$productId]['monthly_volume'] ?? 10),
                            'confidence' => 0.75,
                            'reason' => 'competitive_threat_response',
                            'competitor_reference' => $threat['competitor']
                        ];
                    }
                }
            }
        }
        
        return $opportunities;
    }
    
    /**
     * Get last crawler execution time
     */
    private function getLastCrawlTime(): ?string
    {
        $sql = "SELECT MAX(created_at) as last_crawl FROM competitor_crawl_logs";
        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        
        return $row['last_crawl'];
    }
    
    /**
     * Get existing competitor data from database
     */
    private function getExistingCompetitorData(): array
    {
        $sql = "
            SELECT 
                competitor_id,
                competitor_product_name,
                our_product_id,
                price,
                brand,
                confidence_score,
                crawled_at
            FROM competitor_prices 
            WHERE crawled_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY crawled_at DESC
        ";
        
        $result = $this->db->query($sql);
        $competitorData = $result->fetch_all(MYSQLI_ASSOC);
        
        return [
            'data_source' => 'existing_database',
            'last_updated' => $this->getLastCrawlTime(),
            'competitor_products' => $competitorData,
            'products_count' => count($competitorData)
        ];
    }
    
    /**
     * Determine market position based on price difference
     */
    private function determineMarketPosition(float $priceDiffPercent): string
    {
        if ($priceDiffPercent > 20) return 'premium';
        if ($priceDiffPercent > 5) return 'above_market';
        if ($priceDiffPercent > -5) return 'competitive';
        if ($priceDiffPercent > -20) return 'below_market';
        return 'discount';
    }
    
    /**
     * Enhanced market sentiment with competitor analysis
     */
    private function calculateMarketSentiment(array $salesPatterns, array $competitorAnalysis = []): string
    {
        $sentiment = 'neutral';
        
        // Factor in competitor pricing pressure
        if (!empty($competitorAnalysis['competitive_threats'])) {
            $highThreats = array_filter($competitorAnalysis['competitive_threats'], 
                fn($threat) => $threat['threat_level'] === 'high');
            
            if (count($highThreats) > 3) {
                $sentiment = 'bearish'; // High competitive pressure
            }
        }
        
        // Factor in pricing opportunities
        if (!empty($competitorAnalysis['pricing_opportunities'])) {
            $highOpportunities = array_filter($competitorAnalysis['pricing_opportunities'], 
                fn($opp) => $opp['opportunity_level'] === 'high');
            
            if (count($highOpportunities) > 2) {
                $sentiment = 'bullish'; // Good pricing opportunities
            }
        }
        
        return $sentiment;
    }
}