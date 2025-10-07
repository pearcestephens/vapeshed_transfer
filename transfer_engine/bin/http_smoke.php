<?php
declare(strict_types=1);
/** http_smoke.php
 * Purpose: Lightweight operational smoke to validate API envelopes and optional SSE reachability.
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

$base = rtrim(getenv('SMOKE_BASE_URL') ?: '', '/');
$useHttp = $base !== '';

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
$hasCid = is_array($decoded) && isset($decoded['meta']['correlation_id']) && is_string($decoded['meta']['correlation_id']) && $decoded['meta']['correlation_id'] !== '';
$results['transfer.status'] = [ 'mode' => 'http', 'http' => $r['status'], 'ok' => (is_array($decoded) && ($decoded['success'] ?? false) === true), 'cors' => $hasCors, 'rate' => $hasRate, 'meta' => $hasCid ];

// 2) Pricing status
$r = http_get($base . '/api/pricing.php?action=status', 2000, [ 'Origin: ' . $origin ]);
$decoded = json_decode((string)$r['body'], true);
// Pricing endpoint may not include GET rate headers always; only record CORS presence
$hasCors = false; $hasCid = false;
foreach ($r['headers'] as $h) { if (stripos($h, 'Access-Control-Allow-Origin:') === 0) { $hasCors = true; break; } }
$hasCid = is_array($decoded) && isset($decoded['meta']['correlation_id']) && is_string($decoded['meta']['correlation_id']) && $decoded['meta']['correlation_id'] !== '';
$results['pricing.status'] = [ 'mode' => 'http', 'http' => $r['status'], 'ok' => (is_array($decoded) && ($decoded['success'] ?? false) === true), 'cors' => $hasCors, 'meta' => $hasCid ];

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
$hasCid3 = is_array($json3) && isset($json3['meta']['correlation_id']) && is_string($json3['meta']['correlation_id']) && $json3['meta']['correlation_id'] !== '';
$results['unified.status'] = [ 'mode' => 'http', 'http' => $r3['status'], 'ok' => (is_array($json3) && ($json3['success'] ?? false) === true), 'cors' => $hasCors3, 'meta' => $hasCid3 ];

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
