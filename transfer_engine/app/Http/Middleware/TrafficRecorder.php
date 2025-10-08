<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Db;

/**
 * Records traffic metrics for Section 11 monitoring.
 * Fails silently to never impact user requests.
 */
final class TrafficRecorder
{
    public static function record(array $ctx): void
    {
        try {
            $configPath = function_exists('base_path')
                ? base_path('app/Config/traffic.php')
                : dirname(__DIR__, 2) . '/Config/traffic.php';

            $cfg = is_file($configPath) ? require $configPath : [];
            $cfg = is_array($cfg) ? $cfg : [];

            if (!(bool)($cfg['enabled'] ?? true)) {
                return;
            }

            $sampleRate = max(0.0, min(1.0, (float)($cfg['sample_rate'] ?? 1.0)));
            if ($sampleRate < 1.0 && mt_rand() / mt_getrandmax() > $sampleRate) {
                return;
            }

            $pdo = Db::pdo();
            if (!$pdo) {
                return;
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ipBin = null;
            if ($ip !== '') {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ipBin = inet_pton($ip);
                } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ipBin = inet_pton($ip);
                }
            }

            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $salt = (string)($cfg['privacy_salt'] ?? 'salt');
            $uaHash = $ua !== '' ? pack('H*', md5($ua . $salt)) : null;

            $stmt = $pdo->prepare(
                "INSERT INTO traffic_requests(ts, method, endpoint, status, ms, ip, ua_hash, corr, bytes_out, err)
                 VALUES (NOW(), :m, :e, :s, :ms, :ip, :uah, :c, :bo, :err)"
            );

            $stmt->execute([
                ':m'   => substr((string)($ctx['method'] ?? 'GET'), 0, 8),
                ':e'   => substr((string)($ctx['endpoint'] ?? ''), 0, 120),
                ':s'   => (int)($ctx['status'] ?? 200),
                ':ms'  => (int)($ctx['ms'] ?? 0),
                ':ip'  => $ipBin,
                ':uah' => $uaHash,
                ':c'   => substr((string)($_SERVER['X_CORRELATION_ID'] ?? ''), 0, 16),
                ':bo'  => (int)($ctx['bytes_out'] ?? 0),
                ':err' => (int)((int)($ctx['status'] ?? 200) >= 500),
            ]);
        } catch (\Throwable $e) {
            // Swallow all exceptions - metrics must never break production
            error_log('TrafficRecorder failed silently: ' . $e->getMessage());
        }
    }
}
