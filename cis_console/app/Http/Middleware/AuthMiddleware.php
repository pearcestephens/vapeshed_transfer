<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Middleware;

use CisConsole\App\Support\Response;

final class AuthMiddleware
{
    private string $adminToken;

    public function __construct(string $adminToken)
    {
        $this->adminToken = $adminToken;
    }

    public function enforce(): void
    {
        $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        if ($token === '') {
            $token = $_COOKIE['admin_token'] ?? '';
        }
        if ($token === '' && isset($_GET['token'])) {
            $token = (string)$_GET['token'];
        }
        $env = getenv('APP_ENV') ?: 'dev';
        if ($this->adminToken === '') {
            // Developer mode: allow when header token is present
            if ($env === 'dev') {
                if ($token === '') {
                    Response::json(['success' => false, 'error' => ['code' => 'unauthorized', 'message' => 'Missing admin token header']], 401);
                    exit;
                }
                return; // accept any non-empty header in dev
            }
            Response::json(['success' => false, 'error' => ['code' => 'auth_unconfigured', 'message' => 'Admin token not set']], 500);
            exit;
        }
        if ($token === '') {
            Response::json(['success' => false, 'error' => ['code' => 'unauthorized', 'message' => 'Missing admin token']], 401);
            exit;
        }
        if (!hash_equals($this->adminToken, $token)) {
            Response::json(['success' => false, 'error' => ['code' => 'forbidden', 'message' => 'Invalid admin token']], 403);
            exit;
        }
    }
}
