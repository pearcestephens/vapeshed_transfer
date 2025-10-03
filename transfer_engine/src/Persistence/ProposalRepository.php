<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger; use PDO;
/** ProposalRepository (Phase M12)
 * Writes proposals to proposal_log.
 */
final class ProposalRepository
{
    public function __construct(private Logger $logger) {}
    public function insert(string $type, string $band, float $score, array $features, ?string $blockedBy, array $ctx): int
    {
        $pdo = Db::pdo();
        $contextHash = hash('sha256', json_encode($ctx));
        $sql = "INSERT INTO proposal_log (proposal_type, band, score, features, blocked_by, context_hash) VALUES (?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type,$band,$score,json_encode($features, JSON_UNESCAPED_SLASHES),$blockedBy,$contextHash]);
        $id = (int)$pdo->lastInsertId();
        $this->logger->info('proposal.insert',[ 'id'=>$id,'band'=>$band,'score'=>$score ]);
        return $id;
    }
}
