<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Support\Logger;
use App\Support\Response;
use Unified\Support\Env;
use Unified\Support\Pdo;
use Throwable;

final class HealthController
{
    private string $channel = 'health';

    public function ping(array $request): void
    {
        $data = [
            'status' => 'ok',
            'environment' => Env::get('APP_ENV', 'production'),
            'version' => Env::get('APP_VERSION', '2.0.0'),
            'safe_mode' => $request['safe_mode'],
            'timestamp' => date('c'),
        ];

        Response::success($data, ['endpoint' => 'admin/health/ping']);
    }

    public function phpinfo(array $request): void
    {
        if (Env::get('ALLOW_PHPINFO', 'false') !== 'true') {
            Response::error('phpinfo disabled by configuration', 'DISABLED', ['endpoint' => 'admin/health/phpinfo'], 403);
            return;
        }

        $summary = [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'loaded_extensions' => array_values(array_filter(get_loaded_extensions(), static fn(string $ext): bool => stripos($ext, 'xdebug') === false)),
            'ini' => [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ];

        Response::success($summary, ['endpoint' => 'admin/health/phpinfo']);
    }

    public function bundle(array $request): void
    {
        $started = microtime(true);
        $safeMode = (bool)$request['safe_mode'];

        $checks = [
            'ssl' => $this->checkSsl($safeMode),
            'database' => $this->checkDatabase($safeMode),
            'php_fpm' => $this->checkPhpFpm(),
            'queue' => $this->checkQueue($safeMode),
            'vend' => $this->checkVend($safeMode),
        ];

        $result = [
            'overall_status' => $this->aggregateStatus($checks),
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'checks' => $checks,
            'safe_mode' => $safeMode,
        ];

        Response::success($result, ['endpoint' => 'admin/http/one-click-check']);
    }

    private function checkSsl(bool $safeMode): array
    {
        $url = Env::get('APP_URL', 'https://staff.vapeshed.co.nz');
        $host = parse_url($url, PHP_URL_HOST) ?: 'staff.vapeshed.co.nz';

        if ($safeMode) {
            return [
                'status' => 'warn',
                'message' => 'SAFE_MODE enabled; SSL probe skipped',
                'host' => $host,
            ];
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $status = 'ok';
        $message = 'Certificate valid';
        $expires = null;

        try {
            $client = @stream_socket_client(
                'ssl://' . $host . ':443',
                $errno,
                $errstr,
                2,
                STREAM_CLIENT_CONNECT,
                $context
            );
            if (!$client) {
                throw new \RuntimeException($errstr ?: 'Unable to open SSL socket');
            }
            $params = stream_context_get_params($client);
            if (!empty($params['options']['ssl']['peer_certificate'])) {
                $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                if (isset($cert['validTo_time_t'])) {
                    $expires = date('c', (int)$cert['validTo_time_t']);
                    if ($cert['validTo_time_t'] < time()) {
                        $status = 'fail';
                        $message = 'Certificate expired';
                    }
                }
            }
            fclose($client);
        } catch (Throwable $e) {
            $status = 'warn';
            $message = 'SSL probe failed: ' . $e->getMessage();
            Logger::warn('ssl.check.failed', ['error' => $e->getMessage(), 'host' => $host], $this->channel);
        }

        return [
            'status' => $status,
            'message' => $message,
            'host' => $host,
            'expires_at' => $expires,
        ];
    }

    private function checkDatabase(bool $safeMode): array
    {
        if ($safeMode) {
            return [
                'status' => 'warn',
                'message' => 'SAFE_MODE enabled; database ping skipped',
            ];
        }

        $status = 'ok';
        $message = 'Connected';

        try {
            $started = microtime(true);
            $pdo = Pdo::instance();
            $pdo->query('SELECT 1');
            $duration = (microtime(true) - $started) * 1000;
        } catch (Throwable $e) {
            $status = 'fail';
            $message = 'Database unreachable: ' . $e->getMessage();
            $duration = null;
            Logger::exception($e, ['component' => 'db'], $this->channel);
        }

        return [
            'status' => $status,
            'message' => $message,
            'latency_ms' => $duration !== null ? (int)round($duration) : null,
        ];
    }

    private function checkPhpFpm(): array
    {
        $status = PHP_SAPI === 'fpm-fcgi' ? 'ok' : 'warn';
        $message = PHP_SAPI === 'fpm-fcgi' ? 'Running under PHP-FPM' : 'Not running under PHP-FPM';

        return [
            'status' => $status,
            'message' => $message,
            'sapi' => PHP_SAPI,
        ];
    }

    private function checkQueue(bool $safeMode): array
    {
        $endpoint = Env::get('CHECK_QUEUE_ENDPOINT', 'http://localhost:9000/queue/health');
        if ($safeMode) {
            return [
                'status' => 'warn',
                'message' => 'SAFE_MODE enabled; queue health simulated',
                'endpoint' => $endpoint,
            ];
        }

        $response = $this->httpGet($endpoint, 2);
        return [
            'status' => $response['ok'] ? 'ok' : 'warn',
            'message' => $response['message'],
            'endpoint' => $endpoint,
            'http_code' => $response['code'],
        ];
    }

    private function checkVend(bool $safeMode): array
    {
        $baseUrl = Env::get('VEND_BASE_URL', 'https://api.vendhq.com');
        if ($safeMode || $baseUrl === '') {
            return [
                'status' => 'warn',
                'message' => 'SAFE_MODE enabled or Vend URL missing; skipping realtime probe',
                'endpoint' => $baseUrl,
            ];
        }

        $response = $this->httpGet(rtrim($baseUrl, '/') . '/health', 2);

        return [
            'status' => $response['ok'] ? 'ok' : 'warn',
            'message' => $response['message'],
            'endpoint' => $baseUrl,
            'http_code' => $response['code'],
        ];
    }

    private function aggregateStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');
        if (in_array('fail', $statuses, true)) {
            return 'fail';
        }
        if (in_array('warn', $statuses, true)) {
            return 'warn';
        }
        return 'ok';
    }

    private function httpGet(string $url, int $timeoutSeconds = 2): array
    {
        $message = 'Probe executed';
        $code = null;
        $ok = false;
        $start = microtime(true);

        try {
            $options = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeoutSeconds,
                    'ignore_errors' => true,
                    'header' => [
                        'Accept: application/json',
                        'User-Agent: Vapeshed-HealthBot/1.0',
                    ],
                ],
            ];
            $context = stream_context_create($options);
            $body = @file_get_contents($url, false, $context);
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (stripos($header, 'HTTP/') === 0) {
                        $parts = explode(' ', $header);
                        $code = isset($parts[1]) ? (int)$parts[1] : null;
                        break;
                    }
                }
            }

            if ($code !== null && $code < 400) {
                $ok = true;
            }

            if ($body === false) {
                $message = 'No response body (timeout?)';
            } elseif ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['status'])) {
                    $message = is_string($decoded['status']) ? $decoded['status'] : json_encode($decoded['status']);
                }
            }
        } catch (Throwable $e) {
            $message = 'HTTP probe failed: ' . $e->getMessage();
            Logger::warn('http.probe.failed', ['url' => $url, 'error' => $e->getMessage()], $this->channel);
        }

        return [
            'ok' => $ok,
            'code' => $code,
            'message' => $message,
            'duration_ms' => (int)round((microtime(true) - $start) * 1000),
        ];
    }
}
