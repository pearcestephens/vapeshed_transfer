<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger;
/** GuardrailTraceRepository (Phase M12)
 * Persists guardrail chain results.
 */
final class GuardrailTraceRepository
{
    public function __construct(private Logger $logger) {}
    /** @param array<int,array> $results */
    public function insertBatch(int $proposalId, string $runId, array $results): void
    {
        if (!$results) return; $pdo = Db::pdo();
        $sql = "INSERT INTO guardrail_traces (proposal_id, run_id, sequence, code, status, message, meta) VALUES (?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql); $i=0;
        foreach ($results as $r) {
            $stmt->execute([
                $proposalId,
                $runId,
                ++$i,
                $r['code'],
                $r['status'],
                $r['message'] ?? null,
                isset($r['meta']) ? json_encode($r['meta'], JSON_UNESCAPED_SLASHES) : null
            ]);
        }
        $this->logger->info('guardrail.trace.insert',[ 'proposal_id'=>$proposalId,'count'=>count($results) ]);
    }
}
