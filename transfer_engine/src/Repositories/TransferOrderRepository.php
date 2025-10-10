<?php
declare(strict_types=1);

namespace Unified\Repositories;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Unified\Models\TransferOrder;
use Unified\Services\MonitoringAndAlerting;
use Unified\Support\Logger;
use Unified\Support\Pdo;

/**
 * TransferOrderRepository
 * Persistence layer for transfer_orders domain objects.
 */
final class TransferOrderRepository
{
    private const STATUSES = [
        TransferOrder::STATUS_PROPOSED,
        TransferOrder::STATUS_APPROVED,
        TransferOrder::STATUS_COMMITTED,
        TransferOrder::STATUS_IN_TRANSIT,
        TransferOrder::STATUS_RECEIVED,
        TransferOrder::STATUS_CANCELLED,
    ];

    private const PRIORITIES = [
        TransferOrder::PRIORITY_LOW,
        TransferOrder::PRIORITY_NORMAL,
        TransferOrder::PRIORITY_HIGH,
        TransferOrder::PRIORITY_CRITICAL,
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly Logger $logger,
        private ?MonitoringAndAlerting $monitoring = null
    ) {
    }

    public static function withDefaults(Logger $logger): self
    {
        return new self(Pdo::instance(), $logger);
    }

    public function attachMonitoring(MonitoringAndAlerting $monitoring): void
    {
        $this->monitoring = $monitoring;
    }

