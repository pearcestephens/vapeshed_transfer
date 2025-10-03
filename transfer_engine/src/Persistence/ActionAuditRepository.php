<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger;
/** ActionAuditRepository
 * Records proposal actions (applied / simulated / rejected) for traceability and compliance.
 */
final class ActionAuditRepository
{
    public function __construct(private Logger $logger) {}

    public function record(int $proposalId, string $sku, string $type, string $effect, array $metadata = []): int
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('INSERT INTO action_audit (proposal_id, sku, action_type, effect, metadata) VALUES (?,?,?,?,?)');
        $metaJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null;
        $stmt->execute([$proposalId,$sku,$type,$effect,$metaJson]);
        $id = (int)$pdo->lastInsertId();
        $this->logger->info('action_audit.record',[ 'id'=>$id,'proposal_id'=>$proposalId,'effect'=>$effect ]);
        return $id;
    }

    public function recentApplied(int $hours, ?string $type = null): array
    {
        $pdo = Db::pdo();
        $where = "effect='applied' AND applied_at >= (NOW() - INTERVAL ? HOUR)";
        $params = [$hours];
        if ($type) { $where .= " AND action_type=?"; $params[] = $type; }
        $stmt = $pdo->prepare("SELECT id, proposal_id, sku, action_type, applied_at FROM action_audit WHERE {$where} ORDER BY applied_at DESC LIMIT 50");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
