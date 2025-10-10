<?php
declare(strict_types=1);

namespace Unified\Services\Idempotency;

/**
 * IdempotencyKey
 * Deterministic key generator for transfer creation requests.
 */
final class IdempotencyKey
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromSignal(
        string $storeId,
        string $sku,
        int $qty,
        int $horizonDays,
        int $safetyDays,
        string $sourceHub,
        string $purpose = 'transfer.create'
    ): self {
        $parts = [
            'v1', // version for future schema changes
            trim($purpose),
            trim($storeId),
            trim($sku),
            (string) max(0, $qty),
            (string) max(0, $horizonDays),
            (string) max(0, $safetyDays),
            trim($sourceHub),
        ];

        $canonical = implode('|', $parts);
        return new self(hash('sha256', $canonical));
    }

    public function value(): string
    {
        return $this->value;
    }
}
