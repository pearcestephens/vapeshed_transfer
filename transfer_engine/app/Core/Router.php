<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple Router Class
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Handles HTTP routing for the transfer engine
 */
class Router
{
    private array $routes = [];
    
    public function get(string $path, callable|string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post(string $path, callable|string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put(string $path, callable|string $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function patch(string $path, callable|string $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }
    
    public function delete(string $path, callable|string $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute(string $method, string $path, callable|string $handler): void
    {
        [$pattern, $params] = $this->buildPattern($path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'pattern' => $pattern,
            'params' => $params
        ];
    }
    
    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Remove base path if running in subdirectory
        $basePath = defined('BASE_PATH') ? BASE_PATH . '/public' : dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && strpos($requestPath, $basePath) === 0) {
            $requestPath = substr($requestPath, strlen($basePath));
        }
        
        // Ensure path starts with /
        if (!$requestPath || $requestPath[0] !== '/') {
            $requestPath = '/' . ltrim($requestPath, '/');
        }
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }
            $params = $this->matchRoute($route['pattern'], $route['params'], $requestPath);
            if ($params !== null) {
                $this->callHandler($route['handler'], $params);
                return;
            }
        }
        
        // No route found
        http_response_code(404);
        include APP_ROOT . '/resources/views/errors/404.php';
    }
    
    private function matchRoute(string $pattern, array $paramNames, string $requestPath): ?array
    {
        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        array_shift($matches);
        if (empty($matches)) {
            return [];
        }

        $params = [];
        foreach ($matches as $index => $value) {
            $key = $paramNames[$index] ?? (string)$index;
            $params[$key] = $value;
        }

        return $params;
    }
    
    private function callHandler(callable|string $handler, array $params = []): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        [$controllerName, $methodName] = explode('@', $handler);
        $controllerClass = 'App\\Controllers\\' . $controllerName;
        
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller {$controllerClass} not found");
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $methodName)) {
            throw new \Exception("Method {$methodName} not found in {$controllerClass}");
        }
        
        call_user_func_array([$controller, $methodName], array_values($params));
    }

    private function buildPattern(string $path): array
    {
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([^}]+)\}/', static function (array $matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $path);

        $pattern = '#^' . str_replace('/', '\/', $pattern) . '$#';

        return [$pattern, $paramNames];
    }
}