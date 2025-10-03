<?php
declare(strict_types=1);
/**
 * auto_tune_patch_generator.php - generates SQL patch from calibration drift (requires manual review & apply).
 */
require_once __DIR__.'/_cli_bootstrap.php';
use VapeshedTransfer\App\Repositories\MatchThresholdRepository;
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 

$maxDelta = (float)($argv[1] ?? 0.05); // only patch if delta >= this
$res = $mysqli->query("SELECT scope, suggested_primary, suggested_secondary FROM product_match_threshold_drift WHERE ABS(delta_primary) >= $maxDelta OR ABS(delta_secondary) >= $maxDelta ORDER BY drift_id DESC LIMIT 10");
$patches = [];
while($res && $row=$res->fetch_assoc()){
    $scope = $row['scope'];
    $p = (float)$row['suggested_primary'];
    $s = (float)$row['suggested_secondary'];
    $patches[$scope] = ['primary'=>$p,'secondary'=>$s];
}

if(!$patches){ echo json_encode(['message'=>'No significant drifts detected','max_delta'=>$maxDelta])."\n"; exit(0); }

$sql = "-- AUTO-GENERATED THRESHOLD PATCH (REVIEW BEFORE APPLY)\n";
foreach($patches as $scope=>$vals){
    $sql .= "UPDATE product_match_thresholds SET primary_threshold={$vals['primary']}, secondary_threshold={$vals['secondary']} WHERE scope='$scope';\n";
}
$sql .= "-- End auto-tune patch\n";

echo json_encode(['patches'=>$patches,'sql'=>$sql,'instructions'=>'Review + apply SQL manually'], JSON_PRETTY_PRINT)."\n";
file_put_contents(__DIR__.'/../var/tmp/auto_tune_patch_'.date('Ymd_His').'.sql', $sql);
echo "Patch written to var/tmp/auto_tune_patch_".date('Ymd_His').".sql\n";
