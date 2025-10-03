<?php
declare(strict_types=1);
namespace Unified\Forecast;
use Unified\Support\Logger;
/** HeuristicProvider (Phase M16)
 * Provides simple moving-average and safety stock estimates (placeholder for future ML models).
 */
final class HeuristicProvider
{
    public function __construct(private Logger $logger) {}
    /**
     * @param float[] $history recent daily demand samples
     * @return array { avg, sma_3, sma_7, safety_stock }
     */
    public function summarize(array $history): array
    {
        $avg = $this->mean($history);
        $sma3 = $this->mean(array_slice($history,-3));
        $sma7 = $this->mean(array_slice($history,-7));
        $std = $this->stddev($history, $avg);
        $safety = round(($std * 1.65) + ($avg * 0.25),2); // crude placeholder
        $out = [ 'avg'=>$avg, 'sma_3'=>$sma3, 'sma_7'=>$sma7, 'safety_stock'=>$safety ];
        $this->logger->info('forecast.heuristic.summary',$out);
        return $out;
    }
    private function mean(array $vals): float { $c=count($vals); if($c===0) return 0.0; return round(array_sum($vals)/$c,2); }
    private function stddev(array $vals, float $mean): float { $c=count($vals); if($c<2) return 0.0; $acc=0.0; foreach($vals as $v){ $acc += ($v-$mean)**2; } return round(sqrt($acc/($c-1)),2); }
}
