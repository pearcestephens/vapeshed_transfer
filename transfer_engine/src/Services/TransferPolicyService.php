<?php
declare(strict_types=1);

namespace Unified\Services;

use RuntimeException;
use Unified\Models\TransferOrder;
use Unified\Repositories\SystemConfigRepository;
use Unified\Repositories\TransferOrderRepository;
use Unified\Support\Logger;

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

        $safetyStockDays = (int)$this->config->get('transfers.safety_stock_days', 7);
        $maxMoveQty = (int)$this->config->get('transfers.max_move_qty', 200);
        $autoCreate = (bool)$this->config->get('transfers.auto_create', false);

        $storeId = $signal['store_id'];
        $sku = $signal['sku'];
        $weeklyDemand = (float)$signal['predicted_weekly_demand'];
        $onHand = (int)$signal['current_on_hand'];
        $leadTime = max(1, (int)($signal['lead_time_days'] ?? 4));
        $horizon = max($leadTime, (int)($signal['forecast_horizon_days'] ?? 14));
        $predictionConfidence = max(0.0, min(1.0, (float)($signal['prediction_confidence'] ?? 0.5)));

        $dailyDemand = $weeklyDemand / 7.0;
        $safetyStockUnits = (int)ceil($dailyDemand * $safetyStockDays);
        $oneWeekDemand = (int)ceil($weeklyDemand);
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

        if ($persist) {
            $order = $this->orders->create($payload);
            $this->logger->info('policy.transfer.created', [
                'transfer_id' => $order->transferId(),
                'store_id' => $storeId,
                'sku' => $sku,
                'quantity' => $quantity,
                'priority' => $priority,
                'confidence' => $confidence,
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
        $horizonFactor = max(0.5, min(1.0, 14 / max(1, $horizonDays)));
        $leadPenalty = max(0.5, min(1.0, ($safetyStockDays + 7) / max(1, $leadTimeDays + $safetyStockDays)));
        $confidence = min(1.0, $predictionConfidence * $horizonFactor * $leadPenalty);
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
}
