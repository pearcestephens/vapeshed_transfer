<?php
declare(strict_types=1);

/**
 * image_fetch_worker.php
 * Iterates over product_candidate_images without content_hash and attempts to download & hash them.
 * Stores basic binary meta + perceptual hashes for later similarity comparison.
 */

use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\App\Repositories\ProductCandidateImageRepository;
use VapeshedTransfer\App\Services\Vision\ImageHashService;

require_once __DIR__.'/_cli_bootstrap.php';
$mysqli = cli_db();
$logger = new Logger('image_fetch_worker');
$repo = new ProductCandidateImageRepository($mysqli);
$hasher = new ImageHashService();

$limit = (int)($argv[1] ?? 25);
$res = $mysqli->query("SELECT image_id,image_url FROM product_candidate_images WHERE content_hash IS NULL ORDER BY created_at ASC LIMIT " . $limit);
if(!$res){ $logger->error('Query failed',['err'=>$mysqli->error]); exit(1); }
$processed=0; $errors=0;
while($row=$res->fetch_assoc()){
    $url=$row['image_url']; $imageId=$row['image_id'];
    $logger->info('Fetching image',['image_id'=>$imageId,'url'=>$url]);
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $binary=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
    if($err || $code>=400 || !$binary){ $errors++; $logger->warning('Image fetch failed',['code'=>$code,'err'=>$err]); continue; }
    $bytes=strlen($binary); $contentHash=hash('sha256',$binary);
    // Format guess from header bytes
    $format=null; if(str_starts_with($binary,"\x89PNG")) $format='png'; elseif (str_starts_with($binary,"\xFF\xD8")) $format='jpg'; elseif (str_starts_with($binary,'RIFF') && substr($binary,8,4)==='WEBP') $format='webp';
    $hashes=$hasher->computeAll($binary);
    $repo->updateBinaryMeta($imageId,$contentHash,$bytes,null,null,$format);
    // Persist perceptual hashes
    $stmt=$mysqli->prepare("UPDATE product_candidate_images SET p_hash=?, d_hash=?, a_hash=?, dominant_color=? WHERE image_id=?");
    if($stmt){ $stmt->bind_param('sssss',$hashes['p_hash'],$hashes['d_hash'],$hashes['a_hash'],$hashes['dominant_color'],$imageId); $stmt->execute(); $stmt->close(); }
    $processed++;
}
$logger->info('Image fetch worker complete',['processed'=>$processed,'errors'=>$errors]);
