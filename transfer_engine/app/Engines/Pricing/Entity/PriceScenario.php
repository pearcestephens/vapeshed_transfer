<?php
declare(strict_types=1);

namespace App\Engines\Pricing\Entity;

final class PriceScenario
{
    public function __construct(
        public readonly string $productId,
        public readonly float $candidatePrice,
        public readonly float $predictedUnitsPerDay,
        public readonly float $predictedRetention,
        public readonly float $cost,
        public readonly float $grossMarginPct,
        public readonly float $dailyProfit,
        public readonly float $priceChangePct,
        public readonly float $elasticityUsed
    ) {}
}
