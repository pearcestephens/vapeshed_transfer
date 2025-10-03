<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\Automation\AutonomousProfitEngine;
use App\Services\Crawler\CompetitorCrawlerEngine;
use App\Services\Analytics\SalesDataEngine;
use App\Services\Pricing\PriceIntelligenceEngine;

/**
 * Executive Dashboard Controller
 * 
 * High-level business intelligence and system control
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */
class ExecutiveDashboardController extends BaseController
{
    private AutonomousProfitEngine $autonomousEngine;
    private CompetitorCrawlerEngine $crawlerEngine;
    private SalesDataEngine $salesEngine;
    private PriceIntelligenceEngine $priceEngine;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Main executive dashboard
     */
    public function index(): void
    {
        $this->validateBrowseMode('Executive dashboard requires authentication');
        
        $dashboardData = $this->getDashboardData();
        
        $this->render('dashboard/executive', [
            'title' => 'Executive Intelligence Dashboard',
            'data' => $dashboardData,
            'config' => $this->getDashboardConfig(),
            'real_time_enabled' => true
        ]);
    }
    
    /**
     * System configuration panel
     */
    public function configuration(): void
    {
        $this->validateBrowseMode('System configuration requires authentication');
        
        $currentConfig = $this->getSystemConfiguration();
        $availableModules = $this->getAvailableModules();
        
        $this->render('dashboard/configuration', [
            'title' => 'System Configuration',
            'current_config' => $currentConfig,
            'available_modules' => $availableModules,
            'performance_metrics' => $this->getPerformanceMetrics()
        ]);
    }
    
    /**
     * Competitive intelligence view
     */
    public function competitiveIntelligence(): void
    {
        $this->validateBrowseMode('Competitive intelligence requires authentication');
        
        $competitorData = $this->getCompetitorIntelligence();
        $marketAnalysis = $this->getMarketAnalysis();
        
        $this->render('dashboard/competitive', [
            'title' => 'Competitive Intelligence',
            'competitor_data' => $competitorData,
            'market_analysis' => $marketAnalysis,
            'price_opportunities' => $this->getPricingOpportunities(),
            'threat_assessment' => $this->getThreatAssessment()
        ]);
    }
    
    /**
     * Sales analytics view
     */
    public function salesAnalytics(): void
    {
        $this->validateBrowseMode('Sales analytics requires authentication');
        
        $salesData = $this->getSalesAnalytics();
        
        $this->render('dashboard/sales', [
            'title' => 'Sales Analytics',
            'sales_data' => $salesData,
            'forecasts' => $this->getDemandForecasts(),
            'velocity_analysis' => $this->getVelocityAnalysis(),
            'profit_analysis' => $this->getProfitAnalysis()
        ]);
    }
    
    /**
     * Transfer engine monitoring
     */
    public function transferEngine(): void
    {
        $this->validateBrowseMode('Transfer engine monitoring requires authentication');
        
        $transferData = $this->getTransferEngineData();
        
        $this->render('dashboard/transfers', [
            'title' => 'Transfer Engine Control',
            'transfer_data' => $transferData,
            'autonomous_status' => $this->getAutonomousStatus(),
            'optimization_results' => $this->getOptimizationResults(),
            'system_health' => $this->getSystemHealth()
        ]);
    }
    
    // Private data collection methods
    
    private function getDashboardData(): array
    {
        $startTime = microtime(true);
        
        return [
            'summary_metrics' => $this->getSummaryMetrics(),
            'real_time_stats' => $this->getRealTimeStats(),
            'system_status' => $this->getSystemStatus(),
            'recent_actions' => $this->getRecentActions(),
            'alerts' => $this->getActiveAlerts(),
            'performance' => [
                'data_load_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'cache_hit_rate' => $this->getCacheHitRate(),
                'database_performance' => $this->getDatabasePerformance()
            ]
        ];
    }
    
