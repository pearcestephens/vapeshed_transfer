<?php
declare(strict_types=1);
/** health.php (Phase M8)
 * Lightweight health endpoint returning JSON.
 */
require_once __DIR__.'/../bin/_cli_bootstrap.php';
$base = __DIR__.'/../src';
foreach ([ 'Support/Logger','Support/Env','Support/Config','Support/Pdo','Health/HealthProbe' ] as $rel){ $p=$base.'/'.$rel.'.php'; if(is_file($p)) require_once $p; }
use Unified\Support\Logger; use Unified\Health\HealthProbe; use Unified\Support\Config;
header('Content-Type: application/json');
Config::prime();
$logger = new Logger('health');
$probe = new HealthProbe($logger);
$res = $probe->check();
http_response_code($res['db_ok']?200:500);
echo json_encode(['service'=>'unified','checks'=>$res,'ts'=>date('c')], JSON_UNESCAPED_SLASHES)."\n";
