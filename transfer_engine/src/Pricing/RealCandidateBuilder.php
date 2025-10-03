<?php
declare(strict_types=1);
namespace Unified\Pricing;
use Unified\Support\Logger; use Unified\Persistence\Db;
/** RealCandidateBuilder - Generates pricing candidates from actual sales/inventory data
 * Replaces static samples with DB-driven selection.
 */
final class RealCandidateBuilder
{
    public function __construct(private Logger $logger) {}

    /**
     * Build pricing candidates from real sales + inventory analysis.
     * Heuristic: identify low-margin or slow-moving items for price optimization.
     * @param array $opts { limit:int, min_sales:int, max_age_days:int }
     * @return array<int,array>
     */
    public function build(array $opts = []): array
    {
        $limit = $opts['limit'] ?? 10;
        $minSales = $opts['min_sales'] ?? 5;
        $maxAgeDays = $opts['max_age_days'] ?? 90;

        try {
            $pdo = Db::pdo();
            // Sample query: find products with sales but potentially sub-optimal pricing
            // Adjust table/column names to match your schema
            $sql = "
                SELECT 
                    p.sku,
                    p.name AS product_name,
                    p.cost,
                    p.price AS current_price,
                    COALESCE(SUM(s.quantity), 0) AS total_sales,
                    AVG(p.price) AS avg_price,
                    (p.price - p.cost) / NULLIF(p.price, 0) AS margin_pct
                FROM products p
                LEFT JOIN sales s ON s.product_id = p.id AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                WHERE p.cost > 0 AND p.price > 0
                GROUP BY p.id
                HAVING total_sales >= ?
                ORDER BY margin_pct ASC, total_sales DESC
                LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$maxAgeDays, $minSales, $limit]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $candidates = [];
            foreach ($rows as $r) {
                // Propose modest price increase for low-margin items
                $currentPrice = (float)$r['current_price'];
                $cost = (float)$r['cost'];
                $candidatePrice = round($currentPrice * 1.05, 2); // +5% test
                $projectedRoi = ($candidatePrice - $cost) / max($cost, 0.01);

                $candidates[] = [
                    'sku' => $r['sku'],
                    'product_name' => $r['product_name'],
                    'current_price' => $currentPrice,
                    'candidate_price' => $candidatePrice,
                    'cost' => $cost,
                    'projected_roi' => round($projectedRoi, 2),
                    'total_sales' => (int)$r['total_sales'],
                    'margin_pct' => round((float)$r['margin_pct'], 4),
                    'donor_dsr_post' => 0, // placeholder
                    'receiver_dsr_post' => 0
                ];
            }

            $this->logger->info('pricing.real_candidates',['found'=>count($candidates)]);
            return $candidates;
        } catch (\Throwable $e) {
            $this->logger->error('pricing.real_candidates.failed',['error'=>$e->getMessage()]);
            return [];
        }
    }
}
