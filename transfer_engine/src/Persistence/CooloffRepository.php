<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger;
/** CooloffRepository (Phase M18 follow-on groundwork)
 * Records auto-applied actions to enforce cooloff windows.
 */
final class CooloffRepository
{
    public function __construct(private Logger $logger) {}

    public function record(int $proposalId, string $sku, string $type): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO cooloff_log (proposal_id, sku, action_type) VALUES (?,?,?)');
        $stmt->execute([$proposalId,$sku,$type]);
        $this->logger->info('cooloff.record',[ 'proposal_id'=>$proposalId,'sku'=>$sku,'type'=>$type ]);
    }

    public function inWindow(string $sku, string $type, int $hours): bool
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM cooloff_log WHERE sku=? AND action_type=? AND applied_at >= (NOW() - INTERVAL ? HOUR) LIMIT 1');
        $stmt->execute([$sku,$type,$hours]);
        return (bool)$stmt->fetchColumn();
    }
}
