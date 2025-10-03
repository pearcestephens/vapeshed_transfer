<?php
declare(strict_types=1);

namespace App\Domain\Engine\Contracts;

use App\Domain\Engine\DTO\ExecuteRequest;
use App\Domain\Engine\DTO\ExecuteResult;

/**
 * Canonical URL: https://staff.vapeshed.co.nz/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/app/Domain/Engine/Contracts/EnginePort.php
 * Port for executing the transfer engine use case.
 */
/**
 * Port for executing the transfer engine use case.
 */
interface EnginePort
{
    /**
     * Execute a transfer run and return a result. Implementations may run synchronously or enqueue work.
     */
    public function execute(ExecuteRequest $request): ExecuteResult;
}
