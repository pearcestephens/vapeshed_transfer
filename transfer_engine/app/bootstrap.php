<?php
/**
 * Application Bootstrap (Transitional UI Shell)
 *
 * Phase B Consolidation:
 *  - Delegates configuration, logging, DB access to unified support layer under src/Support
 *  - Retains legacy Container & helper function signatures for backward compatibility
 *  - Marks embedded Config & Service duplication as DEPRECATED (removed now)
 *  - Provides correlation id for request-scoped logging
 *
 * DO NOT add domain logic here. All domain operations must reside in src/ namespaces.
 * This file will be superseded by UiKernel once full dashboard consolidation (M15) lands.
 *
 * @package VapeshedTransfer
 * @version 2.0.0
 */
declare(strict_types=1);

// ============================================
// ERROR HANDLING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Register global error handler after basic settings
// Will be fully initialized after autoloader and logger setup

// ============================================
// PATH CONSTANTS
// ============================================
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('SRC_PATH', ROOT_PATH . '/src');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('VIEWS_PATH', PUBLIC_PATH . '/views');
define('MODULES_PATH', PUBLIC_PATH . '/modules');

// ============================================
// AUTOLOADER
// ============================================
spl_autoload_register(function ($class) {
    $relative = str_replace('\\', '/', $class) . '.php';
    $candidates = [
        APP_PATH . '/' . $relative,     // legacy app path
        SRC_PATH . '/' . $relative      // unified src path (namespaced directory)
    ];
    // Fallback: allow classes under namespace 'Unified\\' to resolve to src/ without the 'Unified/' prefix
    if (str_starts_with($relative, 'Unified/')) {
        $alt = substr($relative, strlen('Unified/'));
        $candidates[] = SRC_PATH . '/' . $alt; // e.g., Unified/Support/Config.php -> src/Support/Config.php
    }
    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});

// ============================================
// UNIFIED CONFIG (Delegated to src/Support/Config)
// ============================================
use Unified\Support\Config as UnifiedConfig;
use Unified\Support\Logger as UnifiedLogger;
use Unified\Support\Pdo as UnifiedPdo;
use Unified\Support\ErrorHandler;

UnifiedConfig::prime(); // prime unified key cache (neuro.unified.*)

// Load legacy UI module metadata (kept separate to avoid polluting unified namespace)
$__uiModules = [];
$__uiModulesPath = CONFIG_PATH . '/modules.php';
if (file_exists($__uiModulesPath)) {
    $__uiModules = require $__uiModulesPath; // array keyed by module slug
}
// Load app-level meta (title, version) for UI only
$__uiApp = [];
$__uiAppPath = CONFIG_PATH . '/app.php';
if (file_exists($__uiAppPath)) { $__uiApp = require $__uiAppPath; }
// DB credentials (still required for backward compatibility helpers if used)
$__uiDb = [];
$__uiDbPath = CONFIG_PATH . '/database.php';
if (file_exists($__uiDbPath)) { $__uiDb = require $__uiDbPath; }

// Provide a unified logger instance for UI channel
$__uiLogger = new UnifiedLogger('ui');
UnifiedConfig::setLogger(new UnifiedLogger('config'));

// Register global error handler with debug mode from config
$debug = UnifiedConfig::get('neuro.unified.environment', 'production') === 'development';
ErrorHandler::register(new UnifiedLogger('errors'), $debug);

// ============================================
// SESSION MANAGEMENT
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => ($_SERVER['HTTPS'] ?? 'off') === 'on',
        'use_strict_mode' => true,
        'cookie_lifetime' => 7200
    ]);
}
// Generate CSRF token (for optional enforcement in APIs)
if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(16)); }

// ============================================
// SHARED SERVICES REGISTRY
// ============================================
class Container
{
    private static array $services = [];
    public static function register(string $name, callable $factory): void { self::$services[$name] = $factory; }
    public static function get(string $name) {
        if (!isset(self::$services[$name])) { throw new Exception("Service '{$name}' not registered"); }
        return (self::$services[$name])();
    }
    public static function has(string $name): bool { return isset(self::$services[$name]); }
}

// ============================================
// DATABASE SERVICE
// ============================================
Container::register('db', function() {
    // Delegate to unified PDO singleton
    return UnifiedPdo::instance();
});

// ============================================
// AUTHENTICATION SERVICE
// ============================================
Container::register('auth', function() use ($__uiLogger) {
    // Placeholder auth service; future: integrate CIS session / RBAC adapter
    return new class($__uiLogger) {
        public function __construct(private $logger) {}
        public function check(): bool { return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); }
        public function user(): array { return $_SESSION['user'] ?? ['id'=>0,'name'=>'Unknown','permissions'=>[]]; }
        public function hasPermission(string $perm): bool { $u=$this->user(); if(($u['id']??0)===0) return false; return in_array('*',$u['permissions']??[])||in_array($perm,$u['permissions']??[]); }
        public function requireAuth(): void { if(!$this->check()){ http_response_code(302); header('Location: /login.php'); exit; } }
        public function requirePermission(string $p): void { $this->requireAuth(); if(!$this->hasPermission($p)){ http_response_code(403); die('Access Denied'); } }
    };
});

