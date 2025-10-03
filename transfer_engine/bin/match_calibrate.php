<?php
declare(strict_types=1);
require_once __DIR__.'/_cli_bootstrap.php';
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$days = (int)($argv[1] ?? 14);
$since = date('Y-m-d H:i:s', time() - ($days*86400));
$sql = "SELECT confidence, status FROM product_candidate_matches WHERE created_at >= '".$mysqli->real_escape_string($since)."'";
$res = $mysqli->query($sql);
$accepted=[]; $proposed=[]; while($res && $row=$res->fetch_assoc()){ if($row['status']==='accepted') $accepted[]=(float)$row['confidence']; else if($row['status']==='proposed') $proposed[]=(float)$row['confidence']; }
sort($accepted); sort($proposed);
$pct = function(array $arr,float $p){ if(!$arr) return null; $idx=(int)floor(($p/100)* (count($arr)-1)); return $arr[$idx]; };
$suggestPrimary = $pct($accepted, 10) ? max(0.60, round($pct($accepted,10)-0.02,2)) : 0.72;
$suggestSecondary = $pct($accepted, 5) ? max(0.55, round($pct($accepted,5)-0.02,2)) : 0.68;
echo json_encode([
  'window_days'=>$days,
  'accepted_count'=>count($accepted),
  'proposed_count'=>count($proposed),
  'suggest_primary'=>$suggestPrimary,
  'suggest_secondary'=>$suggestSecondary,
  'notes'=>'Review before applying to auto-accept thresholds.'
], JSON_PRETTY_PRINT)."\n";
exit(0);
