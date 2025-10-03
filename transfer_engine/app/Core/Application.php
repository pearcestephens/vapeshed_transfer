<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Main Application Class
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed) 
 * @description Core application initialization and dependency management
 */
class Application
{
    private static ?Application $instance = null;
    private array $services = [];
    
    public function __construct()
    {
        self::$instance = $this;
        $this->initialize();
    }
    
    public static function getInstance(): ?Application
    {
        return self::$instance;
    }
    
    private function initialize(): void
    {
        // Initialize session configuration
        $this->configureSession();
        
        // Register core services
        $this->registerServices();
        
        // Setup error handling
        $this->setupErrorHandling();
    }
    
    private function configureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                  || (($_SERVER['SERVER_PORT'] ?? '') === '443');
                  
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'httponly' => true,
                'secure' => $https,
                'samesite' => 'Lax',
            ]);
            
            session_start();
        }
        
        // Generate CSRF token if not present
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    private function registerServices(): void
    {
        // Register database service (lazy)
        $this->services['db'] = function() {
            return new Database();
        };
        
        // Register logger service  
        $this->services['logger'] = function() {
            return new Logger();
        };
        
        // Register configuration service
        $this->services['config'] = function() {
            return new ConfigManager();
        };
        
        // Register transfer engine service
        $this->services['transfer_engine'] = function() {
            return new \App\Services\TransferEngineService();
        };
    }
    
    public function get(string $service)
    {
        if (!isset($this->services[$service])) {
            throw new \Exception("Service '{$service}' not found");
        }
        
        if (is_callable($this->services[$service])) {
            // Lazy load the service
            $this->services[$service] = $this->services[$service]();
        }
        
        return $this->services[$service];
    }
    
    private function setupErrorHandling(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }
    
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (error_reporting() & $level) {
            $logger = $this->get('logger');
            $logger->error("PHP Error: {$message}", [
                'level' => $level,
                'file' => $file,
                'line' => $line
            ]);
        }
        
        return true;
    }
    
    public function handleException(\Throwable $exception): void
    {
        $logger = $this->get('logger');
        $logger->error("Uncaught Exception: " . $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        if (APP_DEBUG) {
            echo "<pre>";
            echo "Exception: " . get_class($exception) . "\n";
            echo "Message: " . $exception->getMessage() . "\n";
            echo "File: " . $exception->getFile() . " (Line: " . $exception->getLine() . ")\n";
            echo "Trace:\n" . $exception->getTraceAsString();
            echo "</pre>";
        } else {
            http_response_code(500);
            include APP_ROOT . '/resources/views/errors/500.php';
        }
    }
}