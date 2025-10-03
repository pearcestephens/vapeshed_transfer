<?php
declare(strict_types=1);

use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\App\Repositories\VendProductImageRepository;
use VapeshedTransfer\App\Services\Vision\ImageHashService;

require_once __DIR__.'/_cli_bootstrap.php';

$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$logger = new Logger('vend_image_fetch_worker');
$repo = new VendProductImageRepository($mysqli);
$hasher = new ImageHashService();

$pending = $repo->listPending((int)($argv[1] ?? 30));
foreach ($pending as $row) {
    $logger->info('Fetching vend product image',['image_id'=>$row['image_id'],'url'=>$row['image_url']]);
    $ch=curl_init($row['image_url']);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $bin=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
    if($err || $code>=400 || !$bin){ $repo->markError($row['image_id'],$err?:('http_'.$code)); continue; }
    $bytes=strlen($bin); $contentHash=hash('sha256',$bin); $format=null; if(str_starts_with($bin,"\x89PNG")) $format='png'; elseif(str_starts_with($bin,"\xFF\xD8")) $format='jpg'; elseif(str_starts_with($bin,'RIFF') && substr($bin,8,4)==='WEBP') $format='webp';
    $hashes=$hasher->computeAll($bin);
    // Try dimensions
    $im=@imagecreatefromstring($bin); $w=null;$h=null; if($im){ $w=imagesx($im); $h=imagesy($im); imagedestroy($im);} 
    $repo->markFetched($row['image_id'], [
        'content_hash'=>$contentHash,
        'p_hash'=>$hashes['p_hash'],
        'd_hash'=>$hashes['d_hash'],
        'a_hash'=>$hashes['a_hash'],
        'dominant_color'=>$hashes['dominant_color'],
        'width'=>$w,'height'=>$h,'bytes'=>$bytes,'format'=>$format
    ]);
}
$logger->info('Vend image fetch complete',['count'=>count($pending)]);
