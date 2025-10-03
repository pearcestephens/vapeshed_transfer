<?php
declare(strict_types=1);
namespace Unified\Persistence\ReadModels;

use PDO;use Unified\Persistence\Db;use Unified\Support\Logger;use PDOException;

/**
 * TransferReadModel
 * Read-only abstraction for transfer-related dashboard statistics & recent activity.
 * Provides a stable facade so UI code does not embed raw SQL and remains insulated
 * from future schema evolution (joins, materialized views, etc.).
 */
final class TransferReadModel
{
    public function __construct(private Logger $logger)
    {
    }

    /**
     * Aggregate last 7 day transfer proposal statistics.
     * @return array{total:int,pending:int,executed:int,failed:int,today:int}
     */
    public function sevenDayStats(): array
    {
        $pdo = Db::pdo();
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
                FROM proposal_log 
                WHERE proposal_type = 'transfer'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        try {
            $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'total' => (int)($row['total'] ?? 0),
                'pending' => (int)($row['pending'] ?? 0),
                'executed' => (int)($row['executed'] ?? 0),
                'failed' => (int)($row['failed'] ?? 0),
                'today' => (int)($row['today'] ?? 0)
            ];
        } catch (PDOException $e) {
            $this->logger->error('transfer.stats.error',[ 'err'=>$e->getMessage() ]);
            return [ 'total'=>0,'pending'=>0,'executed'=>0,'failed'=>0,'today'=>0 ];
        }
    }

    /**
     * Recent transfer proposals (descending).
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 20): array
    {
        $pdo = Db::pdo();
        $sql = "SELECT id, proposal_type, band, score, blocked_by, created_at
                FROM proposal_log
                WHERE proposal_type = 'transfer'
                ORDER BY created_at DESC
                LIMIT :lim";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error('transfer.recent.error',[ 'err'=>$e->getMessage() ]);
            return [];
        }
    }
}
