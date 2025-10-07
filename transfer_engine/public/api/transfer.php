<?php
/**
 * Transfer API Endpoint
 * Provides REST API for transfer module operations
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Persistence\ReadModels\TransferReadModel;
use Unified\Support\UiKernel;
use Unified\Services\TransferService;
use Unified\Support\Config;
use Unified\Support\Api;
use Unified\Support\Validator;

// Standardized API hardening
Api::initJson();
Api::applyCors('GET, POST, PUT, DELETE, OPTIONS');
Api::handleOptionsPreflight();
Api::enforceCsrf();
// Lightweight GET rate limit (group: transfer)
Api::enforceGetRateLimit('transfer');

// Optional token enforcement for read-only requests (GET) via Authorization: Bearer
$__method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($__method === 'GET') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $_SERVER['HTTP_X_API_TOKEN'] = $m[1];
    }
    Api::enforceOptionalToken('neuro.unified.ui.api_token', ['HTTP_X_API_TOKEN','HTTP_AUTHORIZATION']);
}

$method = $__method;
// Determine action from query, PATH_INFO, or REQUEST_URI for subpath support
$action = $_GET['action'] ?? null;
if ($action === null) {
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if ($pathInfo) {
        $action = ltrim($pathInfo, '/');
    } else {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($uriPath && preg_match('#/api/transfer/?([A-Za-z0-9_-]+)$#', $uriPath, $m)) {
            $action = $m[1];
        }
    }
}
// Default action
$action = $action ?: 'status';
// Map common aliases from JS to internal handlers
$aliasMap = [
    'stats' => 'status',
    'exec' => 'execute',
];
$path = $aliasMap[$action] ?? $action;
$logger = UiKernel::logger();

// Simple RBAC: restrict POST actions to users with engine.execute permission
$auth = function_exists('auth') ? auth() : null;
// Optional CSRF enforcement
$csrfRequired = (bool) (Config::get('neuro.unified.security.csrf_required', false));
if ($method === 'POST' && $auth && !$auth->hasPermission('engine.execute')) {
    Api::error('FORBIDDEN', 'You do not have permission to perform this action.', 403);
}
// Optional POST rate limiting (group: transfer)
Api::enforcePostRateLimit('transfer');

try {
    $readModel = new TransferReadModel($logger);
    $service = new TransferService($logger);
    
    switch ("$method:$path") {
        case 'GET:status':
            $stats = $readModel->sevenDayStats();
            // Align with frontend expectations: top-level "stats" with known keys
            $response = [
                'success' => true,
                'stats' => [
                    'pending' => (int)($stats['pending'] ?? 0),
                    'today' => (int)($stats['today'] ?? 0),
                    'failed' => (int)($stats['failed'] ?? 0),
                    'total' => (int)($stats['total'] ?? 0)
                ]
            ];
            break;
            
        case 'GET:queue':
            $recent = $readModel->recent(20);
            $response = [
                'success' => true,
                'data' => [
                    'items' => $recent,
                    'count' => count($recent),
                    'last_update' => time()
                ]
            ];
            break;
            
        case 'POST:execute':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $data = $service->execute((array)($input['ids'] ?? []), correlationId());
            $response = [ 'success' => true, 'data' => $data ];
            break;
            
        case 'POST:clear':
            $data = $service->clearQueue(correlationId());
            $response = [ 'success' => true, 'data' => $data ];
            break;
            
        case 'GET:calculate':
        case 'POST:calculate':
            $fromOutlet = isset($_GET['from']) ? (int)$_GET['from'] : null;
            $toOutlet = isset($_GET['to']) ? (int)$_GET['to'] : null;
            $productId = isset($_GET['product']) ? (int)$_GET['product'] : null;
            $quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
            // Validate minimal presence and sensible ranges
            if ($fromOutlet === null || $toOutlet === null || $productId === null) {
                Api::error('MISSING_PARAMS', 'from, to, and product are required', 400);
            }
            try {
                Validator::intRange($fromOutlet, 1, 2147483647, 'from');
                Validator::intRange($toOutlet, 1, 2147483647, 'to');
                Validator::intRange($productId, 1, 2147483647, 'product');
                Validator::intRange($quantity, 1, 100000, 'quantity');
            } catch (\InvalidArgumentException $ex) {
                Api::error('INVALID_PARAMS', $ex->getMessage(), 400);
            }
            $params = [ 'from' => $fromOutlet, 'to' => $toOutlet, 'product' => $productId, 'quantity' => $quantity ];
            $response = [ 'success' => true, 'data' => $service->calculate($params, correlationId()) ];
            break;
            
        default:
            Api::error('NOT_FOUND', "Endpoint $method:$path not found", 404);
    }
    
} catch (Exception $e) {
    $logger->error('api.transfer.error', [
        'correlation_id' => correlationId(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    Api::error('INTERNAL_ERROR', 'An internal error occurred', 500);
}
// Preserve existing payload shapes for backward compatibility
Api::respond($response);