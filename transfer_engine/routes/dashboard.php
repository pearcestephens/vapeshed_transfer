<?php
/**
 * Executive Dashboard API Routes
 * Advanced API endpoints for real-time business intelligence
 * 
 * @package VapeshedTransfer
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\ExecutiveDashboardController;
use App\Controllers\Api\AutonomousController;
use App\Controllers\Api\CrawlerController;
use App\Controllers\Api\SalesIntelligenceController;
use App\Controllers\Api\ConfigController;
use App\Controllers\Api\ReportsController;

// Initialize router
$router = Router::getInstance();

// Executive Dashboard Controllers
$dashboardController = new ExecutiveDashboardController();
$autonomousController = new AutonomousController();
$crawlerController = new CrawlerController();
$salesController = new SalesIntelligenceController();
$configController = new ConfigController();
$reportsController = new ReportsController();

/**
 * =============================================================================
 * EXECUTIVE DASHBOARD ROUTES
 * =============================================================================
 */

// Main dashboard page
$router->get('/dashboard', [$dashboardController, 'index']);
$router->get('/dashboard/executive', [$dashboardController, 'index']);

// Dashboard modules
$router->get('/dashboard/competitive', [$dashboardController, 'competitiveIntelligence']);
$router->get('/dashboard/sales', [$dashboardController, 'salesAnalytics']);
$router->get('/dashboard/transfers', [$dashboardController, 'transferEngine']);
$router->get('/dashboard/configuration', [$dashboardController, 'configuration']);

/**
 * =============================================================================
 * REAL-TIME DATA API ROUTES
 * =============================================================================
 */

// Real-time dashboard data
$router->get('/api/dashboard/realtime', [$dashboardController, 'getRealTimeData']);
$router->get('/api/dashboard/metrics', [$dashboardController, 'getMetrics']);
$router->get('/api/dashboard/status', [$dashboardController, 'getSystemStatus']);
$router->get('/api/dashboard/alerts', [$dashboardController, 'getAlerts']);

// Server-Sent Events stream
$router->get('/api/stream', [$dashboardController, 'streamEvents']);

/**
 * =============================================================================
 * AUTONOMOUS PROFIT ENGINE API
 * =============================================================================
 */

// Start/stop autonomous optimization
$router->post('/api/autonomous/start', [$autonomousController, 'start']);
$router->post('/api/autonomous/stop', [$autonomousController, 'stop']);
$router->post('/api/autonomous/emergency-stop', [$autonomousController, 'emergencyStop']);

// Autonomous engine status and monitoring
$router->get('/api/autonomous/status', [$autonomousController, 'getStatus']);
$router->get('/api/autonomous/status/{runId}', [$autonomousController, 'getRunStatus']);
$router->get('/api/autonomous/runs', [$autonomousController, 'getRuns']);
$router->get('/api/autonomous/metrics', [$autonomousController, 'getMetrics']);

// Autonomous engine configuration
$router->get('/api/autonomous/config', [$autonomousController, 'getConfig']);
$router->post('/api/autonomous/config', [$autonomousController, 'updateConfig']);

/**
 * =============================================================================
 * COMPETITOR CRAWLER API
 * =============================================================================
 */

// Crawler operations
$router->post('/api/crawler/run', [$crawlerController, 'run']);
$router->post('/api/crawler/stop', [$crawlerController, 'stop']);
$router->get('/api/crawler/status', [$crawlerController, 'getStatus']);

// Competitive intelligence
$router->get('/api/competitive/intelligence', [$crawlerController, 'getIntelligence']);
$router->get('/api/competitive/opportunities', [$crawlerController, 'getOpportunities']);
$router->get('/api/competitive/threats', [$crawlerController, 'getThreats']);
$router->get('/api/competitive/products', [$crawlerController, 'getProducts']);
$router->get('/api/competitive/pricing', [$crawlerController, 'getPricing']);

// Crawler configuration
$router->get('/api/crawler/config', [$crawlerController, 'getConfig']);
$router->post('/api/crawler/config', [$crawlerController, 'updateConfig']);
$router->get('/api/crawler/targets', [$crawlerController, 'getTargets']);
$router->post('/api/crawler/targets', [$crawlerController, 'updateTargets']);

/**
 * =============================================================================
 * SALES INTELLIGENCE API
 * =============================================================================
 */

// Sales analytics
$router->get('/api/sales/overview', [$salesController, 'getOverview']);
$router->get('/api/sales/trends', [$salesController, 'getTrends']);
$router->get('/api/sales/forecasts', [$salesController, 'getForecasts']);
$router->get('/api/sales/performance', [$salesController, 'getPerformance']);

// Store analytics
$router->get('/api/sales/stores', [$salesController, 'getStoreMetrics']);
$router->get('/api/sales/stores/{storeId}', [$salesController, 'getStoreDetails']);
$router->get('/api/sales/stores/{storeId}/trends', [$salesController, 'getStoreTrends']);

