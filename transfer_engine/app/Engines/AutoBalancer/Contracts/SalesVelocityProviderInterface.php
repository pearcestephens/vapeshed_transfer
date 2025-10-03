<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Contracts;

use App\Engines\AutoBalancer\Entity\Store;

interface SalesVelocityProviderInterface
{
    /**
     * Returns associative map: [store_id][product_id] => ['daily' => float, 'weekly' => float]
     * for all products found in provided store inventories.
     * @param Store[] $stores
     * @param array $storeProductMap [store_id => product_id[]]
     * @return array
     */
    public function getVelocities(array $stores, array $storeProductMap): array;
}
