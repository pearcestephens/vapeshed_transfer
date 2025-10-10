<?php
declare(strict_types=1);

namespace CisConsole\App\Http;

use CisConsole\App\Support\Response;
use CisConsole\App\Http\Middleware\AuthMiddleware;
use CisConsole\App\Http\Middleware\RateLimitMiddleware;
use CisConsole\App\Http\Middleware\RequestMetricsMiddleware;

final class Kernel
{
    /** @var array<string, array{controller:string, method:string, auth:bool}> */
    private array $routes;
    private array $security;
    private array $app;

    public function __construct(array $routes, array $security, array $app)
    {
        $this->routes = $routes;
        $this->security = $security;
        $this->app = $app;
    }

    public function handle(string $endpoint): void
    {
        if (!isset($this->routes[$endpoint])) {
            Response::json(['success' => false, 'error' => ['code' => 'not_found', 'message' => 'Unknown endpoint']], 404);
            return;
        }
        $route = $this->routes[$endpoint];

        // Method check (only GET allowed in Phase 1)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== ($route['method'] ?? 'GET')) {
            Response::json(['success' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);
            return;
        }

        // Rate limit sensitive routes
        (new RateLimitMiddleware($this->security['rate_limit']))->enforce($endpoint);

        // Auth when required
        if (!empty($route['auth'])) {
            (new AuthMiddleware($this->security['admin_token']))->enforce();
        }

        // Metrics
        (new RequestMetricsMiddleware())->record($endpoint);

        [$class, $action] = explode('@', $route['controller'], 2);
        $fqcn = 'CisConsole\\App\\Http\\Controllers\\' . $class;
        if (!class_exists($fqcn)) {
            Response::json(['success' => false, 'error' => ['code' => 'controller_missing', 'message' => 'Controller not found']], 500);
            return;
        }
        $controller = new $fqcn($this->app, $this->security);
        if (!method_exists($controller, $action)) {
            Response::json(['success' => false, 'error' => ['code' => 'action_missing', 'message' => 'Action not found']], 500);
            return;
        }
        $controller->$action();
    }
}
