<?php
/**
 * Unified Status API Endpoint
 * Aggregates system-wide status information from multiple sources
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Persistence\ReadModels\TransferReadModel;
use Unified\Persistence\ReadModels\PricingReadModel;
use Unified\Support\UiKernel;
use Unified\Support\Config;
use Unified\Support\Api;

Api::initJson();
Api::applyCors();
Api::handleOptionsPreflight();

// Feature flag guard
if (!Config::get('neuro.unified.ui.unified_status_enabled', false)) {
    Api::error('DISABLED', 'Unified status API is disabled', 403);
}

// Optional token authentication
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $_SERVER['HTTP_X_API_TOKEN'] = $m[1];
}
Api::enforceOptionalToken('neuro.unified.ui.api_token', ['HTTP_X_API_TOKEN','HTTP_AUTHORIZATION']);

// Simple per-IP GET rate limiting (config-controlled, group: unified)
Api::enforceGetRateLimit('unified');

$logger = UiKernel::logger();

try {
    $transferModel = new TransferReadModel($logger);
    $pricingModel = new PricingReadModel($logger);
    
    // Gather stats from all sources
    $transferStats = $transferModel->sevenDayStats();
    $pricingStats = $pricingModel->sevenDayStats();
    
    // Attempt to get health status
    $dbStatus = 'unknown';
    try {
        $pdo = \Unified\Persistence\Db::pdo();
        $pdo->query('SELECT 1');
        $dbStatus = 'connected';
    } catch (Exception $e) {
        $dbStatus = 'error';
    }
    
    // Attempt to get SSE health
    $sseHealth = ['status' => 'unknown', 'global' => 0];
    try {
        $tmpDir = defined('STORAGE_PATH') ? (STORAGE_PATH . '/tmp') : sys_get_temp_dir();
        $locks = glob($tmpDir . '/sse_*_*.lock') ?: [];
        $globalCount = is_array($locks) ? count($locks) : 0;
        $maxGlobal = (int) Config::get('neuro.unified.sse.max_global', 200);
        
        $status = 'green';
        if ($globalCount >= $maxGlobal) {
            $status = 'red';
        } elseif ($globalCount >= max(1, (int) floor($maxGlobal * 0.9))) {
            $status = 'yellow';
        }
        
        $sseHealth = [
            'status' => $status,
            'global' => $globalCount,
            'max_global' => $maxGlobal
        ];
    } catch (Exception $e) {
        // Silent fail
    }
    
    Api::ok([
        'transfer' => $transferStats,
        'pricing' => $pricingStats,
        'database' => [ 'status' => $dbStatus ],
        'sse' => $sseHealth,
        'system' => [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'correlation_id' => correlationId()
        ]
    ]);
    
} catch (Exception $e) {
    $logger->error('api.unified_status.error', [
        'correlation_id' => correlationId(),
        'error' => $e->getMessage()
    ]);
    
    Api::error('INTERNAL_ERROR', 'An internal error occurred', 500);
}
