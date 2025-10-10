<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Middleware;

final class RequestMetricsMiddleware
{
    public function record(string $endpoint): void
    {
        $dir = sys_get_temp_dir() . '/cis_console_metrics/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . 'requests.log';
        $line = json_encode(['ts' => time(), 'endpoint' => $endpoint]) . "\n";
        @file_put_contents($file, $line, FILE_APPEND);
    }

    /**
     * Compute counts within the last N seconds.
     * @return array{rps:float, last5:int}
     */
    public static function windowStats(int $seconds = 60): array
    {
        $file = sys_get_temp_dir() . '/cis_console_metrics/requests.log';
        $now = time();
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $count = 0;
        foreach (array_reverse($lines) as $line) {
            $j = json_decode($line, true);
            if (!is_array($j) || !isset($j['ts'])) continue;
            if (($now - (int)$j['ts']) <= $seconds) {
                $count++;
            } else {
                break;
            }
        }
        return ['rps' => $seconds > 0 ? $count / $seconds : 0.0, 'last5' => self::countSince(300)];
    }

    private static function countSince(int $seconds): int
    {
        $file = sys_get_temp_dir() . '/cis_console_metrics/requests.log';
        $now = time();
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $count = 0;
        foreach (array_reverse($lines) as $line) {
            $j = json_decode($line, true);
            if (!is_array($j) || !isset($j['ts'])) continue;
            if (($now - (int)$j['ts']) <= $seconds) {
                $count++;
            } else {
                break;
            }
        }
        return $count;
    }
}
