<?php
declare(strict_types=1);

namespace Unified\Services;

use Unified\Models\TransferOrder;
use Unified\Repositories\ProductDiscoveryRepository;
use Unified\Support\Logger;

/**
 * AcquisitionOrchestratorService
 * High-level coordinator that converts risk signals into transfer proposals.
 */
final class AcquisitionOrchestratorService
{
    public function __construct(
        private readonly ProductDiscoveryRepository $discovery,
        private readonly TransferPolicyService $policy,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array<int,TransferOrder>
     */
    public function proposeTransfers(int $limit = 20, ?string $storeId = null): array
    {
        $signals = $this->discovery->findAtRisk($limit, $storeId);
        $orders = [];

        foreach ($signals as $signal) {
            try {
                $order = $this->policy->propose($signal, true);
                if ($order instanceof TransferOrder) {
                    $orders[] = $order;
                }
            } catch (\Throwable $e) {
                $this->logger->error('orchestrator.transfer.error', [
                    'store_id' => $signal['store_id'],
                    'sku' => $signal['sku'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('orchestrator.transfer.summary', [
            'signals' => count($signals),
            'created' => count($orders),
            'store_filter' => $storeId,
        ]);

        return $orders;
    }
}
