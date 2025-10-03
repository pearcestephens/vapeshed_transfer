<?php
declare(strict_types=1);

namespace App\Domain\Engine\DTO;

/**
 * Canonical URL: https://staff.vapeshed.co.nz/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/app/Domain/Engine/DTO/ExecuteRequest.php
 * Value object for executing the engine.
 */
final class ExecuteRequest
{
    public ?string $runId;
    public string $mode;
    public string $preset;
    public bool $testMode;
    public bool $simulate;
    public ?string $previousRunId;
    public string $requestedBy;

    public function __construct(string $mode, string $preset, bool $testMode, bool $simulate, ?string $previousRunId, string $requestedBy, ?string $runId = null)
    {
        $this->runId = $runId;
        $this->mode = $mode;
        $this->preset = $preset;
        $this->testMode = $testMode;
        $this->simulate = $simulate;
        $this->previousRunId = $previousRunId;
        $this->requestedBy = $requestedBy;
    }
}
