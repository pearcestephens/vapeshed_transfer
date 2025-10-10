<?php
declare(strict_types=1);

namespace Unified\Services;

use RuntimeException;
use Unified\Models\TransferOrder;
use Unified\Repositories\SystemConfigRepository;
use Unified\Repositories\TransferOrderRepository;
use Unified\Support\Logger;
use Unified\Services\Idempotency\IdempotencyKey;

/**
 * TransferPolicyService
 * Converts demand signals into proposed transfer orders.
 */
final class TransferPolicyService
{
    private const CONFIDENCE_THRESHOLD = 0.70;

    public function __construct(
        private readonly TransferOrderRepository $orders,
        private readonly SystemConfigRepository $config,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param array{
     *   store_id:string,
     *   sku:string,
     *   predicted_weekly_demand:float|int,
     *   current_on_hand:int,
     *   reserved?:int,
     *   lead_time_days?:int,
     *   prediction_confidence?:float,
     *   forecast_horizon_days?:int,
     *   source_hub?:string,
     *   requested_by?:string,
     *   uom?:string
     * } $signal
     */
    public function propose(array $signal, bool $persist = true): ?TransferOrder
    {
        $this->assertSignal($signal);

        // Global defaults
        $globalSafetyDays = (int)$this->config->get('transfers.safety_stock_days', 7);
        $globalMaxMoveQty = (int)$this->config->get('transfers.max_move_qty', 200);
        $autoCreate = (bool)$this->config->get('transfers.auto_create', false);
        $duplicateWindowHours = (int)$this->config->get('transfers.duplicate_window_hours', 6);

        $storeId = $signal['store_id'];
        $sku = $signal['sku'];
        $weeklyDemand = $this->finite((float)$signal['predicted_weekly_demand']);
        $onHand = max(0, (int)$signal['current_on_hand']);
        $leadTime = max(1, (int)($signal['lead_time_days'] ?? 4));
        $horizon = max($leadTime, (int)($signal['forecast_horizon_days'] ?? 14));
        $predictionConfidence = $this->clamp01((float)($signal['prediction_confidence'] ?? 0.5));

        // Resolve overrides: SKU > Store > Global
        $skuSafety = $this->config->get('transfers.overrides.sku.' . $sku . '.safety_stock_days');
        $storeSafety = $this->config->get('transfers.overrides.store.' . $storeId . '.safety_stock_days');
        $safetyStockDays = (int)($skuSafety ?? $storeSafety ?? $globalSafetyDays);

        $skuMaxMove = $this->config->get('transfers.overrides.sku.' . $sku . '.max_move_qty');
        $storeMaxMove = $this->config->get('transfers.overrides.store.' . $storeId . '.max_move_qty');
        $maxMoveQty = max(1, (int)($skuMaxMove ?? $storeMaxMove ?? $globalMaxMoveQty));

        $dailyDemand = $this->finite($weeklyDemand / 7.0);
        $safetyStockUnits = (int)ceil(max(0.0, $dailyDemand) * max(0, $safetyStockDays));
        $oneWeekDemand = (int)ceil(max(0.0, $weeklyDemand));
        $requiredUnits = max(0, $safetyStockUnits + $oneWeekDemand - $onHand);

        if ($requiredUnits <= 0) {
            $this->logger->debug('policy.skip.no_need', [
                'store_id' => $storeId,
                'sku' => $sku,
                'on_hand' => $onHand,
                'weekly_demand' => $weeklyDemand,
                'safety_stock_units' => $safetyStockUnits,
            ]);
            return null;
        }

        $quantity = max(1, min($requiredUnits, $maxMoveQty));

        $confidence = $this->calculateConfidence($predictionConfidence, $horizon, $leadTime, $safetyStockDays);
        if ($confidence < self::CONFIDENCE_THRESHOLD && !$autoCreate) {
            $this->logger->info('policy.skip.confidence', [
                'store_id' => $storeId,
                'sku' => $sku,
                'confidence' => $confidence,
            ]);
            return null;
        }

        $priority = $this->determinePriority($quantity, $maxMoveQty, $confidence, $onHand);
        $sourceHub = $signal['source_hub'] ?? (string)$this->config->get('transfers.default_source_hub', 'HUB_MAIN');

        // Optional duplicate window suppression: find similar open transfer in window
        if ($persist && $this->duplicateWindowHit($storeId, $sku, $quantity, $duplicateWindowHours)) {
            $this->logger->info('transfer.skip', [
                'store_id' => $storeId,
                'sku' => $sku,
                'reason' => 'duplicate_window_hit',
                'required_units' => $requiredUnits,
                'on_hand' => $onHand,
            ]);
            return null;
        }

        $reason = [
            'type' => 'forecast_replenishment',
            'window_days' => $horizon,
            'safety_stock_days' => $safetyStockDays,
            'lead_time_days' => $leadTime,
            'required_units' => $requiredUnits,
            'trigger' => [
                'predicted_weekly_demand' => $weeklyDemand,
                'current_on_hand' => $onHand,
                'reserved' => (int)($signal['reserved'] ?? 0),
            ],
            'preview' => $persist ? false : true,
        ];

        $line = [
            'sku' => $sku,
            'qty' => $quantity,
            'uom' => $signal['uom'] ?? 'ea',
            'rationale' => [
                'safety_stock_breach' => true,
                'forecast_weekly' => $weeklyDemand,
                'safety_stock_units' => $safetyStockUnits,
                'lead_time_days' => $leadTime,
                'computed_confidence' => $confidence,
            ],
        ];

        $payload = [
            'transfer_id' => $this->generateTransferId($storeId, $sku),
            'source_hub' => $sourceHub,
            'dest_store' => $storeId,
            'status' => TransferOrder::STATUS_PROPOSED,
            'priority' => $priority,
            'reason' => $reason,
            'confidence' => $confidence,
            'requested_by' => $signal['requested_by'] ?? 'transfer_policy',
            'lines' => [$line],
        ];

        // Idempotency key
        $idem = IdempotencyKey::fromSignal(
            storeId: $storeId,
            sku: $sku,
            qty: $quantity,
            horizonDays: $horizon,
            safetyDays: $safetyStockDays,
            sourceHub: $sourceHub,
            purpose: 'transfer.create'
        )->value();
        $payload['idempotency_key'] = $idem;

        if ($persist) {
            $order = $this->orders->create($payload);
            $idemFlag = $order->transferId() === $payload['transfer_id'] ? 'first' : 'duplicate';
            $this->logger->info('transfer.create', [
                'transfer_id' => $order->transferId(),
                'store_id' => $storeId,
                'sku' => $sku,
                'qty' => $quantity,
                'priority' => $priority,
                'confidence' => $confidence,
                'idem' => $idemFlag,
            ]);
            return $order;
        }

        return TransferOrder::fromPayload($payload);
    }

    /**
     * @param array<string,mixed> $signal
     */
    private function assertSignal(array $signal): void
    {
        foreach (['store_id', 'sku', 'predicted_weekly_demand', 'current_on_hand'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $signal)) {
                throw new RuntimeException('Transfer signal missing key: ' . $requiredKey);
            }
        }
    }

