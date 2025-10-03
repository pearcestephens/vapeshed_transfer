<?php
declare(strict_types=1);

/**
 * Vapeshed Transfer Engine - Main Entry Point
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @created 2025-09-18
 * @description Main web entry point for the transfer engine control panel
 */

// Security and error handling
ini_set('display_errors', '0'); // Set to '1' for development
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Timezone configuration
date_default_timezone_set('Pacific/Auckland');

// Set execution limits
set_time_limit(defined('MAX_EXECUTION_TIME') ? (int)MAX_EXECUTION_TIME : 600);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration
require_once __DIR__ . '/../config/bootstrap.php';

// Initialize application
$app = new \App\Core\Application();

// Apply security headers
\App\Core\Security::applyHeaders();

// Start routing
$router = new \App\Core\Router();

// Define routes (deduplicated)
$router->get('/', 'DashboardController@index');
$router->get('/dashboard', 'DashboardController@index');
$router->get('/forensics', 'ForensicsController@index');

// Configuration routes
$router->get('/config', 'ConfigController@index');
$router->get('/config/create', 'ConfigController@create');
$router->post('/config', 'ConfigController@store');
$router->get('/config/{id}/edit', 'ConfigController@edit');
$router->post('/config/{id}', 'ConfigController@update');
$router->delete('/config/{id}', 'ConfigController@delete');

// Transfer execution routes
$router->get('/transfer', 'TransferController@index');
$router->get('/transfer/run', 'TransferController@run');
$router->post('/transfer/execute', 'TransferController@execute');
$router->post('/transfer/executeTransfer', 'TransferController@executeTransfer');
$router->get('/transfer/status', 'TransferController@status');
$router->get('/transfer/results', 'TransferController@results');

// Legacy engine compatibility routes (preserve inputs/outputs)
$router->get('/legacy/engine', 'LegacyEngineController@redirect');
$router->post('/legacy/engine', 'LegacyEngineController@forward');

// Reports and analytics routes
$router->get('/reports', 'ReportsController@index');
$router->get('/reports/export', 'ReportsController@export');
$router->post('/reports/generate', 'ReportsController@generate');
// Full-feature dynamic viewer
$router->get('/reports/viewer', 'ReportsController@viewer');

// Logs and monitoring routes
$router->get('/logs', 'LogsController@index');
$router->get('/logs/api', 'LogsController@api');
$router->post('/logs/clear', 'LogsController@clear');

// Console and Settings (UI parity routes)
$router->get('/console', 'LogsController@index');
$router->get('/settings', 'SettingsController@index');

// Register Closures Backfill tool
$router->get('/closures/backfill', 'ClosuresController@backfill');
$router->get('/api/closures/health', 'ClosuresController@apiHealth');
$router->post('/api/closures/scan', 'ClosuresController@apiScan');

// Health check routes (shared with control panel)
$router->get('/health', 'HealthController@check');
$router->get('/api/health', 'HealthController@check');
// Readiness (DB connectivity) routes
$router->get('/ready', 'HealthController@ready');
$router->get('/api/ready', 'HealthController@ready');
$router->get('/api/match/readiness', 'Api\\ReadinessController@index');
$router->get('/api/dashboard/metrics', 'Api\\DashboardMetricsController@index');

// API routes - Engine status and diagnostics
$router->get('/api/engine/status', 'Api\\EngineController@status');
$router->get('/api/engine/diagnostics', 'Api\\EngineController@diagnostics');

// API routes - Kill switch management
$router->get('/api/kill-switch', 'Api\\KillSwitchController@get');
$router->post('/api/kill-switch/activate', 'Api\\KillSwitchController@activate');

// AI Bot Management routes - integrated into transfer engine
$router->get('/bots', 'BotController@dashboard');
$router->get('/bots/dashboard', 'BotController@dashboard');
$router->get('/bots/neural', 'BotController@neural');
$router->get('/bots/performance', 'BotController@performance');
$router->get('/bots/ai-intelligence', 'BotController@aiIntelligence');
$router->post('/api/bots/analyze', 'BotController@generateAnalysis');
$router->get('/api/bots/status', 'BotController@getStatus');
$router->post('/api/bots/test-connections', 'BotController@testConnections');
$router->post('/api/kill-switch/deactivate', 'Api\\KillSwitchController@deactivate');

// API routes - Presets (read-only)
$router->get('/api/presets', 'Api\\PresetsController@index');
$router->post('/api/presets', 'Api\\PresetsController@load');

// API routes - Recent runs
$router->get('/api/runs/recent', 'Api\\RecentRunsController@list');

// API routes - Reports
$router->get('/api/reports/latest', 'Api\\ReportsController@latest');

// API routes - Test/demo execution (safe dry-run)
$router->get('/api/transfer/test', 'Api\\TransferTestController@demo');
// API routes - Fairness sweep (safe dry-run)
$router->get('/api/transfer/fairness-sweep', 'Api\\TransferTestController@sweep');
// API routes - Best-spread (safe dry-run, POST JSON)
$router->post('/api/transfer/best-spread', 'Api\\TransferTestController@bestSpread');

// API routes - Auto-tuning (safe dry-run parameter sweep)
$router->post('/api/transfer/auto-tune', 'Api\\AutoTuneController@run');
$router->get('/autotune', 'AutoTuneApprovalController@index');
$router->post('/autotune/apply', 'AutoTuneApprovalController@apply');

// API routes - Live progress (SSE)
$router->get('/api/transfer/stream', 'Api\\ProgressStreamController@stream');

// API routes - Persistent engine settings
$router->get('/api/settings', 'Api\\SettingsController@get');
$router->post('/api/settings', 'Api\\SettingsController@save');

try {
    // Process the request
    $router->dispatch();
} catch (Exception $e) {
    // Log error
    error_log("Transfer Engine Error: " . $e->getMessage());
    
    // Show error page
    http_response_code(500);
    echo "Error: " . htmlspecialchars($e->getMessage());
}