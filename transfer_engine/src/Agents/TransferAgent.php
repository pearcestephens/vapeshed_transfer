<?php
declare(strict_types=1);

namespace Unified\Agents;

use Unified\Models\TransferOrder;
use Unified\Neuro\NeuroCore;
use Unified\Repositories\ProductDiscoveryRepository;
use Unified\Services\TransferPolicyService;
use Unified\Services\WebhookEmitter;
use Unified\Support\Logger;

/**
 * TransferAgent
 * Autonomous agent that proposes transfer orders based on demand risk.
 */
final class TransferAgent extends BaseAgent
{
    public function __construct(
        private readonly ProductDiscoveryRepository $discovery,
        private readonly TransferPolicyService $policy,
        private readonly NeuroCore $neuro,
        Logger $logger,
        private readonly ?WebhookEmitter $webhooks = null
    ) {
        parent::__construct($logger);
    }

    /** @inheritDoc */
    protected function sense(array $context): array
    {
        $signals = $this->discovery->findAtRisk(20, $context['store_id'] ?? null);
        $this->logger->debug('agent.transfer.sense', [
            'signals' => count($signals),
            'store_filter' => $context['store_id'] ?? null,
        ]);
        return $signals;
    }

    /** @inheritDoc */
    protected function decide(array $signals, array $context): array
    {
        if ($signals === []) {
            return [];
        }

        $directives = $this->neuro->directives();
        $exploration = $directives['exploration_weight'] ?? 0.0;
        $demandFocus = $directives['demand_focus_weight'] ?? 0.0;

        if ($exploration >= 0.8 || $demandFocus < 0.5) {
            $this->logger->info('agent.transfer.skip.directives', [
                'exploration_weight' => $exploration,
                'demand_focus_weight' => $demandFocus,
            ]);
            return [];
        }

        $bias = $directives['transfer_bias'] ?? 0.45;
        $bias = max(0.1, min(1.0, $bias));
        $limit = max(1, (int)ceil(count($signals) * $bias));

        return array_slice($signals, 0, $limit);
    }

    /** @inheritDoc */
    protected function act(array $decisions, array $context): array
    {
        $created = [];
        foreach ($decisions as $signal) {
            $order = $this->policy->propose($signal, true);
            if ($order instanceof TransferOrder) {
                $created[] = $order;
                $this->emitWebhook('transfer.created', $order);
            }
        }

        return [
            'agent' => 'TransferAgent',
            'attempted' => count($decisions),
            'created' => count($created),
            'transfer_ids' => array_map(fn (TransferOrder $order) => $order->transferId(), $created),
        ];
    }

    private function emitWebhook(string $event, TransferOrder $order): void
    {
        if ($this->webhooks === null) {
            return;
        }

        $payload = [
            'event' => $event,
            'transfer' => $order->toArray(),
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
        $this->webhooks->emit($event, $payload);
    }
}
