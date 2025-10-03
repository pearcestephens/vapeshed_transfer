<?php
declare(strict_types=1);
namespace Unified\Transfer;
use Unified\Support\Logger; use Unified\Support\BalancerAdapter;
/** LegacyAdapter (Phase M14)
 * Bridges placeholder BalancerAdapter to unified transfer candidate format.
 * Future: integrate real allocation logic & inventory queries.
 */
final class LegacyAdapter
{
    public function __construct(private Logger $logger, private BalancerAdapter $balancer) {}

    /**
     * Build normalized transfer candidates (static placeholder for now).
     * @return array<int,array>
     */
    public function candidates(array $opts = []): array
    {
        // TODO Phase M15+: call real allocation logic & compute candidate qty based on imbalance
        $samples = [
            [ 'sku'=>'SKU123','donor_outlet'=>101,'receiver_outlet'=>205,'qty'=>8,'donor_stock'=>120,'receiver_stock'=>10,'donor_avg_daily'=>6,'receiver_avg_daily'=>12 ],
            [ 'sku'=>'SKU456','donor_outlet'=>104,'receiver_outlet'=>207,'qty'=>4,'donor_stock'=>70,'receiver_stock'=>6,'donor_avg_daily'=>5,'receiver_avg_daily'=>9 ],
        ];
        $this->logger->info('transfer.adapter.candidates',[ 'count'=>count($samples) ]);
        return $samples;
    }
}
