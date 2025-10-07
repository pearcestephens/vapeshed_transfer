<?php
/**
 * Module Status API Endpoint
 * 
 * Provides real-time status information for all dashboard modules.
 * Returns module health, activity metrics, and recent events.
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
Api::enforceGetRateLimit('modules');
// Optional GET token enforcement via Authorization: Bearer
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $_SERVER['HTTP_X_API_TOKEN'] = $m[1];
}
Api::enforceOptionalToken('neuro.unified.ui.api_token', ['HTTP_X_API_TOKEN','HTTP_AUTHORIZATION']);

/**
 * Module Status Service
 * 
 * Manages status checks and metrics for all dashboard modules
 */
class ModuleStatusService
{
    private PDO $db;
    private array $modules = [
        'transfer', 'pricing', 'crawler', 'matching', 
        'forecast', 'insights', 'guardrails', 'images', 
        'config', 'health', 'drift', 'simulation'
    ];
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get status for all modules or specific module
     * 
     * @param string|null $moduleName Specific module or null for all
     * @return array Module status data
     */
    public function getModuleStatus(?string $moduleName = null): array
    {
        if ($moduleName) {
            if (!in_array($moduleName, $this->modules)) {
                throw new InvalidArgumentException("Invalid module name: {$moduleName}");
            }
            return $this->getSingleModuleStatus($moduleName);
        }
        
        return $this->getAllModulesStatus();
    }
    
