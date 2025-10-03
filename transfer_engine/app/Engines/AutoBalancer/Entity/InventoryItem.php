<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Entity;

final class InventoryItem
{
    public function __construct(
        public readonly string $productId,
        public readonly int $inventoryLevel,
        public readonly float $supplyPrice,
        public readonly float $retailPrice,
        public ?float $dailyVelocity = null,
        public ?float $weeklyVelocity = null,
        public ?float $daysOfStock = null,
        public ?bool $isLow = null,
        public ?bool $isOverstock = null,
        public ?bool $isHighDemand = null,
    ) {}
}
