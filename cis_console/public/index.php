<?php
declare(strict_types=1);

// Minimal GET router for CIS Console

use CisConsole\App\Http\Kernel;
use CisConsole\App\Support\Response;

spl_autoload_register(function ($class) {
    $prefix = 'CisConsole\\';
    $base_dir = __DIR__ . '/../';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $base_dir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$endpoint = isset($_GET['endpoint']) ? (string)$_GET['endpoint'] : '';

// Load configs
$app = require __DIR__ . '/../config/app.php';
$urls = require __DIR__ . '/../config/urls.php';
$security = require __DIR__ . '/../config/security.php';

// Boot kernel
// Compute absolute base URLs from request
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
$indexUrl = $scheme . '://' . $host . $dir . '/index.php';
$assetsUrl = $scheme . '://' . $host . $dir . '/assets';

$app['urls'] = [
    'index' => $indexUrl,
    'assets' => $assetsUrl,
];

if (isset($_GET['token'])) {
    $tokenRaw = (string)$_GET['token'];
    $token = preg_replace('/[^A-Za-z0-9_:\-.]/', '', $tokenRaw);
    if ($token !== '') {
        setcookie('admin_token', $token, [
            'expires' => time() + 3600 * 12,
            'path' => $dir === '' ? '/' : $dir . '/',
            'secure' => $scheme === 'https',
            'httponly' => false,
            'samesite' => 'Strict',
        ]);
        $_COOKIE['admin_token'] = $token;
        if ($endpoint === '') {
            $endpoint = 'admin/dashboard';
        }
    }
}

$kernel = new Kernel($urls['routes'], $security, $app);

try {
    if ($endpoint === '') {
        Response::json(['success' => false, 'error' => ['code' => 'missing_endpoint', 'message' => 'endpoint parameter required']], 400);
        exit;
    }
    $kernel->handle($endpoint);
} catch (Throwable $e) {
    error_log('[cis_console] unhandled exception: ' . $e->getMessage());
    Response::json([
        'success' => false,
        'error' => [
            'code' => 'internal_error',
            'message' => 'An internal error occurred',
        ],
    ], 500);
}
