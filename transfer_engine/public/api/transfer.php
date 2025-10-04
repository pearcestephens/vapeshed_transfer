<?php
/**
 * Transfer API Endpoint
 * Provides REST API for transfer module operations
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Persistence\ReadModels\TransferReadModel;
use Unified\Support\UiKernel;
use Unified\Services\TransferService;

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Correlation-ID');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
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
if ($method === 'POST' && $auth && !$auth->hasPermission('engine.execute')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'FORBIDDEN',
            'message' => 'You do not have permission to perform this action.'
        ]
    ]);
    exit;
}

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
            $fromOutlet = $_GET['from'] ?? null;
            $toOutlet = $_GET['to'] ?? null;
            $productId = $_GET['product'] ?? null;
            $quantity = intval($_GET['quantity'] ?? 1);
            
            $params = [
                'from' => $_GET['from'] ?? null,
                'to' => $_GET['to'] ?? null,
                'product' => $_GET['product'] ?? null,
                'quantity' => (int)($_GET['quantity'] ?? 1)
            ];
            $response = [ 'success' => true, 'data' => $service->calculate($params, correlationId()) ];
            break;
            
        default:
            http_response_code(404);
            $response = [
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => "Endpoint $method:$path not found"
                ]
            ];
    }
    
} catch (Exception $e) {
    $logger->error('api.transfer.error', [
        'correlation_id' => correlationId(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'An internal error occurred'
        ]
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);