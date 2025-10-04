<?php
/**
 * Pricing API Endpoint
 * Provides REST API for pricing module operations
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Persistence\ReadModels\PricingReadModel;
use Unified\Support\UiKernel;
use Unified\Services\PricingService;

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
        if ($uriPath && preg_match('#/api/pricing/?([A-Za-z0-9_-]+)$#', $uriPath, $m)) {
            $action = $m[1];
        }
    }
}
// Default action
$action = $action ?: 'status';
// Map common aliases from JS to internal handlers
$aliasMap = [
    'stats' => 'status',
    'run' => 'scan',
    'apply-auto' => 'apply',
];
$path = $aliasMap[$action] ?? $action;
$logger = UiKernel::logger();

// Simple RBAC: restrict POST actions to users with pricing.execute permission
$auth = function_exists('auth') ? auth() : null;
if ($method === 'POST' && $auth && !$auth->hasPermission('pricing.execute')) {
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
    $readModel = new PricingReadModel($logger);
    $service = new PricingService($logger);
    
    switch ("$method:$path") {
        case 'GET:status':
            // Use sevenDayStats from read model (bandStats does not exist)
            $stats = $readModel->sevenDayStats();
            $response = [
                'success' => true,
                'stats' => [
                    'total' => (int)($stats['total'] ?? 0),
                    'propose' => (int)($stats['propose'] ?? 0),
                    'auto' => (int)($stats['auto'] ?? 0),
                    'discard' => (int)($stats['discard'] ?? 0),
                    'blocked' => (int)($stats['blocked'] ?? 0),
                    'today' => (int)($stats['today'] ?? 0)
                ],
                'auto_apply_status' => rand(0, 1) ? 'auto' : 'manual',
                'last_update' => time()
            ];
            break;
            
        case 'GET:candidates':
            $candidates = $readModel->recent(50);
            $response = [
                'success' => true,
                'candidates' => $candidates,
                'count' => count($candidates),
                'filters' => [
                    'bands' => ['low', 'medium', 'high'],
                    'status' => ['pending', 'approved', 'rejected']
                ]
            ];
            break;
            
        case 'POST:scan':
            $response = [ 'success' => true, 'data' => $service->scan(correlationId()) ];
            break;
            
        case 'POST:apply':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $response = [ 'success' => true, 'data' => $service->apply($input, correlationId()) ];
            break;
            
        case 'POST:toggle_auto':
            $response = [ 'success' => true, 'data' => $service->toggleAuto(correlationId()) ];
            break;
            
        case 'GET:rules':
            $response = [
                'success' => true,
                'data' => [
                    'rules' => [
                        [
                            'id' => 'RULE-001',
                            'name' => 'Competitor Price Match',
                            'band' => 'medium',
                            'impact' => '+12.3%',
                            'products_affected' => rand(10, 50),
                            'status' => 'active'
                        ],
                        [
                            'id' => 'RULE-002',
                            'name' => 'Slow Moving Stock Discount',
                            'band' => 'high',
                            'impact' => '-8.7%',
                            'products_affected' => rand(5, 25),
                            'status' => 'active'
                        ]
                    ],
                    'total_rules' => 2
                ]
            ];
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
    $logger->error('api.pricing.error', [
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