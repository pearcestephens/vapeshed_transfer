<?php
declare(strict_types=1);
// threshold_set.php <scope> <primary> <secondary> <vision> <min_token>
require_once __DIR__.'/_cli_bootstrap.php';
use VapeshedTransfer\App\Repositories\MatchThresholdRepository;
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$scope = $argv[1] ?? 'global';
$p = isset($argv[2])? (float)$argv[2] : 0.78;
$s = isset($argv[3])? (float)$argv[3] : 0.72;
$v = isset($argv[4])? (float)$argv[4] : 0.70;
$mt = isset($argv[5])? (float)$argv[5] : 0.45;
$repo = new MatchThresholdRepository($mysqli);
if($repo->set($scope,$p,$s,$v,$mt)){
    echo json_encode(['updated'=>true,'scope'=>$scope,'values'=>compact('p','s','v','mt')], JSON_PRETTY_PRINT)."\n";
} else {
    echo json_encode(['updated'=>false,'error'=>$mysqli->error], JSON_PRETTY_PRINT)."\n";
}
