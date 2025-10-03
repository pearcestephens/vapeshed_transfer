<?php
/**
 * System Monitor Service
 * Handles all system monitoring and metrics collection
 * 
 * @package VapeshedTransfer\Services
 */

namespace VapeshedTransfer\Services;

use VapeshedTransfer\Core\Database;
use VapeshedTransfer\Core\Logger;

class SystemMonitorService 
{
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Get comprehensive system status
     */
    public function getFullStatus() {
        return [
            'processes' => $this->getProcessStatus(),
            'system' => $this->getSystemMetrics(),
            'services' => $this->getServiceStatus(),
            'health' => $this->getHealthStatus(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get active process information
     */
    public function getProcessStatus() {
        $processes = [];
        
        // Check competitive crawler
        $processes['competitive_crawler'] = [
            'name' => 'Competitive Crawler',
            'running' => $this->isProcessRunning('live_competitive_crawler.js'),
            'pid' => $this->getProcessPID('live_competitive_crawler.js'),
            'memory' => $this->getProcessMemory('live_competitive_crawler.js'),
            'cpu' => $this->getProcessCPU('live_competitive_crawler.js')
        ];
        
        // Check AI crawler
        $processes['ai_crawler'] = [
            'name' => 'AI Crawler',
            'running' => $this->isProcessRunning('adaptive_ai_crawler.js'),
            'pid' => $this->getProcessPID('adaptive_ai_crawler.js'),
            'memory' => $this->getProcessMemory('adaptive_ai_crawler.js'),
            'cpu' => $this->getProcessCPU('adaptive_ai_crawler.js')
        ];
        
        // Check auto balancer
        $processes['auto_balancer'] = [
            'name' => 'Auto Balancer',
            'running' => $this->isProcessRunning('auto_balancer_run.php'),
            'pid' => $this->getProcessPID('auto_balancer_run.php'),
            'memory' => $this->getProcessMemory('auto_balancer_run.php'),
            'cpu' => $this->getProcessCPU('auto_balancer_run.php')
        ];
        
        return $processes;
    }
    
    /**
     * Get system performance metrics
     */
    public function getSystemMetrics() {
        return [
            'load_average' => $this->getLoadAverage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'cpu_usage' => $this->getCPUUsage(),
            'uptime' => $this->getUptime()
        ];
    }
    
    /**
     * Get service status
     */
    public function getServiceStatus() {
        return [
            'database' => $this->checkDatabaseConnection(),
            'file_system' => $this->checkFileSystemAccess(),
            'network' => $this->checkNetworkConnectivity(),
            'logs' => $this->checkLogFileAccess()
        ];
    }
    
    /**
     * Get overall health status
     */
    public function getHealthStatus() {
        $health = 'healthy';
        $issues = [];
        
        // Check system load
        $load = $this->getLoadAverage();
        if ($load > 5.0) {
            $health = 'warning';
            $issues[] = 'High system load: ' . $load;
        }
        
        // Check memory usage
        $memory = $this->getMemoryUsage();
        if ($memory['used_percent'] > 90) {
            $health = 'critical';
            $issues[] = 'High memory usage: ' . $memory['used_percent'] . '%';
        }
        
        // Check critical processes
        if (!$this->isProcessRunning('live_competitive_crawler.js')) {
            $health = 'warning';
            $issues[] = 'Competitive crawler not running';
        }
        
        return [
            'status' => $health,
            'issues' => $issues,
            'score' => $this->calculateHealthScore()
        ];
    }
    
    /**
     * Check if a process is running
     */
    private function isProcessRunning($processName) {
        $output = shell_exec("pgrep -f '$processName'");
        return !empty(trim($output));
    }
    
    /**
     * Get process PID
     */
    private function getProcessPID($processName) {
        $output = shell_exec("pgrep -f '$processName'");
        return trim($output) ?: null;
    }
    
    /**
     * Get process memory usage
     */
    private function getProcessMemory($processName) {
        $pid = $this->getProcessPID($processName);
        if (!$pid) return 0;
        
        $output = shell_exec("ps -o rss= -p $pid");
        return (int)trim($output) * 1024; // Convert KB to bytes
    }
    
    /**
     * Get process CPU usage
     */
    private function getProcessCPU($processName) {
        $pid = $this->getProcessPID($processName);
        if (!$pid) return 0;
        
        $output = shell_exec("ps -o %cpu= -p $pid");
        return (float)trim($output);
    }
    
    /**
     * Get system load average
     */
    private function getLoadAverage() {
        $load = sys_getloadavg();
        return $load[0]; // 1-minute load average
    }
    
    /**
     * Get memory usage information
     */
    private function getMemoryUsage() {
        $memInfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $available);
        
        $totalMB = (int)$total[1] / 1024;
        $availableMB = (int)$available[1] / 1024;
        $usedMB = $totalMB - $availableMB;
        
        return [
            'total' => round($totalMB, 2),
            'used' => round($usedMB, 2),
            'available' => round($availableMB, 2),
            'used_percent' => round(($usedMB / $totalMB) * 100, 2)
        ];
    }
    
    /**
     * Get disk usage information
     */
    private function getDiskUsage() {
        $totalBytes = disk_total_space('.');
        $freeBytes = disk_free_space('.');
        $usedBytes = $totalBytes - $freeBytes;
        
        return [
            'total' => round($totalBytes / 1024 / 1024 / 1024, 2), // GB
            'used' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'free' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'used_percent' => round(($usedBytes / $totalBytes) * 100, 2)
        ];
    }
    
    /**
     * Get CPU usage (simplified)
     */
    private function getCPUUsage() {
        $load = $this->getLoadAverage();
        return min($load * 25, 100); // Rough estimate
    }
    
    /**
     * Get system uptime
     */
    private function getUptime() {
        $uptime = file_get_contents('/proc/uptime');
        $uptimeSeconds = (float)explode(' ', $uptime)[0];
        
        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);
        
        return [
            'seconds' => $uptimeSeconds,
            'formatted' => "{$days}d {$hours}h {$minutes}m"
        ];
    }
    
    /**
     * Check database connection
     */
    private function checkDatabaseConnection() {
        try {
            $this->db->query("SELECT 1");
            return ['status' => 'connected', 'message' => 'Database connection OK'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check file system access
     */
    private function checkFileSystemAccess() {
        $testFile = '/tmp/vapeshed_fs_test_' . time();
        
        if (file_put_contents($testFile, 'test') !== false) {
            unlink($testFile);
            return ['status' => 'ok', 'message' => 'File system access OK'];
        }
        
        return ['status' => 'error', 'message' => 'Cannot write to file system'];
    }
    
    /**
     * Check network connectivity
     */
    private function checkNetworkConnectivity() {
        $result = shell_exec('ping -c 1 8.8.8.8 > /dev/null 2>&1; echo $?');
        
        if (trim($result) === '0') {
            return ['status' => 'connected', 'message' => 'Network connectivity OK'];
        }
        
        return ['status' => 'error', 'message' => 'Network connectivity failed'];
    }
    
    /**
     * Check log file access
     */
    private function checkLogFileAccess() {
        $logFiles = [
            '/tmp/competitive_crawler.log',
            '/tmp/ai_crawler.log',
            '/tmp/auto_balancer.log'
        ];
        
        foreach ($logFiles as $logFile) {
            if (!file_exists($logFile) || !is_readable($logFile)) {
                return ['status' => 'warning', 'message' => "Log file not accessible: $logFile"];
            }
        }
        
        return ['status' => 'ok', 'message' => 'All log files accessible'];
    }
    
    /**
     * Calculate overall health score (0-100)
     */
    private function calculateHealthScore() {
        $score = 100;
        
        // Deduct for high load
        $load = $this->getLoadAverage();
        if ($load > 2.0) $score -= 10;
        if ($load > 5.0) $score -= 20;
        
        // Deduct for high memory usage
        $memory = $this->getMemoryUsage();
        if ($memory['used_percent'] > 80) $score -= 10;
        if ($memory['used_percent'] > 90) $score -= 20;
        
        // Deduct for stopped processes
        if (!$this->isProcessRunning('live_competitive_crawler.js')) $score -= 15;
        if (!$this->isProcessRunning('adaptive_ai_crawler.js')) $score -= 10;
        
        return max($score, 0);
    }
    
    /**
     * Get recent system activity
     */
    public function getRecentActivity($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM system_activity_log 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $this->logger->error('Failed to get recent activity', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Log system activity
     */
    public function logActivity($activity, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_activity_log (activity, details, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$activity, json_encode($details)]);
        } catch (Exception $e) {
            $this->logger->error('Failed to log activity', ['error' => $e->getMessage()]);
        }
    }
}
?>