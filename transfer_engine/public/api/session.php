<?php
declare(strict_types=1);
/**
 * Session API Endpoint
 * Returns session-scoped CSRF token and correlation ID for client usage.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Support\Api;

Api::initJson();
Api::applyCors('GET, OPTIONS');
Api::handleOptionsPreflight();
Api::enforceGetRateLimit('session');

$token = $_SESSION['_csrf'] ?? '';
$cid = correlationId();

\Unified\Support\Api::respond([
    'success' => true,
    'data' => [
        'csrf_token' => $token,
        'correlation_id' => $cid,
        'ts' => time()
    ]
]);
