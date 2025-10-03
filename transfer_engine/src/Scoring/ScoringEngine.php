<?php
declare(strict_types=1);
namespace Unified\Scoring;
use Unified\Support\Config; use Unified\Support\Logger;
/** ScoringEngine (Phase M5)
 * Aggregates feature contributions -> normalized score -> band classification.
 */
final class ScoringEngine
{
    public function __construct(private Logger $logger) { Config::prime(); }

    /**
     * @param array $features key => raw contribution (positive/negative)
     * @return array{score:float,band:string,auto_apply_min:float,propose_min:float,features:array}
     */
    public function score(array $features): array
    {
        $sum = 0.0; $abs = 0.0;
        foreach ($features as $k=>$v) { $sum += $v; $abs += abs($v); }
        $norm = $abs > 0 ? max(min($sum / ($abs ?: 1), 1), -1) : 0.0; // normalized between -1..1
        // Shift to 0..1 for banding
        $score = ($norm + 1) / 2.0; // -1 ->0, 1->1
        $auto = (float)Config::get('neuro.unified.policy.auto_apply_min',0.65);
        $prop = (float)Config::get('neuro.unified.policy.propose_min',0.15);
        $band = 'discard';
        if ($score >= $auto) $band = 'auto'; elseif ($score >= $prop) $band = 'propose';
        $this->logger->info('scoring.result',[ 'score'=>$score,'band'=>$band ]);
        return [ 'score'=>$score,'band'=>$band,'auto_apply_min'=>$auto,'propose_min'=>$prop,'features'=>$features ];
    }
}
