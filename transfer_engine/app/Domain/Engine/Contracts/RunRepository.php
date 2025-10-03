<?php
declare(strict_types=1);

namespace App\Domain\Engine\Contracts;

interface RunRepository
{
    /** Persist the run header with idempotency; returns [existing, run_id, status]. */
    public function persistHeader(string $runId, string $mode, string $preset, bool $simulate, array $meta = []): array;

    /** Update run status fields (state, percent, finished_at, etc.). */
    public function updateState(string $runId, array $fields): void;
}
