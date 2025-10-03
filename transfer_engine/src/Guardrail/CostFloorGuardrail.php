<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Config; use Unified\Support\Logger;
/** CostFloorGuardrail (Phase M3)
 * Ensures candidate price not below cost * margin floor.
 */
final class CostFloorGuardrail extends AbstractGuardrail
{
    public function __construct() { parent::__construct('GR_COST_FLOOR','Candidate price must respect cost floor'); }
    public function evaluate(array $ctx, Logger $logger): array
    {
        // ctx: cost, candidate_price, min_margin_pct
        $cost = (float)($ctx['cost'] ?? 0);
        $cand = (float)($ctx['candidate_price'] ?? 0);
        $minMargin = (float)($ctx['min_margin_pct'] ?? Config::get('neuro.unified.pricing.min_margin_pct',0.22));
        if ($cost <= 0 || $cand <=0) { return $this->block('Invalid cost or candidate price', ['cost'=>$cost,'candidate'=>$cand]); }
        $minPrice = $cost / (1 - $minMargin);
        if ($cand + 1e-6 < $minPrice) {
            return $this->block('Below cost floor', ['candidate'=>$cand,'min_price'=>$minPrice]);
        }
        return $this->pass(['candidate'=>$cand,'min_price'=>$minPrice]);
    }
}
