<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Controllers\Admin;

use CisConsole\App\Http\Middleware\RequestMetricsMiddleware;
final class TrafficController
{
    private array $app;
    private array $security;

    public function __construct(array $app, array $security)
    {
        $this->app = $app;
        $this->security = $security;
    }

    public function live(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        $i = 0;
        while ($i < 10) { // short stream for Phase 1
            $stats = RequestMetricsMiddleware::windowStats(60);
            $payload = json_encode([
                'ts' => time(),
                'env' => $this->app['env'],
                'rps' => $stats['rps'],
                'visitors5min' => $stats['last5'],
            ]);
            echo "event: metrics\n";
            echo 'data: ' . $payload . "\n\n";
            echo ": ping\n\n"; // comment line for keep-alive
            @ob_flush();
            @flush();
            usleep(500000);
            $i++;
        }
    }

    public function metrics(): void
    {
        $m1 = RequestMetricsMiddleware::windowStats(60);
        $m5 = RequestMetricsMiddleware::windowStats(300);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => ['rps' => $m1['rps'], 'last5' => $m5['last5']]]);
    }

    public function alerts(): void
    {
        $m1 = RequestMetricsMiddleware::windowStats(60);
        $alerts = [];
        if ($m1['rps'] > 10) { // placeholder threshold
            $alerts[] = ['code' => 'high_rps', 'message' => 'Requests per second exceeds 10'];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => ['alerts' => $alerts]]);
    }
}
