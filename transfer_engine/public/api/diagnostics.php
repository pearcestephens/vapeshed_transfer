<?php
declare(strict_types=1);
/**
 * Diagnostics API Endpoint
 * Provides system diagnostics including rate limit configuration and usage
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Support\Api;
use Unified\Support\Config;

Api::initJson();
Api::applyCors('GET, OPTIONS');
Api::handleOptionsPreflight();

// Feature flag guard
if (!Config::get('neuro.unified.ui.diagnostics_enabled', false)) {
    Api::error('DISABLED', 'Diagnostics API is disabled', 403);
}

// Optional token authentication
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $_SERVER['HTTP_X_API_TOKEN'] = $m[1];
}
Api::enforceOptionalToken('neuro.unified.ui.api_token', ['HTTP_X_API_TOKEN','HTTP_AUTHORIZATION']);
Api::enforceGetRateLimit('diagnostics');

$logger = \Unified\Support\UiKernel::logger();

try {
    $groups = ['pricing', 'transfer', 'history', 'traces', 'stats', 'modules', 'activity', 'smoke', 'unified', 'session', 'diagnostics'];
    
    $rateLimits = [
        'global' => [
            'get_per_min' => Config::get('neuro.unified.security.get_rate_limit_per_min', 120),
            'get_burst' => Config::get('neuro.unified.security.get_rate_burst', 30),
            'post_per_min' => Config::get('neuro.unified.security.post_rate_limit_per_min', 0),
            'post_burst' => Config::get('neuro.unified.security.post_rate_burst', 0),
        ],
        'groups' => []
    ];
    
    foreach ($groups as $group) {
        $rateLimits['groups'][$group] = [
            'get_per_min' => Config::get("neuro.unified.security.groups.{$group}.get_rate_limit_per_min", 0),
            'get_burst' => Config::get("neuro.unified.security.groups.{$group}.get_rate_burst", 0),
            'post_per_min' => Config::get("neuro.unified.security.groups.{$group}.post_rate_limit_per_min", 0),
            'post_burst' => Config::get("neuro.unified.security.groups.{$group}.post_rate_burst", 0),
        ];
    }
    
    // Gather current bucket usage (read-only peek)
    $tmpDir = defined('STORAGE_PATH') ? (STORAGE_PATH . '/tmp') : sys_get_temp_dir();
    $buckets = [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $safeIp = preg_replace('/[^0-9a-fA-F:\.]/', '', $ip);
    
    foreach (['get', 'post'] as $method) {
        $bucketFile = $tmpDir . '/' . $method . '_' . $safeIp . '.bucket';
        if (is_file($bucketFile)) {
            $raw = @file_get_contents($bucketFile);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $buckets[$method] = [
                    'count' => (int)($decoded['c'] ?? 0),
                    'window_start' => (int)($decoded['w'] ?? 0),
                    'age_sec' => time() - (int)($decoded['w'] ?? 0)
                ];
            }
        }
    }
    
    Api::ok([
        'rate_limits' => $rateLimits,
        'current_ip' => $ip,
        'buckets' => $buckets,
        'environment' => Config::get('neuro.unified.environment', 'production'),
        'csrf_required' => Config::get('neuro.unified.security.csrf_required', false),
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    $logger->error('api.diagnostics.error', [
        'correlation_id' => correlationId(),
        'error' => $e->getMessage()
    ]);
    
    Api::error('INTERNAL_ERROR', 'An internal error occurred', 500);
}
