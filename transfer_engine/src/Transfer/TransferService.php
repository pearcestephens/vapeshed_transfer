<?php
declare(strict_types=1);
namespace Unified\Transfer;
use Unified\Support\Logger; use Unified\Policy\PolicyOrchestrator; use Unified\Scoring\ScoringEngine;
/** TransferService (Phase M14)
 * Generates transfer proposals (propose-only). Wraps legacy adapter outputs.
 */
final class TransferService
{
    public function __construct(
        private Logger $logger,
        private LegacyAdapter $adapter,
        private DsrCalculator $dsr,
        private ScoringEngine $scoring,
        private PolicyOrchestrator $policy
    ) {}

    /**
     * Run transfer proposal cycle.
     * @return array { run_id, candidates, results }
     */
    public function run(array $context = []): array
    {
        $runId = $context['run_id'] ?? bin2hex(random_bytes(8));
        $candidates = $this->adapter->candidates();
        $results = [];
        foreach ($candidates as $cand) {
            $proj = $this->dsr->project(
                ['stock_on_hand'=>$cand['donor_stock'],'avg_daily_demand'=>$cand['donor_avg_daily']],
                ['stock_on_hand'=>$cand['receiver_stock'],'avg_daily_demand'=>$cand['receiver_avg_daily']],
                (int)$cand['qty']
            );
            $features = $this->mapFeatures($cand,$proj);
            $ctx = array_merge($cand,$proj,[
                'type'=>'transfer',
                'run_id'=>$runId
            ]);
            $policy = $this->policy->process($ctx,$features);
            $results[] = [ 'sku'=>$cand['sku'],'policy'=>$policy,'proj'=>$proj,'features'=>$features ];
        }
        return [ 'run_id'=>$runId,'candidates'=>$candidates,'results'=>$results ];
    }

    private function mapFeatures(array $cand, array $proj): array
    {
        $imbalanceBefore = $this->imbalanceRatio($cand['donor_stock'],$cand['donor_avg_daily'],$cand['receiver_stock'],$cand['receiver_avg_daily']);
        $donorPostStock = max(0,$cand['donor_stock'] - $cand['qty']);
        $receiverPostStock = $cand['receiver_stock'] + $cand['qty'];
        $imbalanceAfter = $this->imbalanceRatio($donorPostStock,$cand['donor_avg_daily'],$receiverPostStock,$cand['receiver_avg_daily']);
        return [
            'balance_improvement' => max(0.0,$imbalanceBefore - $imbalanceAfter),
            'delta_qty' => (int)$cand['qty'] / 100.0, // normalized placeholder
            'donor_dsr_post' => $proj['donor_dsr_post'],
            'receiver_dsr_post' => $proj['receiver_dsr_post']
        ];
    }

    private function imbalanceRatio(int $dStock, float $dAvg, int $rStock, float $rAvg): float
    {
        $dDsr = $dAvg>0? $dStock / $dAvg : 0.0;
        $rDsr = $rAvg>0? $rStock / $rAvg : 0.0;
        if ($dDsr+$rDsr <= 0) return 0.0;
        return abs($dDsr - $rDsr) / max($dDsr+$rDsr,0.0001);
    }
}
