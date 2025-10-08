<?php
declare(strict_types=1);

namespace App\Http\Middleware;

final class CorrelationIdMiddleware
{
    public function handle(array $request, callable $next)
    {
        if (!isset($GLOBALS['__correlation_id'])) {
            $incoming = $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;
            $GLOBALS['__correlation_id'] = $incoming && is_string($incoming) && $incoming !== ''
                ? substr($incoming, 0, 64)
                : bin2hex(random_bytes(8));
        }

        header('X-Correlation-ID: ' . $GLOBALS['__correlation_id']);

        return $next($request);
    }
}
