<?php
declare(strict_types=1);
/** http_smoke.php
 * Purpose: Lightweight operational smoke to validate API envelopes and optional// 3b) Unified status API check (GET)
$r3 = http_get($base . '/api/unified_status.php', 2000, [ 'Origin: ' . $origin ]);
$json3 = json_decode((string)$r3['body'], true);
$hasCors3 = false;
foreach ($r3['headers'] as $h) { if (stripos($h, 'Access-Control-Allow-Origin:') === 0) { $hasCors3 = true; break; } }
$metaCheck = check_meta_fields(is_array($json3) ? $json3 : null, $requiredMeta);
$results['unified.status'] = [
    'mode' => 'http',
    'http' => $r3['status'],
    'ok' => (is_array($json3) && ($json3['success'] ?? false) === true) && $metaCheck['ok'],
    'cors' => $hasCors3,
    'meta' => $metaCheck['ok'],
    'meta_missing' => $metaCheck['missing'],
    'meta_type_issues' => $metaCheck['type_issues'],
];

// 3c) History API check (GET)
$r4 = http_get($base . '/api/history.php?limit=10', 2000, [ 'Origin: ' . $origin ]);
$json4 = json_decode((string)$r4['body'], true);
$metaCheck4 = check_meta_fields(is_array($json4) ? $json4 : null, $requiredMeta);
$results['history'] = [
    'mode' => 'http',
    'http' => $r4['status'],
    'ok' => (is_array($json4) && ($json4['success'] ?? false) === true) && $metaCheck4['ok'],
    'meta' => $metaCheck4['ok'],
    'meta_missing' => $metaCheck4['missing'],
    'meta_type_issues' => $metaCheck4['type_issues'],
];

// 3d) Stats API check (GET)
$r5 = http_get($base . '/api/stats.php', 2000, [ 'Origin: ' . $origin ]);
$json5 = json_decode((string)$r5['body'], true);
$metaCheck5 = check_meta_fields(is_array($json5) ? $json5 : null, $requiredMeta);
$results['stats'] = [
    'mode' => 'http',
    'http' => $r5['status'],
    'ok' => (is_array($json5) && ($json5['success'] ?? false) === true) && $metaCheck5['ok'],
    'meta' => $metaCheck5['ok'],
    'meta_missing' => $metaCheck5['missing'],
    'meta_type_issues' => $metaCheck5['type_issues'],
];

// 3e) Modules API check (GET)
$r6 = http_get($base . '/api/modules.php', 2000, [ 'Origin: ' . $origin ]);
$json6 = json_decode((string)$r6['body'], true);
$metaCheck6 = check_meta_fields(is_array($json6) ? $json6 : null, $requiredMeta);
$results['modules'] = [
    'mode' => 'http',
    'http' => $r6['status'],
    'ok' => (is_array($json6) && ($json6['success'] ?? false) === true) && $metaCheck6['ok'],
    'meta' => $metaCheck6['ok'],
    'meta_missing' => $metaCheck6['missing'],
    'meta_type_issues' => $metaCheck6['type_issues'],
];

// 3f) Activity API check (GET)
$r7 = http_get($base . '/api/activity.php?limit=10', 2000, [ 'Origin: ' . $origin ]);
$json7 = json_decode((string)$r7['body'], true);
$metaCheck7 = check_meta_fields(is_array($json7) ? $json7 : null, $requiredMeta);
$results['activity'] = [
    'mode' => 'http',
    'http' => $r7['status'],
    'ok' => (is_array($json7) && ($json7['success'] ?? false) === true) && $metaCheck7['ok'],
    'meta' => $metaCheck7['ok'],
    'meta_missing' => $metaCheck7['missing'],
    'meta_type_issues' => $metaCheck7['type_issues'],
];ty.
 * Usage:
 *   php bin/http_smoke.php               # offline include-mode (no HTTP), checks JSON APIs
 *   SMOKE_BASE_URL=https://staff.vapeshed.co.nz/transfer-engine php bin/http_smoke.php  # HTTP mode incl. SSE
 */

// This script intentionally runs only in HTTP mode to avoid in-process bootstrap duplication.

