<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Contracts\StoreInventoryProviderInterface;
use App\Engines\AutoBalancer\Entity\Store;
use App\Engines\AutoBalancer\Entity\InventoryItem;
use mysqli;

final class StoreInventoryProvider implements StoreInventoryProviderInterface
{
    public function __construct(
        private readonly mysqli $db,
        private readonly int $batchLimit = 500,
    ) {}

    public function getActiveStores(): array
    {
        $sql = "SELECT id, name, physical_state FROM vend_outlets WHERE deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00' ORDER BY id";
        $res = $this->db->query($sql);
        $stores = [];
        while ($row = $res->fetch_assoc()) {
            $stores[] = new Store($row['id'], $row['name'], $row['physical_state'] ?? null);
        }
        return $stores;
    }

    public function getInventoryForStore(Store $store): array
    {
        $sql = "SELECT i.product_id, i.inventory_level, p.supply_price, p.price_including_tax AS retail_price
                FROM vend_inventory i
                JOIN vend_products p ON p.id = i.product_id
                WHERE i.outlet_id = ?
                  AND i.inventory_level > 0
                  AND (i.deleted_at IS NULL OR i.deleted_at='0000-00-00 00:00:00')
                  AND (p.deleted_at IS NULL OR p.deleted_at='0000-00-00 00:00:00')
                LIMIT ?";
    $stmt = $this->db->prepare($sql);
    $sid = $store->id; // avoid any accidental reference handling on readonly prop
    $limit = $this->batchLimit;
    $stmt->bind_param('si', $sid, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = new InventoryItem(
                productId: $row['product_id'],
                inventoryLevel: (int)$row['inventory_level'],
                supplyPrice: (float)$row['supply_price'],
                retailPrice: (float)$row['retail_price']
            );
        }
        $stmt->close();
        return $items;
    }
}
