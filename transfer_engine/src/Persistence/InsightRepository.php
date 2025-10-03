<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger;
/** InsightRepository (Phase M12)
 * Inserts insights into insights_log.
 */
final class InsightRepository
{
    public function __construct(private Logger $logger) {}
    public function insert(string $type, string $message, string $severity, array $meta=[]): int
    {
        $pdo = Db::pdo();
        $sql = "INSERT INTO insights_log (type, message, severity, meta) VALUES (?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type,$message,$severity,json_encode($meta, JSON_UNESCAPED_SLASHES)]);
        $id = (int)$pdo->lastInsertId();
        $this->logger->info('insight.insert',[ 'id'=>$id,'type'=>$type,'sev'=>$severity ]);
        return $id;
    }
}
