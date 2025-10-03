<?php
declare(strict_types=1);
require_once __DIR__.'/_cli_bootstrap.php';
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$res=$mysqli->query("SELECT representative_hash, COUNT(*) c FROM image_hash_clusters GROUP BY representative_hash");
$issues=[]; while($res && $row=$res->fetch_assoc()){ if($row['c']>400){ $issues[]=['rep'=>$row['representative_hash'],'size'=>(int)$row['c'],'issue'=>'oversized_cluster']; } }
echo json_encode(['clusters_checked'=>count($issues),'issues'=>$issues], JSON_PRETTY_PRINT)."\n";
