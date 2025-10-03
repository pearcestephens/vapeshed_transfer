<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Entity\InventoryItem;
use App\Engines\AutoBalancer\Entity\Store;
use App\Engines\AutoBalancer\Config\AutoBalancerConfig;

final class SituationAnalyzer
{
    public function __construct(private readonly AutoBalancerConfig $config) {}

    /**
     * Annotate inventory items with velocity, days-of-stock & classification flags.
     * @param Store $store
     * @param InventoryItem[] $items
     * @param array $velocityMap outlet_id => product_id => ['daily'=>x,'weekly'=>y]
     * @return InventoryItem[] mutated items
     */
    public function analyze(Store $store, array $items, array $velocityMap): array
    {
        $lowDays = $this->config->lowStockDays();
        $overDays = $this->config->overstockDays();
        $mult = $this->config->highDemandMultiplier();

        foreach ($items as $item) {
            $v = $velocityMap[$store->id][$item->productId] ?? [];
            $daily = isset($v['daily']) ? (float)$v['daily'] : 0.0;
            $weekly = isset($v['weekly']) ? (float)$v['weekly'] : 0.0; // stored as 7 * daily trend
            $item->dailyVelocity = $daily;
            $item->weeklyVelocity = $weekly;
            $denom = $daily > 0 ? $daily : 0.1; // avoid div by zero
            $item->daysOfStock = $item->inventoryLevel / $denom;
            $item->isLow = $item->daysOfStock <= $lowDays;
            $item->isOverstock = $item->daysOfStock >= $overDays;
            // Convert weekly back to daily trend (weekly /7) then compare multiplier
            $trendDaily = $weekly > 0 ? ($weekly / 7.0) : 0.0;
            $item->isHighDemand = $trendDaily > 0 && $daily > ($trendDaily * $mult);
        }
        return $items;
    }
}
