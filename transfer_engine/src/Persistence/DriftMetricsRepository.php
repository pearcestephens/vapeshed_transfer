<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger;
/** DriftMetricsRepository (Phase M12)
 * Inserts PSI drift metrics.
 */
final class DriftMetricsRepository
{
    public function __construct(private Logger $logger) {}
    public function insert(string $featureSet, float $psi, string $status, array $buckets): int
    {
        $pdo = Db::pdo();
        $sql = "INSERT INTO drift_metrics (feature_set, psi, status, buckets) VALUES (?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$featureSet,$psi,$status,json_encode($buckets, JSON_UNESCAPED_SLASHES)]);
        $id = (int)$pdo->lastInsertId();
        $this->logger->info('drift.insert',[ 'id'=>$id,'feature_set'=>$featureSet,'psi'=>$psi,'status'=>$status ]);
        return $id;
    }
}
