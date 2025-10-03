<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Security;
use App\Services\TransferEngineService;

/**
 * API: Auto-tune engine parameters by sweeping safe ranges in dry+test_mode
 */
class AutoTuneController extends BaseController
{
    public function run(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        try {
            // Accept JSON payload with optional bounds and products
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];

            // Base config (always non-destructive)
            $base = [
                'dry' => 1,
                'test_mode' => 1,
                'min_lines' => (int)($payload['min_lines'] ?? 3),
                'max_per_product' => (int)($payload['max_per_product'] ?? 40),
                'reserve_percent' => (float)($payload['reserve_percent'] ?? 0.20),
                'reserve_min_units' => 2,
                'min_cap_per_outlet' => (int)($payload['min_cap_per_outlet'] ?? 10),
            ];

            $methods  = $payload['weight_methods'] ?? ['power','softmax'];
            $gammas   = $payload['gammas'] ?? [1.5, 1.7, 1.8, 1.9, 2.0];
            $taus     = $payload['taus']   ?? [4.0, 6.0, 8.0];
            $reserves = $payload['reserves'] ?? [0.10, 0.15, 0.20, 0.25];
            $minCaps  = $payload['min_caps'] ?? [8, 10, 12];
            $maxPer   = $payload['max_per']  ?? [10, 12, 16, 20, 40];
            $minLines = $payload['min_lines_set'] ?? [3, 5];

            // Dataset: explicit products or synthetic demo (supports count/outlets)
            $products = is_array($payload['products'] ?? null) ? $payload['products'] : [];
            if (empty($products) && !empty($payload['demo_products'])) {
                $count = max(1, (int)($payload['count'] ?? 50));
                $outletsN = max(1, (int)($payload['outlets'] ?? 6));
                $outletIds = [];
                for ($o=1; $o <= $outletsN; $o++) { $outletIds[] = 'OUT-' . $o; }
                for ($i=1; $i <= $count; $i++) {
                    $pid = 'P-DEMO-' . $i;
                    $warehouse = 30 + (($i * 7) % 91);
                    $outletStocks = [];
                    $velocities = [];
                    foreach ($outletIds as $idx => $oid) {
                        $outletStocks[$oid] = ($i + $idx) % 6 === 0 ? 0 : (($i + $idx) % 12);
                        $velocities[$oid] = 1 + (($i * ($idx+1)) % 8);
                    }
                    $products[] = [
                        'product_id' => $pid,
                        'warehouse_stock' => $warehouse,
                        'outlet_stocks' => $outletStocks,
                        'sales_velocity' => $velocities,
                        'turnover_rate' => []
                    ];
                }
            }

            $engine = new TransferEngineService();
            $results = [];

            // Simple scoring function favors higher lines with bounded total qty
            $scoreBest = -1;
            $best = null;

            foreach ($methods as $method) {
                if (!in_array($method, ['power','softmax'], true)) continue;

                // Sweep knobs common to both methods
                foreach ($minCaps as $mc) {
                    foreach ($maxPer as $mp) {
                        foreach ($minLines as $ml) {
                            if ($method === 'power') {
                                foreach ($gammas as $g) {
                                    foreach ($reserves as $r) {
                                        $cfg = $base + [
                                            'weight_method' => 'power',
                                            'weight_gamma' => (float)$g,
                                            'reserve_percent' => (float)$r,
                                            'min_cap_per_outlet' => (int)$mc,
                                            'max_per_product' => (int)$mp,
                                            'min_lines' => (int)$ml,
                                        ];
                                        $res = $engine->executeTransfer($cfg, $products);
                                        $score = $this->score($res);
                                        $entry = [ 'config' => $cfg, 'metrics' => $res['summary'] ?? [], 'score' => $score, 'fairness' => $this->fairness($res) ];
                                        $results[] = $entry;
                                        if ($score > $scoreBest) { $scoreBest = $score; $best = $entry; }
                                    }
                                }
                            } else { // softmax
                                foreach ($taus as $t) {
                                    foreach ($reserves as $r) {
                                        $cfg = $base + [
                                            'weight_method' => 'softmax',
                                            'softmax_tau' => (float)$t,
                                            'reserve_percent' => (float)$r,
                                            'min_cap_per_outlet' => (int)$mc,
                                            'max_per_product' => (int)$mp,
                                            'min_lines' => (int)$ml,
                                        ];
                                        $res = $engine->executeTransfer($cfg, $products);
                                        $score = $this->score($res);
                                        $entry = [ 'config' => $cfg, 'metrics' => $res['summary'] ?? [], 'score' => $score, 'fairness' => $this->fairness($res) ];
                                        $results[] = $entry;
                                        if ($score > $scoreBest) { $scoreBest = $score; $best = $entry; }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'best' => $best,
                    'results' => $results,
                ],
                'meta' => [ 'timestamp' => date('c'), 'simulate' => true, 'test_mode' => true ]
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function score(array $res): float
    {
        $s = $res['summary'] ?? [];
        $lines = (int)($s['total_lines'] ?? 0);
        $qty = (int)($s['total_quantity'] ?? 0);
        $outlets = (int)($s['outlets_affected'] ?? 0);
        // Encourage more coverage and lines, penalize excessive quantity and poor fairness
        $fair = $this->fairness($res); // 0 (worst) .. 1 (best)
        return $lines * 2 + $outlets + ($fair * 5) - max(0, $qty - 500) * 0.01;
    }

    private function fairness(array $res): float
    {
        // Compute a simple fairness measure across outlets: 1 - normalized Gini on line counts
        $alloc = $res['allocations'] ?? [];
        if (empty($alloc)) return 0.5; // neutral
        $counts = [];
        foreach ($alloc as $productAlloc) {
            foreach ($productAlloc as $line) {
                $oid = $line['outlet_id'] ?? 'UNKNOWN';
                $counts[$oid] = ($counts[$oid] ?? 0) + 1;
            }
        }
        $vals = array_values($counts);
        $n = count($vals);
        if ($n <= 1) return 0.5;
        sort($vals);
        $cum = 0; $sum = array_sum($vals);
        if ($sum <= 0) return 0.5;
        foreach ($vals as $i => $v) {
            $cum += ($i+1) * $v;
        }
        // Gini = (2*sum(i*v_i))/(n*sum(v)) - (n+1)/n
        $gini = (2 * $cum) / ($n * $sum) - (($n + 1) / $n);
        $gini = max(0.0, min(1.0, $gini));
        return 1.0 - $gini; // fairness: 1=even, 0=uneven
    }
}
