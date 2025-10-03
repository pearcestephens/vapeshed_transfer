<?php
declare(strict_types=1);
namespace Unified\Pricing;
use Unified\Support\Logger;
/** RuleEvaluator (Phase M13 Skeleton)
 * Applies heuristic rules to derive feature contributions (margin uplift, alignment, risk penalty).
 */
final class RuleEvaluator
{
    public function __construct(private Logger $logger) {}

    /**
     * @param array $candidate
     * @return array { margin_uplift, competitor_alignment, risk_penalty }
     */
    public function evaluate(array $candidate): array
    {
        $current = $candidate['current_price'];
        $cand = $candidate['candidate_price'];
        $cost = $candidate['cost'] ?? 0.0;
        $marginCurrent = ($current - $cost) / max($current,0.01);
        $marginCand = ($cand - $cost) / max($cand,0.01);
        $marginUplift = $marginCand - $marginCurrent; // could be negative
        // Placeholder competitor alignment: neutral 0.2 if increase, 0.1 if decrease.
        $competitorAlignment = $cand >= $current ? 0.2 : 0.1;
        // Risk penalty simplistic: if projected_roi below 1.0 penalize
        $riskPenalty = ($candidate['projected_roi'] ?? 1.0) < 1.0 ? -0.15 : 0.0;
        $meta = [
            'margin_uplift' => round($marginUplift,4),
            'competitor_alignment' => $competitorAlignment,
            'risk_penalty' => $riskPenalty
        ];
        $this->logger->info('pricing.rule.eval',$meta);
        return $meta;
    }
}
