<?php
declare(strict_types=1);
use Unified\Bootstrap;
use Unified\Transfer\{Controllers,BalancerEngine,BalancerService,TransferRepository};
use Unified\Support\Logger;

require_once __DIR__.'/../../src/Bootstrap.php';
Bootstrap::init();
$logger = Bootstrap::get('logger');
$db = Bootstrap::get('db');

$repo = new TransferRepository($db);
$engine = new BalancerEngine();
$service = new BalancerService($engine,$repo,$logger);
$ctrl = new Controllers($service);

$endpoint = $_GET['endpoint'] ?? '';
switch($endpoint){
    case 'transfer/run': $ctrl->run(); break;
    case 'transfer/list': $ctrl->list(); break;
    case 'transfer/export': $ctrl->export(); break;
    default:
        http_response_code(404); header('Content-Type: application/json');
        echo json_encode(['error'=>'unknown endpoint']);
}
