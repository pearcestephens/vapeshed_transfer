<?php
declare(strict_types=1);

namespace App\Engines\Pricing\Service;

use App\Engines\Pricing\Config\PricingConfig;
use mysqli;

final class PriceDataProvider
{
    public function __construct(private readonly mysqli $db, private readonly PricingConfig $config) {}

    /**
     * Fetch historical price points (effective price) and units sold for a product.
     * Returns array of ['price'=>float,'units'=>int,'active_days'=>int,'daily_units'=>float]
     */
    public function getHistoricalPricePoints(string $productId): array
    {
        $days = $this->config->historyDays();
        $pid = $this->db->real_escape_string($productId);
        $sql = "SELECT 
                  ROUND(COALESCE(NULLIF(sli.unit_price,0), NULLIF(sli.price,0), NULLIF(sli.price_total/NULLIF(sli.quantity,0),0)),2) AS eff_price,
                  SUM(sli.quantity) AS units,
                  COUNT(DISTINCT DATE(s.sale_date)) AS active_days
                FROM vend_sales_line_items sli
                JOIN vend_sales s ON s.increment_id = sli.sales_increment_id
                WHERE sli.product_id = '$pid'
                  AND s.sale_date >= DATE_SUB(NOW(), INTERVAL $days DAY)
                  AND sli.quantity > 0
                  AND sli.is_return=0
                  AND (s.deleted_at IS NULL OR s.deleted_at='0000-00-00 00:00:00')
                GROUP BY eff_price
                HAVING eff_price > 0 AND units > 0
                ORDER BY eff_price";
        $res = $this->db->query($sql);
        $points = [];
        while ($row = $res->fetch_assoc()) {
            $daily = $row['active_days'] > 0 ? (float)$row['units'] / (float)$row['active_days'] : 0.0;
            $points[] = [
                'price' => (float)$row['eff_price'],
                'units' => (int)$row['units'],
                'active_days' => (int)$row['active_days'],
                'daily_units' => $daily
            ];
        }
        $res->free();
        return $points;
    }

    public function getProductCostAndCurrentPrice(string $productId): ?array
    {
        $pid = $this->db->real_escape_string($productId);
        $sql = "SELECT supply_price, price_including_tax FROM vend_products WHERE id='$pid' AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') LIMIT 1";
        $res = $this->db->query($sql);
        $row = $res->fetch_assoc();
        $res->free();
        return $row ? [ 'cost'=>(float)$row['supply_price'], 'current_price'=>(float)$row['price_including_tax'] ] : null;
    }
}
