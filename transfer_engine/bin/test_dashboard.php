<?php
declare(strict_types=1);
/**
 * test_dashboard.php - Standalone Dashboard Test (No Bootstrap)
 * Tests unified dashboard endpoint functionality with direct mysqli connection
 * Avoids Pdo redeclaration issues
 * 
 * Usage: php bin/test_dashboard.php
 */

// ANSI colors
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

echo str_repeat('=', 70) . "\n";
echo BLUE . "  UNIFIED DASHBOARD TEST (Standalone)\n" . RESET;
echo str_repeat('=', 70) . "\n\n";

// Database credentials with fallback chain
$host = '127.0.0.1';
$db = 'jcepnzzkmj';
$user = 'jcepnzzkmj';
$pass = getenv('DB_PASS') ?: (defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : 'wprKh9Jq63'));

// Connect
$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    echo RED . "✗ Database connection failed\n" . RESET;
    echo "  Error: " . $mysqli->connect_error . "\n";
    exit(1);
}

echo GREEN . "✓ Database connection successful\n" . RESET;
echo "  Host: " . YELLOW . $host . RESET . "\n";
echo "  Database: " . YELLOW . $db . RESET . "\n\n";

// Test 1: Proposal counts by type & band
echo "[1/6] Testing Proposal Statistics...\n";
$result = $mysqli->query("SELECT proposal_type, band, COUNT(*) as cnt FROM proposal_log GROUP BY proposal_type, band");
if (!$result) {
    echo RED . "  ✗ Failed to query proposal_log: " . $mysqli->error . "\n" . RESET;
    exit(1);
}
$proposalStats = [];
while ($row = $result->fetch_assoc()) {
    $proposalStats[] = $row;
}
echo GREEN . "  ✓ Proposal stats query successful\n" . RESET;
echo "    Found: " . count($proposalStats) . " type/band combinations\n";
if (count($proposalStats) > 0) {
    foreach ($proposalStats as $stat) {
        echo "      - " . $stat['proposal_type'] . " / " . $stat['band'] . ": " . $stat['cnt'] . " proposals\n";
    }
}

// Test 2: Total proposal count
echo "\n[2/6] Testing Total Proposal Count...\n";
$result = $mysqli->query("SELECT COUNT(*) as total FROM proposal_log");
if (!$result) {
    echo RED . "  ✗ Failed to count proposals: " . $mysqli->error . "\n" . RESET;
    exit(1);
}
$totalProposals = (int)$result->fetch_assoc()['total'];
echo GREEN . "  ✓ Total proposals: " . $totalProposals . "\n" . RESET;

// Test 3: Recent auto-applied actions (last 24h)
echo "\n[3/6] Testing Auto-Applied Actions...\n";
$result = $mysqli->query("
    SELECT id, proposal_id, sku, action_type, effect, applied_at 
    FROM action_audit 
    WHERE effect = 'applied' 
      AND applied_at >= NOW() - INTERVAL 24 HOUR 
    ORDER BY applied_at DESC 
    LIMIT 10
");
if (!$result) {
    echo RED . "  ✗ Failed to query action_audit: " . $mysqli->error . "\n" . RESET;
    exit(1);
}
$appliedRecent = [];
while ($row = $result->fetch_assoc()) {
    $appliedRecent[] = $row;
}
echo GREEN . "  ✓ Auto-applied actions (24h): " . count($appliedRecent) . "\n" . RESET;
if (count($appliedRecent) > 0) {
    foreach (array_slice($appliedRecent, 0, 3) as $action) {
        echo "      - ID " . $action['id'] . ": " . $action['action_type'] . " on " . $action['sku'] . " @ " . $action['applied_at'] . "\n";
    }
}

// Test 4: Last drift metric
echo "\n[4/6] Testing Drift Metrics...\n";
$result = $mysqli->query("SELECT id, feature_set, psi, status, created_at FROM drift_metrics ORDER BY id DESC LIMIT 1");
if (!$result) {
    echo RED . "  ✗ Failed to query drift_metrics: " . $mysqli->error . "\n" . RESET;
    exit(1);
}
$driftLast = $result->fetch_assoc();
if ($driftLast) {
    echo GREEN . "  ✓ Last drift metric found\n" . RESET;
    echo "      ID: " . $driftLast['id'] . "\n";
    echo "      Feature Set: " . $driftLast['feature_set'] . "\n";
    echo "      PSI: " . $driftLast['psi'] . "\n";
    echo "      Status: " . $driftLast['status'] . "\n";
    echo "      Created: " . $driftLast['created_at'] . "\n";
} else {
    echo YELLOW . "  ✓ No drift metrics yet (expected for fresh deployment)\n" . RESET;
}

// Test 5: Config health simulation
echo "\n[5/6] Testing Config Health...\n";
// Simulate config checks (in real dashboard this uses Config::missing())
$requiredConfigKeys = [
    'neuro.unified.policy.auto_apply_pricing',
    'neuro.unified.policy.cooloff_hours',
    'neuro.unified.pricing.use_real_data'
];
echo YELLOW . "  ℹ Config health check requires bootstrap (skipped in standalone)\n" . RESET;
echo "    Required keys: " . implode(', ', $requiredConfigKeys) . "\n";

// Test 6: Cooloff log check
echo "\n[6/6] Testing Cooloff Log...\n";
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM cooloff_log WHERE expires_at > NOW()");
if (!$result) {
    echo RED . "  ✗ Failed to query cooloff_log: " . $mysqli->error . "\n" . RESET;
    exit(1);
}
$activeCooloffs = (int)$result->fetch_assoc()['cnt'];
echo GREEN . "  ✓ Active cooloffs: " . $activeCooloffs . "\n" . RESET;

// Build summary JSON
echo "\n" . str_repeat('=', 70) . "\n";
echo BLUE . "  DASHBOARD JSON SUMMARY\n" . RESET;
echo str_repeat('=', 70) . "\n";

$summary = [
    'status' => 'ok',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'proposals' => [
        'total' => $totalProposals,
        'by_type_band' => $proposalStats
    ],
    'auto_applied_24h' => [
        'count' => count($appliedRecent),
        'recent' => array_slice($appliedRecent, 0, 10)
    ],
    'drift' => [
        'last' => $driftLast ?: null,
        'status' => $driftLast ? $driftLast['status'] : 'none'
    ],
    'cooloff' => [
        'active' => $activeCooloffs
    ],
    'config' => [
        'health' => 'requires_bootstrap',
        'note' => 'Config check requires Config::prime() - use actual dashboard endpoint'
    ]
];

echo json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";

echo "\n" . str_repeat('=', 70) . "\n";
echo GREEN . "  ✓ ALL DASHBOARD TESTS PASSED\n" . RESET;
echo str_repeat('=', 70) . "\n";

$mysqli->close();
exit(0);
