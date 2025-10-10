#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/_cli_bootstrap.php';

use Unified\Agents\AgentScheduler;
use Unified\Repositories\ProductDiscoveryRepository;
use Unified\Repositories\SystemConfigRepository;
use Unified\Repositories\TransferOrderRepository;
use Unified\Services\MonitoringAndAlerting;
use Unified\Services\TransferPolicyService;
use Unified\Services\WebhookEmitter;
use Unified\Support\Logger;
use Unified\Support\Pdo;
use Unified\Neuro\NeuroCore;

$logFile = storage_path('logs/agents.log');
$logger = new Logger('agents', $logFile);

$configRepo = SystemConfigRepository::withDefaults($logger);
$monitoring = MonitoringAndAlerting::withDefaults($logger);
$orderRepo = new TransferOrderRepository(Pdo::instance(), $logger, $monitoring);
$policy = new TransferPolicyService($orderRepo, $configRepo, $logger);
$discovery = ProductDiscoveryRepository::withDefaults($logger);
$neuro = new NeuroCore($configRepo, $logger);
$webhooks = WebhookEmitter::withDefaults($logger);

$scheduler = AgentScheduler::build($discovery, $policy, $neuro, $logger, $webhooks);
$results = $scheduler->runAll();

$created = array_reduce($results, static function (int $carry, array $result): int {
    return $carry + (int)($result['created'] ?? 0);
}, 0);

$summary = [
    'timestamp' => date(DATE_ATOM),
    'created_transfers' => $created,
    'agents' => $results,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
$logger->info('agents.run.summary', $summary);
