<?php
/**
 * Activity Feed API Endpoint
 * 
 * Provides real-time activity stream for dashboard.
 * Returns recent system events, actions, and notifications.
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
Api::enforceGetRateLimit('activity');
// Optional GET token enforcement via Authorization: Bearer
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $_SERVER['HTTP_X_API_TOKEN'] = $m[1];
}
Api::enforceOptionalToken('neuro.unified.ui.api_token', ['HTTP_X_API_TOKEN','HTTP_AUTHORIZATION']);

/**
 * Activity Feed Service
 * 
 * Aggregates activity from multiple sources into unified feed
 */
class ActivityFeedService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get recent activity feed
     * 
     * @param int $limit Maximum number of items
     * @param int $offset Pagination offset
     * @param string|null $type Filter by activity type
     * @return array Activity feed data
     */
    public function getActivityFeed(int $limit = 50, int $offset = 0, ?string $type = null): array
    {
        $activities = [];
        
        // Gather activities from different sources
        $activities = array_merge($activities, $this->getProposalActivities($limit));
        $activities = array_merge($activities, $this->getGuardrailActivities($limit));
        $activities = array_merge($activities, $this->getInsightActivities($limit));
        $activities = array_merge($activities, $this->getConfigActivities($limit));
        $activities = array_merge($activities, $this->getSystemActivities($limit));
        
        // Filter by type if specified
        if ($type) {
            $activities = array_filter($activities, function($activity) use ($type) {
                return $activity['type'] === $type;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Apply pagination
        $activities = array_slice($activities, $offset, $limit);
        
        return [
            'items' => array_values($activities),
            'total' => count($activities),
            'limit' => $limit,
            'offset' => $offset,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Get proposal-related activities
     */
    private function getProposalActivities(int $limit): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    proposal_type,
                    product_sku,
                    status,
                    created_at,
                    updated_at
                FROM proposal_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($proposal) {
                $type = $proposal['proposal_type'] ?? 'unknown';
                $icon = $type === 'transfer' ? 'exchange-alt' : 'tags';
                $color = $type === 'transfer' ? 'purple' : 'pink';
                
                return [
                    'id' => 'proposal_' . $proposal['id'],
                    'type' => 'proposal',
                    'subtype' => $type,
                    'icon' => $icon,
                    'color' => $color,
                    'title' => ucfirst($type) . ' Proposal Created',
                    'description' => "New {$type} proposal for " . ($proposal['product_sku'] ?? 'product'),
                    'status' => $proposal['status'],
                    'timestamp' => $proposal['created_at'],
                    'metadata' => [
                        'proposal_id' => $proposal['id'],
                        'product_sku' => $proposal['product_sku']
                    ]
                ];
            }, $proposals);
            
        } catch (PDOException $e) {
            error_log("Error fetching proposal activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get guardrail-related activities
     */
    private function getGuardrailActivities(int $limit): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    rule_name,
                    verdict,
                    reason,
                    checked_at
                FROM guardrail_traces 
                WHERE verdict = 'blocked'
                AND checked_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY checked_at DESC 
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $guardrails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($guardrail) {
                return [
                    'id' => 'guardrail_' . $guardrail['id'],
                    'type' => 'guardrail',
                    'subtype' => 'blocked',
                    'icon' => 'shield-alt',
                    'color' => 'orange',
                    'title' => 'Guardrail Triggered',
                    'description' => $guardrail['rule_name'] . ': ' . $guardrail['reason'],
                    'status' => 'blocked',
                    'timestamp' => $guardrail['checked_at'],
                    'metadata' => [
                        'rule_name' => $guardrail['rule_name'],
                        'reason' => $guardrail['reason']
                    ]
                ];
            }, $guardrails);
            
        } catch (PDOException $e) {
            error_log("Error fetching guardrail activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get insight-related activities
     */
    private function getInsightActivities(int $limit): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    category,
                    severity,
                    title,
                    message,
                    created_at
                FROM insights_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $insights = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($insight) {
                $iconMap = [
                    'opportunity' => 'lightbulb',
                    'risk' => 'exclamation-triangle',
                    'anomaly' => 'chart-line'
                ];
                
                $colorMap = [
                    'opportunity' => 'green',
                    'risk' => 'red',
                    'anomaly' => 'blue'
                ];
                
                return [
                    'id' => 'insight_' . $insight['id'],
                    'type' => 'insight',
                    'subtype' => $insight['category'],
                    'icon' => $iconMap[$insight['category']] ?? 'info-circle',
                    'color' => $colorMap[$insight['category']] ?? 'gray',
                    'title' => $insight['title'] ?? 'New Insight',
                    'description' => $insight['message'],
                    'status' => $insight['severity'],
                    'timestamp' => $insight['created_at'],
                    'metadata' => [
                        'category' => $insight['category'],
                        'severity' => $insight['severity']
                    ]
                ];
            }, $insights);
            
        } catch (PDOException $e) {
            error_log("Error fetching insight activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get configuration change activities
     */
    private function getConfigActivities(int $limit): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    config_key,
                    old_value,
                    new_value,
                    changed_by,
                    changed_at
                FROM config_audit 
                WHERE changed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY changed_at DESC 
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($change) {
                return [
                    'id' => 'config_' . $change['id'],
                    'type' => 'config',
                    'subtype' => 'changed',
                    'icon' => 'cog',
                    'color' => 'gray',
                    'title' => 'Configuration Changed',
                    'description' => "Config '{$change['config_key']}' updated by {$change['changed_by']}",
                    'status' => 'completed',
                    'timestamp' => $change['changed_at'],
                    'metadata' => [
                        'config_key' => $change['config_key'],
                        'changed_by' => $change['changed_by']
                    ]
                ];
            }, $changes);
            
        } catch (PDOException $e) {
            error_log("Error fetching config activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system-level activities
     */
    private function getSystemActivities(int $limit): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    run_type,
                    status,
                    items_processed,
                    started_at,
                    completed_at
                FROM run_log 
                WHERE started_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY started_at DESC 
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($run) {
                $status = $run['status'] ?? 'unknown';
                $statusColor = $status === 'completed' ? 'green' : ($status === 'failed' ? 'red' : 'blue');
                
                return [
                    'id' => 'run_' . $run['id'],
                    'type' => 'system',
                    'subtype' => $run['run_type'],
                    'icon' => 'server',
                    'color' => $statusColor,
                    'title' => ucfirst($run['run_type']) . ' Run ' . ucfirst($status),
                    'description' => "Processed {$run['items_processed']} items",
                    'status' => $status,
                    'timestamp' => $run['started_at'],
                    'metadata' => [
                        'run_type' => $run['run_type'],
                        'items_processed' => $run['items_processed'],
                        'duration' => $run['completed_at'] 
                            ? strtotime($run['completed_at']) - strtotime($run['started_at']) 
                            : null
                    ]
                ];
            }, $runs);
            
        } catch (PDOException $e) {
            error_log("Error fetching system activities: " . $e->getMessage());
            return [];
        }
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
    
    // Get query parameters
    $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 items
    $offset = (int)($_GET['offset'] ?? 0);
    if ($limit < 1 || $limit > 200) { \Unified\Support\Api::error('INVALID_LIMIT', 'limit must be between 1 and 200', 400); }
    if ($offset < 0 || $offset > 10000) { \Unified\Support\Api::error('INVALID_OFFSET', 'offset must be between 0 and 10000', 400); }
    $type = $_GET['type'] ?? null;
    
    // Initialize database connection via unified container
    $db = db();
    
    // Initialize service
    $activityService = new ActivityFeedService($db);
    
    // Get activity feed
    $feed = $activityService->getActivityFeed($limit, $offset, $type);
    
    // Send response
    \Unified\Support\Api::ok($feed);
    
} catch (PDOException $e) {
    error_log("Database error in activity API: " . $e->getMessage());
    \Unified\Support\Api::error('DB_ERROR', 'Database connection failed', 503, ['type' => 'database_error']);
    
} catch (Exception $e) {
    error_log("Error in activity API: " . $e->getMessage());
    \Unified\Support\Api::error('INTERNAL_ERROR', 'Internal server error', 500, ['type' => 'server_error']);
}
