<?php
/**
 * Transfer Engine Dashboard Controller
 * 
 * @package VapeshedTransfer
 * @author System Engineer
 * @version 1.0
 * @since 2025-09-28
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../app/Core/Application.php';

use VapeshedTransfer\Core\Application;
use VapeshedTransfer\Controllers\DashboardController;

class TransferDashboardController extends DashboardController 
{
    private $systemMonitor;
    private $crawlerManager;
    private $transferEngine;
    
    public function __construct() {
        parent::__construct();
        $this->systemMonitor = new SystemMonitorService();
        $this->crawlerManager = new CrawlerManagerService();
        $this->transferEngine = new TransferEngineService();
    }
    
    /**
     * Main dashboard index page
     */
    public function index() {
        $data = [
            'page_title' => 'Transfer Engine Control Panel',
            'system_status' => $this->getSystemStatus(),
            'crawler_status' => $this->getCrawlerStatus(),
            'transfer_metrics' => $this->getTransferMetrics(),
            'recent_activity' => $this->getRecentActivity()
        ];
        
        $this->render('dashboard/index', $data);
    }
    
    /**
     * Competitive intelligence monitoring page
     */
    public function competitive() {
        $data = [
            'page_title' => 'Competitive Intelligence Monitor',
            'crawler_details' => $this->crawlerManager->getDetailedStatus(),
            'competitor_data' => $this->crawlerManager->getCompetitorData(),
            'crawl_history' => $this->crawlerManager->getCrawlHistory(),
            'target_sites' => $this->crawlerManager->getTargetSites()
        ];
        
        $this->render('dashboard/competitive', $data);
    }
    
    /**
     * System metrics and performance page
     */
    public function metrics() {
        $data = [
            'page_title' => 'System Metrics',
            'performance_data' => $this->systemMonitor->getPerformanceMetrics(),
            'process_list' => $this->systemMonitor->getActiveProcesses(),
            'resource_usage' => $this->systemMonitor->getResourceUsage(),
            'health_checks' => $this->systemMonitor->getHealthChecks()
        ];
        
        $this->render('dashboard/metrics', $data);
    }
    
    /**
     * Transfer engine management page
     */
    public function transfers() {
        $data = [
            'page_title' => 'Transfer Engine Management',
            'active_transfers' => $this->transferEngine->getActiveTransfers(),
            'pending_queue' => $this->transferEngine->getPendingQueue(),
            'store_status' => $this->transferEngine->getStoreStatus(),
            'balance_opportunities' => $this->transferEngine->getBalanceOpportunities()
        ];
        
        $this->render('dashboard/transfers', $data);
    }
    
    /**
     * System logs viewer
     */
    public function logs() {
        $logType = $_GET['type'] ?? 'all';
        $data = [
            'page_title' => 'System Logs',
            'log_type' => $logType,
            'log_data' => $this->getLogData($logType),
            'log_types' => $this->getAvailableLogTypes()
        ];
        
        $this->render('dashboard/logs', $data);
    }
    
    /**
     * Get system status overview
     */
    private function getSystemStatus() {
        return [
            'processes' => $this->systemMonitor->getProcessCount(),
            'load_average' => $this->systemMonitor->getLoadAverage(),
            'memory_usage' => $this->systemMonitor->getMemoryUsage(),
            'uptime' => $this->systemMonitor->getUptime(),
            'services' => $this->systemMonitor->getServiceStatus()
        ];
    }
    
    /**
     * Get crawler status information
     */
    private function getCrawlerStatus() {
        return [
            'competitive_active' => $this->crawlerManager->isCompetitiveCrawlerActive(),
            'ai_crawler_active' => $this->crawlerManager->isAICrawlerActive(),
            'sites_monitored' => $this->crawlerManager->getSiteCount(),
            'last_crawl_time' => $this->crawlerManager->getLastCrawlTime(),
            'crawl_success_rate' => $this->crawlerManager->getSuccessRate()
        ];
    }
    
    /**
     * Get transfer engine metrics
     */
    private function getTransferMetrics() {
        return [
            'active_transfers' => $this->transferEngine->getActiveCount(),
            'completed_today' => $this->transferEngine->getCompletedToday(),
            'pending_queue' => $this->transferEngine->getPendingCount(),
            'success_rate' => $this->transferEngine->getSuccessRate()
        ];
    }
    
    /**
     * Get recent system activity
     */
    private function getRecentActivity() {
        return $this->systemMonitor->getRecentActivity(50);
    }
    
    /**
     * Get log data for specified type
     */
    private function getLogData($logType) {
        $logService = new LogService();
        return $logService->getLogsByType($logType, 100);
    }
    
    /**
     * Get available log types
     */
    private function getAvailableLogTypes() {
        return [
            'all' => 'All Logs',
            'competitive' => 'Competitive Crawler',
            'ai' => 'AI Crawler', 
            'transfers' => 'Transfer Engine',
            'system' => 'System Events',
            'errors' => 'Error Logs'
        ];
    }
    
    // ========== API ENDPOINTS FOR REAL-TIME UPDATES ==========
    
    /**
     * API: Get current system status
     */
    public function apiSystemStatus() {
        $this->validateCSRFToken();
        $status = $this->getSystemStatus();
        $this->jsonResponse(['success' => true, 'data' => $status]);
    }
    
    /**
     * API: Get recent activity feed
     */
    public function apiRecentActivity() {
        $this->validateCSRFToken();
        $activity = $this->getRecentActivity();
        $this->jsonResponse(['success' => true, 'data' => $activity]);
    }
    
    /**
     * API: Restart specific service
     */
    public function apiRestartService() {
        $this->validateCSRFToken();
        $input = json_decode(file_get_contents('php://input'), true);
        $service = $input['service'] ?? '';
        
        if (empty($service)) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => 'Service name required']]);
            return;
        }
        
        try {
            $result = $this->systemMonitor->restartService($service);
            if ($result['success']) {
                $this->logActivity("Service restarted: {$service}");
                $this->jsonResponse(['success' => true, 'message' => "Service {$service} restarted successfully"]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => ['message' => $result['message']]]);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
    
    /**
     * API: Run transfer check
     */
    public function apiRunTransferCheck() {
        $this->validateCSRFToken();
        
        try {
            $result = $this->transferEngine->runBalanceCheck();
            $this->logActivity("Transfer balance check executed");
            $this->jsonResponse(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
    
    /**
     * API: Toggle AI services
     */
    public function apiToggleAIServices() {
        $this->validateCSRFToken();
        
        try {
            $isActive = $this->crawlerManager->isAICrawlerActive();
            if ($isActive) {
                $result = $this->crawlerManager->stopAICrawler();
                $action = 'stopped';
            } else {
                $result = $this->crawlerManager->startAICrawler();
                $action = 'started';
            }
            
            if ($result['success']) {
                $this->logActivity("AI services {$action}");
                $this->jsonResponse(['success' => true, 'message' => "AI services {$action} successfully"]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => ['message' => $result['message']]]);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
    
    /**
     * API: Start all services
     */
    public function apiStartAllServices() {
        $this->validateCSRFToken();
        
        try {
            $results = [];
            $services = ['competitive_crawler', 'ai_crawler', 'transfer_engine'];
            
            foreach ($services as $service) {
                $result = $this->systemMonitor->startService($service);
                $results[$service] = $result;
            }
            
            $allSuccess = array_reduce($results, function($carry, $result) {
                return $carry && $result['success'];
            }, true);
            
            if ($allSuccess) {
                $this->logActivity("All services started");
                $this->jsonResponse(['success' => true, 'message' => 'All services started successfully']);
            } else {
                $failedServices = array_keys(array_filter($results, function($r) { return !$r['success']; }));
                $this->jsonResponse(['success' => false, 'error' => ['message' => 'Failed to start: ' . implode(', ', $failedServices)]]);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
    
    /**
     * API: Restart all services
     */
    public function apiRestartAllServices() {
        $this->validateCSRFToken();
        
        try {
            $results = [];
            $services = ['competitive_crawler', 'ai_crawler', 'transfer_engine'];
            
            foreach ($services as $service) {
                $result = $this->systemMonitor->restartService($service);
                $results[$service] = $result;
            }
            
            $allSuccess = array_reduce($results, function($carry, $result) {
                return $carry && $result['success'];
            }, true);
            
            if ($allSuccess) {
                $this->logActivity("All services restarted");
                $this->jsonResponse(['success' => true, 'message' => 'All services restarted successfully']);
            } else {
                $failedServices = array_keys(array_filter($results, function($r) { return !$r['success']; }));
                $this->jsonResponse(['success' => false, 'error' => ['message' => 'Failed to restart: ' . implode(', ', $failedServices)]]);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
    
    /**
     * API: Emergency stop all services
     */
    public function apiEmergencyStop() {
        $this->validateCSRFToken();
        
        try {
            $results = [];
            $services = ['competitive_crawler', 'ai_crawler', 'transfer_engine'];
            
            foreach ($services as $service) {
                $result = $this->systemMonitor->stopService($service);
                $results[$service] = $result;
            }
            
            $this->logActivity("EMERGENCY STOP executed - all services halted", 'critical');
            $this->jsonResponse(['success' => true, 'message' => 'Emergency stop executed - all services halted']);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
    
    /**
     * Validate CSRF token for API calls
     */
    private function validateCSRFToken() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => ['message' => 'Invalid request method']], 405);
            exit;
        }
        
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$this->isValidCSRFToken($token)) {
            $this->jsonResponse(['success' => false, 'error' => ['message' => 'Invalid CSRF token']], 403);
            exit;
        }
    }
    
    /**
     * Log activity with timestamp and context
     */
    private function logActivity($message, $level = 'info') {
        $activity = [
            'activity' => $message,
            'level' => $level,
            'created_at' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Log to database and/or file
        $this->systemMonitor->logActivity($activity);
    }
    
    /**
     * Send JSON response with proper headers
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Handle routing
$controller = new TransferDashboardController();

// Check for API actions first
if (isset($_POST) && !empty($_POST) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $apiMethod = 'api' . str_replace('_', '', ucwords($action, '_'));
    
    if (method_exists($controller, $apiMethod)) {
        $controller->$apiMethod();
        exit;
    }
}

// Handle page routing
$page = $_GET['page'] ?? 'index';
if (method_exists($controller, $page)) {
    $controller->$page();
} else {
    $controller->index();
}
?>