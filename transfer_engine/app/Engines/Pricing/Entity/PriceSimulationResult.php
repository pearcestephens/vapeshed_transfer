<?php
declare(strict_types=1);

namespace App\Engines\Pricing\Entity;

final class PriceSimulationResult
{
    /** @param PriceScenario[] $scenarios */
    public function __construct(
        public readonly string $productId,
        public readonly float $baselinePrice,
        public readonly float $baselineUnitsPerDay,
        public readonly float $estimatedElasticity,
        public readonly array $scenarios,
        public readonly ?PriceScenario $bestProfitScenario,
        public readonly ?PriceScenario $bestRetentionWeightedScenario,
        public readonly array $meta
    ) {}
}