// Product analytics
$router->get('/api/sales/products', [$salesController, 'getProductMetrics']);
$router->get('/api/sales/products/top', [$salesController, 'getTopProducts']);
$router->get('/api/sales/products/slow', [$salesController, 'getSlowProducts']);
$router->get('/api/sales/products/{productId}', [$salesController, 'getProductDetails']);

/**
 * =============================================================================
 * SYSTEM CONFIGURATION API
 * =============================================================================
 */

// Dashboard configuration
$router->get('/api/config/dashboard', [$configController, 'getDashboardConfig']);
$router->post('/api/config/dashboard', [$configController, 'updateDashboardConfig']);

// System settings
$router->get('/api/config/system', [$configController, 'getSystemConfig']);
$router->post('/api/config/system', [$configController, 'updateSystemConfig']);

// Engine settings
$router->get('/api/config/engines', [$configController, 'getEngineConfig']);
$router->post('/api/config/engines', [$configController, 'updateEngineConfig']);

// User preferences
$router->get('/api/config/user', [$configController, 'getUserConfig']);
$router->post('/api/config/user', [$configController, 'updateUserConfig']);

/**
 * =============================================================================
 * REPORTING API
 * =============================================================================
 */

// Executive reports
$router->get('/api/reports/executive', [$reportsController, 'generateExecutiveReport']);
$router->post('/api/reports/executive', [$reportsController, 'generateCustomReport']);

// Performance reports
$router->get('/api/reports/performance', [$reportsController, 'getPerformanceReport']);
$router->get('/api/reports/optimization', [$reportsController, 'getOptimizationReport']);
$router->get('/api/reports/competitive', [$reportsController, 'getCompetitiveReport']);

// Historical data
$router->get('/api/reports/history/transfers', [$reportsController, 'getTransferHistory']);
$router->get('/api/reports/history/pricing', [$reportsController, 'getPricingHistory']);
$router->get('/api/reports/history/sales', [$reportsController, 'getSalesHistory']);

// Export formats
$router->get('/api/reports/export/csv', [$reportsController, 'exportCSV']);
$router->get('/api/reports/export/pdf', [$reportsController, 'exportPDF']);
$router->get('/api/reports/export/excel', [$reportsController, 'exportExcel']);

/**
 * =============================================================================
 * HEALTH & MONITORING API
 * =============================================================================
 */

// System health
$router->get('/api/health', [$dashboardController, 'health']);
$router->get('/api/health/detailed', [$dashboardController, 'detailedHealth']);

// Performance monitoring
$router->get('/api/performance/metrics', [$dashboardController, 'getPerformanceMetrics']);
$router->get('/api/performance/slow-queries', [$dashboardController, 'getSlowQueries']);
$router->get('/api/performance/cache-stats', [$dashboardController, 'getCacheStats']);

// Error monitoring
$router->get('/api/errors/recent', [$dashboardController, 'getRecentErrors']);
$router->get('/api/errors/summary', [$dashboardController, 'getErrorSummary']);

// Staff + Customer Intelligence (New)
use App\Controllers\Api\DashboardController as ApiDashboardController;
$apiDash = new ApiDashboardController();
$router->get('/api/dashboard/neuro-state', [$apiDash, 'neuroState']);
$router->get('/api/dashboard/assistant/insights', [$apiDash, 'assistantInsights']);
$router->get('/api/dashboard/behavior/heatmap', [$apiDash, 'behaviorHeatmap']);
$router->get('/api/dashboard/agent/activity', [$apiDash, 'agentActivity']);
$router->get('/api/dashboard/customer/segments', [$apiDash, 'customerSegments']);
$router->get('/api/dashboard/transfer/suggestions', [$apiDash, 'transferSuggestions']);
$router->get('/api/dashboard/crawler/summary', [$apiDash, 'crawlerSummary']);
$router->get('/api/dashboard/kpis', [$apiDash, 'kpis']);

// Assistant conversation & insights
use App\Controllers\Api\AssistantController;
$assistantController = new AssistantController();
$router->post('/api/assistant/message', [$assistantController, 'postMessage']);
$router->post('/api/assistant/insight', [$assistantController, 'postInsight']);
$router->get('/api/assistant/conversations', [$assistantController, 'listConversations']);
$router->get('/api/assistant/messages', [$assistantController, 'listMessages']);
$router->post('/api/assistant/insight/feedback', [$assistantController, 'postInsightFeedback']);

/**
 * =============================================================================
 * BROWSE MODE PROTECTION
 * =============================================================================
 */

// Apply browse mode protection to all routes
$router->group('/dashboard', function() use ($router) {
    $router->middleware('browse_mode_check');
});

$router->group('/api', function() use ($router) {
    $router->middleware('api_auth');
    $router->middleware('rate_limit');
    $router->middleware('csrf_protection');
});

/**
 * =============================================================================
 * EMERGENCY ROUTES (NO AUTH REQUIRED)
 * =============================================================================
 */

