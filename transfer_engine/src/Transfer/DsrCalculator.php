<?php
declare(strict_types=1);
namespace Unified\Transfer;
use Unified\Support\Logger;
/** DsrCalculator (Phase M14)
 * Computes days of supply metrics pre/post transfer for donor/receiver.
 */
final class DsrCalculator
{
    public function __construct(private Logger $logger) {}
    /**
     * @param array $item { stock_on_hand, avg_daily_demand }
     */
    public function dsr(array $item): float
    {
        $demand = (float)($item['avg_daily_demand'] ?? 0.0);
        if ($demand <= 0) return 0.0;
        return round(((float)($item['stock_on_hand'] ?? 0.0)) / max($demand, 0.0001),2);
    }

    /**
     * Compute post-transfer donor/receiver DSR projections.
     * @return array { donor_dsr_post, receiver_dsr_post }
     */
    public function project(array $donor, array $receiver, int $qty): array
    {
        $donorPost = $donor; $receiverPost = $receiver;
        $donorPost['stock_on_hand'] = max(0, ($donorPost['stock_on_hand'] ?? 0) - $qty);
        $receiverPost['stock_on_hand'] = ($receiverPost['stock_on_hand'] ?? 0) + $qty;
        return [
            'donor_dsr_post' => $this->dsr($donorPost),
            'receiver_dsr_post' => $this->dsr($receiverPost)
        ];
    }
}
