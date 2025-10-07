<?php
declare(strict_types=1);
/** smoke_summary.php
 * Summarise the smoke.jsonl log file (produced by cron_http_smoke.sh).
 * Usage:
 *   php bin/smoke_summary.php                      # default path storage/logs/smoke.jsonl
 *   SMOKE_LOG=/path/to/custom.jsonl php bin/smoke_summary.php
 */

$root = realpath(__DIR__ . '/..');
$logPath = getenv('SMOKE_LOG') ?: ($root . '/storage/logs/smoke.jsonl');
if (!is_file($logPath)) {
    fwrite(STDERR, "[smoke_summary] Log not found: $logPath\n");
    exit(2);
}

$lines = array_filter(array_map('trim', file($logPath)));
$recent = array_slice($lines, -100);
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

$out = [
    'log' => $logPath,
    'entries_considered' => $total,
    'counts' => $stats,
    'last' => $last,
    'generated_at' => date('c')
];

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
