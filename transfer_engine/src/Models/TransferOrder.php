<?php
declare(strict_types=1);

namespace Unified\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * TransferOrder.php
 * Value object representing a proposed or in-flight transfer.
 */
final class TransferOrder
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    /**
     * @param array<int,array{sku:string,qty:int,uom:string,rationale:array|null}> $lines
     * @param array<string,mixed>|null $reason
     */
    public function __construct(
        private readonly string $transferId,
        private readonly string $sourceHub,
        private readonly string $destStore,
        private readonly string $status,
        private readonly string $priority,
        private readonly ?array $reason,
        private readonly float $confidence,
        private readonly ?string $requestedBy,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly array $lines
    ) {
        $this->assertStatus($status);
        $this->assertPriority($priority);
        $this->assertConfidence($confidence);
    }

    public static function fromPayload(array $payload): self
    {
        $created = self::parseDate($payload['created_at'] ?? null);
        $updated = self::parseDate($payload['updated_at'] ?? null) ?? $created ?? new DateTimeImmutable();

        return new self(
            transferId: (string)($payload['transfer_id'] ?? $payload['id'] ?? ''),
            sourceHub: (string)($payload['source_hub'] ?? ''),
            destStore: (string)($payload['dest_store'] ?? ''),
            status: (string)($payload['status'] ?? self::STATUS_PROPOSED),
            priority: (string)($payload['priority'] ?? self::PRIORITY_NORMAL),
            reason: isset($payload['reason']) && $payload['reason'] !== null ? (array)$payload['reason'] : null,
            confidence: isset($payload['confidence']) ? (float)$payload['confidence'] : 0.0,
            requestedBy: isset($payload['requested_by']) ? (string)$payload['requested_by'] : null,
            createdAt: $created ?? new DateTimeImmutable(),
            updatedAt: $updated,
            lines: self::normaliseLines($payload['lines'] ?? [])
        );
    }

    /**
     * @return array<int,array{sku:string,qty:int,uom:string,rationale:array|null}>
     */
    public static function normaliseLines(array $lines): array
    {
        $normalised = [];
        foreach ($lines as $line) {
            $normalised[] = [
                'sku' => (string)($line['sku'] ?? ''),
                'qty' => (int)($line['qty'] ?? 0),
                'uom' => (string)($line['uom'] ?? 'ea'),
                'rationale' => isset($line['rationale']) && $line['rationale'] !== null ? (array)$line['rationale'] : null,
            ];
        }
        return $normalised;
    }

    public function withStatus(string $status): self
    {
        $this->assertStatus($status);
        return new self(
            $this->transferId,
            $this->sourceHub,
            $this->destStore,
            $status,
            $this->priority,
            $this->reason,
            $this->confidence,
            $this->requestedBy,
            $this->createdAt,
            new DateTimeImmutable(),
            $this->lines
        );
    }

    public function withLines(array $lines): self
    {
        return new self(
            $this->transferId,
            $this->sourceHub,
            $this->destStore,
            $this->status,
            $this->priority,
            $this->reason,
            $this->confidence,
            $this->requestedBy,
            $this->createdAt,
            $this->updatedAt,
            self::normaliseLines($lines)
        );
    }

    public function transferId(): string
    {
        return $this->transferId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function priority(): string
    {
        return $this->priority;
    }

    public function destStore(): string
    {
        return $this->destStore;
    }

    public function sourceHub(): string
    {
        return $this->sourceHub;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function reason(): ?array
    {
        return $this->reason;
    }

    public function requestedBy(): ?string
    {
        return $this->requestedBy;
    }

    /**
     * @return array<int,array{sku:string,qty:int,uom:string,rationale:array|null}>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'source_hub' => $this->sourceHub,
            'dest_store' => $this->destStore,
            'status' => $this->status,
            'priority' => $this->priority,
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'requested_by' => $this->requestedBy,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            'lines' => $this->lines,
        ];
    }

    private function assertStatus(string $status): void
    {
        $allowed = [
            self::STATUS_PROPOSED,
            self::STATUS_APPROVED,
            self::STATUS_COMMITTED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_RECEIVED,
            self::STATUS_CANCELLED,
        ];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Invalid transfer status "%s"', $status));
        }
    }

    private function assertPriority(string $priority): void
    {
        $allowed = [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_CRITICAL];
        if (!in_array($priority, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Invalid priority "%s"', $priority));
        }
    }

    private function assertConfidence(float $confidence): void
    {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException('Confidence must be between 0.0 and 1.0');
        }
    }

    private static function parseDate(?string $input): ?DateTimeImmutable
    {
        if (!$input) {
            return null;
        }
        try {
            return new DateTimeImmutable($input);
        } catch (\Exception) {
            return null;
        }
    }
}
