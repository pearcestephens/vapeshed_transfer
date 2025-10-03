<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app.php';

use VapeshedTransfer\Core\Database;
use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\App\Repositories\JobQueueRepository;
use VapeshedTransfer\App\Repositories\RunExecutionRepository;

$logger = new Logger('job_worker');
$db = new Database();
$conn = $db->getConnection();
$jobs = new JobQueueRepository($conn);
$runs = new RunExecutionRepository($conn);

$queue = $argv[1] ?? 'default';
$loopSeconds = (int)($argv[2] ?? 30);
$leaseSeconds = 90;
$heartbeatEvery = 30;
$lastHeartbeat = time();

$logger->info('Worker started', ['queue'=>$queue]);

function stepHash(string $runId, string $handler, string $jobId): string { return hash('sha256', $runId.'|'.$handler.'|'.$jobId); }

while (true) {
    try {
        // Reclaim expired leases occasionally
        if (random_int(1,20) === 10) { $reclaimed=$jobs->reclaimExpiredLeases(); if($reclaimed>0){ $logger->info('Reclaimed leases',['count'=>$reclaimed]); } }

        $job = $jobs->lease($queue, $leaseSeconds);
        if (!$job) { sleep(1); continue; }

        $payload = $job['payload_json'] ? json_decode($job['payload_json'], true) : [];
        $runId = $job['run_id'];
        $handler = $payload['handler'] ?? null;
        if (!$handler) {
            $logger->warning('Job missing handler', ['job_id'=>$job['job_id']]);
            $jobs->failAndReschedule($job['job_id'], 60, 'missing_handler');
            continue;
        }

        $jobs->startProcessing($job['job_id']);
        $sHash = stepHash($runId, $handler, $job['job_id']);
        if ($runs->alreadyCompleted($runId, $sHash)) {
            $logger->info('Skipping already-completed step', ['job_id'=>$job['job_id']]);
            $jobs->complete($job['job_id']);
            continue;
        }
        $runs->recordStart($runId, 'job_queue', $handler, $sHash);

        // Heartbeat thread simulation
        if (time() - $lastHeartbeat >= $heartbeatEvery) {
            $jobs->heartbeat($job['job_id']);
            $lastHeartbeat = time();
        }

        $started = microtime(true);
        $resultHash = null;
        $ok = false; $errorMsg = null;

        try {
            // Handler dispatch (extend when real handlers exist)
            switch ($handler) {
                case 'noop':
                    usleep(50000); // 50ms
                    $ok = true; $resultHash = hash('sha256','noop');
                    break;
                default:
                    $errorMsg = 'unknown_handler';
            }
        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
        }

        $durationMs = (int)round((microtime(true)-$started)*1000);

        if ($ok) {
            $runs->markComplete($runId, $sHash, $resultHash);
            $jobs->complete($job['job_id']);
            $logger->info('Job completed', ['job_id'=>$job['job_id'],'ms'=>$durationMs]);
        } else {
            $runs->markFailed($runId, $sHash, $errorMsg ?? 'error');
            $retryDelay = (int)min(600, pow(2, max(0,$job['attempt'])) * 5); // bounded exponential backoff
            $jobs->failAndReschedule($job['job_id'], $retryDelay, $errorMsg ?? 'error');
            $logger->warning('Job failed', ['job_id'=>$job['job_id'],'error'=>$errorMsg,'retry_in'=>$retryDelay]);
        }
    } catch (Throwable $e) {
        $logger->error('Worker loop crash',['error'=>$e->getMessage()]);
        sleep(2);
    }
}
