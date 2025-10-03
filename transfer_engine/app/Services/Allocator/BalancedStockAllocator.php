<?php
declare(strict_types=1);

namespace App\Services\Allocator;

/**
 * BalancedStockAllocator
 *
 * Stock-only allocator that ignores sales velocity and applies simple, fair rules:
 * - Keep hub reserve: max(reserve_min_units, reserve_percent * stock)
 * - For each outlet:
 *   - If stock = 0: seed 2–3 units
 *   - If stock < 5: top up to ~10
 *   - If stock < 20: small top-up (midTopup)
 *   - Else: healthy; consider only small proportional share
 * - Cap per store per product (maxPerStore)
 * - Optionally blend a small proportional share of remaining surplus across all outlets
 *
 * Inputs: product with 'product_id', 'warehouse_stock', and 'outlet_stocks'; outlets list
 * Output: rows of allocations for that product.
 */
final class BalancedStockAllocator
{
    private int $minReserveUnits;      // minimum units to keep at hub
    private float $reservePercent;     // additional hub reserve percent
    private int $seedQtyZero;          // qty when dest stock = 0
    private int $topupLowTo;           // top up threshold target (e.g., 10)
    private int $midTopup;             // small nudge when moderately low
    private int $maxPerStore;          // per-store cap per product
    private float $proportionalShare;  // fraction of remaining to distribute proportionally

    public function __construct(array $cfg = [])
    {
        $this->minReserveUnits  = (int)($cfg['reserve_min_units']   ?? 5);
        $this->reservePercent   = (float)($cfg['reserve_percent']    ?? 0.20);
        $this->seedQtyZero      = (int)($cfg['seed_qty_zero']        ?? 3);
        $this->topupLowTo       = (int)($cfg['topup_low_to']         ?? 10);
        $this->midTopup         = (int)($cfg['mid_topup']            ?? 5);
        $this->maxPerStore      = (int)($cfg['max_per_store']        ?? 40);
        $this->proportionalShare= (float)($cfg['proportional_share'] ?? 0.20);
    }

    /**
     * Allocate stock for a single product across destination outlets.
     *
     * @param array $product  ['product_id'=>string, 'warehouse_stock'=>int, 'outlet_stocks'=>[outlet_id=>int]]
     * @param array $outlets  [ ['outlet_id'=>string, 'name'=>string, 'store_code'=>?string, 'turnover_rate'=>?float], ... ]
     * @param array $weights  Optional map outlet_id => float weight for proportional share (uses turnover_rate if absent)
     * @return array          Rows: [ ['outlet_id','product_id','quantity','demand_score','proportion','capped'] ]
     */
    public function allocate(array $product, array $outlets, array $weights = [], array $cfg = []): array
    {
        $pid = (string)($product['product_id'] ?? '');
        $wh  = max(0, (int)($product['warehouse_stock'] ?? 0));
        if ($pid === '' || $wh <= 0) { return []; }

        // 1) Reserve at hub
        $reserveFloor = max($this->minReserveUnits, (int)ceil($wh * $this->reservePercent));
        $reserve      = min($wh, $reserveFloor);
        $surplus      = max(0, $wh - $reserve);
        if ($surplus <= 0) { return []; }

        // 2) Determine baseline needs per outlet
        $needs = [];
        $order = [];
        foreach ($outlets as $o) {
            $oid = (string)$o['outlet_id'];
            $s   = max(0, (int)($product['outlet_stocks'][$oid] ?? 0));
            $need = 0;
            if ($s === 0) {
                $need = $this->seedQtyZero;                 // seed 2–3
            } elseif ($s < 5) {
                $need = max(0, $this->topupLowTo - $s);     // top up to ~10
            } elseif ($s < 20) {
                $need = $this->midTopup;                    // small nudge
            } else {
                $need = 0;                                  // healthy; fair-share only
            }
            $needs[$oid] = min($need, $this->maxPerStore);
            $order[$oid] = [$s, 0.0];
        }

        // 3) Prepare weights for proportional share, sort by (stock asc, weight desc)
        $rawW = [];
        foreach ($outlets as $o) {
            $oid = (string)$o['outlet_id'];
            $rawW[$oid] = (float)($weights[$oid] ?? ($o['turnover_rate'] ?? ($cfg['default_turnover_pct'] ?? 5.0)));
            $order[$oid][1] = -$rawW[$oid]; // negative so asc stock, then desc weight
        }
        uasort($order, static function($a, $b) {
            return ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]);
        });

        // 4) First pass: satisfy baseline needs greedily
        $alloc = [];
        $remaining = $surplus;
        foreach (array_keys($order) as $oid) {
            if ($remaining <= 0) break;
            $want = (int)max(0, $needs[$oid] ?? 0);
            if ($want <= 0) continue;
            $give = min($want, $remaining, $this->maxPerStore);
            if ($give > 0) {
                $alloc[$oid] = ($alloc[$oid] ?? 0) + $give;
                $remaining  -= $give;
            }
        }

        // 5) Proportional share on remaining (small fraction)
        if ($remaining > 0 && $this->proportionalShare > 0.0) {
            $pool = (int)floor($remaining * $this->proportionalShare);
            $pool = min($pool, $remaining);
            if ($pool > 0) {
                $sumW = array_sum($rawW) ?: 1.0;
                foreach ($rawW as $oid => $w) {
                    $share = (int)floor(($w / $sumW) * $pool);
                    if ($share <= 0) continue;
                    $room = $this->maxPerStore - (int)($alloc[$oid] ?? 0);
                    $give = max(0, min($share, $room));
                    if ($give > 0) {
                        $alloc[$oid] = ($alloc[$oid] ?? 0) + $give;
                        $remaining   -= $give;
                        if ($remaining <= 0) break;
                    }
                }
            }
        }

        // 6) Build rows
        $rows = [];
        foreach ($alloc as $oid => $q) {
            if ($q > 0) {
                $rows[] = [
                    'outlet_id'    => $oid,
                    'product_id'   => $pid,
                    'quantity'     => (int)$q,
                    'demand_score' => 0.0,
                    'proportion'   => 0.0,
                    'capped'       => ($q >= $this->maxPerStore)
                ];
            }
        }
        return $rows;
    }
}
