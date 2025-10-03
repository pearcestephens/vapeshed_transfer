<?php
declare(strict_types=1);
/**
 * feature_store_updater.php
 * Consumes outbox events related to product candidate changes and acknowledges
 * them so they do not accumulate. Placeholder for future enrichment logic that
 * will project candidate changes into a feature store table.
 *
 * Exit Codes:
 *  0 success
 *  2 db connection failure (handled inside cli_db())
 */

use VapeshedTransfer\App\Repositories\OutboxRepository;
use VapeshedTransfer\Core\Logger;

require_once __DIR__.'/_cli_bootstrap.php';

$mysqli = cli_db();
$logger = new Logger('feature_store_updater');
$outbox = new OutboxRepository($mysqli);

$batchSize = (int)(getenv('BATCH') ?: 50);
$batch = $outbox->fetchBatch($batchSize);
$acknowledged = 0;
$skipped = 0;
foreach ($batch as $evt) {
    if ($evt['event_type'] !== 'product.candidate.changed') { $skipped++; continue; }
    $payload = json_decode($evt['payload_json'], true) ?: [];
    // TODO: Map candidate -> internal SKU & write to feature store when implemented.
    $outbox->markDispatched($evt['event_id']);
    $acknowledged++;
    $logger->info('Feature store event acknowledged', [ 'event_id'=>$evt['event_id'], 'candidate'=>$payload['candidate_id']??null ]);
}

echo json_encode([
    'success' => true,
    'processed' => count($batch),
    'acknowledged' => $acknowledged,
    'skipped' => $skipped,
    'batch_size' => $batchSize,
    'timestamp' => date('c'),
]) . "\n";

exit(0);
