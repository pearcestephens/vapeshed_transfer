<?php
declare(strict_types=1);
namespace Unified\Persistence\ReadModels;

use Unified\Persistence\Db;use Unified\Support\Logger;use PDO;use PDOException;

/**
 * HistoryReadModel
 * Enriched history view joining proposal_log with guardrail_traces.
 * Provides detailed decision lineage for both transfer and pricing proposals.
 */
final class HistoryReadModel
{
    public function __construct(private Logger $logger) {}

    /**
     * Get enriched proposal history with guardrail traces.
     * @param string $type 'transfer'|'pricing'|null for all
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function enrichedHistory(string $type = null, int $limit = 50): array
    {
        $pdo = Db::pdo();
        $typeWhere = $type ? "AND p.proposal_type = :type" : "";
        
        $sql = "SELECT 
                    p.id, p.proposal_type, p.band, p.score, p.blocked_by, p.created_at,
                    COUNT(gt.id) as trace_count,
                    GROUP_CONCAT(DISTINCT gt.guardrail_code ORDER BY gt.id SEPARATOR ',') as guardrail_codes,
                    GROUP_CONCAT(DISTINCT gt.result ORDER BY gt.id SEPARATOR ',') as guardrail_results
                FROM proposal_log p
                LEFT JOIN guardrail_traces gt ON gt.proposal_id = p.id
                WHERE 1=1 {$typeWhere}
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT :lim";
        
        try {
            $stmt = $pdo->prepare($sql);
            if ($type) $stmt->bindValue(':type', $type, PDO::PARAM_STR);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Post-process guardrail data for easier display
            foreach ($results as &$row) {
                $row['guardrails'] = [];
                if ($row['guardrail_codes']) {
                    $codes = explode(',', $row['guardrail_codes']);
                    $results_arr = explode(',', $row['guardrail_results']);
                    for ($i = 0; $i < count($codes); $i++) {
                        $row['guardrails'][] = [
                            'code' => $codes[$i],
                            'result' => $results_arr[$i] ?? 'UNKNOWN'
                        ];
                    }
                }
                unset($row['guardrail_codes'], $row['guardrail_results']);
            }
            
            return $results;
        } catch (PDOException $e) {
            $this->logger->error('history.enriched.error', ['type' => $type, 'err' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get guardrail trace details for a specific proposal.
     * @param int $proposalId
     * @return array<int,array<string,mixed>>
     */
    public function proposalTraces(int $proposalId): array
    {
        $pdo = Db::pdo();
        $sql = "SELECT guardrail_code, result, details, created_at
                FROM guardrail_traces
                WHERE proposal_id = :pid
                ORDER BY id ASC";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':pid', $proposalId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error('history.traces.error', ['proposal_id' => $proposalId, 'err' => $e->getMessage()]);
            return [];
        }
    }
}