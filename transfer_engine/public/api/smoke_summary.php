<?php
declare(strict_types=1);
/**
 * Smoke Summary API
 * Returns aggregated status information from smoke.jsonl (if enabled).
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Support\Config;
use Unified\Support\Api;

// Standardized headers and CORS
Api::initJson();
Api::applyCors();
Api::handleOptionsPreflight();

if (!Config::get('neuro.unified.ui.smoke_summary_enabled', false)) {
    Api::error('DISABLED', 'Smoke summary disabled', 403);
}

// Optional token protection when exposed beyond trusted network
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $_SERVER['HTTP_X_SMOKE_TOKEN'] = $m[1];
}
Api::enforceOptionalToken(
    'neuro.unified.ui.smoke_summary_token',
    ['HTTP_X_SMOKE_TOKEN', 'HTTP_X_API_TOKEN'],
    ['token']
);

// Optional per-IP GET rate limit (group: smoke)
Api::enforceGetRateLimit('smoke');

$root = dirname(__DIR__, 2);
$logPath = Config::get('neuro.unified.smoke.log_path', $root . '/storage/logs/smoke.jsonl');
if (!is_file($logPath)) {
    Api::error('NOT_FOUND', 'Smoke log not found', 404, ['path' => $logPath]);
}

$lines = array_filter(array_map('trim', file($logPath)));
$recent = array_slice($lines, -50);
$total = count($recent);
$stats = [ 'GREEN' => 0, 'RED' => 0, 'SKIPPED' => 0, 'OTHER' => 0 ];
$last = null;
foreach ($recent as $line) {
    $data = json_decode($line, true);
    if (!is_array($data)) { $stats['OTHER']++; continue; }
    $status = strtoupper((string)($data['status'] ?? ''));
    if (!isset($stats[$status])) { $stats['OTHER']++; } else { $stats[$status]++; }
    $last = $data;
}

Api::ok([
    'path' => $logPath,
    'entries_considered' => $total,
    'counts' => $stats,
    'last' => $last,
    'generated_at' => date('c')
]);
