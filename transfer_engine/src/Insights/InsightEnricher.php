<?php
declare(strict_types=1);
namespace Unified\Insights;
use Unified\Support\Logger; use Unified\Persistence\ProposalRepository; use Unified\Persistence\DriftMetricsRepository;
/** InsightEnricher (Phase M17)
 * Links recent proposals to drift + demand heuristic metadata (placeholder logic).
 */
final class InsightEnricher
{
    public function __construct(private Logger $logger, private ProposalRepository $proposals, private DriftMetricsRepository $driftRepo) {}

    /**
     * Fetch lightweight enrichment snapshot (last N proposals + last drift metric).
     * @return array { proposals:[], drift:?array }
     */
    public function snapshot(int $limit = 5): array
    {
        $pdo = \Unified\Persistence\Db::pdo();
        $propRows = $pdo->query("SELECT id, proposal_type, band, score, created_at FROM proposal_log ORDER BY id DESC LIMIT ".$limit)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $drift = $pdo->query("SELECT id, feature_set, psi, status, created_at FROM drift_metrics ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC) ?: null;
        $out = [ 'proposals'=>$propRows, 'drift'=>$drift ];
        $this->logger->info('insight.enrich.snapshot',[ 'proposals'=>count($propRows),'has_drift'=>$drift?1:0 ]);
        return $out;
    }
}
