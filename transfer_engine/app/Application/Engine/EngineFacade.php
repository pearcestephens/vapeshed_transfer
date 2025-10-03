<?php
declare(strict_types=1);

namespace App\Application\Engine;

use App\Domain\Engine\Contracts\EnginePort;
use App\Domain\Engine\DTO\ExecuteRequest;
use App\Domain\Engine\DTO\ExecuteResult;

/**
 * EngineFacade
 * Canonical URL: https://staff.vapeshed.co.nz/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/app/Application/Engine/EngineFacade.php
 *
 * Purpose: Provide a stable API for controllers/APIs to trigger transfers without knowing implementation details.
 * Author: Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * Last Modified: 2025-09-20
 * Dependencies: App\Domain\Engine\Contracts\EnginePort
 */
/**
 * EngineFacade provides a stable API for controllers/APIs to trigger transfers
 * without knowing implementation details. Internally delegates to ports/adapters.
 */
final class EngineFacade
{
    private EnginePort $port;

    public function __construct(EnginePort $port)
    {
        $this->port = $port;
    }

    public function execute(ExecuteRequest $request): ExecuteResult
    {
        return $this->port->execute($request);
    }
}
