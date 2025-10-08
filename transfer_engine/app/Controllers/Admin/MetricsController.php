<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Support\Response;
use App\Support\Db;

/**
 * Traffic metrics controller for Section 11 monitoring.
 */
final class MetricsController
{
    /**
     * GET ?endpoint=admin/metrics/snapshot
     * Returns current traffic metrics as JSON.
     */
    public function snapshot(): void
    {
        $pdo = Db::pdo();
        if (!$pdo) {
            Response::error('Database unavailable', 'DB_UNAVAILABLE', [], 500);
            return;
        }

        try {
            // Top endpoints by hits (last 15 minutes)
            $topStmt = $pdo->query("
                SELECT endpoint, COUNT(*) hits,
                       SUM(status >= 500) errs,
                       ROUND(AVG(ms)) avg_ms
                FROM traffic_requests
                WHERE ts >= NOW() - INTERVAL 15 MINUTE
                GROUP BY endpoint
                ORDER BY hits DESC 
                LIMIT 10
            ");
            $top = $topStmt->fetchAll() ?: [];

            // Total metrics (last 1 minute)
            $totalStmt = $pdo->query("
                SELECT COUNT(*) hits, 
                       SUM(status >= 500) errs, 
                       ROUND(AVG(ms)) avg_ms
                FROM traffic_requests 
                WHERE ts >= NOW() - INTERVAL 1 MINUTE
            ");
            $total = $totalStmt->fetch() ?: ['hits' => 0, 'errs' => 0, 'avg_ms' => 0];

            Response::success([
                'total' => $total,
                'top_endpoints' => $top,
                'timestamp' => date('c'),
            ]);
        } catch (\Throwable $e) {
            Response::error('Metrics query failed', 'QUERY_ERROR', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET ?endpoint=admin/metrics/stream
     * Server-Sent Events stream for real-time metrics.
     */
    public function stream(): void
    {
        // Disable output buffering for SSE
        @ini_set('zlib.output_compression', '0');
        if (ob_get_level()) {
            @ob_end_flush();
        }
        @ob_implicit_flush(1);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx compatibility

        $pdo = Db::pdo();
        if (!$pdo) {
            echo "event: error\n";
            echo "data: {\"error\":\"db_unavailable\"}\n\n";
            flush();
            return;
        }

        $configPath = function_exists('base_path')
            ? base_path('app/Config/traffic.php')
            : dirname(__DIR__, 2) . '/Config/traffic.php';
        $cfg = is_file($configPath) ? require $configPath : [];
        $tick = max(500, (int)($cfg['sse_tick_ms'] ?? 2000));

        ignore_user_abort(true);
        $startTime = time();

        while (!connection_aborted() && (time() - $startTime) < 300) { // 5 min max
            try {
                // Recent activity (last 5 seconds)
                $recentStmt = $pdo->query("
                    SELECT COUNT(*) hits, 
                           SUM(status >= 500) errs, 
                           ROUND(AVG(ms)) avg_ms
                    FROM traffic_requests 
                    WHERE ts >= NOW() - INTERVAL 5 SECOND
                ");
                $recent = $recentStmt->fetch() ?: ['hits' => 0, 'errs' => 0, 'avg_ms' => 0];

                // Top error endpoints (last 5 minutes)
                $errorsStmt = $pdo->query("
                    SELECT endpoint, COUNT(*) count
                    FROM traffic_requests
                    WHERE ts >= NOW() - INTERVAL 5 MINUTE AND status >= 500
                    GROUP BY endpoint 
                    ORDER BY count DESC 
                    LIMIT 5
                ");
                $errors = $errorsStmt->fetchAll() ?: [];

                $payload = [
                    'recent' => $recent,
                    'errors' => $errors,
                    'timestamp' => date('c'),
                ];

                echo "event: tick\n";
                echo "data: " . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n\n";
                flush();
            } catch (\Throwable $e) {
                echo "event: error\n";
                echo "data: {\"error\":\"query_failed\"}\n\n";
                flush();
                break;
            }

            usleep($tick * 1000);
        }
    }
}
