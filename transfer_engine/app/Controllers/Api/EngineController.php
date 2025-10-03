<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class EngineController extends BaseController
{
    public function status(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        $killFile = APP_ROOT . '/KILL_SWITCH';
        $killActive = is_file($killFile);

        $status = 'healthy';
        $metrics = [
            'uptime' => 0,
            'latency' => 0,
            'memory' => memory_get_usage(true),
            'last_run' => '--'
        ];

        // DB connectivity check (optional)
        try {
            if (defined('DB_CONFIGURED') && DB_CONFIGURED) {
                $db = \App\Core\Database::getInstance()->getConnection();
                $db->query('SELECT 1');
            }
        } catch (\Throwable $e) {
            $status = 'warning';
        }

        // If kill switch is on, mark as offline
        if ($killActive) {
            $status = 'offline';
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'status' => $status,
                'metrics' => $metrics,
                'kill_switch' => $killActive,
            ],
            'meta' => [
                'timestamp' => date('c')
            ]
        ]);
    }

    public function diagnostics(): void
    {
        header('Content-Type: application/json');
        $results = [
            'php' => [ 'passed' => true, 'message' => 'PHP ' . PHP_VERSION ],
            'memory' => [ 'passed' => true, 'message' => (string)round(memory_get_usage(true)/1048576,2) . ' MB used' ],
        ];
        // DB check
        try {
            if (defined('DB_CONFIGURED') && DB_CONFIGURED) {
                $db = \App\Core\Database::getInstance()->getConnection();
                $ok = $db->query('SELECT 1') ? true : false;
                $results['database'] = [ 'passed' => $ok, 'message' => $ok ? 'Connected' : 'Query failed' ];
            } else {
                $results['database'] = [ 'passed' => false, 'message' => 'Not configured' ];
            }
        } catch (\Throwable $e) {
            $results['database'] = [ 'passed' => false, 'message' => $e->getMessage() ];
        }
        echo json_encode([ 'success' => true, 'data' => $results, 'meta' => [ 'timestamp' => date('c') ] ]);
    }
}
