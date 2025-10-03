<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger;
/** RunLogRepository (Phase M12)
 * Records run metadata and metrics.
 */
final class RunLogRepository
{
    public function __construct(private Logger $logger) {}
    public function upsert(string $runId, string $module, string $status, array $metrics=[]): void
    {
        $pdo = Db::pdo();
        $sql = "INSERT INTO run_log (run_id, module, status, metrics) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), metrics=VALUES(metrics)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$runId,$module,$status,json_encode($metrics, JSON_UNESCAPED_SLASHES)]);
        $this->logger->info('runlog.upsert',[ 'run_id'=>$runId,'module'=>$module,'status'=>$status ]);
    }
}
