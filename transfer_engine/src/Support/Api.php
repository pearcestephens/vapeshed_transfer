<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * Api.php (Phase M2)
 * Reusable helpers for JSON API endpoints: headers, CORS, preflight, tokens, rate limiting.
 */
final class Api
{
    /** Set standard JSON headers for APIs */
    public static function initJson(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('X-Correlation-ID: ' . \correlationId());
    }

    /** Apply environment-aware CORS with configurable allowed methods and optional allowlist */
    public static function applyCors(string $allowedMethods = 'GET, OPTIONS', ?array $allowlist = null): void
    {
        $env = Config::get('neuro.unified.environment', 'production');
        // If allowlist not provided, try to read from config
        if ($allowlist === null) {
            $cfg = Config::get('neuro.unified.security.cors_allowlist', null);
            if (is_string($cfg)) { $allowlist = array_filter(array_map('trim', explode(',', $cfg))); }
            elseif (is_array($cfg)) { $allowlist = $cfg; }
        }
        if ($env === 'development') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: ' . $allowedMethods);
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-TOKEN, X-Requested-With');
        } else {
            // In non-dev, optionally allow specific origins
            if (is_array($allowlist) && !empty($allowlist)) {
                $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
                if ($origin && in_array($origin, $allowlist, true)) {
                    header('Access-Control-Allow-Origin: ' . $origin);
                    header('Access-Control-Allow-Methods: ' . $allowedMethods);
                    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-TOKEN, X-Requested-With');
                    header('Vary: Origin');
                } else {
                    header('Vary: Origin');
                }
            } else {
                header('Vary: Origin');
            }
        }
    }

    /** Handle OPTIONS preflight and exit */
    public static function handleOptionsPreflight(): void
    {
        if ((($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
            http_response_code(200);
            echo json_encode(['success' => true, 'meta' => ['preflight' => true]]);
            exit;
        }
    }

    /** Enforce feature flag, 403 + exit if disabled */
    public static function enforceFeature(string $configKey, string $message = 'API is disabled'): void
    {
        if (!Config::get($configKey, false)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => [ 'code' => 'DISABLED', 'message' => $message ]
            ]);
            exit;
        }
    }

    /** Enforce optional token if configured; 401 + exit on mismatch */
    public static function enforceOptionalToken(
        string $tokenKey = 'neuro.unified.ui.api_token',
        array $headerKeys = ['HTTP_X_API_TOKEN'],
        array $queryKeys = ['token']
    ): void
    {
        $requiredToken = (string) Config::get($tokenKey, '');
        if ($requiredToken !== '') {
            $provided = '';
            // Try query keys first
            foreach ($queryKeys as $qk) {
                if (isset($_GET[$qk])) { $provided = (string) $_GET[$qk]; break; }
            }
            // Then headers
            if ($provided === '') {
                foreach ($headerKeys as $hk) {
                    if (!empty($_SERVER[$hk])) { $provided = (string) $_SERVER[$hk]; break; }
                }
            }
            if (!hash_equals($requiredToken, $provided)) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => [ 'code' => 'UNAUTHORIZED', 'message' => 'Invalid or missing API token' ]
                ]);
                exit;
            }
        }
    }

    /** Enforce simple per-IP GET rate limit; 429 + exit on exceed.
     * Optionally provide a $group to read group-specific config keys:
     *   neuro.unified.security.groups.{group}.get_rate_limit_per_min
     *   neuro.unified.security.groups.{group}.get_rate_burst
     */
    public static function enforceGetRateLimit(
        ?string $group = null,
        string $perMinKey = 'neuro.unified.security.get_rate_limit_per_min',
        string $burstKey = 'neuro.unified.security.get_rate_burst'
    ): void {
        if ($group !== null && $group !== '') {
            $gPer = sprintf('neuro.unified.security.groups.%s.get_rate_limit_per_min', $group);
            $gBur = sprintf('neuro.unified.security.groups.%s.get_rate_burst', $group);
            $rlPerMin = (int) Config::get($gPer, Config::get($perMinKey, 120));
            $rlBurst  = (int) Config::get($gBur, Config::get($burstKey, 30));
        } else {
            $rlPerMin = (int) Config::get($perMinKey, 120);
            $rlBurst  = (int) Config::get($burstKey, 30);
        }
        if ($rlPerMin <= 0) { return; }
        if ((($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') { return; }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        $bucketDir = defined('STORAGE_PATH') ? (STORAGE_PATH . '/tmp') : sys_get_temp_dir();
        if (!is_dir($bucketDir)) { @mkdir($bucketDir, 0775, true); }
        $safeIp = preg_replace('/[^0-9a-fA-F:\.]/', '', $ip);
        $bucket = $bucketDir . '/get_' . $safeIp . '.bucket';
        $state = ['w' => $now, 'c' => 0];
        if (is_file($bucket)) {
            $raw = @file_get_contents($bucket);
            $dec = $raw ? json_decode($raw, true) : null;
            if (is_array($dec)) { $state = $dec; }
        }
        if ($now - (int) $state['w'] >= 60) { $state = ['w' => $now, 'c' => 0]; }
        $state['c'] = (int) $state['c'] + 1;
        @file_put_contents($bucket, json_encode($state));
        $limitMax = $rlPerMin + max(0, $rlBurst);
        $allowed = $state['c'] <= $limitMax;
        // Observability headers (safe to expose)
        $remaining = max(0, $limitMax - (int)$state['c']);
        $resetAt = (int)$state['w'] + 60;
        header('X-RateLimit-Limit: ' . $rlPerMin);
        header('X-RateLimit-Burst: ' . max(0, $rlBurst));
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $resetAt);
        if (!$allowed) {
            http_response_code(429);
            $retryIn = max(1, 60 - ($now - (int) $state['w']));
            header('Retry-After: ' . $retryIn);
            echo json_encode([
                'success' => false,
                'error' => [ 'code' => 'RATE_LIMITED', 'message' => 'Too many GET requests, slow down' ]
            ]);
            exit;
        }
    }

    /** Enforce simple per-IP POST rate limit; 429 + exit on exceed.
     * Optionally provide a $group to read group-specific config keys:
     *   neuro.unified.security.groups.{group}.post_rate_limit_per_min
     *   neuro.unified.security.groups.{group}.post_rate_burst
     */
    public static function enforcePostRateLimit(
        ?string $group = null,
        string $perMinKey = 'neuro.unified.security.post_rate_limit_per_min',
        string $burstKey = 'neuro.unified.security.post_rate_burst'
    ): void {
        if ($group !== null && $group !== '') {
            $gPer = sprintf('neuro.unified.security.groups.%s.post_rate_limit_per_min', $group);
            $gBur = sprintf('neuro.unified.security.groups.%s.post_rate_burst', $group);
            $rlPerMin = (int) Config::get($gPer, Config::get($perMinKey, 0));
            $rlBurst  = (int) Config::get($gBur, Config::get($burstKey, 0));
        } else {
            $rlPerMin = (int) Config::get($perMinKey, 0);
            $rlBurst  = (int) Config::get($burstKey, 0);
        }
        if ($rlPerMin <= 0) { return; }
        if ((($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') { return; }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        $bucketDir = defined('STORAGE_PATH') ? (STORAGE_PATH . '/tmp') : sys_get_temp_dir();
        if (!is_dir($bucketDir)) { @mkdir($bucketDir, 0775, true); }
        $safeIp = preg_replace('/[^0-9a-fA-F:\.]/', '', $ip);
        $bucket = $bucketDir . '/post_' . $safeIp . '.bucket';
        $state = ['w' => $now, 'c' => 0];
        if (is_file($bucket)) {
            $raw = @file_get_contents($bucket);
            $dec = $raw ? json_decode($raw, true) : null;
            if (is_array($dec)) { $state = $dec; }
        }
        if ($now - (int) $state['w'] >= 60) { $state = ['w' => $now, 'c' => 0]; }
        $state['c'] = (int) $state['c'] + 1;
        @file_put_contents($bucket, json_encode($state));
        $limitMax = $rlPerMin + max(0, $rlBurst);
        $allowed = $state['c'] <= $limitMax;
        $remaining = max(0, $limitMax - (int)$state['c']);
        $resetAt = (int)$state['w'] + 60;
        header('X-RateLimit-POST-Limit: ' . $rlPerMin);
        header('X-RateLimit-POST-Burst: ' . max(0, $rlBurst));
        header('X-RateLimit-POST-Remaining: ' . $remaining);
        header('X-RateLimit-POST-Reset: ' . $resetAt);
        if (!$allowed) {
            http_response_code(429);
            $retryIn = max(1, 60 - ($now - (int) $state['w']));
            header('Retry-After: ' . $retryIn);
            echo json_encode([
                'success' => false,
                'error' => [ 'code' => 'RATE_LIMITED', 'message' => 'Too many POST requests, slow down' ]
            ]);
            exit;
        }
    }

    /** Standard JSON response with optional status code */
    public static function respond(array $payload, int $status = 200): void
    {
        // Ensure meta exists and includes correlation + standard fields
        $cid = \correlationId();
        $now = microtime(true);
        $started = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? ($now - 0.0));
        $durationMs = max(0, (int) round(($now - $started) * 1000));
        $defaults = [
            'correlation_id' => $cid,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'endpoint' => basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
            'path' => (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''),
            'ts' => time(),
            'duration_ms' => $durationMs
        ];
        if (!isset($payload['meta'])) {
            $payload['meta'] = $defaults;
        } elseif (is_array($payload['meta'])) {
            // Add any missing default fields without overriding explicit meta
            foreach ($defaults as $k => $v) {
                if (!array_key_exists($k, $payload['meta'])) { $payload['meta'][$k] = $v; }
            }
        }
        http_response_code($status);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Convenience OK response */
    public static function ok(array $data, int $status = 200): void
    { self::respond(['success' => true, 'data' => $data], $status); }

    /** Convenience error response */
    public static function error(string $code, string $message, int $status = 400, array $extra = []): void
    {
        $payload = ['success' => false, 'error' => [ 'code' => $code, 'message' => $message ]];
        // Always include correlation id in meta for observability
        $meta = array_merge(['correlation_id' => \correlationId()], $extra);
        if ($meta) { $payload['meta'] = $meta; }
        self::respond($payload, $status);
    }

    /** Enforce CSRF for POST-like methods if enabled via config */
    public static function enforceCsrf(
        string $configKey = 'neuro.unified.security.csrf_required',
        string $headerKey = 'HTTP_X_CSRF_TOKEN',
        string $postKey = '_csrf'
    ): void {
        $required = (bool) Config::get($configKey, false);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!$required) { return; }
        if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) { return; }
        $clientToken = $_SERVER[$headerKey] ?? ($_POST[$postKey] ?? '');
        $sessionToken = $_SESSION['_csrf'] ?? '';
        $valid = (!empty($sessionToken) && hash_equals((string)$sessionToken, (string)$clientToken));
        if (!$valid) {
            self::error('CSRF_TOKEN_MISMATCH', 'Invalid CSRF token', 419);
        }
    }
}
