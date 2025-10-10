<?php
declare(strict_types=1);

namespace Unified\Repositories;

use PDO;
use Unified\Support\Logger;
use Unified\Support\Pdo;

/**
 * ProductDiscoveryRepository
 * Retrieves candidate SKUs/stores that require transfer attention.
 */
final class ProductDiscoveryRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Logger $logger
    ) {
    }

    public static function withDefaults(Logger $logger): self
    {
        return new self(Pdo::instance(), $logger);
    }

    /**
     * @return array<int,array{
     *   store_id:string,
     *   sku:string,
     *   predicted_weekly_demand:float,
     *   current_on_hand:int,
     *   reserved:int,
     *   lead_time_days:int,
     *   prediction_confidence:float,
     *   forecast_horizon_days:int
     * }>
     */
    public function findAtRisk(int $limit = 20, ?string $storeId = null): array
    {
        $limit = max(1, min($limit, 200));

        $baseSql = <<<SQL
SELECT
    s.store_id,
    s.sku,
    s.on_hand,
    s.reserved,
    COALESCE(f.demand_p50, 0) AS demand_p50,
    COALESCE(f.demand_p90, 0) AS demand_p90,
    COALESCE(f.feature_date, CURDATE()) AS feature_date
FROM store_stock_snapshots s
LEFT JOIN (
    SELECT fsd.sku_id,
           CAST(fsd.store_id AS CHAR) AS store_id,
           fsd.demand_p50,
           fsd.demand_p90,
           fsd.feature_date
    FROM features_sku_store_daily fsd
    INNER JOIN (
        SELECT sku_id, store_id, MAX(feature_date) AS feature_date
        FROM features_sku_store_daily
        GROUP BY sku_id, store_id
    ) latest ON latest.sku_id = fsd.sku_id
            AND latest.store_id = fsd.store_id
            AND latest.feature_date = fsd.feature_date
) f ON f.sku_id = s.sku AND f.store_id = s.store_id
SQL;

        $conditions = [];
        $params = [];

        if ($storeId !== null) {
            $conditions[] = 's.store_id = ?';
            $params[] = $storeId;
        }

        $conditions[] = 's.on_hand < (COALESCE(f.demand_p50, 0) + COALESCE(f.demand_p50, 0) * 0.25)';

        $sql = $baseSql;
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY (COALESCE(f.demand_p50, 0) - s.on_hand) DESC LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $predicted = (float)$row['demand_p50'];
            $weekly = $predicted > 0 ? $predicted : $this->baselineWeeklyDemand((int)$row['on_hand']);
            $confidence = $this->confidenceFromDemand((float)$row['demand_p50'], (float)$row['demand_p90']);

            $results[] = [
                'store_id' => (string)$row['store_id'],
                'sku' => (string)$row['sku'],
                'predicted_weekly_demand' => round($weekly, 2),
                'current_on_hand' => (int)$row['on_hand'],
                'reserved' => (int)$row['reserved'],
                'lead_time_days' => 4,
                'prediction_confidence' => $confidence,
                'forecast_horizon_days' => 14,
            ];
        }

        $this->logger->info('discovery.at_risk.sample', [
            'store_filter' => $storeId,
            'returned' => count($results),
        ]);

        return $results;
    }

    private function baselineWeeklyDemand(int $onHand): float
    {
        $baseline = max(5, $onHand * 0.6);
        return round($baseline, 2);
    }

    private function confidenceFromDemand(float $p50, float $p90): float
    {
        if ($p50 <= 0) {
            return 0.55;
        }
        if ($p90 <= 0) {
            return 0.75;
        }
        $ratio = max(0.1, min(1.0, $p50 / $p90));
        return round(min(0.95, max(0.5, 0.6 + ($ratio * 0.35))), 3);
    }
}
