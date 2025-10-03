<?php
declare(strict_types=1);

namespace App\Engines\Pricing\Service;

use App\Engines\Pricing\Config\PricingConfig;

final class ElasticityEstimator
{
    public function __construct(private readonly PricingConfig $config) {}

    /**
     * Estimate elasticity using log-log regression: ln(q) = a + b ln(p); elasticity = b.
     * Returns [elasticity, a_intercept]
     */
    public function estimate(array $pricePoints): array
    {
        if (count($pricePoints) < 2) {
            return [$this->config->fallbackElasticity(), null];
        }
        $xs = []; $ys = [];
        foreach ($pricePoints as $pt) {
            if ($pt['price'] > 0 && $pt['daily_units'] > 0) {
                $xs[] = log($pt['price']);
                $ys[] = log($pt['daily_units']);
            }
        }
        $n = count($xs);
        if ($n < 2) {
            return [$this->config->fallbackElasticity(), null];
        }
        $sumX = array_sum($xs); $sumY = array_sum($ys);
        $sumXX = 0.0; $sumXY = 0.0;
        for ($i=0;$i<$n;$i++) { $sumXX += $xs[$i]*$xs[$i]; $sumXY += $xs[$i]*$ys[$i]; }
        $den = ($n*$sumXX - $sumX*$sumX);
        if (abs($den) < 1e-9) {
            return [$this->config->fallbackElasticity(), null];
        }
        $b = ($n*$sumXY - $sumX*$sumY)/$den; // elasticity (slope)
        $a = ($sumY - $b*$sumX)/$n; // intercept
        // Sanity constraint: elasticity should be negative for normal goods
        if ($b >= -0.05) { // essentially inelastic or positive -> fallback
            $b = $this->config->fallbackElasticity();
            $a = null; // recalc later
        }
        return [$b, $a];
    }

    /**
     * Given elasticity b and one known (price, quantity) point produce intercept a.
     */
    public function backfillIntercept(float $elasticity, float $price, float $dailyUnits): float
    {
        $p = max($price, 0.01); $q = max($dailyUnits, 0.0001);
        // ln q = a + b ln p => a = ln q - b ln p
        return log($q) - $elasticity * log($p);
    }
}
