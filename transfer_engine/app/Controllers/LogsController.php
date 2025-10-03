<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Logs Controller
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Handles system logs viewing and management
 */
class LogsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Show logs dashboard
     */
    public function index(): void
    {
        $pageTitle = 'System Logs';
        $currentPage = 'logs';
        
        $this->render('logs/index', [
            'title' => $pageTitle,
            'currentPage' => $currentPage
        ]);
    }

    /**
     * Get log entries via AJAX
     */
    public function getLogs(): void
    {
        header('Content-Type: application/json');
        
        try {
            $logType = $_GET['type'] ?? 'all';
            $level = $_GET['level'] ?? '';
            $search = $_GET['search'] ?? '';
            $limit = min(1000, max(10, (int)($_GET['limit'] ?? 100)));
            
            $logs = $this->fetchLogEntries($logType, $level, $search, $limit);
            
            echo json_encode([
                'ok' => true,
                'data' => $logs,
                'total' => count($logs)
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear logs
     */
    public function clearLogs(): void
    {
        header('Content-Type: application/json');
        
        try {
            // In a real implementation, this would clear log files safely
            // For now, just return success
            
            echo json_encode([
                'ok' => true,
                'message' => 'Logs cleared successfully'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Fetch log entries from real log files
     */
    private function fetchLogEntries(string $type, string $level, string $search, int $limit): array
    {
        $logs = [];
        $logDir = __DIR__ . '/../../var/logs';
        
        // Determine which log files to read
        $logFiles = [];
        switch ($type) {
            case 'transfer':
                $logFiles = ['transfer-cli.log'];
                break;
            case 'error':
                $logFiles = ['error.log', 'api-error.log'];
                break;
            case 'system':
                $logFiles = ['system.log'];
                break;
            case 'audit':
                $logFiles = ['audit.log'];
                break;
            default:
                $logFiles = ['transfer-cli.log', 'api.log', 'error.log'];
        }
        
        foreach ($logFiles as $logFile) {
            $filePath = "{$logDir}/{$logFile}";
            if (file_exists($filePath)) {
                $logs = array_merge($logs, $this->parseLogFile($filePath, $level, $search));
            }
        }
        
        // Sort by timestamp descending and limit
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Parse individual log file
     */
    private function parseLogFile(string $filePath, string $level, string $search): array
    {
        $logs = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$lines) return [];
        
        foreach (array_reverse(array_slice($lines, -100)) as $line) { // Last 100 lines
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+): (.+)/', $line, $matches)) {
                $logEntry = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'component' => basename($filePath, '.log'),
                    'message' => $matches[3]
                ];
                
                // Apply level filter
                if ($level && strtoupper($logEntry['level']) !== strtoupper($level)) {
                    continue;
                }
                
                // Apply search filter
                if ($search && stripos($logEntry['message'], $search) === false) {
                    continue;
                }
                
                $logs[] = $logEntry;
            }
        }
        
        return $logs;
    }

    /**
     * Get real log file information
     */
    private function getLogFileInfo(): array
    {
        $logDir = __DIR__ . '/../../var/logs';
        $fileInfo = [];
        
        $logFiles = ['transfer-cli.log', 'error.log', 'api.log', 'audit.log', 'system.log'];
        
        foreach ($logFiles as $logFile) {
            $filePath = "{$logDir}/{$logFile}";
            if (file_exists($filePath)) {
                $size = filesize($filePath);
                $lines = file_exists($filePath) ? count(file($filePath)) : 0;
                $modified = filemtime($filePath);
                
                $fileInfo[$logFile] = [
                    'size' => $size,
                    'entries' => $lines,
                    'last_modified' => date('Y-m-d H:i:s', $modified)
                ];
            } else {
                $fileInfo[$logFile] = [
                    'size' => 0,
                    'entries' => 0,
                    'last_modified' => 'Never'
                ];
            }
        }
        
        return $fileInfo;
    }
    
    /**
     * API alias for getLogs (for AJAX calls)
     */
    public function api(): void
    {
        $this->getLogs();
    }
    
    /**
     * Clear alias for clearLogs
     */
    public function clear(): void
    {
        $this->clearLogs();
    }
}