// ============================================
// VIEW RENDERER SERVICE
// ============================================
Container::register('view', function() use ($__uiLogger) {
    return new class($__uiLogger) {
        private array $data = [];
        private string $layout = 'base';
        public function __construct(private $logger) {}
        public function setLayout(string $layout): self { $this->layout = $layout; return $this; }
        public function with(string|array $key, $value = null): self { if (is_array($key)) $this->data = array_merge($this->data,$key); else $this->data[$key]=$value; return $this; }
        public function render(string $view): string {
            $this->data['__correlation_id'] = correlationId();
            extract($this->data);
            $viewFile = VIEWS_PATH . '/' . $view . '.php';
            if (!file_exists($viewFile)) { throw new Exception("View not found: {$view}"); }
            ob_start(); include $viewFile; $content = ob_get_clean();
            if ($this->layout) {
                $layoutFile = VIEWS_PATH . '/layouts/' . $this->layout . '.php';
                if (file_exists($layoutFile)) { ob_start(); include $layoutFile; $html = ob_get_clean(); $this->logger->info('ui.render', ['view'=>$view,'layout'=>$this->layout,'cid'=>correlationId()]); return $html; }
            }
            $this->logger->info('ui.render.raw', ['view'=>$view,'cid'=>correlationId()]);
            return $content;
        }
        public function json(array $data, int $code = 200): void { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data, JSON_PRETTY_PRINT); exit; }
    };
});

// ============================================
// HELPER FUNCTIONS (Globally Available)
// ============================================

/**
 * Get service from container
 */
function app(string $service = null) {
    if ($service === null) {
        return Container::class;
    }
    return Container::get($service);
}

/**
 * Get configuration value
 */
function config(string $key, $default = null) {
    // First check unified config keys
    $val = UnifiedConfig::get($key, null);
    if ($val !== null) return $val;
    // Fall back to UI metadata bundles
    global $__uiModules, $__uiApp, $__uiDb;
    if ($key === 'modules') {
        return $__uiModules ?: $default;
    }
    if (str_starts_with($key, 'modules.')) {
        $path = explode('.', $key); // modules, slug, rest...
        $slug = $path[1] ?? null; $sub = $path[2] ?? null;
        if ($slug && isset($__uiModules[$slug])) {
            if ($sub === null) return $__uiModules[$slug];
            return $__uiModules[$slug][$sub] ?? $default;
        }
    }
    if ($key === 'app.name') return $__uiApp['name'] ?? $default;
    if ($key === 'app.version') return $__uiApp['version'] ?? $default;
    if ($key === 'database.host') return $__uiDb['host'] ?? $default;
    return $default;
}

/**
 * Get database connection
 */
function db(): PDO { return Container::get('db'); }

/**
 * Get authentication service
 */
function auth() { return Container::get('auth'); }

/**
 * Render view
 */
function view(string $view, array $data = []) { return Container::get('view')->with($data)->render($view); }

/**
 * Return JSON response
 */
function json(array $data, int $code = 200): void { Container::get('view')->json($data, $code); }

/**
 * Format number
 */
function formatNumber($number, int $decimals = 0): string {
    return number_format((float)$number, $decimals, '.', ',');
}

/**
 * Format currency
 */
function formatCurrency($amount, string $currency = '$'): string {
    return $currency . number_format((float)$amount, 2, '.', ',');
}

/**
 * Format percentage
 */
function formatPercent($value, int $decimals = 1): string {
    return number_format((float)$value * 100, $decimals) . '%';
}

/**
 * Format date
 */
function formatDate($date, string $format = 'Y-m-d'): string {
    if (empty($date)) return '-';
    return date($format, is_numeric($date) ? $date : strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime, string $format = 'Y-m-d H:i:s'): string {
    if (empty($datetime)) return '-';
    return date($format, is_numeric($datetime) ? $datetime : strtotime($datetime));
}


/**
 * Escape HTML
 */
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

/**
 * Get asset URL
 */
function asset(string $path): string { return '/assets/' . ltrim($path, '/'); }

/**
 * Generate URL
 */
function url(string $path = ''): string { $base = rtrim(config('app.url', ''), '/'); return $base . '/' . ltrim($path, '/'); }

/**
 * Redirect
 */
function redirect(string $url): void { header('Location: ' . $url); exit; }

/**
 * Get old input value (for form repopulation)
 */
function old(string $key, $default = '') {
    return $_SESSION['_old'][$key] ?? $default;
}

/**
 * Flash message to session
 */
function flash(string $key, $value = null) {
    if ($value === null) {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }
    $_SESSION['_flash'][$key] = $value;
}

// ============================================
// CORRELATION ID & LOGGER ACCESSORS
// ============================================
function correlationId(): string {
    static $cid = null; if ($cid === null) { $cid = bin2hex(random_bytes(8)); }
    return $cid;
}
function logger(): UnifiedLogger { global $__uiLogger; return $__uiLogger; }

// Include view helpers
if (file_exists(PUBLIC_PATH . '/views/helpers/stats.php')) {
    require_once PUBLIC_PATH . '/views/helpers/stats.php';
}

// Log module request entry (if in module context)
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/modules/') !== false) {
    $module = basename(dirname($_SERVER['REQUEST_URI']));
    logger()->info('ui.module.entry', [
        'module' => $module,
        'cid' => correlationId(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Mark application as loaded
define('APP_LOADED', true);
logger()->info('ui.bootstrap.ready',[ 'cid'=>correlationId() ]);
