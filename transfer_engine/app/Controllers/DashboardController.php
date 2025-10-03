<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

/**
 * Dashboard Controller - Simplified Working Version
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Main dashboard with metrics and overview - simplified for immediate use
 */

class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display main dashboard
     */
    public function index(): void
    {
        try {
            // Get database connection only if configured
            if (defined('DB_CONFIGURED') && DB_CONFIGURED) {
                $db = Database::getInstance()->getConnection();
            } else {
                $db = null;
            }
            
            // Get basic metrics
            $metrics = [
                'active_configs' => $db ? $this->getConfigCount($db) : 0,
                'preset_templates' => $db ? $this->getPresetCount($db) : 0,
                'total_executions' => $db ? $this->getExecutionCount($db) : 0,
                'system_health' => '100%'
            ];
            
            // Get configurations for display
            $configurations = $db ? $this->getConfigurations($db) : [];
            
            // System information
            $systemInfo = [
                'php_version' => PHP_VERSION,
                'server_time' => date('Y-m-d H:i:s T'),
                'database_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'environment' => $_ENV['APP_ENV'] ?? 'development',
                'version' => '1.0.0'
            ];
            
            // Render the dashboard using the proper view system
            $this->render('dashboard/index', [
                'title' => 'Dashboard - Vapeshed Transfer Engine',
                'metrics' => $metrics,
                'configurations' => $configurations,
                'system_info' => $systemInfo
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error('Dashboard error: ' . $e->getMessage());
            http_response_code(500);
            if (defined('APP_DEBUG') && APP_DEBUG) {
                echo "Dashboard Error: " . htmlspecialchars($e->getMessage());
            } else {
                include APP_ROOT . '/resources/views/errors/500.php';
            }
        }
    }
    
    private function getConfigCount($db): int
    {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM transfer_configurations WHERE is_active = 1");
            return (int) $result->fetch_assoc()['count'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getPresetCount($db): int
    {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM transfer_configurations WHERE is_preset = 1");
            return (int) $result->fetch_assoc()['count'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getExecutionCount($db): int
    {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM transfer_executions");
            return (int) $result->fetch_assoc()['count'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getConfigurations($db): array
    {
        try {
            $result = $db->query("
                SELECT id, name, description, allocation_method, power_factor, is_preset 
                FROM transfer_configurations 
                WHERE is_active = 1 
                ORDER BY is_preset DESC, name ASC
            ");
            
            $configs = [];
            while ($row = $result->fetch_assoc()) {
                $configs[] = [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'method' => $row['allocation_method'] == 2 ? 'Softmax' : 'Proportional',
                    'power_factor' => $row['power_factor'],
                    'is_preset' => (bool) $row['is_preset']
                ];
            }
            return $configs;
        } catch (Exception $e) {
            return [];
        }
    }


    /**
     * Get recent executions from database
     */
    private function getRecentExecutions($db): array
    {
        try {
            // mysqli connection expected here
            $sql = "
                SELECT e.*, c.name AS config_name
                FROM transfer_executions e
                LEFT JOIN transfer_configurations c ON e.config_id = c.id
                ORDER BY COALESCE(e.created_at, e.start_time, e.updated_at) DESC
                LIMIT 10
            ";
            $result = $db->query($sql);
            if ($result === false) {
                return [];
            }
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        } catch (\Exception $e) {
            // Log error and return empty array
            error_log("Dashboard: Failed to fetch executions: " . $e->getMessage());
            return [];
        }
    }
}