// Emergency system control (no auth for critical situations)
$router->post('/emergency/stop-all', function() {
    try {
        // Stop all autonomous operations immediately
        $autonomousController = new AutonomousController();
        $crawlerController = new CrawlerController();
        
        $autonomousResult = $autonomousController->emergencyStop();
        $crawlerResult = $crawlerController->stop();
        
        return [
            'success' => true,
            'message' => 'All systems stopped',
            'autonomous' => $autonomousResult,
            'crawler' => $crawlerResult,
            'timestamp' => date('c')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ];
    }
});

// System status (no auth for monitoring)
$router->get('/status', function() {
    return [
        'status' => 'operational',
        'timestamp' => date('c'),
        'version' => '2.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'production'
    ];
});

/**
 * =============================================================================
 * WEBSOCKET ROUTES (Future Enhancement)
 * =============================================================================
 */

// WebSocket endpoint for real-time updates
$router->get('/ws/dashboard', function() {
    // WebSocket handler would be implemented here
    header('HTTP/1.1 426 Upgrade Required');
    header('Upgrade: websocket');
    header('Connection: Upgrade');
    return ['message' => 'WebSocket upgrade required'];
});

/**
 * =============================================================================
 * ERROR HANDLING
 * =============================================================================
 */

// 404 handler for API routes
$router->fallback('/api/*', function() {
    http_response_code(404);
    return [
        'success' => false,
        'error' => 'API endpoint not found',
        'code' => 'ENDPOINT_NOT_FOUND',
        'timestamp' => date('c')
    ];
});

// 404 handler for dashboard routes
$router->fallback('/dashboard/*', function() {
    http_response_code(404);
    include __DIR__ . '/../resources/views/errors/404.php';
});

/**
 * =============================================================================
 * MIDDLEWARE DEFINITIONS
 * =============================================================================
 */

// Browse mode check middleware
$router->defineMiddleware('browse_mode_check', function($request, $next) {
    // Check if browse mode is enabled
    if (isset($_ENV['BROWSE_MODE']) && $_ENV['BROWSE_MODE'] === 'true') {
        // Redirect to read-only view
        if (strpos($request['path'], '/api/') === 0) {
            return [
                'success' => false,
                'error' => 'System is in browse mode - modifications disabled',
                'code' => 'BROWSE_MODE_ACTIVE'
            ];
        }
    }
    return $next($request);
});

// API authentication middleware
$router->defineMiddleware('api_auth', function($request, $next) {
    // Implement API key or session authentication
    if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
        return [
            'success' => false,
            'error' => 'Authentication required',
            'code' => 'AUTH_REQUIRED'
        ];
    }
    return $next($request);
});

// Rate limiting middleware
$router->defineMiddleware('rate_limit', function($request, $next) {
    // Implement rate limiting logic
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = "rate_limit:{$clientIp}";
    
    // Simple in-memory rate limiting (should use Redis in production)
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    $window = 60; // 1 minute window
    $maxRequests = 100; // 100 requests per minute
    
    if (!isset($_SESSION['rate_limits'][$clientIp])) {
        $_SESSION['rate_limits'][$clientIp] = ['count' => 0, 'window_start' => $now];
    }
    
    $rateData = $_SESSION['rate_limits'][$clientIp];
    
    // Reset window if expired
    if ($now - $rateData['window_start'] > $window) {
        $rateData = ['count' => 0, 'window_start' => $now];
    }
    
    $rateData['count']++;
    $_SESSION['rate_limits'][$clientIp] = $rateData;
    
    if ($rateData['count'] > $maxRequests) {
        http_response_code(429);
        return [
            'success' => false,
            'error' => 'Rate limit exceeded',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $window - ($now - $rateData['window_start'])
        ];
    }
    
    return $next($request);
});

// CSRF protection middleware
$router->defineMiddleware('csrf_protection', function($request, $next) {
    if (in_array($request['method'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        $token = $request['headers']['X-CSRF-Token'] ?? $_POST['_token'] ?? null;
        
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            return [
                'success' => false,
                'error' => 'CSRF token mismatch',
                'code' => 'CSRF_TOKEN_INVALID'
            ];
        }
    }
    
    return $next($request);
});

// Log all API requests
$router->middleware(function($request, $next) {
    $startTime = microtime(true);
    $result = $next($request);
    $duration = microtime(true) - $startTime;
    
    // Log request
    error_log(sprintf(
        'API Request: %s %s - %s - %.3fs',
        $request['method'],
        $request['path'],
        $result['success'] ?? false ? 'SUCCESS' : 'FAILED',
        $duration
    ));
    
    return $result;
});

/**
 * =============================================================================
 * ROUTE COMPILATION
 * =============================================================================
 */

// Compile all routes for performance
$router->compile();

echo "✅ Executive Dashboard API routes loaded successfully\n";
echo "📊 Dashboard available at: /dashboard\n";
echo "🔌 API endpoints: " . count($router->getRoutes()) . " routes registered\n";
echo "🛡️ Security: Browse mode protection, rate limiting, CSRF protection enabled\n";