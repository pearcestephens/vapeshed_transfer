<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Pdo; use Unified\Support\Logger;
/** ProposalStore (Phase M9)
 * Placeholder persistence API for proposed actions (pricing / transfer). Future: schema-backed.
 */
final class ProposalStore
{
    public function __construct(private Logger $logger, private ProposalRepository $repo) {}
    /**
     * Persist a proposal and return its primary key ID.
     * @param array $proposal Expected keys: type, band, score, features, blocked_by, ctx
     * @return int Inserted proposal ID
     */
    public function persist(array $proposal): int
    {
        $id = $this->repo->insert(
            $proposal['type'] ?? 'unknown',
            $proposal['band'] ?? 'propose',
            (float)($proposal['score'] ?? 0.0),
            $proposal['features'] ?? [],
            $proposal['blocked_by'] ?? null,
            $proposal['ctx'] ?? []
        );
        $this->logger->info('proposal.persisted',[ 'id'=>$id ]);
        return $id;
    }
}
