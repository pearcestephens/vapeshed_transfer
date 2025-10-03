<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Entity\InventoryItem;
use App\Engines\AutoBalancer\Entity\Store;
use App\Engines\AutoBalancer\Config\AutoBalancerConfig;

final class InsightsService
{
    public function __construct(private readonly AutoBalancerConfig $config) {}

    /**
     * Produce business intelligence style aggregates for personalization & dashboards.
     * @param Store[] $stores
     * @param array $storeItems store_id => InventoryItem[] analyzed
     * @return array
     */
    public function generate(array $stores, array $storeItems): array
    {
        $highDemand = [];
        $lowStock = [];
        $overstock = [];
        $velocityLeaders = [];

        foreach ($stores as $store) {
            foreach ($storeItems[$store->id] ?? [] as $item) {
                if ($item->isHighDemand) { $highDemand[] = [$store->id,$item->productId,$item->dailyVelocity,$item->daysOfStock]; }
                if ($item->isLow) { $lowStock[] = [$store->id,$item->productId,$item->dailyVelocity,$item->daysOfStock]; }
                if ($item->isOverstock) { $overstock[] = [$store->id,$item->productId,$item->dailyVelocity,$item->daysOfStock,$item->inventoryLevel]; }
                if ($item->dailyVelocity !== null && $item->dailyVelocity > 0) {
                    $velocityLeaders[] = [$store->id,$item->productId,$item->dailyVelocity];
                }
            }
        }

        usort($velocityLeaders, fn($a,$b)=> $b[2] <=> $a[2]);
        $velocityLeaders = array_slice($velocityLeaders,0,50);

        return [
            'generated_at' => date('c'),
            'high_demand' => $highDemand,
            'low_stock' => $lowStock,
            'overstock' => $overstock,
            'velocity_leaders_top50' => $velocityLeaders,
        ];
    }
}