function http_get(string $url, int $timeoutMs = 1500, array $headers = []): array {
    $headerStr = "Accept: */*\r\n";
    foreach ($headers as $h) { $headerStr .= $h . "\r\n"; }
    $opts = [ 'http' => [ 'method' => 'GET', 'timeout' => max(1, (int)ceil($timeoutMs/1000)), 'ignore_errors' => true, 'header' => $headerStr ] ];
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0; $headers = $http_response_header ?? [];
    foreach ($headers as $h) { if (preg_match('#HTTP/\S+\s+(\d+)#', $h, $m)) { $status = (int)$m[1]; break; } }
    return [ 'status' => $status, 'headers' => $headers, 'body' => $body ];
}

function check_meta_fields(?array $payload, array $required): array {
    $meta = $payload['meta'] ?? null;
    if (!is_array($meta)) {
        return ['ok' => false, 'missing' => $required];
    }
    $missing = [];
    foreach ($required as $field) {
        if (!array_key_exists($field, $meta)) {
            $missing[] = $field;
        }
    }
    $typeFailures = [];
    if (isset($meta['correlation_id']) && !is_string($meta['correlation_id'])) { $typeFailures[] = 'correlation_id'; }
    if (isset($meta['method']) && !is_string($meta['method'])) { $typeFailures[] = 'method'; }
    if (isset($meta['endpoint']) && !is_string($meta['endpoint'])) { $typeFailures[] = 'endpoint'; }
    if (isset($meta['path']) && !is_string($meta['path'])) { $typeFailures[] = 'path'; }
    if (isset($meta['ts']) && !is_int($meta['ts'])) { $typeFailures[] = 'ts'; }
    if (isset($meta['duration_ms']) && !is_int($meta['duration_ms'])) { $typeFailures[] = 'duration_ms'; }
    return [
        'ok' => empty($missing) && empty($typeFailures),
        'missing' => $missing,
        'type_issues' => $typeFailures,
    ];
}

$base = rtrim(getenv('SMOKE_BASE_URL') ?: '', '/');
$useHttp = $base !== '';
$requiredMeta = ['correlation_id', 'method', 'endpoint', 'path', 'ts', 'duration_ms'];

$results = [];

