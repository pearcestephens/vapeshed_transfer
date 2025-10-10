<?php
declare(strict_types=1);

namespace Unified\Agents;

use Unified\Support\Logger;

/**
 * BaseAgent
 * Template for autonomous agents.
 */
abstract class BaseAgent
{
    public function __construct(protected readonly Logger $logger)
    {
    }

    final public function run(array $context = []): array
    {
        $signals = $this->sense($context);
        $decisions = $this->decide($signals, $context);
        return $this->act($decisions, $context);
    }

    /**
     * @return array<mixed>
     */
    abstract protected function sense(array $context): array;

    /**
     * @param array<mixed> $signals
     * @return array<mixed>
     */
    abstract protected function decide(array $signals, array $context): array;

    /**
     * @param array<mixed> $decisions
     * @return array<mixed>
     */
    abstract protected function act(array $decisions, array $context): array;
}