    private function getSummaryMetrics(): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT o.outlet_id) as total_outlets,
                COUNT(DISTINCT p.product_id) as total_products,
                SUM(oi.current_stock) as total_inventory_units,
                COUNT(DISTINCT DATE(s.sale_date)) as active_sales_days
            FROM outlets o
            LEFT JOIN outlet_inventory oi ON oi.outlet_id = o.outlet_id
            LEFT JOIN products p ON p.product_id = oi.product_id
            LEFT JOIN sales_transactions s ON s.outlet_id = o.outlet_id 
                AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            WHERE o.deleted_at IS NULL AND o.is_active = 1
        ";
        
        $result = $this->db->query($sql);
        $metrics = $result->fetch_assoc();
        
        // Add revenue metrics
        $revenueSQL = "
            SELECT 
                SUM(total_amount) as total_revenue_30d,
                AVG(total_amount) as avg_transaction_value,
                COUNT(*) as total_transactions_30d
            FROM sales_transactions 
            WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ";
        
        $revenueResult = $this->db->query($revenueSQL);
        $revenueMetrics = $revenueResult->fetch_assoc();
        
        return array_merge($metrics, $revenueMetrics);
    }
    
    private function getRealTimeStats(): array
    {
        return [
            'today_sales' => $this->getTodaySales(),
            'active_transfers' => $this->getActiveTransfers(),
            'competitor_crawls' => $this->getRecentCrawls(),
            'autonomous_actions' => $this->getRecentAutonomousActions(),
            'system_load' => $this->getSystemLoad()
        ];
    }
    
    private function getSystemStatus(): array
    {
        return [
            'autonomous_engine' => $this->getAutonomousEngineStatus(),
            'competitor_crawler' => $this->getCrawlerStatus(),
            'transfer_engine' => $this->getTransferEngineStatus(),
            'pricing_engine' => $this->getPricingEngineStatus(),
            'database' => $this->getDatabaseStatus(),
            'storage' => $this->getStorageStatus()
        ];
    }
    
    private function getRecentActions(): array
    {
        $sql = "
            SELECT 
                'transfer' as action_type,
                CONCAT('Transferred ', quantity, ' units of ', product_id, ' to ', outlet_id) as description,
                created_at as timestamp,
                'success' as status
            FROM transfer_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            UNION ALL
            
            SELECT 
                'price_change' as action_type,
                CONCAT('Price changed for ', product_id, ' from $', old_price, ' to $', new_price) as description,
                updated_at as timestamp,
                'success' as status
            FROM price_change_logs 
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            UNION ALL
            
            SELECT 
                'crawl' as action_type,
                CONCAT('Crawled ', competitor_name, ' - found ', products_found, ' products') as description,
                created_at as timestamp,
                status
            FROM competitor_crawl_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            ORDER BY timestamp DESC
            LIMIT 20
        ";
        
        $result = $this->db->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getActiveAlerts(): array
    {
        $alerts = [];
        
        // Low stock alerts
        $lowStockSQL = "
            SELECT 
                p.name as product_name,
                oi.outlet_id,
                o.name as outlet_name,
                oi.current_stock,
                oi.min_stock_level
            FROM outlet_inventory oi
            JOIN products p ON p.product_id = oi.product_id
            JOIN outlets o ON o.outlet_id = oi.outlet_id
            WHERE oi.current_stock <= oi.min_stock_level
                AND oi.min_stock_level > 0
                AND o.is_active = 1
            ORDER BY (oi.current_stock / GREATEST(oi.min_stock_level, 1)) ASC
            LIMIT 10
        ";
        
        $result = $this->db->query($lowStockSQL);
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'type' => 'low_stock',
                'severity' => $row['current_stock'] == 0 ? 'critical' : 'warning',
                'message' => "Low stock: {$row['product_name']} at {$row['outlet_name']} ({$row['current_stock']} remaining)",
                'data' => $row
            ];
        }
        
        // Competitive threats
        $threatSQL = "
            SELECT 
                ca.our_product_id,
                p.name as product_name,
                ca.competitor_id,
                ca.our_price,
                ca.competitor_price,
                ca.price_difference_percent
            FROM competitive_analysis ca
            JOIN products p ON p.product_id = ca.our_product_id
            WHERE ca.threat_level = 'high'
                AND ca.analyzed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY ABS(ca.price_difference_percent) DESC
            LIMIT 5
        ";
        
        $result = $this->db->query($threatSQL);
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'type' => 'competitive_threat',
                'severity' => 'warning',
                'message' => "Price threat: {$row['product_name']} - competitor {$row['price_difference_percent']}% cheaper",
                'data' => $row
            ];
        }
        
        return $alerts;
    }
    
    private function getDashboardConfig(): array
    {
        return [
            'refresh_interval' => 30000, // 30 seconds
            'chart_themes' => ['dark', 'light', 'auto'],
            'data_ranges' => ['24h', '7d', '30d', '90d'],
            'modules' => [
                'sales_analytics' => true,
                'competitive_intelligence' => true,
                'transfer_optimization' => true,
                'autonomous_engine' => true,
                'system_monitoring' => true
            ],
            'customization' => [
                'widgets_draggable' => true,
                'layouts_saveable' => true,
                'export_enabled' => true,
                'real_time_updates' => true
            ]
        ];
    }
    
    private function getSystemConfiguration(): array
    {
        return [
            'autonomous_engine' => [
                'enabled' => !file_exists(STORAGE_PATH . '/AUTONOMOUS_KILL_SWITCH'),
                'run_frequency' => 60, // minutes
                'max_price_change' => 25.0, // percent
                'min_profit_increase' => 10.0, // dollars
                'safety_mode' => true
            ],
            'competitor_crawler' => [
                'enabled' => true,
                'crawl_frequency' => 4, // hours
                'max_concurrent' => 1,
                'stealth_mode' => true,
                'screenshot_enabled' => true
            ],
            'transfer_engine' => [
                'auto_transfers' => true,
                'max_per_product' => 50,
                'velocity_threshold' => 0.1,
                'safety_stock_days' => 14
            ],
            'pricing_engine' => [
                'dynamic_pricing' => true,
                'competitor_matching' => true,
                'elasticity_analysis' => true,
                'clearance_automation' => true
            ]
        ];
    }
    
    private function getTodaySales(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as transaction_count,
                SUM(total_amount) as revenue,
                SUM(quantity) as units_sold,
                AVG(total_amount) as avg_transaction,
                COUNT(DISTINCT outlet_id) as active_outlets
            FROM sales_transactions 
            WHERE DATE(sale_date) = CURDATE()
        ";
        
        $result = $this->db->query($sql);
        return $result->fetch_assoc();
    }
    
    private function getAutonomousEngineStatus(): array
    {
        $killSwitchActive = file_exists(STORAGE_PATH . '/AUTONOMOUS_KILL_SWITCH');
        
        $sql = "
            SELECT 
                status,
                start_time,
                transfers_executed,
                price_changes,
                profit_impact
            FROM autonomous_runs 
            ORDER BY start_time DESC 
            LIMIT 1
        ";
        
        $result = $this->db->query($sql);
        $lastRun = $result->fetch_assoc();
        
        return [
            'status' => $killSwitchActive ? 'disabled' : ($lastRun['status'] ?? 'idle'),
            'last_run' => $lastRun['start_time'] ?? null,
            'recent_transfers' => $lastRun['transfers_executed'] ?? 0,
            'recent_price_changes' => $lastRun['price_changes'] ?? 0,
            'recent_profit_impact' => $lastRun['profit_impact'] ?? 0
        ];
    }
    
    private function getCrawlerStatus(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_crawls,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_crawls,
                SUM(products_found) as total_products_found,
                MAX(created_at) as last_crawl
            FROM competitor_crawl_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";
        
        $result = $this->db->query($sql);
        $stats = $result->fetch_assoc();
        
        return [
            'status' => 'active',
            'success_rate' => $stats['total_crawls'] > 0 ? 
                round(($stats['successful_crawls'] / $stats['total_crawls']) * 100, 1) : 0,
            'products_found_24h' => $stats['total_products_found'],
            'last_crawl' => $stats['last_crawl']
        ];
    }
}