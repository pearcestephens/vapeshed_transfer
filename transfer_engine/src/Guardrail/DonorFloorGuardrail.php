<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Config; use Unified\Support\Logger;
/** DonorFloorGuardrail (Phase M4)
 * Ensures donor DSR not pushed below minimum.
 */
final class DonorFloorGuardrail extends AbstractGuardrail
{
    public function __construct() { parent::__construct('GR_DONOR_FLOOR','Donor DSR must remain above minimum'); }
    public function evaluate(array $ctx, Logger $logger): array
    {
        $post = (float)($ctx['donor_dsr_post'] ?? 0);
        $min = (float)($ctx['donor_min_dsr'] ?? Config::get('neuro.unified.balancer.donor_min_dsr', 5));
        if ($post < $min) { return $this->block('Donor DSR below floor', ['post'=>$post,'min'=>$min]); }
        return $this->pass(['post'=>$post,'min'=>$min]);
    }
}
