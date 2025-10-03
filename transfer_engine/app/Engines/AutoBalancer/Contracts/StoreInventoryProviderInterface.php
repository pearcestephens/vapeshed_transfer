<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Contracts;

use App\Engines\AutoBalancer\Entity\Store;
use App\Engines\AutoBalancer\Entity\InventoryItem;

interface StoreInventoryProviderInterface
{
    /** @return Store[] */
    public function getActiveStores(): array;

    /**
     * @param Store $store
     * @return InventoryItem[] batched up to configured limit
     */
    public function getInventoryForStore(Store $store): array;
}
