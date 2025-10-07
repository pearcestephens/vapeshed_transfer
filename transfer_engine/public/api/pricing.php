<?php
/**
 * Pricing API Endpoint
 * Provides REST API for pricing module operations
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Persistence\ReadModels\PricingReadModel;
use Unified\Support\UiKernel;
use Unified\Services\PricingService;
use Unified\Support\Config;
use Unified\Support\Api;
use Unified\Support\Validator;

// Standardized API hardening
Api::initJson();
Api::applyCors('GET, POST, PUT, DELETE, OPTIONS');
Api::handleOptionsPreflight();
Api::enforceCsrf();
// Use group-aware rate limiting for pricing API
Api::enforceGetRateLimit('pricing');

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
// Optional CSRF enforcement
$csrfRequired = (bool) (Config::get('neuro.unified.security.csrf_required', false));
if ($method === 'POST' && $auth && !$auth->hasPermission('pricing.execute')) {
    Api::error('FORBIDDEN', 'You do not have permission to perform this action.', 403);
}
// Optional POST rate limiting
// Grouped POST rate limit for pricing writes
Api::enforcePostRateLimit('pricing');

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
            // Optional limit filter with bounds
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            try { Validator::intRange($limit, 1, 200, 'limit'); } catch (\InvalidArgumentException $ex) { Api::error('INVALID_LIMIT', 'Limit must be between 1 and 200', 400); }
            $candidates = $readModel->recent($limit);
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
            // no body required, but accept options object in future
            $response = [ 'success' => true, 'data' => $service->scan(correlationId()) ];
            break;
            
        case 'POST:apply':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            if (!is_array($input)) { Api::error('INVALID_JSON', 'Body must be JSON object', 400); }
            // Either apply_all=true or proposal_ids array may be present
            if (!empty($input['proposal_ids']) && !is_array($input['proposal_ids'])) { Api::error('INVALID_PROPOSAL_IDS', 'proposal_ids must be an array', 400); }
            $response = [ 'success' => true, 'data' => $service->apply($input, correlationId()) ];
            break;
            
        case 'POST:toggle_auto':
            // no body required
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
            Api::error('NOT_FOUND', "Endpoint $method:$path not found", 404);
    }
    
} catch (Exception $e) {
    $logger->error('api.pricing.error', [
        'correlation_id' => correlationId(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Api::error('INTERNAL_ERROR', 'An internal error occurred', 500);
}
// Preserve existing payload shapes for backward compatibility
Api::respond($response);