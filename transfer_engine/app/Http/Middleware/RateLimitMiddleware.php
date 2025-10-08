<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Response;

final class RateLimitMiddleware
{
    /** @var array<string, array{per_minute:int, burst:int|null}> */
    private array $limits;

    public function __construct(array $limits)
    {
        $this->limits = $limits;
    }

    public function handle(array $request, callable $next)
    {
        $endpoint = $request['endpoint'];
        $key = $this->resolveKey($request);
        $limits = $this->limits[$endpoint] ?? $this->limits['default'] ?? ['per_minute' => 60, 'burst' => 10];

        $perMinute = max(1, (int)($limits['per_minute'] ?? 60));
        $burst = (int)($limits['burst'] ?? $perMinute);
        $bucketKey = $endpoint . ':' . $key;

        if (!isset($_SESSION['_rate_limits'])) {
            $_SESSION['_rate_limits'] = [];
        }

        if (!isset($_SESSION['_rate_limits'][$bucketKey])) {
            $_SESSION['_rate_limits'][$bucketKey] = [
                'tokens' => $burst,
                'updated_at' => microtime(true),
            ];
        }

        $bucket = &$_SESSION['_rate_limits'][$bucketKey];
        $now = microtime(true);
        $elapsed = $now - $bucket['updated_at'];
        $refillPerSecond = $perMinute / 60;
        $bucket['tokens'] = min($burst, $bucket['tokens'] + $elapsed * $refillPerSecond);
        $bucket['updated_at'] = $now;

        if ($bucket['tokens'] < 1) {
            $retryAfter = max(1, (int)ceil((1 - $bucket['tokens']) / $refillPerSecond));
            Response::json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many requests',
                ],
                'meta' => [
                    'retry_after' => $retryAfter,
                    'endpoint' => $endpoint,
                ]
            ], 429, [
                'Retry-After' => (string)$retryAfter,
                'X-RateLimit-Limit' => (string)$perMinute,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string)(time() + $retryAfter),
            ]);
            return false;
        }

        $bucket['tokens'] -= 1;

        header('X-RateLimit-Limit: ' . $perMinute);
        header('X-RateLimit-Remaining: ' . (int)$bucket['tokens']);

        return $next($request);
    }

    private function resolveKey(array $request): string
    {
        $userId = $request['user']['id'] ?? ($_SESSION['user_id'] ?? null);
        if ($userId) {
            return 'user:' . $userId;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return 'ip:' . $ip;
    }
}
