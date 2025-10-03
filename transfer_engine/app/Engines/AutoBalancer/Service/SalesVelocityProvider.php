<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Contracts\SalesVelocityProviderInterface;
use App\Engines\AutoBalancer\Entity\Store;
use mysqli;

final class SalesVelocityProvider implements SalesVelocityProviderInterface
{
    private int $chunkSize = 400; // product id chunk size to keep IN() lists efficient

    public function __construct(
        private readonly mysqli $db,
        private readonly int $velocityDays = 14,
        private readonly int $trendDays = 56,
    ) {}

    public function getVelocities(array $stores, array $storeProductMap): array
    {
        if (empty($stores) || empty($storeProductMap)) {
            return [];
        }
        $storeIds = array_map(fn(Store $s) => $this->db->real_escape_string($s->id), $stores);
        $storeIn = "'" . implode("','", $storeIds) . "'";

        // Flatten product ids
        $productIds = [];
        foreach ($storeProductMap as $pids) { foreach ($pids as $pid) { $productIds[$pid] = true; } }
        if (empty($productIds)) { return []; }
        $productIds = array_keys($productIds);

        $velocityMap = [];
        $dateExprVelocity = "s.sale_date >= DATE_SUB(NOW(), INTERVAL {$this->velocityDays} DAY)";
        $dateExprTrend    = "s.sale_date >= DATE_SUB(NOW(), INTERVAL {$this->trendDays} DAY)";

        $chunked = array_chunk($productIds, $this->chunkSize);
        foreach ($chunked as $chunk) {
            $productIn = "'" . implode("','", array_map(fn($p)=>$this->db->real_escape_string($p), $chunk)) . "'";

            // Velocity (recent)
            $velocitySql = "SELECT s.outlet_id, sli.product_id, SUM(sli.quantity) / {$this->velocityDays} AS daily
                              FROM vend_sales_line_items sli
                              JOIN vend_sales s ON s.increment_id = sli.sales_increment_id
                              WHERE $dateExprVelocity
                                AND s.outlet_id IN ($storeIn)
                                AND sli.product_id IN ($productIn)
                                AND s.status='CLOSED'
                                AND sli.is_return=0
                                AND (s.deleted_at IS NULL OR s.deleted_at='0000-00-00 00:00:00')
                                AND sli.quantity > 0
                              GROUP BY s.outlet_id, sli.product_id";

            if ($res1 = $this->db->query($velocitySql)) {
                while ($row = $res1->fetch_assoc()) {
                    $velocityMap[$row['outlet_id']][$row['product_id']]['daily'] = (float)$row['daily'];
                }
                $res1->free();
            }

            // Trend (longer window)
            $trendSql = "SELECT s.outlet_id, sli.product_id, SUM(sli.quantity) / {$this->trendDays} AS daily
                          FROM vend_sales_line_items sli
                          JOIN vend_sales s ON s.increment_id = sli.sales_increment_id
                          WHERE $dateExprTrend
                            AND s.outlet_id IN ($storeIn)
                            AND sli.product_id IN ($productIn)
                            AND s.status='CLOSED'
                            AND sli.is_return=0
                            AND (s.deleted_at IS NULL OR s.deleted_at='0000-00-00 00:00:00')
                            AND sli.quantity > 0
                          GROUP BY s.outlet_id, sli.product_id";
            if ($res2 = $this->db->query($trendSql)) {
                while ($row = $res2->fetch_assoc()) {
                    $velocityMap[$row['outlet_id']][$row['product_id']]['weekly'] = (float)$row['daily'] * 7.0; // convert to daily-equivalent *7
                }
                $res2->free();
            }
        }

        return $velocityMap;
    }
}
