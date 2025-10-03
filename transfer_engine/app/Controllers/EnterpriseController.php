<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\TransferEngineService;
use App\Services\PricingSystemService;
use App\Services\CompetitiveCrawlerService;
use App\Services\AiAgentService;
use App\Services\SystemMetricsService;

/**
 * Enterprise Control Panel Controller
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Main enterprise control panel integrating all core business systems
 */
class EnterpriseController extends BaseController
{
    private TransferEngineService $transferEngine;
    private PricingSystemService $pricingSystem;
    private CompetitiveCrawlerService $crawlerService;
    private AiAgentService $aiService;
    private SystemMetricsService $metricsService;

    public function __construct()
    {
        parent::__construct();
        
        // Initialize all core services
        $db = Database::getInstance()->getConnection();
        $this->transferEngine = new TransferEngineService($db);
        $this->pricingSystem = new PricingSystemService($db);
        $this->crawlerService = new CompetitiveCrawlerService($db);
        $this->aiService = new AiAgentService($db);
        $this->metricsService = new SystemMetricsService($db);
    }

    /**
     * Main Enterprise Dashboard
     */
    public function dashboard(): void
    {
        try {
            // Get comprehensive system status
            $systemStatus = [
                'transfer_engine' => $this->transferEngine->getStatus(),
                'pricing_system' => $this->pricingSystem->getStatus(),
                'crawlers' => $this->crawlerService->getStatus(),
                'ai_agents' => $this->aiService->getStatus(),
                'system_health' => $this->metricsService->getOverallHealth()
            ];

            // Get key metrics
            $metrics = [
                'active_transfers' => $this->transferEngine->getActiveTransferCount(),
                'pricing_updates' => $this->pricingSystem->getTodayUpdates(),
                'crawler_success_rate' => $this->crawlerService->getSuccessRate(),
                'ai_tasks_processed' => $this->aiService->getTasksProcessedToday(),
                'system_uptime' => $this->metricsService->getSystemUptime()
            ];

            // Get recent activity
            $recentActivity = [
                'transfers' => $this->transferEngine->getRecentTransfers(10),
                'price_updates' => $this->pricingSystem->getRecentUpdates(10),
                'crawler_runs' => $this->crawlerService->getRecentRuns(10),
                'ai_completions' => $this->aiService->getRecentCompletions(10)
            ];

            // Render dashboard
            $this->render('enterprise/dashboard', [
                'title' => 'Vapeshed Enterprise Control Panel',
                'systemStatus' => $systemStatus,
                'metrics' => $metrics,
                'recentActivity' => $recentActivity,
                'timestamp' => date('Y-m-d H:i:s T')
            ]);

        } catch (\Exception $e) {
            $this->handleError('Enterprise Dashboard Error', $e);
        }
    }

    /**
     * Transfer Engine Management
     */
    public function transferEngine(): void
    {
        try {
            $action = $_GET['action'] ?? 'overview';
            
            switch ($action) {
                case 'run':
                    $result = $this->transferEngine->runAutoBalancer($_POST['dry_run'] ?? true);
                    $this->jsonResponse(['success' => true, 'result' => $result]);
                    break;
                    
                case 'status':
                    $status = $this->transferEngine->getDetailedStatus();
                    $this->jsonResponse(['success' => true, 'status' => $status]);
                    break;
                    
                case 'history':
                    $history = $this->transferEngine->getTransferHistory($_GET['limit'] ?? 50);
                    $this->jsonResponse(['success' => true, 'history' => $history]);
                    break;
                    
                default:
                    $overview = [
                        'status' => $this->transferEngine->getStatus(),
                        'recent_runs' => $this->transferEngine->getRecentRuns(20),
                        'performance_metrics' => $this->transferEngine->getPerformanceMetrics(),
                        'configuration' => $this->transferEngine->getConfiguration()
                    ];
                    
                    $this->render('enterprise/transfer-engine', [
                        'title' => 'Transfer Engine Management',
                        'overview' => $overview
                    ]);
            }
            
        } catch (\Exception $e) {
            $this->handleError('Transfer Engine Error', $e);
        }
    }

