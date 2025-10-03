<?php
declare(strict_types=1);

namespace App\Domain\Engine\DTO;

/**
 * Canonical URL: https://staff.vapeshed.co.nz/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/app/Domain/Engine/DTO/ExecuteResult.php
 * Result object returned by EnginePort execution.
 */
final class ExecuteResult
{
    public string $runId;
    public bool $accepted;
    public bool $existing;
    public string $status;
    public ?string $message;

    public function __construct(string $runId, bool $accepted, bool $existing = false, string $status = 'queued', ?string $message = null)
    {
        $this->runId = $runId;
        $this->accepted = $accepted;
        $this->existing = $existing;
        $this->status = $status;
        $this->message = $message;
    }
}
