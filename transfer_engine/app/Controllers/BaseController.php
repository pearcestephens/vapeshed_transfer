<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Security;

/**
 * Base Controller
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Base controller with common functionality
 */
abstract class BaseController
{
    protected Logger $logger;
    
    public function __construct()
    {
        $this->logger = new Logger();
    }
    
    /**
     * Render a view template
     */
    protected function render(string $view, array $data = []): void
    {
        // Extract data to local variables
        extract($data);
        
        // Add global view data
        $csrf_token = $csrf_token ?? \App\Core\Security::generateCSRFToken();
        // Provide global kill switch state to all views by default
        $kill_switch_active = $kill_switch_active ?? \App\Core\Security::isKillSwitchActive();
        $app_name = APP_NAME;
        $csp_nonce = $_SESSION['csp_nonce'] ?? '';
        
        // Load layout wrapper
        $viewPath = APP_ROOT . '/resources/views/' . $view . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \Exception("View not found: {$view}");
        }
        
        // Start output buffering for content
        ob_start();
        include $viewPath;
        $content = ob_get_clean();
        
        // Load layout
        include APP_ROOT . '/resources/views/layouts/app.php';
    }
    
    /**
     * Return JSON response
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Redirect to URL
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Get request input with optional default
     */
    protected function input(string $key, $default = null)
    {
        return $_REQUEST[$key] ?? $default;
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired(array $fields): array
    {
        $errors = [];
        
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        if (!empty($errors)) {
            throw new \Exception(implode(', ', $errors));
        }
        
        return $_POST;
    }
    
    /**
     * Generate URL with base path
     */
    protected function url(string $path = ''): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH . '/public' : '';
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Generate asset URL
     */
    protected function asset(string $path): string
    {
        return $this->url('assets/' . ltrim($path, '/'));
    }

    // ---------------- API Helper Methods (added for consistency across controllers) ----------------
    protected function successResponse(array $data, string $message = 'OK'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'timestamp' => date('c'),
                'request_id' => $this->generateRequestId()
            ]
        ];
    }

    protected function errorResponse(string $message, int $code = 400, array $extra = []): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'meta' => [
                'timestamp' => date('c'),
                'request_id' => $this->generateRequestId()
            ],
            'context' => $extra
        ];
    }

    protected function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function validateCsrfToken(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') { return; }
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!$token || !\App\Core\Security::validateCSRFToken($token)) {
            throw new \Exception('Invalid CSRF token');
        }
    }

    protected function validateBrowseMode(string $reason): void
    {
        if (defined('BROWSE_MODE') && BROWSE_MODE === true) {
            throw new \Exception('Action blocked in browse mode: ' . $reason);
        }
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Require administrative access for sensitive dashboards / actions.
     * Allows either:
     *  - Session flag $_SESSION['is_admin'] true
     *  - Access token provided via ?access_token= / POST access_token / X-Access-Token header
     *    matching env DASHBOARD_ACCESS_TOKEN
     */
    protected function requireAdmin(string $context = 'admin'): void
    {
        $sessionOk = !empty($_SESSION['is_admin']);
        $provided = $_GET['access_token'] ?? $_POST['access_token'] ?? ($_SERVER['HTTP_X_ACCESS_TOKEN'] ?? '');
        $envToken = $_ENV['DASHBOARD_ACCESS_TOKEN'] ?? getenv('DASHBOARD_ACCESS_TOKEN');
        $hasEnv = !empty($envToken);
        $tokenOk = $hasEnv ? ($provided && hash_equals($envToken, (string)$provided)) : false;
        if (!($sessionOk || $tokenOk)) {
            $this->denyAdmin($context);
        }
    }

    private function denyAdmin(string $context): void
    {
        http_response_code(403);
        $isJson = str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [ 'code' => 403, 'message' => 'Forbidden: admin required', 'context' => $context ],
                'meta' => [ 'timestamp' => date('c'), 'request_id' => $this->generateRequestId() ]
            ]);
        } else {
            echo '<div style="font-family:monospace;padding:2rem">403 – Admin access required</div>';
        }
        exit;
    }
}