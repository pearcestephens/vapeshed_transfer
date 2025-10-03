<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Config\AutoBalancerConfig;

final class ScoringService
{
    public function __construct(private readonly AutoBalancerConfig $config) {}

    /**
     * Compute recommended transfer and urgency.
     * @param array $needy store status
     * @param array $surplus store status
     * @param float $supplyPrice
     */
    public function score(array $needy, array $surplus, float $supplyPrice): array
    {
        $targetDays = $this->config->targetDaysMin();
        $keepDays = $this->config->sourceKeepDays();

        $needyVelocity = $needy['daily_velocity'];
        $surplusVelocity = $surplus['daily_velocity'];
        $neededQty = max(0, ($targetDays * $needyVelocity) - $needy['inventory_level']);
        $canSpare = max(0, $surplus['inventory_level'] - ($keepDays * ($surplusVelocity > 0 ? $surplusVelocity : 0.1)));
        $recommended = (int)max(0, floor(min($neededQty, $canSpare)));
        $value = $recommended * $supplyPrice;

        $urgency = 0; $reasons = [];
        if ($needy['days_of_stock'] <= 1) { $urgency += 100; $reasons[] = 'CRITICAL <1d'; }
        elseif ($needy['days_of_stock'] <= $this->config->lowStockDays()) { $urgency += 50; $reasons[] = 'LOW'; }
        if ($needy['is_high_demand']) { $urgency += 30; $reasons[] = 'DEMAND'; }
        if ($surplus['days_of_stock'] >= $this->config->overstockDays()) { $urgency += 20; $reasons[] = 'SURPLUS'; }

        return [
            'recommended_qty' => $recommended,
            'transfer_value' => $value,
            'urgency_score' => $urgency,
            'reason' => implode(',', $reasons),
        ];
    }
}
