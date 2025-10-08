<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Response;

final class AuthenticationMiddleware
{
    public function handle(array $request, callable $next)
    {
        $requirements = $request['route']['auth'] ?? null;
        if ($requirements === null) {
            return $next($request);
        }

        $auth = function_exists('auth') ? auth() : null;
        if ($auth === null || !$auth->check()) {
            Response::error('Authentication required', 'UNAUTHENTICATED', ['endpoint' => $request['endpoint']], 401);
            return false;
        }

        $request['user'] = $auth->user();

        if (isset($requirements['role'])) {
            $role = (string)$requirements['role'];
            if (method_exists($auth, 'hasPermission') && !$auth->hasPermission($role)) {
                Response::error('Insufficient permissions', 'FORBIDDEN', ['role' => $role], 403);
                return false;
            }
        }

        return $next($request);
    }
}
