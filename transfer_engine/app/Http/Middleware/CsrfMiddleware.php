<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Response;

final class CsrfMiddleware
{
    private bool $required;
    private string $tokenKey;

    public function __construct(bool $required, string $tokenKey)
    {
        $this->required = $required;
        $this->tokenKey = $tokenKey;
    }

    public function handle(array $request, callable $next)
    {
        if (!$this->required || !in_array($request['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $sessionTokens = array_filter([
            $_SESSION[$this->tokenKey] ?? null,
            $_SESSION['csrf_token'] ?? null,
            $_SESSION['_csrf'] ?? null,
        ]);

        $provided = $this->extractToken($request);

        if ($provided === null || empty($sessionTokens) || !in_array($provided, $sessionTokens, true)) {
            Response::error('Invalid CSRF token', 'CSRF_MISMATCH', ['endpoint' => $request['endpoint']], 419);
            return false;
        }

        return $next($request);
    }

    private function extractToken(array $request): ?string
    {
        $input = $request['input'] ?? [];
        $query = $request['query'] ?? [];
        $headers = $request['headers'] ?? [];

        $candidates = [
            $input[$this->tokenKey] ?? null,
            $query[$this->tokenKey] ?? null,
            $headers['X-CSRF-TOKEN'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
