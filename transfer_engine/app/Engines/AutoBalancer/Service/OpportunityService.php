<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Entity\InventoryItem;
use App\Engines\AutoBalancer\Entity\Store;
use App\Engines\AutoBalancer\Config\AutoBalancerConfig;

final class OpportunityService
{
    public function __construct(private readonly AutoBalancerConfig $config) {}

    /**
     * Build cross-store structure per product for needy vs surplus classification.
     * @param Store[] $stores
     * @param array $storeItems store_id => InventoryItem[] (already analyzed)
     * @return array product_id => ['supply_price'=>float,'stores'=>storeStatus[]]
     */
    public function buildProductMatrix(array $stores, array $storeItems): array
    {
        $matrix = [];
        foreach ($stores as $store) {
            $items = $storeItems[$store->id] ?? [];
            foreach ($items as $item) {
                $pid = $item->productId;
                if (!isset($matrix[$pid])) {
                    $matrix[$pid] = [
                        'supply_price' => $item->supplyPrice,
                        'stores' => []
                    ];
                }
                $matrix[$pid]['stores'][$store->id] = [
                    'inventory_level' => $item->inventoryLevel,
                    'days_of_stock' => $item->daysOfStock,
                    'daily_velocity' => $item->dailyVelocity,
                    'is_low' => $item->isLow,
                    'is_overstock' => $item->isOverstock,
                    'is_high_demand' => $item->isHighDemand,
                    'store_ref' => $store,
                ];
            }
        }
        return $matrix;
    }
}
