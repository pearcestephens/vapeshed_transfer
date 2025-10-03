<?php
declare(strict_types=1);

// match_evaluate.php
// Computes basic metrics on product_candidate_matches to monitor auto-accept calibration.

use VapeshedTransfer\Core\Logger;

require_once __DIR__.'/_cli_bootstrap.php';

$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$logger = new Logger('match_evaluate');

// Fetch recent matches timeframe (last N days; default 7)
$days = (int)($argv[1] ?? 7);
$since = date('Y-m-d H:i:s', time() - ($days*86400));

$sql = "SELECT status, confidence, method, created_at FROM product_candidate_matches WHERE created_at >= '".$mysqli->real_escape_string($since)."'";
$res = $mysqli->query($sql);
$total=0; $accepted=0; $proposed=0; $rejected=0; $confAcc=[]; $confProp=[];
while($res && $row=$res->fetch_assoc()){
    $total++; $c=(float)$row['confidence'];
    switch($row['status']){
        case 'accepted': $accepted++; $confAcc[]=$c; break;
        case 'proposed': $proposed++; $confProp[]=$c; break;
        case 'rejected': $rejected++; break;
    }
}
$avgAcc = $confAcc? round(array_sum($confAcc)/count($confAcc),4):null;
$avgProp = $confProp? round(array_sum($confProp)/count($confProp),4):null;
$acceptRate = $total? round($accepted/$total,4):0.0;
$logger->info('Match evaluation summary',[
    'window_days'=>$days,
    'total'=>$total,
    'accepted'=>$accepted,
    'proposed'=>$proposed,
    'rejected'=>$rejected,
    'accept_rate'=>$acceptRate,
    'avg_confidence_accepted'=>$avgAcc,
    'avg_confidence_proposed'=>$avgProp
]);

echo json_encode([
    'window_days'=>$days,
    'total'=>$total,
    'accepted'=>$accepted,
    'proposed'=>$proposed,
    'rejected'=>$rejected,
    'accept_rate'=>$acceptRate,
    'avg_confidence_accepted'=>$avgAcc,
    'avg_confidence_proposed'=>$avgProp
], JSON_PRETTY_PRINT)."\n";