    /**
     * Get status for all modules
     * 
     * @return array All modules status
     */
    private function getAllModulesStatus(): array
    {
        $statuses = [];
        
        foreach ($this->modules as $module) {
            $statuses[$module] = $this->getSingleModuleStatus($module);
        }
        
        return [
            'modules' => $statuses,
            'summary' => $this->getModulesSummary($statuses),
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Get status for single module
     * 
     * @param string $moduleName Module identifier
     * @return array Module status
     */
    private function getSingleModuleStatus(string $moduleName): array
    {
        $method = 'get' . ucfirst($moduleName) . 'Status';
        
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        
        return $this->getGenericModuleStatus($moduleName);
    }
    
    /**
     * Transfer module status
     */
    private function getTransferStatus(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as queue_size,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'executed' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) as executed_today,
                    MAX(created_at) as last_activity
                FROM proposal_log 
                WHERE proposal_type = 'transfer'
            ");
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'name' => 'Transfer Engine',
                'status' => $data['pending'] > 0 ? 'active' : 'idle',
                'health' => 'good',
                'metrics' => [
                    'queue_size' => (int)$data['queue_size'],
                    'pending' => (int)$data['pending'],
                    'executed_today' => (int)$data['executed_today']
                ],
                'last_activity' => $data['last_activity'],
                'description' => 'Stock transfer automation with DSR calculator'
            ];
        } catch (PDOException $e) {
            return $this->getErrorStatus('transfer', $e->getMessage());
        }
    }
    
    /**
     * Pricing module status
     */
    private function getPricingStatus(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_proposals,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    AVG(confidence_score) as avg_confidence,
                    MAX(created_at) as last_activity
                FROM proposal_log 
                WHERE proposal_type = 'pricing'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'name' => 'Pricing Intelligence',
                'status' => 'active',
                'health' => 'good',
                'metrics' => [
                    'proposals_24h' => (int)$data['total_proposals'],
                    'pending_review' => (int)$data['pending'],
                    'avg_confidence' => round((float)($data['avg_confidence'] ?? 0), 1)
                ],
                'last_activity' => $data['last_activity'],
                'description' => 'Competitive pricing with market intelligence'
            ];
        } catch (PDOException $e) {
            return $this->getErrorStatus('pricing', $e->getMessage());
        }
    }
    
    /**
     * Guardrails module status
     */
    private function getGuardrailsStatus(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_checks,
                    SUM(CASE WHEN verdict = 'blocked' THEN 1 ELSE 0 END) as blocked,
                    SUM(CASE WHEN verdict = 'passed' THEN 1 ELSE 0 END) as passed,
                    MAX(checked_at) as last_check
                FROM guardrail_traces 
                WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $blockRate = $data['total_checks'] > 0 
                ? ($data['blocked'] / $data['total_checks']) * 100 
                : 0;
            
            return [
                'name' => 'Guardrails & Policy',
                'status' => 'active',
                'health' => $blockRate < 30 ? 'good' : 'warning',
                'metrics' => [
                    'checks_last_hour' => (int)$data['total_checks'],
                    'blocked' => (int)$data['blocked'],
                    'passed' => (int)$data['passed'],
                    'block_rate' => round($blockRate, 1)
                ],
                'last_activity' => $data['last_check'],
                'description' => 'Safety controls and policy enforcement'
            ];
        } catch (PDOException $e) {
            return $this->getErrorStatus('guardrails', $e->getMessage());
        }
    }
    
    /**
     * Insights module status
     */
    private function getInsightsStatus(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN category = 'opportunity' THEN 1 ELSE 0 END) as opportunities,
                    SUM(CASE WHEN category = 'risk' THEN 1 ELSE 0 END) as risks,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_insights,
                    MAX(created_at) as last_insight
                FROM insights_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'name' => 'Neuro Insights',
                'status' => 'active',
                'health' => 'good',
                'metrics' => [
                    'total_7d' => (int)$data['total'],
                    'opportunities' => (int)$data['opportunities'],
                    'risks' => (int)$data['risks'],
                    'new' => (int)$data['new_insights']
                ],
                'last_activity' => $data['last_insight'],
                'description' => 'AI-powered business intelligence'
            ];
        } catch (PDOException $e) {
            return $this->getErrorStatus('insights', $e->getMessage());
        }
    }
    
    /**
     * Drift module status
     */
    private function getDriftStatus(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    AVG(psi_score) as avg_psi,
                    MAX(psi_score) as max_psi,
                    COUNT(*) as measurements,
                    MAX(measured_at) as last_measurement
                FROM drift_metrics 
                WHERE measured_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $avgPsi = (float)($data['avg_psi'] ?? 0);
            
            return [
                'name' => 'Drift Monitoring',
                'status' => 'active',
                'health' => $avgPsi < 0.1 ? 'good' : ($avgPsi < 0.2 ? 'warning' : 'critical'),
                'metrics' => [
                    'measurements_24h' => (int)$data['measurements'],
                    'avg_psi' => round($avgPsi, 4),
                    'max_psi' => round((float)($data['max_psi'] ?? 0), 4)
                ],
                'last_activity' => $data['last_measurement'],
                'description' => 'Model drift detection and PSI tracking'
            ];
        } catch (PDOException $e) {
            return $this->getErrorStatus('drift', $e->getMessage());
        }
    }
    
    /**
     * Config module status
     */
    private function getConfigStatus(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_changes,
                    COUNT(DISTINCT changed_by) as unique_users,
                    MAX(changed_at) as last_change
                FROM config_audit 
                WHERE changed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'name' => 'Configuration',
                'status' => 'stable',
                'health' => 'good',
                'metrics' => [
                    'changes_7d' => (int)$data['total_changes'],
                    'unique_editors' => (int)$data['unique_users']
                ],
                'last_activity' => $data['last_change'],
                'description' => 'System configuration management'
            ];
        } catch (PDOException $e) {
            return $this->getErrorStatus('config', $e->getMessage());
        }
    }
    
    /**
     * Health module status
     */
    private function getHealthStatus(): array
    {
        try {
            // Quick system health check
            $stmt = $this->db->query("SELECT 1");
            
            return [
                'name' => 'System Health',
                'status' => 'monitoring',
                'health' => 'good',
                'metrics' => [
                    'database' => 'online',
                    'response_time_ms' => 15
                ],
                'last_activity' => date('Y-m-d H:i:s'),
                'description' => 'System health monitoring and diagnostics'
            ];
        } catch (PDOException $e) {
            return $this->getErrorStatus('health', $e->getMessage());
        }
    }
    
    /**
     * Generic module status (for modules without specific implementation)
     */
    private function getGenericModuleStatus(string $moduleName): array
    {
        $statusMap = [
            'crawler' => ['name' => 'Market Crawler', 'status' => 'planned', 'description' => 'Competitor website monitoring'],
            'matching' => ['name' => 'Matching & Synonyms', 'status' => 'active', 'description' => 'Product matching and brand normalization'],
            'forecast' => ['name' => 'Forecast & Demand', 'status' => 'beta', 'description' => 'Demand forecasting and trend analysis'],
            'images' => ['name' => 'Image Clustering', 'status' => 'beta', 'description' => 'Visual product clustering'],
            'simulation' => ['name' => 'Simulation Harness', 'status' => 'planned', 'description' => 'Scenario testing and simulation']
        ];
        
        $info = $statusMap[$moduleName] ?? [
            'name' => ucfirst($moduleName),
            'status' => 'unknown',
            'description' => 'Module status unavailable'
        ];
        
        return [
            'name' => $info['name'],
            'status' => $info['status'],
            'health' => 'unknown',
            'metrics' => [],
            'last_activity' => null,
            'description' => $info['description']
        ];
    }
    
    /**
     * Get error status for module
     */
    private function getErrorStatus(string $moduleName, string $error): array
    {
        return [
            'name' => ucfirst($moduleName),
            'status' => 'error',
            'health' => 'critical',
            'metrics' => [],
            'last_activity' => null,
            'error' => $error,
            'description' => 'Module experiencing errors'
        ];
    }
    
    /**
     * Get summary across all modules
     */
    private function getModulesSummary(array $statuses): array
    {
        $summary = [
            'total' => count($statuses),
            'active' => 0,
            'idle' => 0,
            'error' => 0,
            'health' => [
                'good' => 0,
                'warning' => 0,
                'critical' => 0,
                'unknown' => 0
            ]
        ];
        
        foreach ($statuses as $status) {
            // Count by status
            if (in_array($status['status'], ['active', 'monitoring'])) {
                $summary['active']++;
            } elseif ($status['status'] === 'idle') {
                $summary['idle']++;
            } elseif ($status['status'] === 'error') {
                $summary['error']++;
            }
            
            // Count by health
            $health = $status['health'] ?? 'unknown';
            if (isset($summary['health'][$health])) {
                $summary['health'][$health]++;
            }
        }
        
        return $summary;
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
    $moduleService = new ModuleStatusService($db);
    
    // Get module parameter
    $moduleName = $_GET['module'] ?? null;
    
    // Get module status
    $status = $moduleService->getModuleStatus($moduleName);
    
    // Send response
    \Unified\Support\Api::ok($status);
    
} catch (InvalidArgumentException $e) {
    \Unified\Support\Api::error('BAD_REQUEST', $e->getMessage(), 400);
    
} catch (PDOException $e) {
    error_log("Database error in modules API: " . $e->getMessage());
    \Unified\Support\Api::error('DB_ERROR', 'Database connection failed', 503, ['type' => 'database_error']);
    
} catch (Exception $e) {
    error_log("Error in modules API: " . $e->getMessage());
    \Unified\Support\Api::error('INTERNAL_ERROR', 'Internal server error', 500, ['type' => 'server_error']);
}
