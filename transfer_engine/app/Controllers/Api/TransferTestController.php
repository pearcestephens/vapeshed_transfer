<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Security;
use App\Services\TransferEngineService;

/**
 * API: Safe test-run endpoint to demonstrate engine behavior without writes
 */
class TransferTestController extends BaseController
{
    public function demo(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        try {
            // Build a minimal, safe configuration. Always DRY and TEST MODE.
            $config = [
                'dry' => 1,
                'min_lines' => 3,
                'max_per_product' => 40,
                'reserve_percent' => 0.20,
                'reserve_min_units' => 2,
                'weight_method' => 'power',
                'weight_gamma' => 1.8,
                'softmax_tau' => 6.0,
                'weight_mix_beta' => 0.8,
                // Optional test flag read by service to use synthetic data if needed
                'test_mode' => 1,
            ];

            // Optional query overrides (non-destructive)
            $q = $_GET;
            if (isset($q['min_lines']))   { $config['min_lines'] = max(1, (int)$q['min_lines']); }
            if (isset($q['max_per']))     {
                $mp = (int)$q['max_per'];
                // Allow 0 to mean "no cap" in test mode by mapping to a large sentinel
                $config['max_per_product'] = $mp <= 0 ? 1000000 : max(1, $mp);
            }
            if (isset($q['reserve']))     { $config['reserve_percent'] = max(0.0, min(0.9, (float)$q['reserve'])); }
            if (isset($q['method']))      { $config['weight_method'] = in_array($q['method'], ['power','softmax']) ? $q['method'] : 'power'; }
            if (isset($q['gamma']))       { $config['weight_gamma'] = max(0.1, (float)$q['gamma']); }
            if (isset($q['min_cap']))     { $config['min_cap_per_outlet'] = max(0, (int)$q['min_cap']); }
            if (isset($q['min_lines']))   { $config['min_lines'] = max(1, (int)$q['min_lines']); }

            // Outlet filters: only_outlet_codes, only_outlet_ids, skip_outlets
            if (!empty($q['only_codes'])) { $config['only_outlet_codes'] = $q['only_codes']; }
            if (!empty($q['only_ids']))   { $config['only_outlet_ids'] = $q['only_ids']; }
            if (!empty($q['skip']))       { $config['skip_outlets'] = $q['skip']; }

            $engine = new TransferEngineService();

            // Generate a synthetic product list for demo if caller asks (supports count/outlets)
            $products = [];
            if (!empty($q['demo_products'])) {
                $count = isset($q['count']) ? max(1, (int)$q['count']) : 50;
                $outletsN = isset($q['outlets']) ? max(1, (int)$q['outlets']) : 6;
                $outletIds = [];
                for ($o=1; $o <= $outletsN; $o++) { $outletIds[] = 'OUT-' . $o; }
                for ($i=1; $i <= $count; $i++) {
                    $pid = 'P-DEMO-' . $i;
                    // Scenario types: new, seeding, low-sales restock, high-stock
                    $scenarioType = $i % 4; // 0=new,1=seeding,2=low-sales,3=high-stock
                    // Warehouse stock bands: new small, seeding medium, low-sales low, high-stock large
                    $warehouse = match($scenarioType) {
                        0 => 24 + (($i * 3) % 12),    // new: ~24-35
                        1 => 55 + (($i * 5) % 25),    // seeding: ~55-79
                        2 => 28 + (($i * 2) % 14),    // low-sales restock: ~28-41
                        default => 120 + (($i * 7) % 80) // high-stock: ~120-199
                    };
                    $outletStocks = [];
                    $velocities = [];
                    foreach ($outletIds as $idx => $oid) {
                        if ($scenarioType === 0) {
                            // New: zero or near-zero everywhere
                            $outletStocks[$oid] = ($i + $idx) % 5 === 0 ? 1 : 0;
                            $velocities[$oid] = 1 + (($idx + $i) % 2); // tiny
                        } elseif ($scenarioType === 1) {
                            // Seeding: small stocks, medium velocity at top two
                            $outletStocks[$oid] = ($idx % 2) ? 1 : 0;
                            $velocities[$oid] = 2 + (($idx + 1) % 3);
                        } elseif ($scenarioType === 2) {
                            // Low-sales restock: residual stock, low velocity
                            $outletStocks[$oid] = 1 + (($i + $idx) % 4);
                            $velocities[$oid] = 1 + (($i + $idx) % 2);
                        } else {
                            // High-stock push: outlet stocks varied, velocity higher in first 3
                            $outletStocks[$oid] = ($i + $idx) % 8;
                            $velocities[$oid] = 2 + (($idx < 3) ? 4 : 2) + (($i + $idx) % 2);
                        }
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

            $result = $engine->executeTransfer($config, $products);

            // Optional lightweight outlet comparison summary (e.g., Frankton vs Whakatane)
            $compare = [];
            if (!empty($q['compare_codes'])) {
                $codes = array_filter(array_map('trim', explode(',', (string)$q['compare_codes'])));
                // Build totals by store_code if present in outlet list
                $codeTotals = [];
                foreach (($result['allocations'] ?? []) as $pa) {
                    foreach ($pa as $row) {
                        $outletId = $row['outlet_id'];
                        $qty = (int)$row['quantity'];
                        // Find this outlet's code from result outlets
                        foreach ($result['outlets'] as $o) {
                            if ($o['outlet_id'] === $outletId) {
                                $code = $o['store_code'] ?? null;
                                if ($code) {
                                    $codeTotals[$code] = ($codeTotals[$code] ?? 0) + $qty;
                                }
                                break;
                            }
                        }
                    }
                }
                foreach ($codes as $c) {
                    $compare[$c] = $codeTotals[$c] ?? 0;
                }
            }

            echo json_encode([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'simulate' => true,
                    'test_mode' => true,
                    'timestamp' => date('c'),
                    'compare' => $compare,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'meta' => [ 'timestamp' => date('c') ]
            ]);
        }
    }

    /**
     * API: Fairness sweep over multiple parameter sets (safe demo mode, no writes)
     * Query params (all optional):
     *   reserves: CSV of reserve fractions e.g. 0.1,0.2
     *   max_per: CSV of integers e.g. 30,40
     *   method: CSV of 'power'|'softmax'
     *   gamma: CSV of floats e.g. 1.5,1.8
     *   dynamic_k: CSV of 0/1
     *   count: products count (default 60)
     *   outlets: outlets count (default 8)
     *   max_runs: cap total combinations (default 12)
     */
    public function sweep(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        try {
            $q = $_GET;
            $count = isset($q['count']) ? max(1, (int)$q['count']) : 60;
            $outletsN = isset($q['outlets']) ? max(1, (int)$q['outlets']) : 8;
            $maxRuns = isset($q['max_runs']) ? max(1, (int)$q['max_runs']) : 12;

            $parseCsvNum = function ($key, $default) use ($q) {
                if (empty($q[$key])) { return $default; }
                $parts = array_map('trim', explode(',', (string)$q[$key]));
                $nums = [];
                foreach ($parts as $p) { if ($p === '') continue; $nums[] = (float)$p; }
                return $nums ?: $default;
            };
            $parseCsvInt = function ($key, $default) use ($q) {
                if (empty($q[$key])) { return $default; }
                $parts = array_map('trim', explode(',', (string)$q[$key]));
                $nums = [];
                foreach ($parts as $p) { if ($p === '') continue; $nums[] = (int)$p; }
                return $nums ?: $default;
            };
            $parseCsvMethod = function ($key, $default) use ($q) {
                if (empty($q[$key])) { return $default; }
                $parts = array_map('trim', explode(',', (string)$q[$key]));
                $out = [];
                foreach ($parts as $p) { if (in_array($p, ['power','softmax'], true)) { $out[] = $p; } }
                return $out ?: $default;
            };
            $parseCsvBool = function ($key, $default) use ($q) {
                if (empty($q[$key])) { return $default; }
                $parts = array_map('trim', explode(',', (string)$q[$key]));
                $out = [];
                foreach ($parts as $p) { $out[] = ((string)$p === '1' || strtolower($p) === 'true') ? 1 : 0; }
                return $out ?: $default;
            };

            // Defaults keep total combos small; users can expand via CSV
            $reserves = $parseCsvNum('reserves', [0.20]);
            $maxPer  = $parseCsvInt('max_per', [40]);
            $methods = $parseCsvMethod('method', ['power','softmax']);
            $gammas  = $parseCsvNum('gamma', [1.8]);
            $dynKs   = $parseCsvBool('dynamic_k', [0,1]);

            // Build deterministic demo dataset once to compare apples-to-apples
            $outletIds = [];
            for ($o=1; $o <= $outletsN; $o++) { $outletIds[] = 'OUT-' . $o; }
            $baseProducts = [];
            for ($i=1; $i <= $count; $i++) {
                $pid = 'P-DEMO-' . $i;
                $scenarioType = $i % 4;
                $warehouse = match($scenarioType) {
                    0 => 24 + (($i * 3) % 12),
                    1 => 55 + (($i * 5) % 25),
                    2 => 28 + (($i * 2) % 14),
                    default => 120 + (($i * 7) % 80)
                };
                $outletStocks = [];
                $velocities = [];
                foreach ($outletIds as $idx => $oid) {
                    if ($scenarioType === 0) {
                        $outletStocks[$oid] = ($i + $idx) % 5 === 0 ? 1 : 0;
                        $velocities[$oid] = 1 + (($idx + $i) % 2);
                    } elseif ($scenarioType === 1) {
                        $outletStocks[$oid] = ($idx % 2) ? 1 : 0;
                        $velocities[$oid] = 2 + (($idx + 1) % 3);
                    } elseif ($scenarioType === 2) {
                        $outletStocks[$oid] = 1 + (($i + $idx) % 4);
                        $velocities[$oid] = 1 + (($i + $idx) % 2);
                    } else {
                        $outletStocks[$oid] = ($i + $idx) % 8;
                        $velocities[$oid] = 2 + (($idx < 3) ? 4 : 2) + (($i + $idx) % 2);
                    }
                }
                $baseProducts[] = [
                    'product_id' => $pid,
                    'warehouse_stock' => $warehouse,
                    'outlet_stocks' => $outletStocks,
                    'sales_velocity' => $velocities,
                    'turnover_rate' => []
                ];
            }

            $engine = new TransferEngineService();

            $runs = [];
            $totalCombos = 0;
            foreach ($reserves as $reserve) {
                foreach ($maxPer as $mp) {
                    foreach ($methods as $method) {
                        foreach ($gammas as $gamma) {
                            foreach ($dynKs as $dk) {
                                if ($totalCombos >= $maxRuns) { break 5; }
                                $totalCombos++;

                                $config = [
                                    'dry' => 1,
                                    'min_lines' => 3,
                                    // Allow 0 to mean unlimited in sweep/demo context
                                    'max_per_product' => ((int)$mp) <= 0 ? 1000000 : max(1, (int)$mp),
                                    'reserve_percent' => max(0.0, min(0.9, (float)$reserve)),
                                    'reserve_min_units' => 2,
                                    'weight_method' => $method,
                                    'weight_gamma' => max(0.1, (float)$gamma),
                                    'softmax_tau' => 6.0,
                                    'weight_mix_beta' => 0.8,
                                    'test_mode' => 1,
                                ];
                                if ($dk) { $config['dynamic_top_k'] = 1; }

                                // Copy products to avoid accidental mutation across runs
                                $products = $baseProducts; // arrays are copied by value in PHP for scalars; nested arrays ok as no mutation expected
                                $result = $engine->executeTransfer($config, $products);

                                // Metrics: lines, units, per-outlet totals, fairness
                                $lines = 0; $units = 0; $perOutlet = [];
                                if (!empty($result['allocations'])) {
                                    foreach ($result['allocations'] as $rows) {
                                        foreach ($rows as $row) {
                                            $qty = (int)($row['quantity'] ?? 0);
                                            if ($qty > 0) { $lines++; $units += $qty; $oid = (string)($row['outlet_id'] ?? ''); if ($oid !== '') { $perOutlet[$oid] = ($perOutlet[$oid] ?? 0) + $qty; } }
                                        }
                                    }
                                } elseif (!empty($result['decision_trace'])) {
                                    foreach ($result['decision_trace'] as $t) {
                                        foreach (($t['outlets'] ?? []) as $row) {
                                            if (($row['reason'] ?? '') === 'allocated') {
                                                $qty = (int)($row['allocated_qty'] ?? 0);
                                                if ($qty > 0) { $lines++; $units += $qty; $oid = (string)($row['outlet_id'] ?? ''); if ($oid !== '') { $perOutlet[$oid] = ($perOutlet[$oid] ?? 0) + $qty; } }
                                            }
                                        }
                                    }
                                }

                                $outletsAffected = 0; $values = [];
                                foreach ($perOutlet as $v) { if ($v > 0) { $outletsAffected++; $values[] = $v; } }
                                $fairness = $this->fairnessOneMinusGini($values);

                                $runs[] = [
                                    'params' => [
                                        'reserve' => (float)$reserve,
                                        'max_per' => (int)$mp,
                                        'method' => $method,
                                        'gamma' => (float)$gamma,
                                        'dynamic_k' => (int)$dk,
                                    ],
                                    'metrics' => [
                                        'lines' => $lines,
                                        'units' => $units,
                                        'products' => $count,
                                        'outlets_affected' => $outletsAffected,
                                        'fairness' => $fairness,
                                        'units_per_line' => $lines ? round($units/$lines, 2) : 0.0,
                                        'lines_per_product' => $count ? round($lines/$count, 2) : 0.0,
                                    ]
                                ];
                            }
                        }
                    }
                }
            }

            // Aggregate summary
            $fairnessVals = array_map(fn($r) => (float)$r['metrics']['fairness'], $runs);
            $avgFair = $this->avg($fairnessVals);
            $bestIdx = $this->argmax($fairnessVals);
            $worstIdx = $this->argmin($fairnessVals);
            $summary = [
                'runs' => count($runs),
                'avg_fairness' => $avgFair,
                'best' => $runs[$bestIdx] ?? null,
                'worst' => $runs[$worstIdx] ?? null,
            ];

            echo json_encode([
                'success' => true,
                'data' => [
                    'runs' => $runs,
                    'summary' => $summary,
                ],
                'meta' => [
                    'simulate' => true,
                    'test_mode' => true,
                    'timestamp' => date('c'),
                    'max_runs' => $maxRuns,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'meta' => [ 'timestamp' => date('c') ]
            ]);
        }
    }

    /**
     * API: Best-spread selector (POST JSON)
     * Finds a single configuration that maximizes distribution fairness across outlets and per-product
     * JSON body (all fields optional):
     * {
     *   products: [ { product_id, warehouse_stock, outlet_stocks: {OUT-1: n,...}, sales_velocity: {...} } ],
     *   bands: { low: 20, medium: 20, high: 20 },
     *   outlets: 8,
     *   sweep: { reserves: [0.15,0.2,0.25], max_per: [30,40], method: ["power","softmax"], gamma: [1.6,1.8], dynamic_k: [0,1], max_runs: 20 },
     *   objective: { outlet_weight: 0.5, product_weight: 0.5, units_weight: 0.0 }
     * }
     */
    public function bestSpread(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        try {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw ?: '{}', true) ?: [];

            $outletsN = isset($body['outlets']) ? max(1, (int)$body['outlets']) : 8;
            $sweep = $body['sweep'] ?? [];
            $objective = $body['objective'] ?? [];
            $ow = isset($objective['outlet_weight']) ? max(0.0, min(1.0, (float)$objective['outlet_weight'])) : 0.5;
            $pw = isset($objective['product_weight']) ? max(0.0, min(1.0, (float)$objective['product_weight'])) : 0.5;
            $uw = isset($objective['units_weight']) ? max(0.0, min(1.0, (float)$objective['units_weight'])) : 0.0;
            $norm = max(1.0, $ow + $pw + $uw);
            $ow /= $norm; $pw /= $norm; $uw /= $norm;

            // Build dataset: use provided products or generate via bands
            $outletIds = [];
            for ($o=1; $o <= $outletsN; $o++) { $outletIds[] = 'OUT-' . $o; }
            $products = [];
            if (!empty($body['products']) && is_array($body['products'])) {
                foreach ($body['products'] as $p) {
                    if (!isset($p['product_id'], $p['warehouse_stock'])) { continue; }
                    $products[] = [
                        'product_id' => (string)$p['product_id'],
                        'warehouse_stock' => (int)$p['warehouse_stock'],
                        'outlet_stocks' => (array)($p['outlet_stocks'] ?? []),
                        'sales_velocity' => (array)($p['sales_velocity'] ?? []),
                        'turnover_rate' => (array)($p['turnover_rate'] ?? [])
                    ];
                }
            } else {
                $bands = $body['bands'] ?? ['low'=>20,'medium'=>20,'high'=>20];
                $total = max(1, (int)(($bands['low'] ?? 0) + ($bands['medium'] ?? 0) + ($bands['high'] ?? 0)));
                $i = 0;
                // helper to push product with ranges
                $pushBand = function(int $n, int $minW, int $maxW, int $scenario) use (&$i, &$products, $outletIds) {
                    for ($k=0; $k<$n; $k++) {
                        $i++;
                        $pid = 'P-CUST-' . $i;
                        $warehouse = $minW + ($i*7 % max(1, $maxW-$minW+1));
                        $outletStocks = [];
                        $velocities = [];
                        foreach ($outletIds as $idx => $oid) {
                            if ($scenario === 0) { // new
                                $outletStocks[$oid] = ($i + $idx) % 5 === 0 ? 1 : 0;
                                $velocities[$oid] = 1 + (($idx + $i) % 2);
                            } elseif ($scenario === 1) { // seeding
                                $outletStocks[$oid] = ($idx % 2) ? 1 : 0;
                                $velocities[$oid] = 2 + (($idx + 1) % 3);
                            } else { // high-stock push
                                $outletStocks[$oid] = ($i + $idx) % 8;
                                $velocities[$oid] = 2 + (($idx < 3) ? 4 : 2) + (($i + $idx) % 2);
                            }
                        }
                        $products[] = [
                            'product_id' => $pid,
                            'warehouse_stock' => $warehouse,
                            'outlet_stocks' => $outletStocks,
                            'sales_velocity' => $velocities,
                            'turnover_rate' => []
                        ];
                    }
                };
                $pushBand((int)($bands['low'] ?? 0), 20, 40, 0);
                $pushBand((int)($bands['medium'] ?? 0), 50, 85, 1);
                $pushBand((int)($bands['high'] ?? 0), 110, 200, 2);
            }

            // Sweep params
            $reserves = $this->csvOrDefault($sweep['reserves'] ?? null, [0.15, 0.20, 0.25]);
            $maxPer   = $this->csvOrDefault($sweep['max_per'] ?? null, [30, 40]);
            $methods  = $this->csvOrDefault($sweep['method'] ?? null, ['power','softmax']);
            $gammas   = $this->csvOrDefault($sweep['gamma'] ?? null, [1.6, 1.8]);
            $dynKs    = $this->csvOrDefault($sweep['dynamic_k'] ?? null, [0,1]);
            $maxRuns  = isset($sweep['max_runs']) ? max(1, (int)$sweep['max_runs']) : 20;

            $engine = new TransferEngineService();
            $runs = [];
            $total = 0;
            foreach ($reserves as $reserve) {
                foreach ($maxPer as $mp) {
                    foreach ($methods as $method) {
                        foreach ($gammas as $gamma) {
                            foreach ($dynKs as $dk) {
                                if ($total >= $maxRuns) { break 5; }
                                $total++;
                                $config = [
                                    'dry' => 1,
                                    'min_lines' => 3,
                                    // Allow 0 to mean unlimited in best-spread sweep context
                                    'max_per_product' => ((int)$mp) <= 0 ? 1000000 : max(1, (int)$mp),
                                    'reserve_percent' => max(0.0, min(0.9, (float)$reserve)),
                                    'reserve_min_units' => 2,
                                    'weight_method' => in_array($method, ['power','softmax'], true) ? $method : 'power',
                                    'weight_gamma' => max(0.1, (float)$gamma),
                                    'softmax_tau' => 6.0,
                                    'weight_mix_beta' => 0.8,
                                    'test_mode' => 1,
                                ];
                                if ($dk) { $config['dynamic_top_k'] = 1; }

                                $result = $engine->executeTransfer($config, $products);
                                // Metrics
                                $m = $this->metricsFromResult($result);
                                $runs[] = [
                                    'params' => [
                                        'reserve' => (float)$reserve,
                                        'max_per' => (int)$mp,
                                        'method' => (string)$method,
                                        'gamma' => (float)$gamma,
                                        'dynamic_k' => (int)$dk,
                                    ],
                                    'metrics' => $m,
                                    'result' => $result,
                                ];
                            }
                        }
                    }
                }
            }

            if (empty($runs)) {
                echo json_encode(['success'=>true,'data'=>['runs'=>[],'summary'=>['runs'=>0]],'meta'=>['timestamp'=>date('c')]]);
                return;
            }

            // Normalize units for scoring
            $maxUnits = 0; foreach ($runs as $r) { $maxUnits = max($maxUnits, (int)($r['metrics']['units'] ?? 0)); }
            foreach ($runs as &$r) {
                $units = (int)($r['metrics']['units'] ?? 0);
                $fo = (float)($r['metrics']['fairness_outlet'] ?? 0);
                $fp = (float)($r['metrics']['fairness_product_avg'] ?? 0);
                $combined = $ow * $fo + $pw * $fp;
                $nu = $maxUnits > 0 ? ($units / $maxUnits) : 0.0;
                $score = $combined + $uw * $nu;
                $r['metrics']['fairness_combined'] = round($combined, 6);
                $r['metrics']['score'] = round($score, 6);
            }
            unset($r);

            // Pick best by score, tie-breakers
            usort($runs, function($a, $b) {
                $da = $a['metrics']['score'] <=> $b['metrics']['score']; if ($da !== 0) return -$da; // desc
                $fb = $a['metrics']['fairness_outlet'] <=> $b['metrics']['fairness_outlet']; if ($fb !== 0) return -$fb;
                $ub = $a['metrics']['units'] <=> $b['metrics']['units']; if ($ub !== 0) return -$ub;
                $lp = $a['metrics']['units_per_line'] <=> $b['metrics']['units_per_line']; if ($lp !== 0) return -$lp;
                return 0;
            });

            $best = $runs[0];
            $top = array_slice(array_map(function($r){ return [ 'params'=>$r['params'], 'metrics'=>$r['metrics'] ]; }, $runs), 0, 5);

            echo json_encode([
                'success' => true,
                'data' => [
                    'best' => $best,
                    'top' => $top,
                    'summary' => [
                        'runs' => count($runs),
                        'avg_fairness_outlet' => $this->avg(array_map(fn($r)=>(float)$r['metrics']['fairness_outlet'], $runs)),
                        'avg_fairness_product' => $this->avg(array_map(fn($r)=>(float)$r['metrics']['fairness_product_avg'], $runs)),
                    ],
                ],
                'meta' => [ 'timestamp' => date('c'), 'simulate'=>true, 'test_mode'=>true ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'meta' => [ 'timestamp' => date('c') ]
            ]);
        }
    }

    private function metricsFromResult(array $result): array
    {
        $lines = 0; $units = 0; $perOutlet = [];
        $perProductAlloc = []; // product_id => outlet_id => qty

        if (!empty($result['allocations'])) {
            foreach ($result['allocations'] as $pid => $rows) {
                foreach ($rows as $row) {
                    $qty = (int)($row['quantity'] ?? 0);
                    if ($qty > 0) {
                        $lines++; $units += $qty;
                        $oid = (string)($row['outlet_id'] ?? ''); if ($oid !== '') { $perOutlet[$oid] = ($perOutlet[$oid] ?? 0) + $qty; }
                        $perProductAlloc[$pid][$oid] = ($perProductAlloc[$pid][$oid] ?? 0) + $qty;
                    }
                }
            }
        } elseif (!empty($result['decision_trace'])) {
            foreach ($result['decision_trace'] as $pid => $t) {
                foreach (($t['outlets'] ?? []) as $row) {
                    if (($row['reason'] ?? '') === 'allocated') {
                        $qty = (int)($row['allocated_qty'] ?? 0);
                        if ($qty > 0) {
                            $lines++; $units += $qty;
                            $oid = (string)($row['outlet_id'] ?? ''); if ($oid !== '') { $perOutlet[$oid] = ($perOutlet[$oid] ?? 0) + $qty; }
                            $perProductAlloc[$pid][$oid] = ($perProductAlloc[$pid][$oid] ?? 0) + $qty;
                        }
                    }
                }
            }
        }

        $outletsAffected = 0; $vals = [];
        foreach ($perOutlet as $v) { if ($v > 0) { $outletsAffected++; $vals[] = $v; } }
        $fairOutlet = $this->fairnessOneMinusGini($vals);

        // Product-level fairness: average of (1-Gini) across each product's per-outlet allocations
        $prodFairList = [];
        foreach ($perProductAlloc as $pid => $dist) {
            $v = array_values(array_filter($dist, static fn($x)=>$x>0));
            if (!empty($v)) { $prodFairList[] = $this->fairnessOneMinusGini($v); }
        }
        $fairProdAvg = $this->avg($prodFairList);

        return [
            'lines' => $lines,
            'units' => $units,
            'outlets_affected' => $outletsAffected,
            'fairness_outlet' => $fairOutlet,
            'fairness_product_avg' => $fairProdAvg,
            'units_per_line' => $lines ? round($units/$lines, 2) : 0.0,
        ];
    }

    private function csvOrDefault($value, array $default): array
    {
        if ($value === null) return $default;
        if (is_array($value)) return $value ?: $default;
        $parts = array_map('trim', explode(',', (string)$value));
        return $parts ?: $default;
    }

    private function fairnessOneMinusGini(array $values): float
    {
        $v = array_values(array_filter($values, static fn($x) => $x > 0));
        $n = count($v);
        if ($n === 0) { return 0.0; }
        sort($v, SORT_NUMERIC);
        $sum = array_sum($v);
        if ($sum <= 0) { return 0.0; }
        $cum = 0.0; for ($i=0; $i<$n; $i++) { $cum += ($i+1) * $v[$i]; }
        $gini = (($n + 1) - 2 * ($cum / $sum)) / $n;
        $fair = 1.0 - $gini;
        if ($fair < 0) { return 0.0; }
        if ($fair > 1) { return 1.0; }
        return round($fair, 6);
    }

    private function avg(array $nums): float
    {
        $nums = array_values(array_filter($nums, static fn($x) => is_numeric($x)));
        $n = count($nums); if ($n === 0) return 0.0;
        return round(array_sum($nums) / $n, 6);
    }

    private function argmax(array $nums): int
    {
        if (empty($nums)) return 0;
        $max = null; $idx = 0; foreach ($nums as $i => $v) { if ($max === null || $v > $max) { $max = $v; $idx = $i; } }
        return $idx;
    }

    private function argmin(array $nums): int
    {
        if (empty($nums)) return 0;
        $min = null; $idx = 0; foreach ($nums as $i => $v) { if ($min === null || $v < $min) { $min = $v; $idx = $i; } }
        return $idx;
    }
}
