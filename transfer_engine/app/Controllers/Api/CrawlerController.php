<?php
/**
 * Crawler API Controller
 * Handles API endpoints for competitor crawling operations
 * 
 * @package VapeshedTransfer
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Crawler\CompetitorCrawlerEngine;
use App\Core\Database;
use Exception;

class CrawlerController extends BaseController
{
    private CompetitorCrawlerEngine $crawler;
    private Database $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->crawler = new CompetitorCrawlerEngine();
        $this->db = Database::getInstance();
    }
    
    /**
     * Start competitor crawling
     */
    public function run(): array
    {
        try {
            $input = $this->getJsonInput();
            
            $options = [
                'priority_competitors' => $input['priority_competitors'] ?? ['vapingkiwi', 'vapoureyes', 'nzvapor'],
                'max_products' => $input['max_products'] ?? 1000,
                'stealth_mode' => $input['stealth_mode'] ?? true,
                'full_extraction' => $input['full_extraction'] ?? false,
                'timeout' => $input['timeout'] ?? 300,
                'concurrent_limit' => $input['concurrent_limit'] ?? 1
            ];
            
            // Validate options
            if ($options['max_products'] > 10000) {
                throw new Exception('Maximum products limit exceeded (10000)');
            }
            
            if ($options['concurrent_limit'] > 3) {
                throw new Exception('Concurrent limit too high (max 3 for stealth)');
            }
            
            // Start the crawler
            $result = $this->crawler->crawlAllCompetitors($options);
            
            // Log the start
            $this->logCrawlerAction('start', $options, $result);
            
            return $this->apiResponse(true, $result, [
                'message' => 'Competitor crawling started successfully',
                'estimated_duration' => $this->estimateCrawlDuration($options),
                'targets' => count($options['priority_competitors'])
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to start crawler', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Stop competitor crawling
     */
    public function stop(): array
    {
        try {
            $result = $this->crawler->stopCrawling();
            
            $this->logCrawlerAction('stop', [], $result);
            
            return $this->apiResponse(true, $result, [
                'message' => 'Crawler stopped successfully'
            ]);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get crawler status
     */
    public function getStatus(): array
    {
        try {
            $status = $this->crawler->getCrawlerStatus();
            
            // Add additional metrics
            $status['metrics'] = $this->getCrawlerMetrics();
            $status['recent_activity'] = $this->getRecentCrawlerActivity();
            $status['stealth_status'] = $this->getStealthStatus();
            
            return $this->apiResponse(true, $status);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get competitive intelligence data
     */
    public function getIntelligence(): array
    {
        try {
            $timeRange = $_GET['range'] ?? '24h';
            
            $intelligence = [
                'opportunities' => $this->getPriceOpportunities($timeRange),
                'threats' => $this->getCompetitiveThreats($timeRange),
                'market_trends' => $this->getMarketTrends($timeRange),
                'product_gaps' => $this->getProductGaps($timeRange)
            ];
            
            return $this->apiResponse(true, $intelligence);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get price opportunities
     */
    public function getOpportunities(): array
    {
        try {
            $limit = (int)($_GET['limit'] ?? 20);
            $minProfitIncrease = (float)($_GET['min_profit'] ?? 5.0);
            
            $opportunities = $this->getPriceOpportunities('24h', $limit, $minProfitIncrease);
            
            return $this->apiResponse(true, [
                'opportunities' => $opportunities,
                'total_potential_profit' => array_sum(array_column($opportunities, 'potential_profit')),
                'filters' => [
                    'min_profit' => $minProfitIncrease,
                    'limit' => $limit
                ]
            ]);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get competitive threats
     */
    public function getThreats(): array
    {
        try {
            $limit = (int)($_GET['limit'] ?? 20);
            $maxPriceDiff = (float)($_GET['max_price_diff'] ?? -10.0);
            
            $threats = $this->getCompetitiveThreats('24h', $limit, $maxPriceDiff);
            
            return $this->apiResponse(true, [
                'threats' => $threats,
                'at_risk_revenue' => $this->calculateAtRiskRevenue($threats),
                'filters' => [
                    'max_price_diff' => $maxPriceDiff,
                    'limit' => $limit
                ]
            ]);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get competitor products
     */
    public function getProducts(): array
    {
        try {
            $competitor = $_GET['competitor'] ?? null;
            $category = $_GET['category'] ?? null;
            $limit = (int)($_GET['limit'] ?? 100);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $products = $this->getCompetitorProducts($competitor, $category, $limit, $offset);
            
            return $this->apiResponse(true, [
                'products' => $products,
                'filters' => [
                    'competitor' => $competitor,
                    'category' => $category,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get competitive pricing data
     */
    public function getPricing(): array
    {
        try {
            $productId = $_GET['product_id'] ?? null;
            $sku = $_GET['sku'] ?? null;
            $timeRange = $_GET['range'] ?? '7d';
            
            if (!$productId && !$sku) {
                return $this->apiResponse(false, null, null, 'Product ID or SKU required');
            }
            
            $pricing = $this->getCompetitivePricing($productId, $sku, $timeRange);
            
            return $this->apiResponse(true, $pricing);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get crawler configuration
     */
    public function getConfig(): array
    {
        try {
            $config = $this->crawler->getConfiguration();
            
            return $this->apiResponse(true, $config);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Update crawler configuration
     */
    public function updateConfig(): array
    {
        try {
            $input = $this->getJsonInput();
            
            // Validate configuration
            $this->validateCrawlerConfiguration($input);
            
            $result = $this->crawler->updateConfiguration($input);
            
            $this->logCrawlerAction('config_update', $input, $result);
            
            return $this->apiResponse(true, $result, [
                'message' => 'Crawler configuration updated successfully'
            ]);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get crawler targets
     */
    public function getTargets(): array
    {
        try {
            $targets = $this->getCrawlerTargets();
            
            return $this->apiResponse(true, [
                'targets' => $targets,
                'total' => count($targets),
                'active' => count(array_filter($targets, fn($t) => $t['active']))
            ]);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Update crawler targets
     */
    public function updateTargets(): array
    {
        try {
            $input = $this->getJsonInput();
            
            // Validate targets
            $this->validateCrawlerTargets($input);
            
            $result = $this->updateCrawlerTargets($input);
            
            $this->logCrawlerAction('targets_update', $input, $result);
            
            return $this->apiResponse(true, $result, [
                'message' => 'Crawler targets updated successfully'
            ]);
            
        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }
    
    /**
     * Get price opportunities
     */
    private function getPriceOpportunities(string $timeRange, int $limit = 20, float $minProfit = 5.0): array
    {
        $hours = $this->parseTimeRange($timeRange);
        
        $sql = "
            SELECT 
                cp.product_name,
                cp.competitor_name,
                cp.competitor_price,
                op.current_price as our_price,
                op.product_id,
                cp.last_crawled,
                (op.current_price - cp.competitor_price) as price_difference,
                CASE 
                    WHEN cp.competitor_price > 0 
                    THEN ((op.current_price - cp.competitor_price) / cp.competitor_price) * 100 
                    ELSE 0 
                END as price_difference_percent,
                COALESCE(sp.avg_units_per_day, 1) * (op.current_price - cp.competitor_price) as potential_profit
            FROM competitor_products cp
            JOIN our_products op ON (
                cp.matched_product_id = op.product_id 
                OR SOUNDEX(cp.product_name) = SOUNDEX(op.product_name)
            )
            LEFT JOIN sales_performance sp ON sp.product_id = op.product_id
            WHERE 
                cp.last_crawled >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND cp.competitor_price > 0
                AND op.current_price > cp.competitor_price
                AND (op.current_price - cp.competitor_price) >= ?
            ORDER BY potential_profit DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('idi', $hours, $minProfit, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $opportunities = [];
        
        while ($row = $result->fetch_assoc()) {
            $opportunities[] = [
                'product_name' => $row['product_name'],
                'competitor_name' => $row['competitor_name'],
                'our_price' => (float)$row['our_price'],
                'competitor_price' => (float)$row['competitor_price'],
                'price_difference' => (float)$row['price_difference'],
                'price_difference_percent' => (float)$row['price_difference_percent'],
                'potential_profit' => (float)$row['potential_profit'],
                'last_crawled' => $row['last_crawled'],
                'product_id' => $row['product_id']
            ];
        }
        
        return $opportunities;
    }
    
    /**
     * Get competitive threats
     */
    private function getCompetitiveThreats(string $timeRange, int $limit = 20, float $maxPriceDiff = -10.0): array
    {
        $hours = $this->parseTimeRange($timeRange);
        
        $sql = "
            SELECT 
                cp.product_name,
                cp.competitor_name,
                cp.competitor_price,
                op.current_price as our_price,
                op.product_id,
                cp.last_crawled,
                (op.current_price - cp.competitor_price) as price_difference,
                CASE 
                    WHEN op.current_price > 0 
                    THEN ((cp.competitor_price - op.current_price) / op.current_price) * 100 
                    ELSE 0 
                END as price_difference_percent,
                COALESCE(sp.avg_units_per_day, 1) * op.current_price as at_risk_revenue
            FROM competitor_products cp
            JOIN our_products op ON (
                cp.matched_product_id = op.product_id 
                OR SOUNDEX(cp.product_name) = SOUNDEX(op.product_name)
            )
            LEFT JOIN sales_performance sp ON sp.product_id = op.product_id
            WHERE 
                cp.last_crawled >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND cp.competitor_price > 0
                AND cp.competitor_price < op.current_price
                AND ((cp.competitor_price - op.current_price) / op.current_price) * 100 <= ?
            ORDER BY at_risk_revenue DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('idi', $hours, $maxPriceDiff, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $threats = [];
        
        while ($row = $result->fetch_assoc()) {
            $threats[] = [
                'product_name' => $row['product_name'],
                'competitor_name' => $row['competitor_name'],
                'our_price' => (float)$row['our_price'],
                'competitor_price' => (float)$row['competitor_price'],
                'price_difference' => (float)$row['price_difference'],
                'price_difference_percent' => (float)$row['price_difference_percent'],
                'at_risk_revenue' => (float)$row['at_risk_revenue'],
                'last_crawled' => $row['last_crawled'],
                'product_id' => $row['product_id']
            ];
        }
        
        return $threats;
    }
    
    /**
     * Get crawler metrics
     */
    private function getCrawlerMetrics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_runs,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_runs,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_runs,
                AVG(products_found) as avg_products_per_run,
                AVG(execution_time) as avg_execution_time,
                MAX(last_run) as last_run,
                SUM(products_found) as total_products_crawled
            FROM crawler_runs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";
        
        $result = $this->db->getConnection()->query($sql);
        $metrics = $result->fetch_assoc();
        
        $totalRuns = (int)$metrics['total_runs'];
        $successfulRuns = (int)$metrics['successful_runs'];
        $successRate = $totalRuns > 0 ? ($successfulRuns / $totalRuns) * 100 : 0;
        
        return [
            'total_runs' => $totalRuns,
            'success_rate' => round($successRate, 2),
            'avg_products_per_run' => round((float)$metrics['avg_products_per_run'], 0),
            'avg_execution_time' => round((float)$metrics['avg_execution_time'], 2),
            'last_run' => $metrics['last_run'],
            'total_products_crawled' => (int)$metrics['total_products_crawled'],
            'failed_runs' => (int)$metrics['failed_runs']
        ];
    }
    
    /**
     * Get recent crawler activity
     */
    private function getRecentCrawlerActivity(int $limit = 10): array
    {
        $sql = "
            SELECT 
                competitor_name,
                products_found,
                status,
                execution_time,
                created_at,
                errors
            FROM crawler_runs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC 
            LIMIT ?
        ";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $activities = [];
        
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'competitor' => $row['competitor_name'],
                'products_found' => (int)$row['products_found'],
                'status' => $row['status'],
                'execution_time' => (float)$row['execution_time'],
                'timestamp' => $row['created_at'],
                'errors' => $row['errors'] ? json_decode($row['errors'], true) : null
            ];
        }
        
        return $activities;
    }
    
    /**
     * Get stealth status
     */
    private function getStealthStatus(): array
    {
        return [
            'user_agent_rotation' => true,
            'proxy_rotation' => $this->crawler->isProxyRotationActive(),
            'request_delays' => $this->crawler->getRequestDelaySettings(),
            'detection_avoidance' => true,
            'fingerprint_randomization' => true,
            'last_detection_attempt' => $this->getLastDetectionAttempt()
        ];
    }
    
    /**
     * Log crawler action
     */
    private function logCrawlerAction(string $action, array $data, array $result): void
    {
        $sql = "
            INSERT INTO crawler_runs (
                run_id, action_type, action_data, result_data, 
                status, created_at
            ) VALUES (?, ?, ?, ?, 'started', NOW())
        ";
        
        $runId = 'crawl_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
        $actionData = json_encode($data);
        $resultData = json_encode($result);
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('ssss', $runId, $action, $actionData, $resultData);
        $stmt->execute();
        
        $this->logger->info('Crawler action logged', [
            'run_id' => $runId,
            'action' => $action,
            'data' => $data,
            'result' => $result
        ]);
    }
    
    /**
     * Estimate crawl duration
     */
    private function estimateCrawlDuration(array $options): int
    {
        $competitorCount = count($options['priority_competitors']);
        $productsPerCompetitor = $options['max_products'] / $competitorCount;
        $timePerProduct = $options['stealth_mode'] ? 3 : 1; // seconds
        
        return (int)($competitorCount * $productsPerCompetitor * $timePerProduct / 60); // minutes
    }
    
    /**
     * Validate crawler configuration
     */
    private function validateCrawlerConfiguration(array $config): void
    {
        $required = ['max_concurrent', 'timeout_seconds', 'stealth_mode'];
        
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new Exception("Required configuration field missing: $field");
            }
        }
        
        if ($config['max_concurrent'] > 5) {
            throw new Exception('Maximum concurrent crawlers cannot exceed 5');
        }
        
        if ($config['timeout_seconds'] > 600) {
            throw new Exception('Timeout cannot exceed 600 seconds');
        }
    }
    
    /**
     * Parse time range string to hours
     */
    private function parseTimeRange(string $range): int
    {
        switch ($range) {
            case '1h': return 1;
            case '6h': return 6;
            case '12h': return 12;
            case '24h': return 24;
            case '7d': return 168;
            case '30d': return 720;
            default: return 24;
        }
    }
}