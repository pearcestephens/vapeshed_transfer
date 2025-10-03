<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Automation\AutonomousProfitEngine;

/**
 * Autonomous Engine Controller
 * 
 * Controls the autonomous profit optimization engine
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */
class AutonomousController extends BaseController
{
    private AutonomousProfitEngine $autonomousEngine;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * POST /api/autonomous/start
     * Start autonomous optimization
     */
    public function start(): array
    {
        try {
            $this->validateBrowseMode('Autonomous engine control requires authentication');
            $this->validateCsrfToken();
            
            $input = $this->getJsonInput();
            
            $config = [
                'dry_run' => (bool)($input['dry_run'] ?? false),
                'warehouse_id' => $input['warehouse_id'] ?? 'WAREHOUSE-001',
                'min_profit_increase' => (float)($input['min_profit_increase'] ?? 10.0),
                'max_price_change_percent' => (float)($input['max_price_change_percent'] ?? 25.0),
                'clearance_enabled' => (bool)($input['clearance_enabled'] ?? true),
                'transfer_enabled' => (bool)($input['transfer_enabled'] ?? true),
                'pricing_enabled' => (bool)($input['pricing_enabled'] ?? true),
                'continuous_mode' => (bool)($input['continuous_mode'] ?? false)
            ];
            
            $this->autonomousEngine = new AutonomousProfitEngine($config);
            
            if ($config['continuous_mode']) {
                // Start background daemon
                $this->startBackgroundDaemon($config);
                
                return $this->successResponse([
                    'mode' => 'continuous',
                    'status' => 'daemon_started',
                    'config' => $config
                ], 'Autonomous engine started in continuous mode');
            } else {
                // Run single optimization cycle
                $results = $this->autonomousEngine->runAutonomousOptimization();
                
                return $this->successResponse($results, 'Autonomous optimization cycle completed');
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Autonomous engine start failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to start autonomous engine: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/autonomous/stop
     * Stop autonomous engine
     */
    public function stop(): array
    {
        try {
            $this->validateBrowseMode('Autonomous engine control requires authentication');
            $this->validateCsrfToken();
            
            // Create kill switch file
            $killSwitchPath = STORAGE_PATH . '/AUTONOMOUS_KILL_SWITCH';
            file_put_contents($killSwitchPath, json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'stopped_by' => 'user_request',
                'reason' => 'Manual stop requested'
            ]));
            
            // Kill any running daemon processes
            $this->killDaemonProcesses();
            
            return $this->successResponse([
                'status' => 'stopped',
                'kill_switch_active' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Autonomous engine stopped successfully');
            
        } catch (\Exception $e) {
            $this->logger->error('Autonomous engine stop failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to stop autonomous engine: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/autonomous/status
     * Get autonomous engine status
     */
    public function getStatus(): array
    {
        try {
            $this->validateBrowseMode('Autonomous engine status requires authentication');
            
            $killSwitchActive = file_exists(STORAGE_PATH . '/AUTONOMOUS_KILL_SWITCH');
            $daemonRunning = $this->isDaemonRunning();
            
            $status = [
                'daemon_running' => $daemonRunning,
                'kill_switch_active' => $killSwitchActive,
                'last_run' => $this->getLastRunInfo(),
                'performance_stats' => $this->getPerformanceStats(),
                'active_processes' => $this->getActiveProcesses(),
                'system_health' => $this->getSystemHealth()
            ];
            
            return $this->successResponse($status, 'Autonomous engine status retrieved');
            
        } catch (\Exception $e) {
            $this->logger->error('Autonomous status check failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to get autonomous status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/autonomous/history
     * Get autonomous engine run history
     */
    public function getHistory(): array
    {
        try {
            $this->validateBrowseMode('Autonomous engine history requires authentication');
            
            $limit = min(100, (int)($_GET['limit'] ?? 50));
            $offset = (int)($_GET['offset'] ?? 0);
            
            $sql = "
                SELECT 
                    run_id,
                    start_time,
                    execution_time,
                    transfers_executed,
                    price_changes,
                    clearance_items,
                    profit_impact,
                    status,
                    error_message
                FROM autonomous_runs 
                ORDER BY start_time DESC 
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            
            $runs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get summary statistics
            $summaryStats = $this->getHistorySummaryStats();
            
            return $this->successResponse([
                'runs' => $runs,
                'summary_stats' => $summaryStats,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total_runs' => $this->getTotalRunCount()
                ]
            ], 'Autonomous engine history retrieved');
            
        } catch (\Exception $e) {
            $this->logger->error('Autonomous history retrieval failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to get autonomous history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/autonomous/clear-kill-switch
     * Clear kill switch and allow autonomous operations
     */
    public function clearKillSwitch(): array
    {
        try {
            $this->validateBrowseMode('Kill switch control requires authentication');
            $this->validateCsrfToken();
            
            $killSwitchFiles = [
                STORAGE_PATH . '/KILL_SWITCH',
                STORAGE_PATH . '/AUTONOMOUS_KILL_SWITCH'
            ];
            
            $cleared = 0;
            foreach ($killSwitchFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    $cleared++;
                }
            }
            
            return $this->successResponse([
                'kill_switches_cleared' => $cleared,
                'autonomous_enabled' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Kill switch cleared - autonomous operations enabled');
            
        } catch (\Exception $e) {
            $this->logger->error('Kill switch clear failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to clear kill switch: ' . $e->getMessage(), 500);
        }
    }
    
    // Private helper methods
    
    private function startBackgroundDaemon(array $config): void
    {
        $phpPath = '/usr/bin/php';
        $scriptPath = __DIR__ . '/../../bin/autonomous_daemon.php';
        $configJson = base64_encode(json_encode($config));
        
        $command = sprintf(
            'nohup %s %s %s > /dev/null 2>&1 & echo $!',
            $phpPath,
            $scriptPath,
            $configJson
        );
        
        $pid = shell_exec($command);
        
        if ($pid) {
            // Store PID for tracking
            file_put_contents(STORAGE_PATH . '/autonomous_daemon.pid', trim($pid));
            
            $this->logger->info('Autonomous daemon started', [
                'pid' => trim($pid),
                'config' => $config
            ]);
        }
    }
    
    private function killDaemonProcesses(): void
    {
        $pidFile = STORAGE_PATH . '/autonomous_daemon.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            
            if ($pid && is_numeric($pid)) {
                shell_exec("kill -TERM $pid 2>/dev/null");
                sleep(2);
                shell_exec("kill -KILL $pid 2>/dev/null");
            }
            
            unlink($pidFile);
        }
        
        // Kill any PHP processes running autonomous engine
        shell_exec("pkill -f 'autonomous_daemon.php' 2>/dev/null");
    }
    
    private function isDaemonRunning(): bool
    {
        $pidFile = STORAGE_PATH . '/autonomous_daemon.pid';
        
        if (!file_exists($pidFile)) {
            return false;
        }
        
        $pid = trim(file_get_contents($pidFile));
        
        if (!$pid || !is_numeric($pid)) {
            return false;
        }
        
        // Check if process is actually running
        $result = shell_exec("ps -p $pid -o pid= 2>/dev/null");
        return !empty(trim($result));
    }
    
    private function getLastRunInfo(): ?array
    {
        $sql = "
            SELECT * FROM autonomous_runs 
            ORDER BY start_time DESC 
            LIMIT 1
        ";
        
        $result = $this->db->query($sql);
        return $result->fetch_assoc();
    }
    
    private function getPerformanceStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_runs,
                SUM(transfers_executed) as total_transfers,
                SUM(price_changes) as total_price_changes,
                SUM(clearance_items) as total_clearance_items,
                SUM(profit_impact) as total_profit_impact,
                AVG(execution_time) as avg_execution_time,
                MAX(start_time) as last_run_time
            FROM autonomous_runs 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";
        
        $result = $this->db->query($sql);
        return $result->fetch_assoc();
    }
    
    private function getActiveProcesses(): array
    {
        $processes = [];
        
        // Check for autonomous daemon
        $daemonProcs = shell_exec("ps aux | grep '[a]utonomous_daemon.php' | wc -l");
        $processes['autonomous_daemon'] = (int)trim($daemonProcs);
        
        // Check for transfer processes
        $transferProcs = shell_exec("ps aux | grep '[t]ransfer_worker.php' | wc -l");
        $processes['transfer_workers'] = (int)trim($transferProcs);
        
        return $processes;
    }
    
    private function getSystemHealth(): array
    {
        return [
            'database_connected' => $this->db->ping(),
            'storage_writable' => is_writable(STORAGE_PATH),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'load_average' => sys_getloadavg(),
            'disk_free_space' => disk_free_space(STORAGE_PATH)
        ];
    }
}