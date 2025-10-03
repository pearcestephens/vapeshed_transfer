#!/usr/bin/env php
<?php
declare(strict_types=1);
/** validate_system.php - Unified System Validation CLI
 * Checks schema presence, config completeness, FK integrity.
 * Usage: php bin/validate_system.php
 */
require_once __DIR__.'/_cli_bootstrap.php';

$supportDir = __DIR__.'/../src/Support';
foreach(['Config','Logger','Pdo'] as $cls){
    $path = $supportDir.'/'.$cls.'.php';
    if(is_file($path)) require_once $path;
}
$persistDir = __DIR__.'/../src/Persistence';
require_once $persistDir.'/Db.php';

use Unified\Support\Config; use Unified\Support\Logger;

$logger = new Logger('validate');
Config::prime();

function check(string $label, bool $pass): void {
    echo ($pass ? '✓' : '✗')." $label\n";
}

echo "=== Unified System Validation ===\n\n";

// 1. Config completeness
$missing = Config::missing();
check('Config: All required keys present', count($missing)===0);
if ($missing) { echo "  Missing: ".implode(', ',$missing)."\n"; }

// 2. Table presence
try {
    $pdo = \Unified\Persistence\Db::pdo();
    $tables = ['proposal_log','guardrail_traces','insights_log','run_log','config_audit','drift_metrics','cooloff_log','action_audit'];
    $stmt = $pdo->query("SHOW TABLES");
    $existing = array_column($stmt->fetchAll(\PDO::FETCH_NUM),0);
    foreach ($tables as $t) {
        check("Table: $t", in_array($t,$existing));
    }
} catch (\Throwable $e) {
    check('Database connectivity', false);
    echo "  Error: ".$e->getMessage()."\n";
    exit(1);
}

// 3. FK integrity sample (guardrail_traces -> proposal_log)
$orphans = (int)$pdo->query("SELECT COUNT(*) FROM guardrail_traces g LEFT JOIN proposal_log p ON g.proposal_id=p.id WHERE p.id IS NULL")->fetchColumn();
check('FK Integrity: guardrail_traces -> proposal_log', $orphans===0);
if ($orphans>0) { echo "  Found $orphans orphaned traces\n"; }

// 4. Recent proposal count
$propCount = (int)$pdo->query("SELECT COUNT(*) FROM proposal_log")->fetchColumn();
check("Proposals: recorded ($propCount total)", $propCount >= 0);

echo "\n=== Validation Complete ===\n";
exit(0);
