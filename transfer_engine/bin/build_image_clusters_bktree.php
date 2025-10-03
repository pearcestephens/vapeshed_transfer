<?php
declare(strict_types=1);
require_once __DIR__.'/_cli_bootstrap.php';
use VapeshedTransfer\App\Services\Image\BKTree;
$mysqli = cli_db();
$limit=(int)($argv[1]??8000); $radius=(int)($argv[2]??10);
$res=$mysqli->query("SELECT DISTINCT p_hash FROM vend_product_images WHERE p_hash IS NOT NULL LIMIT $limit");
$hashes=[]; while($res && $row=$res->fetch_assoc()){ $hashes[]=$row['p_hash']; }
$tree=new BKTree(); foreach($hashes as $h){ $tree->add($h); }
$visited=[]; $clusters=[];
foreach($hashes as $h){ if(isset($visited[$h])) continue; $group=$tree->radiusSearch($h,$radius); foreach($group as $g){ $visited[$g]=true; } $rep=min($group); $clusters[]=['rep'=>$rep,'members'=>$group]; }
$insert=0; foreach($clusters as $c){ $rep=$mysqli->real_escape_string($c['rep']); foreach($c['members'] as $m){ $mEsc=$mysqli->real_escape_string($m); $mysqli->query("INSERT IGNORE INTO image_hash_clusters(p_hash,representative_hash) VALUES('$mEsc','$rep')"); $insert+=$mysqli->affected_rows; }}
echo json_encode(['strategy'=>'bk-tree','radius'=>$radius,'clusters'=>count($clusters),'assignments'=>$insert], JSON_PRETTY_PRINT)."\n";
