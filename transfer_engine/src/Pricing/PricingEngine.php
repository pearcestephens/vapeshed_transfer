<?php
declare(strict_types=1);
namespace Unified\Pricing;
use Unified\Support\Logger; use Unified\Pricing\CandidateBuilder; use Unified\Pricing\RuleEvaluator; use Unified\Scoring\ScoringEngine; use Unified\Policy\PolicyOrchestrator;
/** PricingEngine (Phase M13 Skeleton)
 * Generates pricing candidates, evaluates rules, delegates to policy orchestrator for scoring & persistence.
 * Propose-only at this phase. Auto-apply logic deferred until guardrail war/cooloff implemented.
 */
final class PricingEngine
{
    public function __construct(
        private Logger $logger,
        private CandidateBuilder $builder,
        private RuleEvaluator $rules,
        private ScoringEngine $scoring,
        private PolicyOrchestrator $policy
    ) {}

    /**
     * Execute pricing evaluation cycle.
     * @param array $context global context (e.g., run metadata)
     * @return array { run_id, candidates: [...], results: [...] }
     */
    public function run(array $context = []): array
    {
        $candidates = $this->builder->build($context);
        $results = [];
        foreach ($candidates as $cand) {
            $ruleMeta = $this->rules->evaluate($cand);
            $features = $this->mapFeatures($cand, $ruleMeta);
            $ctx = array_merge($cand,[
                'type'=>'pricing',
                'run_id'=>$context['run_id'] ?? null,
                'rule_meta'=>$ruleMeta
            ]);
            $res = $this->policy->process($ctx,$features);
            $results[] = [
                'sku'=>$cand['sku'],
                'current_price'=>$cand['current_price'],
                'candidate_price'=>$cand['candidate_price'],
                'policy'=>$res,
                'rule_meta'=>$ruleMeta
            ];
        }
        return [ 'run_id'=>$context['run_id'] ?? null, 'candidates'=>$candidates, 'results'=>$results ];
    }

    /** Map candidate + rule evaluation to scoring feature vector */
    private function mapFeatures(array $cand, array $ruleMeta): array
    {
        // Placeholder features; later integrate true margin uplift, risk penalty, inventory balance.
        $deltaPct = ($cand['candidate_price'] - $cand['current_price']) / max($cand['current_price'],0.01);
        return [
            'margin_uplift' => $ruleMeta['margin_uplift'] ?? 0.0,
            'competitor_alignment' => $ruleMeta['competitor_alignment'] ?? 0.0,
            'risk_penalty' => $ruleMeta['risk_penalty'] ?? 0.0,
            'delta_magnitude' => min(1.0, abs($deltaPct)) * 0.1 // small weight placeholder
        ];
    }
}
