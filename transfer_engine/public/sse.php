<?php
/**
 * Server-Sent Events Endpoint (Hardened)
 * Goals: bounded lifetime, throttled cadence, per-IP caps, topic filters, minimal overhead.
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Unified\Support\Config;

// Basic SSE headers and buffering controls
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// CORS (development only)
if (Config::read('neuro.unified.environment', 'production') === 'development') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Cache-Control');
}

// Throttling and capacity controls
$MAX_LIFETIME_SEC = 60;        // hard cap per connection
$STATUS_PERIOD_SEC = 5;        // status cadence
$HEARTBEAT_PERIOD_SEC = 15;    // heartbeat cadence
$RETRY_MS = 3000;              // client backoff hint
$MAX_GLOBAL = 200;             // soft global cap
$MAX_PER_IP = 3;               // per-IP cap

// Topic filters (client can request subset: e.g., topics=status,transfer)
$topics = array_filter(array_map('trim', explode(',', $_GET['topics'] ?? 'status,heartbeat,transfer,pricing,system')));
$allow = [
    'status' => in_array('status', $topics, true),
    'transfer' => in_array('transfer', $topics, true),
    'pricing' => in_array('pricing', $topics, true),
    'heartbeat' => in_array('heartbeat', $topics, true),
    'system' => in_array('system', $topics, true),
];

// Minimal connection slot accounting via temp files (bounded lifetime mitigates leak risk)
$tmpDir = defined('STORAGE_PATH') ? (STORAGE_PATH . '/tmp') : sys_get_temp_dir();
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
$ip = preg_replace('/[^0-9a-fA-F:\.]/', '', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$slot = $tmpDir . '/sse_' . $ip . '_' . getmypid() . '_' . bin2hex(random_bytes(4)) . '.lock';

// Helper to count active locks
$countLocks = static function (string $pattern): int {
    $list = glob($pattern) ?: [];
    return is_array($list) ? count($list) : 0;
};

$globalCount = $countLocks($tmpDir . '/sse_*_*.lock');
$ipCount = $countLocks($tmpDir . '/sse_' . $ip . '_*.lock');

if ($globalCount >= $MAX_GLOBAL || $ipCount >= $MAX_PER_IP) {
    @error_log(sprintf('SSE over-capacity: global=%d/%d ip=%s ipCount=%d/%d', $globalCount, $MAX_GLOBAL, $ip, $ipCount, $MAX_PER_IP));
    // Over capacity: advise client to backoff and close quickly
    echo "retry: $RETRY_MS\n\n";
    echo "event: system\n";
    echo 'data: ' . json_encode([
        'type' => 'over_capacity',
        'retry_ms' => $RETRY_MS,
        'message' => 'SSE capacity reached, please retry later'
    ]) . "\n\n";
    flush();
    exit; // refuse connection to protect server
}

// Reserve slot and ensure cleanup
@touch($slot);
register_shutdown_function(static function () use ($slot) { @unlink($slot); });

// Output helpers
if (function_exists('ob_get_level') && ob_get_level()) { @ob_end_clean(); }
@ob_implicit_flush(true);
ignore_user_abort(true);

function sse_send($event, array $payload, $id = null): void {
    if ($id !== null) { echo 'id: ' . $id . "\n"; }
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n\n";
    flush();
}

// Client retry/backoff hint
echo 'retry: ' . $RETRY_MS . "\n\n";

// Initial system connected event
if ($allow['system']) {
    sse_send('system', [
        'type' => 'connected',
        'timestamp' => time(),
        'correlation_id' => correlationId(),
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

$start = time();
$nextStatus = $start + $STATUS_PERIOD_SEC;
$nextHeartbeat = $start + $HEARTBEAT_PERIOD_SEC;
$eventId = (int)($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0) + 1;

try {
    while (true) {
        $now = time();
        if (connection_aborted() || ($now - $start) >= $MAX_LIFETIME_SEC) { break; }

        // status cadence
        if ($allow['status'] && $now >= $nextStatus) {
            $status = [
                'database' => [ 'status' => 'connected', 'last_check' => $now ],
                'queue' => [ 'transfer_pending' => rand(0, 15), 'pricing_candidates' => rand(5, 25), 'last_update' => $now ],
                'engine' => [ 'status' => 'active', 'version' => '2.0.0', 'uptime' => $now - strtotime('today') ]
            ];
            sse_send('status', $status, $eventId++);
            $nextStatus += $STATUS_PERIOD_SEC;
        }

        // rare simulated module events (kept sparse to avoid load)
        if ($allow['transfer'] && mt_rand(1, 25) === 1) {
            sse_send('transfer', [ 'type' => 'transfer_completed', 'items_count' => mt_rand(1, 5), 'timestamp' => $now ], $eventId++);
        }
        if ($allow['pricing'] && mt_rand(1, 25) === 1) {
            sse_send('pricing', [ 'type' => 'pricing_proposal', 'product_count' => mt_rand(1, 6), 'timestamp' => $now ], $eventId++);
        }

        // heartbeat cadence
        if ($allow['heartbeat'] && $now >= $nextHeartbeat) {
            sse_send('heartbeat', [ 'type' => 'heartbeat', 'timestamp' => $now, 'connection_duration' => $now - $start ], $eventId++);
            $nextHeartbeat += $HEARTBEAT_PERIOD_SEC;
        }

        // CPU-friendly sleep with jitter
        usleep(250000 + mt_rand(0, 250000));
    }
} catch (\Throwable $e) {
    // Log minimal error and notify client once
    error_log('SSE Error: ' . $e->getMessage());
    sse_send('error', [ 'type' => 'error', 'message' => 'Connection error occurred', 'timestamp' => time() ]);
} finally {
    if ($allow['system']) {
        sse_send('system', [ 'type' => 'disconnected', 'timestamp' => time(), 'reason' => 'normal_closure' ]);
    }
}
