<?php
declare(strict_types=1);

namespace Unified\Agents;

use Unified\Neuro\NeuroCore;
use Unified\Repositories\ProductDiscoveryRepository;
use Unified\Services\TransferPolicyService;
use Unified\Services\WebhookEmitter;
use Unified\Support\Logger;

/**
 * AgentScheduler
 * Executes registered agents sequentially and aggregates results.
 */
final class AgentScheduler
{
    /** @var array<int,BaseAgent> */
    private array $agents = [];

    public function __construct(private readonly Logger $logger)
    {
    }

    public static function build(
        ProductDiscoveryRepository $discovery,
        TransferPolicyService $policy,
        NeuroCore $neuro,
        Logger $logger,
        ?WebhookEmitter $webhooks = null
    ): self {
        $scheduler = new self($logger);
        $scheduler->register(new TransferAgent($discovery, $policy, $neuro, $logger, $webhooks));
        return $scheduler;
    }

    public function register(BaseAgent $agent): void
    {
        $this->agents[] = $agent;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function runAll(array $context = []): array
    {
        $results = [];
        foreach ($this->agents as $agent) {
            $start = microtime(true);
            $result = $agent->run($context);
            $results[] = $result;
            $this->logger->info('agent.run.complete', [
                'agent' => $result['agent'] ?? $agent::class,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'output' => $result,
            ]);
        }

        return $results;
    }
}
