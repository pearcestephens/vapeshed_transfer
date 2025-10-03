<?php
declare(strict_types=1);
require_once __DIR__.'/_cli_bootstrap.php';
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$retentionDays = (int)($argv[1] ?? 30);
$cutoff = date('Y-m-d 00:00:00', time() - ($retentionDays*86400));
$sql = "SELECT DATE(created_at) d, event_type, COUNT(*) c FROM product_candidate_match_events WHERE created_at < '".$mysqli->real_escape_string($cutoff)."' GROUP BY d,event_type";
$res=$mysqli->query($sql); $rows=0;
while($res && $row=$res->fetch_assoc()){
  $d=$row['d']; $et=$row['event_type']; $c=(int)$row['c'];
  $mysqli->query("INSERT INTO product_candidate_match_event_rollup(rollup_date,event_type,events) VALUES('$d','$et',$c) ON DUPLICATE KEY UPDATE events=events+$c");
  $rows++;
}
$mysqli->query("DELETE FROM product_candidate_match_events WHERE created_at < '".$mysqli->real_escape_string($cutoff)."'");
echo json_encode(['rolled_up'=>$rows,'cutoff'=>$cutoff], JSON_PRETTY_PRINT)."\n";
exit(0);