if (!$useHttp) {
    echo json_encode([
        'status' => 'SKIPPED',
        'reason' => 'Set SMOKE_BASE_URL to run HTTP smoke checks (include-mode disabled to avoid class redeclaration in CLI).',
        'ts' => date('c')
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

// 1) Transfer status
$origin = 'https://staff.vapeshed.co.nz';
$r = http_get($base . '/api/transfer.php?action=status', 2000, [ 'Origin: ' . $origin ]);
$decoded = json_decode((string)$r['body'], true);
$hasCors = false; $hasRate = false; $hasCid = false;
foreach ($r['headers'] as $h) {
    if (stripos($h, 'Access-Control-Allow-Origin:') === 0) { $hasCors = true; }
    if (stripos($h, 'X-RateLimit-') === 0) { $hasRate = true; }
}
$metaCheck = check_meta_fields(is_array($decoded) ? $decoded : null, $requiredMeta);
$results['transfer.status'] = [
    'mode' => 'http',
    'http' => $r['status'],
    'ok' => (is_array($decoded) && ($decoded['success'] ?? false) === true) && $metaCheck['ok'],
    'cors' => $hasCors,
    'rate' => $hasRate,
    'meta' => $metaCheck['ok'],
    'meta_missing' => $metaCheck['missing'],
    'meta_type_issues' => $metaCheck['type_issues'],
];

// 2) Pricing status
$r = http_get($base . '/api/pricing.php?action=status', 2000, [ 'Origin: ' . $origin ]);
$decoded = json_decode((string)$r['body'], true);
// Pricing endpoint may not include GET rate headers always; only record CORS presence
$hasCors = false; $hasCid = false;
foreach ($r['headers'] as $h) { if (stripos($h, 'Access-Control-Allow-Origin:') === 0) { $hasCors = true; break; } }
$metaCheck = check_meta_fields(is_array($decoded) ? $decoded : null, $requiredMeta);
$results['pricing.status'] = [
    'mode' => 'http',
    'http' => $r['status'],
    'ok' => (is_array($decoded) && ($decoded['success'] ?? false) === true) && $metaCheck['ok'],
    'cors' => $hasCors,
    'meta' => $metaCheck['ok'],
    'meta_missing' => $metaCheck['missing'],
    'meta_type_issues' => $metaCheck['type_issues'],
];

// Optional POST checks (require SMOKE_POST=1)
if ((getenv('SMOKE_POST') ?: '') === '1') {
    // Get CSRF + correlation id
    $sess = http_get($base . '/api/session.php');
    $sessJson = json_decode((string)$sess['body'], true);
    $csrf = $sessJson['data']['csrf_token'] ?? '';
    $cid  = $sessJson['data']['correlation_id'] ?? '';
    $headers = "Content-Type: application/json\r\n" . ($cid ? ("X-Correlation-ID: $cid\r\n") : '') . ($csrf ? ("X-CSRF-Token: $csrf\r\n") : '');

    // POST transfer execute (no-op stub)
    $ctx = stream_context_create([ 'http' => [ 'method' => 'POST', 'timeout' => 3, 'header' => $headers, 'content' => json_encode(['ids'=>[1,2]]) ] ]);
    $p1Body = @file_get_contents($base . '/api/transfer.php?action=execute', false, $ctx);
    $p1 = json_decode((string)$p1Body, true);
    $results['transfer.execute'] = [ 'mode' => 'http', 'ok' => (is_array($p1) && ($p1['success'] ?? false) === true) ];

    // POST pricing apply (no-op stub)
    $ctx2 = stream_context_create([ 'http' => [ 'method' => 'POST', 'timeout' => 3, 'header' => $headers, 'content' => json_encode(['apply_all'=>false,'proposal_ids'=>[]]) ] ]);
    $p2Body = @file_get_contents($base . '/api/pricing.php?action=apply', false, $ctx2);
    $p2 = json_decode((string)$p2Body, true);
    $results['pricing.apply'] = [ 'mode' => 'http', 'ok' => (is_array($p2) && ($p2['success'] ?? false) === true) ];
}

// 3) Health endpoints (HTTP-only preferred)
$r = http_get($base . '/health.php');
$ok = ($r['status'] === 200);
$results['health'] = [ 'mode' => 'http', 'http' => $r['status'], 'ok' => $ok ];
$r2 = http_get($base . '/health_sse.php');
$ok2 = ($r2['status'] === 200);
$results['health_sse'] = [ 'mode' => 'http', 'http' => $r2['status'], 'ok' => $ok2 ];

// 3b) Unified status API check (GET)
$r3 = http_get($base . '/api/unified_status.php', 2000, [ 'Origin: ' . $origin ]);
$json3 = json_decode((string)$r3['body'], true);
$hasCors3 = false; $hasCid3 = false;
foreach ($r3['headers'] as $h) { if (stripos($h, 'Access-Control-Allow-Origin:') === 0) { $hasCors3 = true; break; } }
$metaCheck = check_meta_fields(is_array($json3) ? $json3 : null, $requiredMeta);
$results['unified.status'] = [
    'mode' => 'http',
    'http' => $r3['status'],
    'ok' => (is_array($json3) && ($json3['success'] ?? false) === true) && $metaCheck['ok'],
    'cors' => $hasCors3,
    'meta' => $metaCheck['ok'],
    'meta_missing' => $metaCheck['missing'],
    'meta_type_issues' => $metaCheck['type_issues'],
];

// 4) SSE quick probe (HTTP mode only). Short timeout and read small chunk.
$sseUrl = $base . '/sse.php?topics=status,heartbeat';
$context = stream_context_create([ 'http' => [ 'method' => 'GET', 'timeout' => 2, 'header' => "Accept: text/event-stream\r\n" ] ]);
$fp = @fopen($sseUrl, 'r', false, $context);
if ($fp) { stream_set_timeout($fp, 2); $chunk = @fread($fp, 2048) ?: ''; @fclose($fp);
    $results['sse'] = [ 'mode' => 'http', 'ok' => (strpos($chunk, 'event:') !== false || strpos($chunk, 'retry:') !== false) ];
} else { $results['sse'] = [ 'mode' => 'http', 'ok' => false ]; }

// Summarize
$fail = array_filter($results, fn($r) => empty($r['ok']));
$status = empty($fail) ? 'GREEN' : 'RED';
echo json_encode([ 'status' => $status, 'results' => $results, 'ts' => date('c') ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
exit(empty($fail) ? 0 : 1);
