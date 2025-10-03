<?php
declare(strict_types=1);
namespace Unified\Policy;
use Unified\Support\Logger; use Unified\Scoring\ScoringEngine; use Unified\Guardrail\GuardrailChain; use Unified\Persistence\ProposalStore; use Unified\Persistence\GuardrailTraceRepository; use Unified\Support\Util; use Unified\Persistence\CooloffRepository; // added for Phase M18 enhancement
use Unified\Persistence\ActionAuditRepository;
/** PolicyOrchestrator (Phase M10)
 * Coordinates guardrail evaluation, scoring, and proposal persistence (skeleton only)
 */
final class PolicyOrchestrator
{
    public function __construct(private Logger $logger, private GuardrailChain $chain, private ScoringEngine $scoring, private ProposalStore $store, private GuardrailTraceRepository $traceRepo, private ?CooloffRepository $cooloff=null, private ?ActionAuditRepository $audit=null) {}

    /**
     * @param array $ctx candidate context
     * @param array $features feature contributions
     * @return array orchestrated result
     */
    public function process(array $ctx, array $features): array
    {
        $runId = $ctx['run_id'] ?? (function(){ return bin2hex(random_bytes(16)); })();
        $gr = $this->chain->evaluate($ctx);
        if ($gr['final_status']==='BLOCK') {
            return ['status'=>'blocked','guardrail'=>$gr,'run_id'=>$runId];
        }
        $score = $this->scoring->score($features);
        $payload = [
            'type'=>$ctx['type'] ?? 'pricing',
            'band'=>$score['band'],
            'score'=>$score['score'],
            'features'=>$score['features'],
            'blocked_by'=>$gr['blocked_by'],
            'ctx'=>$ctx
        ];
        $proposalId = $this->store->persist($payload);
        $this->traceRepo->insertBatch($proposalId, $runId, $gr['results']);
        // Phase M18: Auto-apply pilot (narrow scope) - placeholder logic
        $autoApplied = false; $autoReason = null;
        if (($ctx['type'] ?? '') === 'pricing' && ($score['band'] === 'promote')) {
            $cooloffHours = (int)\Unified\Support\Config::get('neuro.unified.policy.cooloff_hours', 24);
            $cooloffOk = $this->cooloff? !$this->cooloff->inWindow($ctx['sku'] ?? 'unknown','pricing',$cooloffHours) : true;
            $flag = \Unified\Support\Config::get('neuro.unified.policy.auto_apply_pricing', false);
            if ($flag && $cooloffOk) {
                $autoApplied = true; $autoReason = 'pilot_promote_band';
                if ($this->cooloff && isset($ctx['sku'])) {
                    $this->cooloff->record($proposalId,$ctx['sku'],'pricing');
                }
                if ($this->audit && isset($ctx['sku'])) {
                    $this->audit->record($proposalId,$ctx['sku'],'pricing','applied',['reason'=>$autoReason]);
                }
            }
        }
        return [
            'status'=>$score['band'],
            'guardrail'=>$gr,
            'score'=>$score,
            'run_id'=>$runId,
            'proposal_id'=>$proposalId,
            'auto_applied'=>$autoApplied,
            'auto_reason'=>$autoReason
        ];
    }
}
