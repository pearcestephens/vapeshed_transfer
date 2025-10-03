<?php
declare(strict_types=1);
/**
 * build_image_clusters.php
 * Baseline O(n^2) perceptual hash clustering (fallback / reference implementation).
 * For large datasets prefer BK-tree version: build_image_clusters_bktree.php
 *
 * Clustering Strategy:
 *   - Load distinct p_hash values (bounded by --limit)
 *   - Sequentially assign each hash to the first existing cluster whose representative
 *     is within HAMMING radius (--radius). Representative chosen as first member (stable).
 *   - Persist assignments into image_hash_clusters (INSERT IGNORE for idempotency).
 *
 * Usage:
 *   php bin/build_image_clusters.php [--limit=5000] [--radius=10] [--dry-run]
 *
 * Exit Codes:
 *   0 success, 1 usage error, 2 DB error
 */

require_once __DIR__.'/_cli_bootstrap.php';

// -------------------- CLI Arg Parsing --------------------
$args = $argv; array_shift($args);
$opts = [ 'limit'=>5000, 'radius'=>10, 'dry-run'=>false ];
foreach($args as $a){
    if(preg_match('/^--limit=(\d+)$/',$a,$m)){ $opts['limit']=(int)$m[1]; continue; }
    if(preg_match('/^--radius=(\d+)$/',$a,$m)){ $opts['radius']=(int)$m[1]; continue; }
    if($a==='--dry-run'){ $opts['dry-run']=true; continue; }
    if(in_array($a,['-h','--help','help'],true)) { usage(); exit(0); }
}
if($opts['limit']<=0){ fail('Invalid --limit'); }
if($opts['radius']<=0 || $opts['radius']>48){ fail('Unreasonable --radius (1..48)'); }

// -------------------- DB Bootstrap --------------------
$mysqli = cli_db();

// -------------------- Data Load --------------------
$sql = sprintf("SELECT DISTINCT p_hash FROM vend_product_images WHERE p_hash IS NOT NULL LIMIT %d", $opts['limit']);
$res = $mysqli->query($sql);
if(!$res){ fail('Query failed: '.$mysqli->error,2); }
$hashes=[]; while($row=$res->fetch_assoc()){ $hashes[]=$row['p_hash']; }
$total = count($hashes);
if(!$total){ echo json_encode(['clusters'=>0,'assignments'=>0,'total_hashes'=>0,'radius'=>$opts['radius'],'note'=>'No hashes found'], JSON_PRETTY_PRINT)."\n"; exit(0);} 

// -------------------- Clustering O(n^2) --------------------
$clusters=[]; // each: ['rep'=>string,'members'=>[]]
foreach($hashes as $h){
    $placed=false; foreach($clusters as &$c){
        $dist = hammingHex($h,$c['rep']);
        if($dist!==null && $dist <= $opts['radius']){ $c['members'][]=$h; $placed=true; break; }
    }
    if(!$placed){ $clusters[]=['rep'=>$h,'members'=>[$h]]; }
}
unset($c);

// -------------------- Persist --------------------
$assignments=0;
if(!$opts['dry-run']){
    foreach($clusters as $c){
        $repEsc = $mysqli->real_escape_string($c['rep']);
        foreach($c['members'] as $m){
            $mEsc=$mysqli->real_escape_string($m);
            $mysqli->query("INSERT IGNORE INTO image_hash_clusters(p_hash,representative_hash) VALUES('$mEsc','$repEsc')");
            $assignments += $mysqli->affected_rows;
        }
    }
}

$out = [
    'strategy' => 'o2_baseline',
    'total_hashes' => $total,
    'clusters' => count($clusters),
    'assignments' => $assignments,
    'radius' => $opts['radius'],
    'limit' => $opts['limit'],
    'dry_run' => $opts['dry-run']
];
echo json_encode($out, JSON_PRETTY_PRINT)."\n";

// -------------------- Helpers --------------------
function hammingHex(string $a,string $b): ?int { if(strlen($a)!==strlen($b)) return null; $ba=hex2bin($a); $bb=hex2bin($b); if($ba===false||$bb===false) return null; $len=strlen($ba); $d=0; for($i=0;$i<$len;$i++){ $x= ord($ba[$i]) ^ ord($bb[$i]); $d += substr_count(decbin($x),'1'); } return $d; }
function fail(string $m,int $code=1): void { fwrite(STDERR,$m."\n"); exit($code);} 
function usage(): void { echo "Perceptual Hash Clustering (Baseline)\nOptions: --limit=INT --radius=INT --dry-run\n"; }
