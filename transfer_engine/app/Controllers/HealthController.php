<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Health Controller
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description System health and status checks
 */
class HealthController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Health check endpoint (liveness)
     *
     * Purpose: Must NOT require database credentials. Returns basic process
     * liveness and filesystem checks so it’s always safe to call from
     * uptime monitors and load balancers.
     */
    public function check(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        
        try {
            // Filesystem + process checks only (no DB)
            $now = date('c');
            $killPath = APP_ROOT . '/KILL_SWITCH';
            $tmpDir = APP_ROOT . '/var/tmp';
            $logDir = APP_ROOT . '/var/logs';

            $tmpWritable = @is_writable($tmpDir) || @mkdir($tmpDir, 0755, true);
            $logsReadable = @is_readable($logDir) || @mkdir($logDir, 0755, true);
            $killActive = is_file($killPath);

            $payload = [
                'success' => true,
                'data' => [
                    'healthy' => $tmpWritable && $logsReadable && !$killActive,
                    'status' => $killActive ? 'Kill switch active' : 'OK',
                    'version' => '1.0.0',
                    'uptime' => $this->getUptime(),
                    'engine' => [
                        'ok' => !$killActive,
                        'status' => $killActive ? 'stopped' : 'running',
                        'last_activity' => date('Y-m-d H:i:s')
                    ],
                    'services' => [
                        // Report DB configuration state without connecting
                        'database_configured' => defined('DB_CONFIGURED') ? (bool)DB_CONFIGURED : false,
                        'database_ready_endpoint' => (defined('APP_BASE_URL') ? APP_BASE_URL : '') . '/public/ready'
                    ],
                    'system' => [
                        'php_version' => PHP_VERSION,
                        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
                    ]
                ],
                'meta' => [
                    'timestamp' => $now,
                    'request_id' => uniqid('health_', true),
                    'browse_mode' => false
                ]
            ];

            // Optional diagnostics in debug mode (no secrets leaked)
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $payload['data']['services']['db_eval'] = [
                    'DB_USERNAME_defined' => defined('DB_USERNAME'),
                    'DB_USERNAME_len' => defined('DB_USERNAME') ? strlen((string)DB_USERNAME) : 0,
                    'DB_PASSWORD_defined' => defined('DB_PASSWORD'),
                    'DB_PASSWORD_len' => defined('DB_PASSWORD') ? strlen((string)DB_PASSWORD) : 0,
                    'DB_DATABASE_defined' => defined('DB_DATABASE'),
                    'DB_DATABASE_len' => defined('DB_DATABASE') ? strlen((string)DB_DATABASE) : 0,
                    'DB_CONFIGURED_const' => defined('DB_CONFIGURED') ? (bool)DB_CONFIGURED : null,
                    'env_username_len' => strlen((string)env('DB_USERNAME', '')),
                    'env_password_len' => strlen((string)env('DB_PASSWORD', '')),
                    'env_database_len' => strlen((string)env('DB_DATABASE', ''))
                ];
            }

            http_response_code(200);
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(200); // Liveness should never 5xx; report degraded instead
            echo json_encode([
                'success' => true,
                'data' => [
                    'healthy' => false,
                    'status' => 'Degraded',
                    'error' => [
                        'type' => get_class($e),
                        'message' => $e->getMessage()
                    ]
                ],
                'meta' => [
                    'timestamp' => date('c'),
                    'request_id' => uniqid('health_err_', true)
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
    
    private function getCount($db, string $table, string $condition = '1=1'): int
    {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM $table WHERE $condition");
            return $result ? (int) $result->fetch_assoc()['count'] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
    
    private function getUptime(): string
    {
        if (function_exists('uptime')) {
            return uptime();
        }
        return 'unavailable';
    }

    /**
     * Readiness probe (DB connectivity + schema presence)
     * Returns 200 when ready, 503 when blocked/not configured.
     */
    public function ready(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        // If DB not configured, report not ready but don’t crash
        if (!defined('DB_CONFIGURED') || DB_CONFIGURED === false) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'error' => [
                    'message' => 'Database not configured',
                    'code' => 'DB_NOT_CONFIGURED'
                ],
                'meta' => [
                    'timestamp' => date('c'),
                    'request_id' => uniqid('ready_', true)
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            // Use the actual DatabaseManager used across the app
            $db = \VapeshedTransfer\Database\DatabaseManager::getInstance()->getConnection();
            // Simple ping
            $ok = $db->query('SELECT 1');

            $tables = ['transfer_configurations', 'transfer_executions'];
            $tableStatus = [];
            foreach ($tables as $table) {
                $check = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
                $tableStatus[$table] = $check && $check->num_rows > 0 ? 'exists' : 'missing';
            }

            $allOk = $ok && !in_array('missing', $tableStatus, true);
            http_response_code($allOk ? 200 : 503);
            echo json_encode([
                'success' => $allOk,
                'data' => [
                    'ready' => $allOk,
                    'database' => 'connected',
                    'tables' => $tableStatus
                ],
                'meta' => [
                    'timestamp' => date('c'),
                    'request_id' => uniqid('ready_', true)
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'error' => [
                    'message' => 'Database not reachable',
                    'details' => $e->getMessage(),
                    'code' => 'DB_UNAVAILABLE'
                ],
                'meta' => [
                    'timestamp' => date('c'),
                    'request_id' => uniqid('ready_err_', true)
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
}