    private function calculateConfidence(float $predictionConfidence, int $horizonDays, int $leadTimeDays, int $safetyStockDays): float
    {
        $horizonSafe = max(1, $horizonDays);
        $horizonFactor = $this->clamp01(14 / $horizonSafe);
        $leadDen = max(1, $leadTimeDays + max(0, $safetyStockDays));
        $leadPenalty = $this->clamp01(($safetyStockDays + 7) / $leadDen);
        $confidence = $this->clamp01($this->finite($predictionConfidence) * $horizonFactor * $leadPenalty);
        return round($confidence, 3);
    }

    private function determinePriority(int $quantity, int $maxMoveQty, float $confidence, int $onHand): string
    {
        if ($quantity >= max(1, (int)round($maxMoveQty * 0.9))) {
            return TransferOrder::PRIORITY_CRITICAL;
        }

        if ($confidence >= 0.9 || $onHand <= 2) {
            return TransferOrder::PRIORITY_HIGH;
        }

        if ($quantity >= max(1, (int)round($maxMoveQty * 0.5))) {
            return TransferOrder::PRIORITY_HIGH;
        }

        return $confidence >= 0.75 ? TransferOrder::PRIORITY_NORMAL : TransferOrder::PRIORITY_LOW;
    }

    private function generateTransferId(string $storeId, string $sku): string
    {
        return sprintf('TR_%s_%s_%s', $storeId, $sku, strtoupper(bin2hex(random_bytes(3))));
    }

    private function clamp01(float $v): float
    {
        if (!is_finite($v)) {
            return 0.0;
        }
        return max(0.0, min(1.0, $v));
    }

    private function finite(float $v): float
    {
        return is_finite($v) ? $v : 0.0;
    }

    private function duplicateWindowHit(string $storeId, string $sku, int $qty, int $windowHours): bool
    {
        // Lightweight scan via repository SQL (if exists); fallback to false to avoid blocking
        try {
            $pdo = new \ReflectionProperty($this->orders, 'pdo');
            $pdo->setAccessible(true);
            /** @var \PDO $conn */
            $conn = $pdo->getValue($this->orders);

            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $hours = max(0, (int)$windowHours);
                $sql = 'SELECT t.transfer_id, t.status, l.qty
                        FROM transfer_orders t
                        JOIN transfer_lines l ON l.transfer_id = t.transfer_id
                        WHERE t.dest_store = :dest AND l.sku = :sku AND t.created_at >= datetime("now", :offset)
                        ORDER BY t.created_at DESC LIMIT 1';
                $stmt = $conn->prepare($sql);
                $offset = sprintf('-%d hours', $hours);
                $stmt->bindValue(':dest', $storeId);
                $stmt->bindValue(':sku', $sku);
                $stmt->bindValue(':offset', $offset);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare(
                    'SELECT t.transfer_id, t.status, l.qty
                     FROM transfer_orders t
                     JOIN transfer_lines l ON l.transfer_id = t.transfer_id
                     WHERE t.dest_store = ? AND l.sku = ? AND t.created_at >= (NOW() - INTERVAL ? HOUR)
                     ORDER BY t.created_at DESC LIMIT 1'
                );
                $stmt->execute([$storeId, $sku, $windowHours]);
            }
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $existingQty = (int)$row['qty'];
                $delta = abs($existingQty - $qty);
                $within10pct = $existingQty > 0 ? ($delta / $existingQty) <= 0.10 : ($qty <= 1);
                return $within10pct;
            }
        } catch (\Throwable) {
            // Best-effort; do not block creation on failure
        }
        return false;
    }
}
