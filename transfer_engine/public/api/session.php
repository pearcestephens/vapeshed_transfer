<?php
declare(strict_types=1);
/**
 * Session API Endpoint
 * Returns session-scoped CSRF token, correlation ID, and session info for client usage.
 * 
 * @version 2.0.0
 * @date 2025-10-07
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Support\Api;
use Unified\Support\Config;

Api::initJson();
Api::applySecurityHeaders();
Api::applyCors('GET, OPTIONS');
Api::handleOptionsPreflight();
Api::requireMethod('GET');
Api::enforceGetRateLimit('session');

// Generate CSRF token if not exists
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['_csrf'] ?? '';
$cid = correlationId();

// Session information (non-sensitive)
$sessionData = [
    'csrf_token' => $token,
    'correlation_id' => $cid,
    'session_id' => session_id(),
    'session_lifetime' => (int) ini_get('session.gc_maxlifetime'),
    'session_started' => $_SESSION['_session_started'] ?? time(),
    'ts' => time(),
];

// Store session start time if not set
if (!isset($_SESSION['_session_started'])) {
    $_SESSION['_session_started'] = time();
}

// Optional: Include environment info in development
$env = Config::get('neuro.unified.environment', 'production');
if ($env === 'development') {
    $sessionData['environment'] = $env;
    $sessionData['debug'] = true;
}

\Unified\Support\Api::ok($sessionData);

