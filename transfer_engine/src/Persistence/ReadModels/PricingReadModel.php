<?php
declare(strict_types=1);
namespace Unified\Persistence\ReadModels;

use Unified\Persistence\Db;use Unified\Support\Logger;use PDO;use PDOException;

/**
 * PricingReadModel
 * Read-only façade for pricing proposal statistics & recent records.
 * Mirrors transfer read model pattern to keep UI decoupled from schema.
 */
final class PricingReadModel
{
    public function __construct(private Logger $logger) {}

    /**
     * Aggregate last 7 day pricing proposal metrics.
     * @return array{total:int,propose:int,auto:int,discard:int,blocked:int,today:int}
     */
    public function sevenDayStats(): array
    {
        $pdo = Db::pdo();
        $sql = "SELECT 
                   COUNT(*) total,
                   SUM(CASE WHEN band = 'propose' THEN 1 ELSE 0 END) propose,
                   SUM(CASE WHEN band = 'auto' THEN 1 ELSE 0 END) auto,
                   SUM(CASE WHEN band = 'discard' THEN 1 ELSE 0 END) discard,
                   SUM(CASE WHEN blocked_by IS NOT NULL THEN 1 ELSE 0 END) blocked,
                   SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) today
                FROM proposal_log
                WHERE proposal_type='pricing'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        try {
            $r = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'total'=>(int)($r['total']??0),
                'propose'=>(int)($r['propose']??0),
                'auto'=>(int)($r['auto']??0),
                'discard'=>(int)($r['discard']??0),
                'blocked'=>(int)($r['blocked']??0),
                'today'=>(int)($r['today']??0),
            ];
        } catch (PDOException $e) {
            $this->logger->error('pricing.stats.error',[ 'err'=>$e->getMessage() ]);
            return [ 'total'=>0,'propose'=>0,'auto'=>0,'discard'=>0,'blocked'=>0,'today'=>0 ];
        }
    }

    /**
     * Recent pricing proposals.
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 25): array
    {
        $pdo = Db::pdo();
        $sql = "SELECT id, band, score, blocked_by, created_at
                FROM proposal_log
                WHERE proposal_type='pricing'
                ORDER BY created_at DESC LIMIT :lim";
        try {
            $st = $pdo->prepare($sql); $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error('pricing.recent.error',[ 'err'=>$e->getMessage() ]);
            return [];
        }
    }
}
