<?php
declare(strict_types=1);
// Computes calibrator suggestions and logs drift vs stored thresholds.
require_once __DIR__.'/_cli_bootstrap.php';
use VapeshedTransfer\App\Repositories\MatchThresholdRepository;
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$days=(int)($argv[1]??14);
$since = date('Y-m-d H:i:s', time()-($days*86400));
$res = $mysqli->query("SELECT confidence,status FROM product_candidate_matches WHERE created_at>='".$mysqli->real_escape_string($since)."'");
$acc=[];$prop=[]; while($res && $r=$res->fetch_assoc()){ if($r['status']==='accepted') $acc[]=(float)$r['confidence']; elseif($r['status']==='proposed') $prop[]=(float)$r['confidence']; }
sort($acc); sort($prop); $pct=function($arr,$p){ if(!$arr) return null; $i=(int)floor(($p/100)*(count($arr)-1)); return $arr[$i];};
$suggestPrimary = $pct($acc,10)? max(0.55, round($pct($acc,10)-0.02,2)) : 0.78;
$suggestSecondary = $pct($acc,5)? max(0.50, round($pct($acc,5)-0.02,2)) : 0.72;
$repo = new MatchThresholdRepository($mysqli);
$scopes = ['global','liquid','coil','hardware'];
$out = ['window_days'=>$days,'drifts'=>[]];
foreach($scopes as $scope){
    $cur=$repo->get($scope);
    $dp = round($suggestPrimary - $cur['primary'],4); $ds= round($suggestSecondary - $cur['secondary'],4);
    $stmt=$mysqli->prepare("INSERT INTO product_match_threshold_drift(scope,current_primary,suggested_primary,current_secondary,suggested_secondary,delta_primary,delta_secondary,accepted_sample,proposed_sample,window_days) VALUES(?,?,?,?,?,?,?,?,?,?)");
    if($stmt){ $stmt->bind_param('sddddddiii',$scope,$cur['primary'],$suggestPrimary,$cur['secondary'],$suggestSecondary,$dp,$ds,count($acc),count($prop),$days); $stmt->execute(); $stmt->close(); }
    $out['drifts'][$scope]=['suggest_primary'=>$suggestPrimary,'suggest_secondary'=>$suggestSecondary,'delta_primary'=>$dp,'delta_secondary'=>$ds];
}
echo json_encode($out, JSON_PRETTY_PRINT)."\n";