    /**
     * Pricing System Management
     */
    public function pricingSystem(): void
    {
        try {
            $action = $_GET['action'] ?? 'overview';
            
            switch ($action) {
                case 'analyze':
                    $result = $this->pricingSystem->runAnalysis($_POST['product_ids'] ?? []);
                    $this->jsonResponse(['success' => true, 'result' => $result]);
                    break;
                    
                case 'recommendations':
                    $recommendations = $this->pricingSystem->getRecommendations($_GET['category'] ?? null);
                    $this->jsonResponse(['success' => true, 'recommendations' => $recommendations]);
                    break;
                    
                case 'history':
                    $history = $this->pricingSystem->getPriceHistory($_GET['product_id'] ?? null);
                    $this->jsonResponse(['success' => true, 'history' => $history]);
                    break;
                    
                default:
                    $overview = [
                        'status' => $this->pricingSystem->getStatus(),
                        'recent_analyses' => $this->pricingSystem->getRecentAnalyses(15),
                        'performance_summary' => $this->pricingSystem->getPerformanceSummary(),
                        'configuration' => $this->pricingSystem->getConfiguration()
                    ];
                    
                    $this->render('enterprise/pricing-system', [
                        'title' => 'Pricing System Management',
                        'overview' => $overview
                    ]);
            }
            
        } catch (\Exception $e) {
            $this->handleError('Pricing System Error', $e);
        }
    }

    /**
     * Competitive Intelligence Management
     */
    public function competitiveIntelligence(): void
    {
        try {
            $action = $_GET['action'] ?? 'overview';
            
            switch ($action) {
                case 'run_crawler':
                    $result = $this->crawlerService->runCrawler($_POST['crawler_type'] ?? 'stealth');
                    $this->jsonResponse(['success' => true, 'result' => $result]);
                    break;
                    
                case 'results':
                    $results = $this->crawlerService->getLatestResults($_GET['competitor'] ?? null);
                    $this->jsonResponse(['success' => true, 'results' => $results]);
                    break;
                    
                case 'schedule':
                    $result = $this->crawlerService->scheduleRun($_POST);
                    $this->jsonResponse(['success' => true, 'scheduled' => $result]);
                    break;
                    
                default:
                    $overview = [
                        'status' => $this->crawlerService->getStatus(),
                        'recent_runs' => $this->crawlerService->getRecentRuns(15),
                        'success_metrics' => $this->crawlerService->getSuccessMetrics(),
                        'competitive_data' => $this->crawlerService->getCompetitiveSnapshot()
                    ];
                    
                    $this->render('enterprise/competitive-intelligence', [
                        'title' => 'Competitive Intelligence Center',
                        'overview' => $overview
                    ]);
            }
            
        } catch (\Exception $e) {
            $this->handleError('Competitive Intelligence Error', $e);
        }
    }

    /**
     * AI Agent Management
     */
    public function aiAgents(): void
    {
        try {
            $action = $_GET['action'] ?? 'overview';
            
            switch ($action) {
                case 'execute_task':
                    $result = $this->aiService->executeTask($_POST);
                    $this->jsonResponse(['success' => true, 'result' => $result]);
                    break;
                    
                case 'agent_status':
                    $status = $this->aiService->getAgentStatus($_GET['agent_id'] ?? null);
                    $this->jsonResponse(['success' => true, 'status' => $status]);
                    break;
                    
                case 'task_queue':
                    $queue = $this->aiService->getTaskQueue();
                    $this->jsonResponse(['success' => true, 'queue' => $queue]);
                    break;
                    
                default:
                    $overview = [
                        'status' => $this->aiService->getStatus(),
                        'active_agents' => $this->aiService->getActiveAgents(),
                        'recent_completions' => $this->aiService->getRecentCompletions(15),
                        'performance_metrics' => $this->aiService->getPerformanceMetrics()
                    ];
                    
                    $this->render('enterprise/ai-agents', [
                        'title' => 'AI Agent Management Center',
                        'overview' => $overview
                    ]);
            }
            
        } catch (\Exception $e) {
            $this->handleError('AI Agent Error', $e);
        }
    }

    /**
     * System Monitoring
     */
    public function systemMonitoring(): void
    {
        try {
            $metrics = [
                'system_health' => $this->metricsService->getSystemHealth(),
                'performance_metrics' => $this->metricsService->getPerformanceMetrics(),
                'resource_usage' => $this->metricsService->getResourceUsage(),
                'error_rates' => $this->metricsService->getErrorRates(),
                'uptime_stats' => $this->metricsService->getUptimeStats()
            ];

            $this->render('enterprise/system-monitoring', [
                'title' => 'System Monitoring Dashboard',
                'metrics' => $metrics
            ]);

        } catch (\Exception $e) {
            $this->handleError('System Monitoring Error', $e);
        }
    }

    /**
     * API Endpoint for real-time metrics
     */
    public function apiMetrics(): void
    {
        try {
            $metrics = [
                'timestamp' => time(),
                'transfer_engine' => $this->transferEngine->getQuickStatus(),
                'pricing_system' => $this->pricingSystem->getQuickStatus(),
                'crawlers' => $this->crawlerService->getQuickStatus(),
                'ai_agents' => $this->aiService->getQuickStatus(),
                'system' => $this->metricsService->getQuickStatus()
            ];

            $this->jsonResponse(['success' => true, 'metrics' => $metrics]);

        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}