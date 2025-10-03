<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app.php';

use VapeshedTransfer\Core\Database;
use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\App\Repositories\OutboxRepository;

$logger = new Logger('outbox_dispatcher');
$db = new Database();
$conn = $db->getConnection();
$outbox = new OutboxRepository($conn);

$batchSize = (int)($argv[1] ?? 50);
$webhookEndpoint = getenv('OUTBOX_WEBHOOK_URL') ?: null; // if null, just mark dispatched (internal systems consume differently)

while (true) {
    try {
        $events = $outbox->fetchBatch($batchSize);
        if (!$events) { sleep(2); continue; }
        foreach ($events as $ev) {
            $payload = $ev['payload_json'] ?? '{}';
            $ok = true; $err = null;
            if ($webhookEndpoint) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $webhookEndpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Event-Type: '.$ev['event_type'],
                    'X-Event-Id: '.$ev['event_id']
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $resp = curl_exec($ch);
                $err = curl_error($ch) ?: null;
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                if ($err || $code >= 400) { $ok=false; $err = $err ?: ('http_'.$code); }
            }
            if ($ok) {
                $outbox->markDispatched($ev['event_id']);
                $logger->info('Event dispatched',['event_id'=>$ev['event_id'],'type'=>$ev['event_type']]);
            } else {
                $outbox->markFailed($ev['event_id'], $err ?? 'unknown');
                $logger->warning('Event dispatch failed',['event_id'=>$ev['event_id'],'error'=>$err]);
            }
        }
    } catch (Throwable $e) {
        $logger->error('Dispatcher loop error',['error'=>$e->getMessage()]);
        sleep(5);
    }
}
