<?php
declare(strict_types=1);

namespace App\Services\Picker;

use VapeshedTransfer\Database\DatabaseManager;

/**
 * ProductPicker
 *
 * Picks up to N SKUs per destination store based on destination stock priority:
 * - Priority 3: dest stock = 0 (highest)
 * - Priority 2: dest stock < 5
 * - Priority 1: dest stock < 20
 * - Priority 0: else
 * Within same priority, order by warehouse stock descending.
 */
final class ProductPicker
{
    private DatabaseManager $db;

    public function __construct(?DatabaseManager $db = null)
    {
        $this->db = $db ?: DatabaseManager::getInstance();
    }

    /**
     * @param string $hubId
     * @param string[] $storeIds
     * @param int $limitPerStore
     * @param int $minHubQty
     * @return array store_id => [ product_id => [wh_qty:int, dest_qty:int], ... ]
     */
    public function pickPerStore(string $hubId, array $storeIds, int $limitPerStore = 25, int $minHubQty = 1): array
    {
        $qtyCol = $this->detectInventoryQtyColumn();
        $out = [];
        foreach ($storeIds as $sid) {
            $sql = "
                SELECT
                    wh.product_id,
                    GREATEST(0, wh.`{$qtyCol}`) AS wh_qty,
                    GREATEST(0, COALESCE(dst.`{$qtyCol}`, 0)) AS dest_qty,
                    CASE
                        WHEN COALESCE(dst.`{$qtyCol}`,0) = 0 THEN 3
                        WHEN COALESCE(dst.`{$qtyCol}`,0) < 5 THEN 2
                        WHEN COALESCE(dst.`{$qtyCol}`,0) < 20 THEN 1
                        ELSE 0
                    END AS priority
                FROM vend_inventory wh
                LEFT JOIN vend_inventory dst
                  ON dst.product_id = wh.product_id
                 AND dst.outlet_id = ?
                WHERE wh.outlet_id = ?
                  AND wh.`{$qtyCol}` >= ?
                ORDER BY priority DESC, wh.`{$qtyCol}` DESC
                LIMIT ?
            ";
            $res = $this->db->query($sql, [$sid, $hubId, $minHubQty, $limitPerStore], 'ssii');
            $rows = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[(string)$r['product_id']] = [
                        'wh_qty'   => (int)$r['wh_qty'],
                        'dest_qty' => (int)$r['dest_qty'],
                    ];
                }
            }
            $out[$sid] = $rows;
        }
        return $out;
    }

    private function detectInventoryQtyColumn(): string
    {
        $cols = array_map(static fn($c) => strtolower((string)$c['COLUMN_NAME']), $this->db->getTableColumns('vend_inventory'));
        foreach (['inventory_level','current_amount','on_hand','onhand','quantity','qty'] as $c) {
            if (in_array($c, $cols, true)) { return $c; }
        }
        return 'inventory_level';
    }
}
