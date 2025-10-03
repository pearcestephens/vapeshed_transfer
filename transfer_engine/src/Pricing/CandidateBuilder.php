<?php
declare(strict_types=1);
namespace Unified\Pricing;
use Unified\Support\Logger;
/** CandidateBuilder (Phase M13 Skeleton)
 * Produces pricing candidates. Later will query DB / views; for now returns static samples.
 */
final class CandidateBuilder
{
    public function __construct(private Logger $logger) {}

    /** @return array<int,array> */
    public function build(array $context = []): array
    {
        // TODO Phase M14+: integrate real data sources (competitor gaps, cost, inventory)
        $samples = [
            [ 'sku'=>'SKU123','current_price'=>12.00,'candidate_price'=>12.50,'cost'=>5.00,'projected_roi'=>1.5,'donor_dsr_post'=>7,'receiver_dsr_post'=>15 ],
            [ 'sku'=>'SKU456','current_price'=>20.00,'candidate_price'=>19.40,'cost'=>9.50,'projected_roi'=>0.9,'donor_dsr_post'=>6,'receiver_dsr_post'=>18 ],
        ];
        $this->logger->info('pricing.candidates.generated',[ 'count'=>count($samples) ]);
        return $samples;
    }
}
