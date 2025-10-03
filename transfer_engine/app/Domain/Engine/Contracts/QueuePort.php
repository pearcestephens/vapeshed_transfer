<?php
declare(strict_types=1);

namespace App\Domain\Engine\Contracts;

interface QueuePort
{
    /** Enqueue a job payload and return a job ID. */
    public function enqueue(array $payload): string;

    /** Reserve the next available job or return null. */
    public function reserve(int $timeoutSec = 0): ?array;

    /** Mark a job as completed and clean up. */
    public function complete(string $jobId, array $result = []): void;

    /** Mark a job as failed; may requeue based on adapter policy. */
    public function fail(string $jobId, string $reason, bool $requeue = false): void;
}
