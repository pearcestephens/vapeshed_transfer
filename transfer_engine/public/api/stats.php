<?php
/**
 * Dashboard Statistics API Endpoint
 * 
 * Provides real-time KPI statistics for the main dashboard.
 * Returns transfer metrics, proposals, alerts, insights, and system health.
 * 
 * @package VapeshedTransfer
 * @subpackage API
 * @version 1.0.0
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Support\Api;

Api::initJson();
Api::applyCors('GET, OPTIONS');
Api::handleOptionsPreflight();
Api::enforceGetRateLimit('stats');

/**
 * Dashboard Statistics Service
 * 
 * Aggregates statistics from multiple sources to provide
 * comprehensive dashboard metrics.
 */
class DashboardStatsService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get all dashboard statistics
     * 
     * @return array Complete statistics payload
     */
    public function getAllStats(): array
    {
        return [
            'transfers' => $this->getTransferStats(),
            'proposals' => $this->getProposalStats(),
            'alerts' => $this->getAlertStats(),
            'insights' => $this->getInsightStats(),
            'health' => $this->getHealthStats(),
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Get transfer-specific statistics
     * 
     * @return array Transfer metrics
     */
    private function getTransferStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
                FROM proposal_log 
                WHERE proposal_type = 'transfer'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total' => (int)$result['total'],
                'pending' => (int)$result['pending'],
                'approved' => (int)$result['approved'],
                'executed' => (int)$result['executed'],
                'failed' => (int)$result['failed'],
                'today' => (int)$result['today'],
                'success_rate' => $result['total'] > 0 
                    ? round(($result['executed'] / $result['total']) * 100, 1)
                    : 0
            ];
        } catch (PDOException $e) {
            error_log("Transfer stats error: " . $e->getMessage());
            return $this->getEmptyTransferStats();
        }
    }
    
    /**
     * Get proposal statistics
     * 
     * @return array Proposal metrics
     */
    private function getProposalStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    AVG(confidence_score) as avg_confidence
                FROM proposal_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total' => (int)$result['total'],
                'pending' => (int)$result['pending'],
                'approved' => (int)$result['approved'],
                'rejected' => (int)$result['rejected'],
                'avg_confidence' => round((float)($result['avg_confidence'] ?? 0), 1),
                'approval_rate' => $result['total'] > 0
                    ? round((($result['approved'] + $result['rejected']) / $result['total']) * 100, 1)
                    : 0
            ];
        } catch (PDOException $e) {
            error_log("Proposal stats error: " . $e->getMessage());
            return $this->getEmptyProposalStats();
        }
    }
    
    /**
     * Get alert statistics
     * 
     * @return array Alert metrics
     */
    private function getAlertStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,
                    SUM(CASE WHEN status = 'unresolved' THEN 1 ELSE 0 END) as unresolved
                FROM guardrail_traces 
                WHERE verdict = 'blocked'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total' => (int)$result['total'],
                'critical' => (int)$result['critical'],
                'high' => (int)$result['high'],
                'medium' => (int)$result['medium'],
                'low' => (int)$result['low'],
                'unresolved' => (int)$result['unresolved']
            ];
        } catch (PDOException $e) {
            error_log("Alert stats error: " . $e->getMessage());
            return $this->getEmptyAlertStats();
        }
    }
    
    /**
     * Get insight statistics
     * 
     * @return array Insight metrics
     */
    private function getInsightStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN category = 'opportunity' THEN 1 ELSE 0 END) as opportunities,
                    SUM(CASE WHEN category = 'risk' THEN 1 ELSE 0 END) as risks,
                    SUM(CASE WHEN category = 'anomaly' THEN 1 ELSE 0 END) as anomalies,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_insights
                FROM insights_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total' => (int)$result['total'],
                'opportunities' => (int)$result['opportunities'],
                'risks' => (int)$result['risks'],
                'anomalies' => (int)$result['anomalies'],
                'new' => (int)$result['new_insights']
            ];
        } catch (PDOException $e) {
            error_log("Insight stats error: " . $e->getMessage());
            return $this->getEmptyInsightStats();
        }
    }
    
    /**
     * Get system health metrics
     * 
     * @return array Health metrics
     */
    private function getHealthStats(): array
    {
        try {
            // Database health
            $dbHealth = $this->checkDatabaseHealth();
            
            // Queue health
            $queueHealth = $this->checkQueueHealth();
            
            // Engine health
            $engineHealth = $this->checkEngineHealth();
            
            // Calculate overall health score
            $healthScore = ($dbHealth['score'] + $queueHealth['score'] + $engineHealth['score']) / 3;
            
            return [
                'overall_score' => round($healthScore, 1),
                'status' => $this->getHealthStatus($healthScore),
                'database' => $dbHealth,
                'queue' => $queueHealth,
                'engine' => $engineHealth,
                'uptime' => $this->getSystemUptime()
            ];
        } catch (Exception $e) {
            error_log("Health stats error: " . $e->getMessage());
            return $this->getEmptyHealthStats();
        }
    }
    
    /**
     * Check database health
     * 
     * @return array Database health metrics
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            $this->db->query("SELECT 1");
            $latency = round((microtime(true) - $start) * 1000, 2);
            
            $score = 100;
            if ($latency > 100) $score = 80;
            if ($latency > 500) $score = 50;
            if ($latency > 1000) $score = 20;
            
            return [
                'status' => 'healthy',
                'score' => $score,
                'latency_ms' => $latency,
                'connections' => $this->getDatabaseConnections()
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'unhealthy',
                'score' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check queue health
     * 
     * @return array Queue health metrics
     */
    private function checkQueueHealth(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM proposal_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $failureRate = $result['total'] > 0 
                ? ($result['failed'] / $result['total']) * 100 
                : 0;
            
            $score = 100;
            if ($failureRate > 5) $score = 80;
            if ($failureRate > 15) $score = 50;
            if ($failureRate > 30) $score = 20;
            if ($result['pending'] > 100) $score -= 20;
            
            return [
                'status' => $score > 70 ? 'healthy' : 'degraded',
                'score' => max(0, $score),
                'pending' => (int)$result['pending'],
                'failed' => (int)$result['failed'],
                'failure_rate' => round($failureRate, 1)
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'unhealthy',
                'score' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check engine health
     * 
     * @return array Engine health metrics
     */
    private function checkEngineHealth(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    MAX(created_at) as last_activity,
                    COUNT(*) as recent_executions
                FROM run_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $minutesSinceActivity = $result['last_activity'] 
                ? round((time() - strtotime($result['last_activity'])) / 60)
                : 999;
            
            $score = 100;
            if ($minutesSinceActivity > 30) $score = 80;
            if ($minutesSinceActivity > 60) $score = 50;
            if ($minutesSinceActivity > 120) $score = 20;
            
            return [
                'status' => $score > 70 ? 'active' : 'idle',
                'score' => $score,
                'last_activity' => $result['last_activity'] ?? null,
                'recent_executions' => (int)$result['recent_executions']
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'unknown',
                'score' => 50,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get database connection count
     * 
     * @return int Number of active connections
     */
    private function getDatabaseConnections(): int
    {
        try {
            $stmt = $this->db->query("SHOW STATUS LIKE 'Threads_connected'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['Value'] ?? 0);
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Get system uptime
     * 
     * @return string Formatted uptime
     */
    private function getSystemUptime(): string
    {
        try {
            $stmt = $this->db->query("SHOW STATUS LIKE 'Uptime'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $seconds = (int)($result['Value'] ?? 0);
            
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            
            return "{$days}d {$hours}h";
        } catch (PDOException $e) {
            return 'Unknown';
        }
    }
    
    /**
     * Get health status label
     * 
     * @param float $score Health score
     * @return string Status label
     */
    private function getHealthStatus(float $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        if ($score >= 30) return 'poor';
        return 'critical';
    }
    
    // Empty fallback methods
    private function getEmptyTransferStats(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'executed' => 0,
            'failed' => 0,
            'today' => 0,
            'success_rate' => 0
        ];
    }
    
    private function getEmptyProposalStats(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'avg_confidence' => 0,
            'approval_rate' => 0
        ];
    }
    
    private function getEmptyAlertStats(): array
    {
        return [
            'total' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'unresolved' => 0
        ];
    }
    
    private function getEmptyInsightStats(): array
    {
        return [
            'total' => 0,
            'opportunities' => 0,
            'risks' => 0,
            'anomalies' => 0,
            'new' => 0
        ];
    }
    
    private function getEmptyHealthStats(): array
    {
        return [
            'overall_score' => 0,
            'status' => 'unknown',
            'database' => ['status' => 'unknown', 'score' => 0],
            'queue' => ['status' => 'unknown', 'score' => 0],
            'engine' => ['status' => 'unknown', 'score' => 0],
            'uptime' => 'Unknown'
        ];
    }
}

/**
 * API Response Handler
 * 
 * Standardizes API responses with consistent structure
 */
class ApiResponse
{
    /**
     * Send success response
     * 
     * @param mixed $data Response data
     * @param int $code HTTP status code
     */
    public static function success($data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $details Additional error details
     */
    public static function error(string $message, int $code = 500, array $details = []): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'details' => $details
            ],
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

try {
    // Verify authentication using unified auth service
    if (!function_exists('auth') || !auth()->check()) {
    \Unified\Support\Api::error('UNAUTHORIZED', 'Unauthorized', 401);
    }
    
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    \Unified\Support\Api::error('METHOD_NOT_ALLOWED', 'Method not allowed', 405);
    }
    
    // Initialize database connection via unified container
    $db = db();
    
    // Initialize service
    $statsService = new DashboardStatsService($db);
    
    // Get all statistics
    $stats = $statsService->getAllStats();
    
    // Send response
    \Unified\Support\Api::ok($stats);
    
} catch (PDOException $e) {
    error_log("Database error in stats API: " . $e->getMessage());
    \Unified\Support\Api::error('DB_ERROR', 'Database connection failed', 503, ['type' => 'database_error']);
    
} catch (Exception $e) {
    error_log("Error in stats API: " . $e->getMessage());
    \Unified\Support\Api::error('INTERNAL_ERROR', 'Internal server error', 500, ['type' => 'server_error']);
}
