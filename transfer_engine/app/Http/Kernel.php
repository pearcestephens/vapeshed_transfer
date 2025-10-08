<?php
declare(strict_types=1);

namespace App\Http;

use App\Support\Logger;
use App\Support\Response;
use App\Http\Middleware\AuthenticationMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\CorrelationIdMiddleware;
use Unified\Support\Env;

final class Kernel
{
    private array $routes;
    private array $security;
    private array $middleware;
    private string $configDir;

    public function __construct()
    {
        $this->configDir = defined('CONFIG_PATH') ? CONFIG_PATH : dirname(__DIR__, 2) . '/config';
        $this->routes = $this->loadConfig($this->configDir . '/urls.php');
        $this->security = $this->loadConfig($this->configDir . '/security.php');
        $csrfConfig = $this->security['csrf'] ?? ['required' => false, 'token_key' => '_csrf'];
        $rateConfig = $this->security['rate_limits'] ?? [];

        $this->middleware = [
            new CorrelationIdMiddleware(),
            new RateLimitMiddleware($rateConfig),
            new AuthenticationMiddleware(),
            new CsrfMiddleware((bool)($csrfConfig['required'] ?? false), (string)($csrfConfig['token_key'] ?? '_csrf')),
        ];
    }

    public function handle(): bool
    {
        $endpoint = $_GET['endpoint'] ?? null;
        if ($endpoint === null) {
            return false;
        }

        if (!isset($this->routes[$endpoint])) {
            Logger::warn('Unknown admin endpoint', ['endpoint' => $endpoint], 'http');
            Response::error('Endpoint not found', 'NOT_FOUND', ['endpoint' => $endpoint], 404);
            return true;
        }

        $route = $this->routes[$endpoint];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, $route['methods'], true)) {
            Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', ['endpoint' => $endpoint], 405);
            return true;
        }

        $request = $this->buildRequest($endpoint, $route, $method);
        $this->runMiddleware($request, function (array $request) {
            return $this->dispatch($request);
        });

        return true;
    }

    private function buildRequest(string $endpoint, array $route, string $method): array
    {
        return [
            'endpoint' => $endpoint,
            'route' => $route,
            'method' => $method,
            'query' => $_GET,
            'input' => $this->parseInput(),
            'raw_body' => $this->readBody(),
            'headers' => $this->collectHeaders(),
            'is_sse' => (bool)($route['sse'] ?? false),
            'safe_mode' => Env::get('SAFE_MODE', 'false') === 'true',
        ];
    }

    private function runMiddleware(array $request, callable $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static function ($next, $middleware) {
                return static function ($request) use ($middleware, $next) {
                    return $middleware->handle($request, $next);
                };
            },
            $destination
        );

        return $pipeline($request);
    }

    private function dispatch(array $request)
    {
        [$class, $method] = $this->parseHandler($request['route']['handler']);

        if (!class_exists($class)) {
            Logger::error('Controller not found', ['controller' => $class, 'endpoint' => $request['endpoint']], 'http');
            Response::error('Handler not found', 'HANDLER_MISSING', ['endpoint' => $request['endpoint']], 500);
            return false;
        }

        $controller = new $class();
        if (!method_exists($controller, $method)) {
            Logger::error('Controller method missing', ['controller' => $class, 'method' => $method], 'http');
            Response::error('Handler not found', 'HANDLER_MISSING', ['endpoint' => $request['endpoint']], 500);
            return false;
        }

        try {
            if ($request['is_sse']) {
                $this->prepareSse();
            }
            $reflection = new \ReflectionMethod($controller, $method);
            if ($reflection->getNumberOfParameters() === 0) {
                return $controller->{$method}();
            }

            return $controller->{$method}($request);
        } catch (\Throwable $e) {
            Logger::exception($e, ['endpoint' => $request['endpoint']], 'http');
            Response::error('Internal server error', 'UNEXPECTED_EXCEPTION', ['endpoint' => $request['endpoint']], 500);
            return false;
        }
    }

    private function parseHandler(string $handler): array
    {
        if (str_contains($handler, '@')) {
            [$controller, $method] = explode('@', $handler, 2);
        } else {
            $controller = $handler;
            $method = '__invoke';
        }

        if (!str_starts_with($controller, 'App\\Controllers')) {
            $controller = 'App\\Controllers\\' . $controller;
        }

        return [$controller, $method];
    }

    private function parseInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $body = $this->readBody();
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST ?: [];
    }

    private function readBody(): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = file_get_contents('php://input') ?: '';
        }

        return $cache;
    }

    private function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private function prepareSse(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        $retry = (int)($this->security['sse']['retry_ms'] ?? 4000);
        echo "retry: {$retry}\n\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        if (function_exists('flush')) {
            @flush();
        }
    }

    private function loadConfig(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $config = require $path;
        return is_array($config) ? $config : [];
    }
}
