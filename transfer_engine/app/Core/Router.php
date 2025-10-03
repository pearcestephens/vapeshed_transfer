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
    
    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put(string $path, string $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete(string $path, string $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute(string $method, string $path, string $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
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
            if ($route['method'] === $requestMethod && $this->pathMatches($route['path'], $requestPath)) {
                $this->callHandler($route['handler']);
                return;
            }
        }
        
        // No route found
        http_response_code(404);
        include APP_ROOT . '/resources/views/errors/404.php';
    }
    
    private function pathMatches(string $routePath, string $requestPath): bool
    {
        // Simple exact match for now
        // TODO: Add parameter support like /user/{id}
        return $routePath === $requestPath;
    }
    
    private function callHandler(string $handler): void
    {
        list($controllerName, $methodName) = explode('@', $handler);
        
        // Handle API controllers in subdirectory
        if (strpos($controllerName, 'Api\\') === 0) {
            $controllerClass = 'App\\Controllers\\' . $controllerName;
        } else {
            $controllerClass = 'App\\Controllers\\' . $controllerName;
        }
        
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller {$controllerClass} not found");
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $methodName)) {
            throw new \Exception("Method {$methodName} not found in {$controllerClass}");
        }
        
        $controller->$methodName();
    }
}