    /**
     * @param array{transfer_id?:string,source_hub:string,dest_store:string,status?:string,priority?:string,reason?:array|null,confidence?:float,requested_by?:string|null,lines:array<int,array{sku:string,qty:int,uom?:string,rationale?:array|null}>} $payload
     */
    public function create(array $payload): TransferOrder
    {
        $transferId = $payload['transfer_id'] ?? $this->generateTransferId();
        $status = $payload['status'] ?? TransferOrder::STATUS_PROPOSED;
        $priority = $payload['priority'] ?? TransferOrder::PRIORITY_NORMAL;

        $this->guardStatus($status);
        $this->guardPriority($priority);

        $reason = $payload['reason'] ?? null;
        $confidence = isset($payload['confidence']) ? (float)$payload['confidence'] : 0.0;
        $requestedBy = $payload['requested_by'] ?? null;
        $lines = $payload['lines'] ?? [];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO transfer_orders (transfer_id, source_hub, dest_store, status, priority, reason, confidence, requested_by) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $transferId,
                $payload['source_hub'],
                $payload['dest_store'],
                $status,
                $priority,
                $reason !== null ? json_encode($reason, JSON_UNESCAPED_SLASHES) : null,
                $confidence,
                $requestedBy,
            ]);

            $this->insertLines($transferId, $lines);
            $this->appendAudit($transferId, 'created', null, $status, $requestedBy, null, [
                'priority' => $priority,
                'confidence' => $confidence,
            ]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Failed to create transfer order: ' . $e->getMessage(), 0, $e);
        }

        $order = $this->getByTransferId($transferId);
        if ($order !== null && $this->monitoring !== null) {
            $this->monitoring->incrementTransfersCreated();
            $this->monitoring->refreshTransferPendingGauge();
        }

        $this->logger->info('transfer_order.create', [
            'transfer_id' => $transferId,
            'status' => $status,
            'priority' => $priority,
            'lines' => count($lines),
        ]);

        return $order ?? TransferOrder::fromPayload([
            'transfer_id' => $transferId,
            'source_hub' => $payload['source_hub'],
            'dest_store' => $payload['dest_store'],
            'status' => $status,
            'priority' => $priority,
            'reason' => $reason,
            'confidence' => $confidence,
            'requested_by' => $requestedBy,
            'lines' => $lines,
            'created_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'updated_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ]);
    }

    /**
     * @param array<int,array{sku:string,qty:int,uom?:string,rationale?:array|null}> $lines
     */
    public function addLines(string $transferId, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->insertLines($transferId, $lines);

        $this->logger->info('transfer_order.add_lines', [
            'transfer_id' => $transferId,
            'count' => count($lines),
        ]);
    }

    public function getByTransferId(string $transferId): ?TransferOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transfer_orders WHERE transfer_id = ? LIMIT 1');
        $stmt->execute([$transferId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $lines = $this->fetchLines($transferId);
        $row['reason'] = $row['reason'] ? json_decode($row['reason'], true) : null;
        $row['confidence'] = isset($row['confidence']) ? (float)$row['confidence'] : 0.0;
        $row['lines'] = $lines;

        return TransferOrder::fromPayload($row);
    }

    /**
     * @return array<int,TransferOrder>
     */
    public function list(array $filters = []): array
    {
        $sql = 'SELECT * FROM transfer_orders';
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'status = ?';
            $params[] = (string)$filters['status'];
        }
        if (!empty($filters['dest_store'])) {
            $conditions[] = 'dest_store = ?';
            $params[] = (string)$filters['dest_store'];
        }
        if (!empty($filters['priority'])) {
            $conditions[] = 'priority = ?';
            $params[] = (string)$filters['priority'];
        }
        if (!empty($filters['since'])) {
            $since = $filters['since'];
            if ($since instanceof DateTimeImmutable) {
                $conditions[] = 'created_at >= ?';
                $params[] = $since->format('Y-m-d H:i:s');
            } else {
                $conditions[] = 'created_at >= ?';
                $params[] = (string)$since;
            }
        }
        if (!empty($filters['until'])) {
            $until = $filters['until'];
            if ($until instanceof DateTimeImmutable) {
                $conditions[] = 'created_at <= ?';
                $params[] = $until->format('Y-m-d H:i:s');
            } else {
                $conditions[] = 'created_at <= ?';
                $params[] = (string)$filters['until'];
            }
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY created_at DESC';

        $limit = isset($filters['limit']) ? max(1, min((int)$filters['limit'], 500)) : 100;
        $offset = isset($filters['offset']) ? max((int)$filters['offset'], 0) : 0;
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $orders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transferId = (string)$row['transfer_id'];
            $row['reason'] = $row['reason'] ? json_decode($row['reason'], true) : null;
            $row['confidence'] = isset($row['confidence']) ? (float)$row['confidence'] : 0.0;
            $row['lines'] = $this->fetchLines($transferId);
            $orders[] = TransferOrder::fromPayload($row);
        }

        return $orders;
    }

    public function updateStatus(string $transferId, string $newStatus, ?string $actor = null, ?string $note = null): bool
    {
        $this->guardStatus($newStatus);

        $current = $this->getByTransferId($transferId);
        if ($current === null) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE transfer_orders SET status = ?, updated_at = NOW() WHERE transfer_id = ?');
        $stmt->execute([$newStatus, $transferId]);
        $updated = $stmt->rowCount() > 0;

        if ($updated) {
            $this->appendAudit($transferId, 'status_changed', $current->status(), $newStatus, $actor, $note);
            $this->logger->info('transfer_order.update_status', [
                'transfer_id' => $transferId,
                'from' => $current->status(),
                'to' => $newStatus,
                'actor' => $actor,
            ]);

            if ($this->monitoring !== null) {
                $this->monitoring->refreshTransferPendingGauge();
                if ($newStatus === TransferOrder::STATUS_COMMITTED) {
                    $this->monitoring->incrementTransfersCommitted();
                }
            }
        }

        return $updated;
    }

    public function appendAudit(
        string $transferId,
        string $eventType,
        ?string $statusFrom = null,
        ?string $statusTo = null,
        ?string $actor = null,
        ?string $note = null,
        ?array $payload = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO transfer_order_audit (transfer_id, event_type, status_from, status_to, actor, note, payload) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $transferId,
            $eventType,
            $statusFrom,
            $statusTo,
            $actor,
            $note,
            $payload !== null ? json_encode($payload, JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    /**
     * @return array<int,array{sku:string,qty:int,uom:string,rationale:array|null}>
     */
    private function fetchLines(string $transferId): array
    {
        $stmt = $this->pdo->prepare('SELECT sku, qty, uom, rationale FROM transfer_lines WHERE transfer_id = ? ORDER BY id ASC');
        $stmt->execute([$transferId]);

        $lines = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = [
                'sku' => (string)$row['sku'],
                'qty' => (int)$row['qty'],
                'uom' => (string)$row['uom'],
                'rationale' => $row['rationale'] ? json_decode($row['rationale'], true) : null,
            ];
        }

        return $lines;
    }

    /**
     * @param array<int,array{sku:string,qty:int,uom?:string,rationale?:array|null}> $lines
     */
    private function insertLines(string $transferId, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO transfer_lines (transfer_id, sku, qty, uom, rationale) VALUES (?,?,?,?,?)'
        );

        foreach ($lines as $line) {
            $stmt->execute([
                $transferId,
                (string)$line['sku'],
                (int)$line['qty'],
                isset($line['uom']) ? (string)$line['uom'] : 'ea',
                isset($line['rationale']) && $line['rationale'] !== null ? json_encode($line['rationale'], JSON_UNESCAPED_SLASHES) : null,
            ]);
        }
    }

    private function guardStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Invalid transfer status: ' . $status);
        }
    }

    private function guardPriority(string $priority): void
    {
        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new RuntimeException('Invalid transfer priority: ' . $priority);
        }
    }

    private function generateTransferId(): string
    {
        return 'TR_' . strtoupper(bin2hex(random_bytes(6)));
    }
}
