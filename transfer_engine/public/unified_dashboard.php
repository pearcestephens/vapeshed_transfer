<?php
declare(strict_types=1);
/** unified_dashboard.php - Unified System Dashboard JSON Endpoint
 * Provides summary of proposals, auto-applied actions, drift status, config health.
 * Usage: curl http://.../public/unified_dashboard.php
 */
require_once __DIR__.'/../config/bootstrap.php';

// Load repositories
$supportDir = __DIR__.'/../src/Support';
foreach(['Env','Config','Util','Logger','Pdo'] as $cls){
    $path = $supportDir.'/'.$cls.'.php';
    if(is_file($path)) require_once $path;
}
$persistDir = __DIR__.'/../src/Persistence';
foreach(['Db','ProposalRepository','ActionAuditRepository','DriftMetricsRepository'] as $pr){
    $ppr=$persistDir.'/'.$pr.'.php';
    if(is_file($ppr)) require_once $ppr;
}

use Unified\Support\Logger; use Unified\Support\Config; use Unified\Persistence\ActionAuditRepository; use Unified\Persistence\DriftMetricsRepository;

header('Content-Type: application/json; charset=utf-8');
Config::prime();

$logger = new Logger('unified_dashboard');
$auditRepo = new ActionAuditRepository($logger);
$driftRepo = new DriftMetricsRepository($logger);

try {
    $pdo = \Unified\Persistence\Db::pdo();

    // Proposal counts by type & band
    $stmt = $pdo->query("SELECT proposal_type, band, COUNT(*) as cnt FROM proposal_log GROUP BY proposal_type, band");
    $proposalStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Auto-applied actions last 24h
    $appliedRecent = $auditRepo->recentApplied(24);

    // Last drift metric
    $driftLast = $pdo->query("SELECT id, feature_set, psi, status, created_at FROM drift_metrics ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC) ?: null;

    // Config health
    $missing = Config::missing();
    $configHealth = count($missing)===0 ? 'ok' : 'incomplete';

    // System summary
    $summary = [
        'status' => 'ok',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'proposals' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM proposal_log")->fetchColumn(),
            'by_type_band' => $proposalStats
        ],
        'auto_applied_24h' => [
            'count' => count($appliedRecent),
            'recent' => array_slice($appliedRecent,0,10)
        ],
        'drift' => [
            'last' => $driftLast,
            'status' => $driftLast ? $driftLast['status'] : 'none'
        ],
        'config' => [
            'health' => $configHealth,
            'missing_keys' => $missing,
            'auto_apply_pricing_enabled' => Config::get('neuro.unified.policy.auto_apply_pricing', false),
            'cooloff_hours' => Config::get('neuro.unified.policy.cooloff_hours', 24)
        ]
    ];

    echo json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
}
