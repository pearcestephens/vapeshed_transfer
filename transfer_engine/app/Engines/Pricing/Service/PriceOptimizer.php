<?php
declare(strict_types=1);

namespace App\Engines\Pricing\Service;

use App\Engines\Pricing\Config\PricingConfig;
use App\Engines\Pricing\Entity\PriceScenario;
use App\Engines\Pricing\Entity\PriceSimulationResult;
use App\Engines\Pricing\Service\{PriceDataProvider,ElasticityEstimator};

final class PriceOptimizer
{
    public function __construct(
        private readonly PricingConfig $config,
        private readonly PriceDataProvider $dataProvider,
        private readonly ElasticityEstimator $estimator
    ) {}

    public function simulate(string $productId): ?PriceSimulationResult
    {
        $meta = [];
        $pricePoints = $this->dataProvider->getHistoricalPricePoints($productId);
        $productInfo = $this->dataProvider->getProductCostAndCurrentPrice($productId);
        if (!$productInfo) { return null; }
        $cost = $productInfo['cost'];
        $currentPrice = $productInfo['current_price'];

        if (empty($pricePoints)) {
            $meta['reason'] = 'NO_HISTORY';
            $baselineUnits = 0.0;
        } else {
            // choose baseline as price point with highest units (stable demand anchor)
            usort($pricePoints, fn($a,$b)=> $b['daily_units'] <=> $a['daily_units']);
            $baseline = $pricePoints[0];
            $baselineUnits = $baseline['daily_units'];
            $currentPrice = $baseline['price'];
        }

        [$elasticity, $intercept] = $this->estimator->estimate($pricePoints);
        if ($intercept === null) {
            // Recompute intercept using baseline or current price
            $refUnits = $baselineUnits > 0 ? $baselineUnits : max(0.1, $baselineUnits);
            $intercept = $this->estimator->backfillIntercept($elasticity, $currentPrice, max($refUnits, 0.1));
        }

        $scenarios = $this->buildScenarios($productId, $cost, $currentPrice, $baselineUnits ?: 0.1, $elasticity, $intercept);
        if (empty($scenarios)) { return null; }

        // Best profit plain
        $bestProfit = null; $bestRetentionWeighted = null; $bestRetentionScore = -INF;
        foreach ($scenarios as $s) {
            if ($bestProfit === null || $s->dailyProfit > $bestProfit->dailyProfit) { $bestProfit = $s; }
            $retentionWeighted = $s->dailyProfit * $s->predictedRetention; // simplistic retention weighting
            if ($retentionWeighted > $bestRetentionScore) { $bestRetentionScore = $retentionWeighted; $bestRetentionWeighted = $s; }
        }

        return new PriceSimulationResult(
            productId: $productId,
            baselinePrice: $currentPrice,
            baselineUnitsPerDay: $baselineUnits,
            estimatedElasticity: $elasticity,
            scenarios: $scenarios,
            bestProfitScenario: $bestProfit,
            bestRetentionWeightedScenario: $bestRetentionWeighted,
            meta: $meta
        );
    }

    /**
     * Build candidate price ladder and compute scenarios.
     * @return PriceScenario[]
     */
    private function buildScenarios(string $productId, float $cost, float $baselinePrice, float $baselineUnits, float $elasticity, float $intercept): array
    {
        $inc = $this->config->priceIncrement();
        $steps = $this->config->candidateSteps();
        $maxMarkup = $this->config->maxMarkupMultiple();
        $minMargin = $this->config->minMarginPct();

        $minPrice = max($cost * (1 + $minMargin), $baselinePrice * 0.7); // allow a modest decrease
        $maxPrice = min($cost * $maxMarkup, $baselinePrice * 1.6); // cap exploration
        if ($maxPrice <= $minPrice) { $maxPrice = $minPrice + $inc; }

        $range = $maxPrice - $minPrice;
        $scenarios = [];
        for ($i=0;$i<=$steps;$i++) {
            $rawPrice = $minPrice + ($range * $i / $steps);
            // round to increment
            $candidate = round($rawPrice / $inc) * $inc;
            if ($candidate <= 0) { continue; }
            // Predict units: ln q = a + b ln p -> q = exp(a) * p^b
            $predUnits = exp($intercept) * pow($candidate, $elasticity);
            if (!is_finite($predUnits) || $predUnits < 0) { continue; }
            // Retention model
            $changePct = ($candidate - $baselinePrice) / $baselinePrice;
            $retention = $this->retentionFactor($changePct);
            $profitPerUnit = $candidate - $cost;
            if ($profitPerUnit <= 0) { continue; }
            $marginPct = $profitPerUnit / $candidate;
            if ($marginPct < $minMargin) { continue; }
            $dailyProfit = $profitPerUnit * $predUnits * $retention; // retention impacts repeat value
            $scenarios[] = new PriceScenario(
                productId: $productId,
                candidatePrice: $candidate,
                predictedUnitsPerDay: $predUnits,
                predictedRetention: $retention,
                cost: $cost,
                grossMarginPct: $marginPct,
                dailyProfit: $dailyProfit,
                priceChangePct: $changePct,
                elasticityUsed: $elasticity
            );
        }
        return $scenarios;
    }

    private function retentionFactor(float $priceChangePct): float
    {
        if ($priceChangePct <= 0) { return 1.0; } // price decrease or same -> full retention
        $soft = $this->config->softCapPct();
        $hard = $this->config->hardCapPct();
        $penaltyFactor = $this->config->penaltyFactor();
        $floor = $this->config->retentionFloor();
        if ($priceChangePct <= $soft) { return 1.0; }
        if ($priceChangePct >= $hard) { return $floor; }
        $normalized = ($priceChangePct - $soft) / ($hard - $soft); // 0..1
        $drop = $penaltyFactor * $normalized; // portion of max penalty
        $retention = 1.0 - $drop;
        if ($retention < $floor) { $retention = $floor; }
        return $retention;
    }
}
