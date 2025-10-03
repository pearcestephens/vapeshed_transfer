<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Vapeshed — HTTP Ad-hoc Hub→Stores Rebalance (JSON Export)
 * ----------------------------------------------------------
 * • Runs over HTTP (GET params) and prints JSON to screen
 * • Stock-only allocator (no sales)
 * • Hub reserve: max(20%, 5 units) per product
 * • Baseline seed/top-up + weighted, inverse-saturation fair-share
 * • Unlimited products by default; cap with &limit-products=N (alias &limit=N)
 * • Dry run by default; when &live=1 inserts into:
 *      - stock_transfers (parent per destination)
 *      - stock_products_to_transfer (child per product line)
 *
 * Required param: hub=02dcd191-ae2b-11e6-f485-8eceed6eeafb
 *
 * Useful params (defaults):
 *   live=0
 *   limit-products=0            (0 = unlimited, still capped by max-products)
 *   reserve-percent=0.20        (0..0.90)
 *   reserve-min=5
 *   max-per-store=40
 *   min-line-qty=1
 *   prop-share=0.20
 *   target-level=10
 *   no-send-if-atleast=5        (final “drop if dest already has ≥ N”)
 *   round-multiple-over-10=5    (secondary rounding for big lines when no pack rule)
 *   alpha-deficiency=1.25
 *   cover-days=10
 *   seed-unclassified-default=3
 *   max-products=500
 *   max-outlets=30
 *
 * Exclusions:
 *   exclude-outlet-ids=...      (comma/space separated)
 *   exclude-codes=LEA           (store_code list; e.g. LEA=Leamington)
 *   exclude-name-like=TEST%     (exclude hub products whose name LIKE pattern)
 *
 * Brand/Supplier gating (modern):
 *   brand-supplier-mode=none|brand|supplier|and|or  (default none)
 *   supplier-flag=ordering|transferring|transfersales (default ordering)
 *
 * Legacy gating (kept for compatibility; ignored if brand-supplier-mode != none):
 *   filter-brand-enabled=0/1
 *   filter-supplier-auto=0/1    (uses supplier-flag column choice)
 *
 * Quantizer (final smoothing):
 *   snap-multiple=10            (near-ten snapping; e.g., 9→10, 11→10)
 *   snap-delta=1
 *
 * DISPOSVAPE:
 *   Always packed in 10s via brand rule + hard fallback.
 */

// ------------------------- HTTP/ENV GUARD -------------------------
set_time_limit(600);
ini_set('memory_limit', '1024M');

// ------------------------- DB DEFAULTS ----------------------------
$dbHost = $_GET['db-host'] ?? (getenv('CIS_DB_HOST') ?: '127.0.0.1');
$dbName = $_GET['db-name'] ?? (getenv('CIS_DB_NAME') ?: 'jcepnzzkmj');
$dbUser = $_GET['db-user'] ?? (getenv('CIS_DB_USER') ?: 'jcepnzzkmj');
$dbPass = $_GET['db-pass'] ?? (getenv('CIS_DB_PASS') ?: 'wprKh9Jq63');
$dbPort = (int)($_GET['db-port'] ?? (getenv('CIS_DB_PORT') ?: 3306));

// ------------------------- PARAMS ---------------------------------
$hubId          = req('hub');
$live           = (int)($_GET['live'] ?? 0);
$limitProducts  = (int)($_GET['limit-products'] ?? ($_GET['limit'] ?? 0));
$reservePct     = clampFloat((float)($_GET['reserve-percent'] ?? 0.20), 0.0, 0.90);
$reserveMin     = max(0, (int)($_GET['reserve-min'] ?? 5));
$maxPerStore    = max(1, (int)($_GET['max-per-store'] ?? 40));
$minLineQty     = max(1, (int)($_GET['min-line-qty'] ?? 1));
$propShare      = clampFloat((float)($_GET['prop-share'] ?? 0.20), 0.0, 1.0);
$targetLevel    = max(1, (int)($_GET['target-level'] ?? 10));
$runId          = 'run_' . date('Ymd_His') . '_' . random_int(100000, 999999);

// Algorithm tuning
$noSendIfAtLeast  = max(0, (int)($_GET['no-send-if-atleast'] ?? 5));      // final drop threshold
$alphaDeficiency  = (float)($_GET['alpha-deficiency'] ?? 1.25);
$roundMultOver10  = max(1, (int)($_GET['round-multiple-over-10'] ?? 5));

// Anti-waste & rounding guardrails (new, optional)
$highStockFactor            = clampFloat((float)($_GET['high-stock-factor'] ?? 1.5), 1.0, 5.0); // drop entirely if dest >= factor * target
$seedMaxOpen                = max(0, (int)($_GET['seed-max-open'] ?? 10));                       // cap for first-time seed openings
$seedMinQty                 = max(0, (int)($_GET['seed-min-qty'] ?? 2));                         // minimum for seed lines
$singletonKillerThreshold   = max(1, (int)($_GET['singleton-killer-threshold'] ?? 1));           // drop lines <= threshold (unless seeded)
$debugDecisionIndex         = (int)($_GET['debug-decision-index'] ?? 0);                         // include decision_index in meta if 1
$debugDecisionTrace         = (int)($_GET['debug-decision-trace'] ?? 0);                          // include per-line _trace if 1
// Turnover-aware seeding (per-outlet scaling of seed thresholds)
$turnoverSeedEnabled        = (int)($_GET['turnover-seed'] ?? 1);
// Turnover band config mode (default | override) + optional overrides (tb-*)
$turnoverBandsMode          = strtolower((string)($_GET['turnover-bands-mode'] ?? 'default'));

// Demand/seed tuning and caps
$coverDays = max(1, (int)($_GET['cover-days'] ?? 10));
$seedUnclassifiedDefault = max(0, (int)($_GET['seed-unclassified-default'] ?? 3));
$maxProductsCap = max(10, (int)($_GET['max-products'] ?? 500));
$maxOutletsCap  = max(1,  (int)($_GET['max-outlets']  ?? 30));

// Name pattern exclusion (hub fetch)
$excludeNameLike = trim((string)($_GET['exclude-name-like'] ?? ''));

// Brand/Supplier gating (modern)
$filterMode   = strtolower($_GET['brand-supplier-mode'] ?? 'none'); // none|brand|supplier|and|or
$supplierFlag = strtolower($_GET['supplier-flag'] ?? 'ordering');   // ordering|transferring|transfersales

// Legacy flags (only used if $filterMode === 'none')
$filterBrandEnabled = (int)($_GET['filter-brand-enabled'] ?? 0);
$filterSupplierAuto = (int)($_GET['filter-supplier-auto'] ?? 0);

// Quantizer
$snapMultiple = (int)($_GET['snap-multiple'] ?? 10);
$snapDelta    = (int)($_GET['snap-delta'] ?? 1);

// Canonical classification / pack system integration (optional, non-breaking)
$packPriorityMode          = strtolower((string)($_GET['pack-priority'] ?? 'product-first')); // product-first|category-first
$GLOBALS['packPriorityMode'] = $packPriorityMode;
$classificationSource       = strtolower((string)($_GET['classification-source'] ?? 'unified')); // unified|canonical|auto
$useOverrides               = (int)($_GET['use-overrides'] ?? 1); // 1=allow product/brand overrides, 0=category-only when category-first
$enforceCategoryPack        = (int)($_GET['enforce-category-pack'] ?? 0); // 0=off, 1=force outer multiple adherence when category rule applies
$GLOBALS['classificationSource'] = $classificationSource;
$GLOBALS['useOverrides']         = (int)$useOverrides;
$GLOBALS['enforceCategoryPack']  = (int)$enforceCategoryPack;
$enforceCategoryMinTransfer= (int)($_GET['enforce-min-transfer'] ?? 0); // 0=off (default)
$enforceCategorySeedDefault= (int)($_GET['enforce-category-seed-default'] ?? 0); // 0=off (default)

// Freight estimation (optional)
$estimateFreight = (int)($_GET['estimate-freight'] ?? 0);
$carrierId       = (int)($_GET['carrier-id'] ?? 1); // 1=NZ Post by convention

// ------------------------- MAIN -----------------------------------
try {


    $isEntrypoint = isset($_SERVER['SCRIPT_FILENAME'])
    && @realpath($_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__);

if ($isEntrypoint && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

    // DB + session guards
    $db = db($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    setSessionGuards($db);

    // Dest outlets
    $destOutlets = fetchDestOutlets($db, $hubId);

    // Exclude outlets by id / code (e.g. &exclude-codes=LEA)
    $excludeIds   = listParam('exclude-outlet-ids');
    $excludeCodes = array_map('strtoupper', listParam('exclude-codes'));
    if (!empty($excludeIds) || !empty($excludeCodes)) {
        $destOutlets = array_values(array_filter($destOutlets, function ($o) use ($excludeIds, $excludeCodes) {
            if (in_array($o['outlet_id'], $excludeIds, true)) return false;
            $code = strtoupper((string)($o['store_code'] ?? ''));
            if ($code !== '' && in_array($code, $excludeCodes, true)) return false;
            return true;
        }));
    }

    if (empty($destOutlets)) {
        throw new RuntimeException('No eligible destination outlets found.');
    }

    // Effective product cap
    $effectiveLimit = ($limitProducts > 0) ? min($limitProducts, $maxProductsCap) : $maxProductsCap;

    // Hub products
    $hubProducts = fetchHubProducts(
        $db,
        $hubId,
        $effectiveLimit,
        $filterMode,
        $supplierFlag,
        $filterBrandEnabled,
        $filterSupplierAuto,
        $excludeNameLike
    );

    if (empty($hubProducts)) {
        throw new RuntimeException('No hub products with stock found (check filters/exclusions).');
    }

    // IDs and stock matrix
    $destIds      = array_slice(array_column($destOutlets, 'outlet_id'), 0, $maxOutletsCap);
    $productIds   = array_slice(array_column($hubProducts, 'product_id'), 0, $maxProductsCap);
    $destMatrix   = fetchDestStocksMatrix($db, $destIds, $productIds); // [pid][oid] = qty
    $outletWeights= [];
    foreach ($destOutlets as $o) $outletWeights[$o['outlet_id']] = (float)($o['turnover_rate'] ?? 5.0);

    // Preload classification/meta + pack rules + freight (includes brand data, brand pack, DISPOSVAPE fallback)
    $preload      = preload_product_meta($db, $productIds, $seedUnclassifiedDefault);
    $meta         = $preload['meta'];
    $freightRules = $preload['freight_rules'];
    $queuedUnclassified = (int)($preload['queued'] ?? 0);
    $preDiag      = $preload['diag'] ?? null;

    $t0 = microtime(true);

    // Build effective turnover bands config (supports optional overrides via GET)
    $TURNOVER_BANDS = build_turnover_bands_config($turnoverBandsMode);
    set_turnover_bands_config($TURNOVER_BANDS);

    // Results
    $perOutletLines = [];   // outlet_id => [ lines... ]
    $totLines = 0; $totUnits = 0;
    $decisionIndex = [];

    // Hub qty + product name lookup
    $hubQty = [];
    $productNameById = [];
    foreach ($hubProducts as $hp) {
        $hubQty[$hp['product_id']] = (int)$hp['wh_qty'];
        $productNameById[(string)$hp['product_id']] = (string)($hp['product_name'] ?? '');
    }

    foreach ($productIds as $pid) {
        $wh = max(0, (int)($hubQty[$pid] ?? 0));
        if ($wh <= 0) continue;

        // Reserve & surplus
        $reserve = max($reserveMin, (int)ceil($wh * $reservePct));
        $surplus = $wh - $reserve;
        if ($surplus <= 0) continue;

        // Category-aware guard profile for this product
        $pMeta = $meta[$pid] ?? [];
        $guardDefaults = [
            'min_line_qty' => $minLineQty,
            'seed_min_qty' => $seedMinQty,
            'seed_max_open' => $seedMaxOpen,
            'high_stock_factor' => $highStockFactor,
            'singleton_killer_threshold' => $singletonKillerThreshold,
        ];
        $gp = compute_guard_profile($pMeta, $guardDefaults);
        $minLineQtyP = (int)$gp['min_line_qty'];
        $seedMinQtyP = (int)$gp['seed_min_qty'];
        $seedMaxOpenP = (int)$gp['seed_max_open'];
        $hsFactorP    = (float)$gp['high_stock_factor'];
        $singletonKillerThresholdP = (int)$gp['singleton_killer_threshold'];
        if (!empty($gp['_profile'])) {
            $k = 'profile_' . $gp['_profile'];
            $decisionIndex[$k] = ($decisionIndex[$k] ?? 0) + 1;
        }

        // Current dest stocks
        $destStocks = [];
        foreach ($destIds as $oid) {
            $destStocks[$oid] = max(0, (int)($destMatrix[$pid][$oid] ?? 0));
        }

        // ---------- Baseline needs ----------
        $baselineTarget = max($targetLevel, dynamic_target($surplus));
        $baselineNeeds = [];
        $seeded = [];
        foreach ($destIds as $oid) {
            $s = $destStocks[$oid];
            // High stock outright drop
            $highThresh = (int)floor($hsFactorP * $baselineTarget);
            if ($s >= $highThresh) { $baselineNeeds[$oid] = 0; $decisionIndex['drop_high_dest_factor'] = ($decisionIndex['drop_high_dest_factor'] ?? 0) + 1; continue; }
            // Global no-send threshold
            if ($s >= $noSendIfAtLeast) { $baselineNeeds[$oid] = 0; $decisionIndex['drop_due_to_no_send_threshold'] = ($decisionIndex['drop_due_to_no_send_threshold'] ?? 0) + 1; continue; }
            $need = 0;
            if ($s === 0) {
                $seedQty = (int)($meta[$pid]['default_seed_qty'] ?? $seedUnclassifiedDefault);
                // Turnover-aware scaling (per-outlet) of seed thresholds
                $seedMinAdj = $seedMinQtyP; $seedMaxAdj = $seedMaxOpenP; $turnBand = null;
                if ($turnoverSeedEnabled) {
                    $turn = (float)($outletWeights[$oid] ?? 5.0);
                    [$turnBand, $minMul, $maxMul] = turnover_seed_profile($turn);
                    if ($minMul !== 1.0) {
                        $prev = $seedMinAdj;
                        $seedMinAdj = max(0, (int)ceil($seedMinAdj * $minMul));
                        if ($seedMinAdj !== $prev) { $decisionIndex['turnover_seed_min_scaled'] = ($decisionIndex['turnover_seed_min_scaled'] ?? 0) + 1; }
                    }
                    if ($maxMul !== 1.0 && $seedMaxAdj > 0) {
                        $prev = $seedMaxAdj;
                        $seedMaxAdj = max(0, (int)floor($seedMaxAdj * $maxMul));
                        if ($seedMaxAdj !== $prev) { $decisionIndex['turnover_seed_max_scaled'] = ($decisionIndex['turnover_seed_max_scaled'] ?? 0) + 1; }
                    }
                    if ($turnBand) { $decisionIndex['turnover_band_'.$turnBand] = ($decisionIndex['turnover_band_'.$turnBand] ?? 0) + 1; }
                }
                // Enforce seed min and cap for openings (with turnover-aware adjustments)
                $need = max($seedMinAdj, $seedQty);
                if ($seedMaxAdj > 0) $need = min($need, $seedMaxAdj);
                $seeded[$oid] = true;
                if ($need !== $seedQty) {
                    $decisionIndex['seed_clamped'] = ($decisionIndex['seed_clamped'] ?? 0) + 1;
                }
            } elseif ($s < 5) {
                $need = max(0, 10 - $s);
            } else {
                $need = 5;
            }
            $baselineNeeds[$oid] = min($need, $maxPerStore);
        }

    // ---------- Greedy baseline ----------
        $alloc = array_fill_keys($destIds, 0);
        $remaining = $surplus;

        $order = [];
        foreach ($destIds as $oid) $order[$oid] = [$destStocks[$oid], -$outletWeights[$oid]];
        uasort($order, fn($a,$b) => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));

        foreach (array_keys($order) as $oid) {
            if ($remaining <= 0) break;
            $want = (int)($baselineNeeds[$oid] ?? 0);
            if ($want <= 0) continue;

            $give = min($want, $maxPerStore, $remaining);
            if ($give > 0 && empty($seeded[$oid]) && $give < $minLineQtyP) {
                $decisionIndex['dropped_below_min_line'] = ($decisionIndex['dropped_below_min_line'] ?? 0) + 1;
                $give = 0;
            }

            if ($give > 0) { $alloc[$oid] += $give; $remaining -= $give; $decisionIndex['baseline_allocated'] = ($decisionIndex['baseline_allocated'] ?? 0) + $give; }
        }

        // ---------- Deficiency-weighted fair-share ----------
        if ($remaining > 0 && $propShare > 0.0) {
            $pool   = (int)floor($remaining * $propShare);
            $pool   = min($pool, $remaining);
            $target = dynamic_target($surplus); // could also use fixed $targetLevel

            if ($pool > 0) {
                $scores = []; $sum = 0.0;
                foreach ($destIds as $oid) {
                    $s      = $destStocks[$oid] + $alloc[$oid];
                    $def    = max(0.0, $target - (float)$s);
                    $w      = (float)$outletWeights[$oid];
                    $capRoom= max(0, $maxPerStore - $alloc[$oid]);
                    $score  = ($capRoom > 0 && $def > 0) ? pow($def, $alphaDeficiency) * $w : 0.0;
                    $scores[$oid] = $score; $sum += $score;
                }

                if ($sum > 0.0) {
                    foreach ($destIds as $oid) {
                        if ($pool <= 0) break;

                        $capRoom = max(0, $maxPerStore - $alloc[$oid]);
                        if ($capRoom <= 0) continue;

                        $share = (int)floor(($scores[$oid] / $sum) * $pool);
                        $give  = max(0, min($share, $capRoom, $pool));

                        if ($give > 0 && $alloc[$oid] === 0 && empty($seeded[$oid]) && $give < $minLineQtyP) {
                            $give = min($minLineQtyP, $capRoom, $pool);
                            if ($give < $minLineQtyP) { $give = 0; }
                            else { $decisionIndex['bumped_opening_to_min_line'] = ($decisionIndex['bumped_opening_to_min_line'] ?? 0) + 1; }
                        }

                        if ($give > 0) { $alloc[$oid] += $give; $pool -= $give; $remaining -= $give; }
                    }
                }
            }
        }

    // ---------- Pack rules ----------
        $packRule = $meta[$pid]['pack'] ?? null;

        // Hard fallback: product name contains DISPOSVAPE (in case brand name didn’t hit)
        if (!$packRule) {
            $pname = $productNameById[(string)$pid] ?? '';
            if ($pname !== '' && stripos($pname, 'disposvape') !== false) {
                $packRule = [
                    'pack_size'      => 10,
                    'outer_multiple' => 10,
                    'rounding_mode'  => 'round',
                    'enforce_outer'  => true,
                    'source'         => 'fallback',
                    'enforce_reason' => 'fallback',
                ];
            }
        }

    // Track per-oid pack step details for debug tracing
    $qBeforePackByOid = [];
    $qPackedByOid     = [];
    $cartonBumpedByOid= [];
    foreach ($destIds as $oid) { $qBeforePackByOid[$oid] = null; $qPackedByOid[$oid] = null; $cartonBumpedByOid[$oid] = false; }

    if (!empty($packRule)) {
            // Telemetry: pack source usage
            if (!empty($packRule['source'])) {
                $decisionIndex['pack_source_'.($packRule['source'])] = ($decisionIndex['pack_source_'.($packRule['source'])] ?? 0) + 1;
            }
            if (!empty($packRule['enforce_reason']) && $packRule['enforce_reason'] === 'engine' && !empty($packRule['outer_multiple'])) {
                $decisionIndex['pack_enforce_category_outer'] = ($decisionIndex['pack_enforce_category_outer'] ?? 0) + 1;
            }
            foreach ($destIds as $oid) {
                $q0 = (int)$alloc[$oid];
                if ($q0 <= 0) continue;
                // record before-pack quantity for trace
                $qBeforePackByOid[$oid] = $q0;
                $qPacked = apply_pack_rules($q0, $packRule);
                // Optional carton enforcement
                $cartonSpec = $meta[$pid]['carton'] ?? null;
                if ($cartonSpec && !empty($cartonSpec['carton_mandatory']) && !empty($cartonSpec['units_per_carton'])) {
                    $before = $qPacked;
                    $upc = max(1, (int)$cartonSpec['units_per_carton']);
                    $qPacked = (int)ceil($qPacked / $upc) * $upc;
                    if ($qPacked !== $before) { 
                        $decisionIndex['carton_bumped'] = ($decisionIndex['carton_bumped'] ?? 0) + 1;
                        $cartonBumpedByOid[$oid] = true;
                    }
                }
                // record after-pack quantity for trace
                $qPackedByOid[$oid] = $qPacked;
                if ($qPacked > $q0) {
                    $capRoom = max(0, $maxPerStore - $q0);
                    $delta = min($qPacked - $q0, $remaining, $capRoom);
                    if ($delta > 0) { $alloc[$oid] += $delta; $remaining -= $delta; $decisionIndex['pack_rounded_up'] = ($decisionIndex['pack_rounded_up'] ?? 0) + 1; }
                } elseif ($qPacked < $q0) {
                    $delta = $q0 - $qPacked;
                    if ($delta > 0) { $alloc[$oid] = $qPacked; $remaining += $delta; $decisionIndex['pack_rounded_down'] = ($decisionIndex['pack_rounded_down'] ?? 0) + 1; }
                }
            }
        }

        // ---------- Enforce min-line after pack ----------
        foreach ($destIds as $oid) {
            $q = (int)$alloc[$oid];
            if ($q > 0 && $q < $minLineQtyP && empty($seeded[$oid])) {
                $capRoom = max(0, $maxPerStore - $q);
                $need = $minLineQtyP - $q;
                $bump = min($need, $remaining, $capRoom);
                if ($bump >= $need) { $alloc[$oid] += $bump; $remaining -= $bump; }
                else { $remaining += $q; $alloc[$oid] = 0; }
            }
        }

        // ---------- Optional: enforce category min transfer or seed defaults ----------
        if ($enforceCategoryMinTransfer || $enforceCategorySeedDefault) {
            foreach ($destIds as $oid) {
                $q0 = (int)$alloc[$oid]; if ($q0 <= 0) continue;
                $catCode = $meta[$pid]['category'] ?? null;
                // Pull category rule (canonical mapping stored into $meta['pack'] already; but min_transfer/seed may be tracked at category table)
                // Lightweight fetch from catPack via meta since we computed it earlier; safest is to re-derive from chosen pack if available
                $minTransfer = null; $seedDefault = null;
                // Use pack size or outer multiple as a hint only; real min/seed should come from category rules in DB (out of scope for now)
                // This block remains conservative, only bumps up to seedDefault if explicitly enabled and only for s==0 openings
                if ($enforceCategorySeedDefault && !empty($seeded[$oid])) {
                    // Already seeded with seedMinAdj above; nothing to do here
                } elseif ($enforceCategorySeedDefault && !empty($meta[$pid]['default_seed_qty'])) {
                    $seedDefault = (int)$meta[$pid]['default_seed_qty'];
                    if ($q0 < $seedDefault) {
                        $capRoom = max(0, $maxPerStore - $q0);
                        $bump = min($seedDefault - $q0, $remaining, $capRoom);
                        if ($bump > 0) { $alloc[$oid] += $bump; $remaining -= $bump; $decisionIndex['category_seed_bumped'] = ($decisionIndex['category_seed_bumped'] ?? 0) + 1; }
                    }
                }
                if ($enforceCategoryMinTransfer && $packRule) {
                    // If category-first and pack has a pack_size, we can treat it as a soft min
                    $hint = (int)($packRule['pack_size'] ?? 0);
                    if ($hint > 0 && $q0 > 0 && $q0 < $hint && empty($seeded[$oid])) {
                        $capRoom = max(0, $maxPerStore - $q0);
                        $bump = min($hint - $q0, $remaining, $capRoom);
                        if ($bump > 0) { $alloc[$oid] += $bump; $remaining -= $bump; $decisionIndex['category_min_transfer_bumped'] = ($decisionIndex['category_min_transfer_bumped'] ?? 0) + 1; }
                    }
                }
            }
        }

        // ---------- Secondary rounding (>=10) if no pack ----------
        if ($roundMultOver10 > 1 && empty($packRule)) {
            foreach ($destIds as $oid) {
                if ($alloc[$oid] >= 10) {
                    $capRoom = max(0, $maxPerStore - $alloc[$oid]);
                    $rounded = round_to_multiple($alloc[$oid], $roundMultOver10);

                    if ($rounded > $alloc[$oid]) {
                        $delta = min($rounded - $alloc[$oid], $remaining, $capRoom);
                        if ($delta > 0) { $alloc[$oid] += $delta; $remaining -= $delta; $decisionIndex['snap_up'] = ($decisionIndex['snap_up'] ?? 0) + 1; }
                    } elseif ($rounded < $alloc[$oid]) {
                        $delta = $alloc[$oid] - $rounded;
                        if ($delta > 0) { $alloc[$oid] = $rounded; $remaining += $delta; $decisionIndex['snap_down'] = ($decisionIndex['snap_down'] ?? 0) + 1; }
                    }
                }
            }
        }

        // ---------- Final smart quantization ----------
        $preservePacks = !empty($packRule) && ( !empty($packRule['outer_multiple']) || !empty($packRule['pack_size']) );
        smart_quantize_allocations(
            $alloc,
            $destStocks,
            $seeded,
            $maxPerStore,
            $minLineQtyP,
            $noSendIfAtLeast,      // final drop if dest already has ≥ threshold
            $snapMultiple,         // e.g. 10
            $snapDelta,            // e.g. within ±1 → snap to 10
            $remaining,
            $preservePacks,
            $decisionIndex,
            $singletonKillerThresholdP,
            $seedMinQtyP
        );

        // ---------- Surplus audit & trim (safety) ----------
        $sumAlloc = 0; foreach ($alloc as $x) $sumAlloc += (int)$x;
        $surplusStart = $surplus; // initial for safety
        if ($sumAlloc > $surplusStart) {
            $excess = $sumAlloc - $surplusStart;
            trim_excess_allocations($alloc, $seeded, $minLineQtyP, $seedMinQtyP, $excess, $decisionIndex);
        }

        // ---------- Post-guard: prevent overfilling high-stock destinations ----------
        $postHighThresh = (int)floor($hsFactorP * $baselineTarget);
        foreach ($destIds as $oid) {
            $q = (int)($alloc[$oid] ?? 0);
            if ($q <= 0) continue;
            $destAfter = (int)($destStocks[$oid] ?? 0) + $q;
            if ($destAfter >= $postHighThresh) {
                $decisionIndex['post_guard_high_dest_drop'] = ($decisionIndex['post_guard_high_dest_drop'] ?? 0) + 1;
                $alloc[$oid] = 0; // return to remaining pool (not reallocated at this late stage)
            }
        }

        // ---------- Record lines ----------
        $ppu = (int)($meta[$pid]['effective_weight'] ?? 0); // grams
        foreach ($destIds as $oid) {
            $q = (int)$alloc[$oid];
            if ($q <= 0) continue;
            // Telemetry per line
            if (!empty($packRule['source'])) {
                $decisionIndex['lines_pack_source_'.($packRule['source'])] = ($decisionIndex['lines_pack_source_'.($packRule['source'])] ?? 0) + 1;
            }
            if (!empty($packRule['enforce_reason']) && $packRule['enforce_reason'] === 'engine' && !empty($packRule['outer_multiple'])) {
                $decisionIndex['lines_pack_engine_enforced_outer'] = ($decisionIndex['lines_pack_engine_enforced_outer'] ?? 0) + 1;
            }

            $productName = $productNameById[(string)$pid] ?? '';

            $line = [
                'product_id'        => $pid,
                'product_name'      => $productName,
                'name'              => $productName,
                'qty_ham_east'      => $wh,
                'qty_destination'   => $destStocks[$oid],
                'qty_sent'          => $q,
                'weight_per_unit_g' => $ppu,
                'total_weight_g'    => $ppu * $q,
            ];
            if ($debugDecisionIndex) {
                // enrich line with classification + guard profile when debug is enabled
                $line['_dbg'] = 1;
                $line['type'] = $meta[$pid]['type'] ?? null;
                $line['category'] = $meta[$pid]['category'] ?? null;
                $line['brand_name'] = $meta[$pid]['brand_name'] ?? '';
                $line['guard_profile'] = $gp['_profile'] ?? 'default';
                if ($turnoverSeedEnabled) {
                    $turn = (float)($outletWeights[$oid] ?? 5.0);
                    [$band, $minMul, $maxMul] = turnover_seed_profile($turn);
                    $line['turnover_band'] = $band;
                    $line['seed_min_qty_adj'] = (int)max(0, (int)ceil($seedMinQtyP * $minMul));
                    $line['seed_max_open_adj'] = (int)max(0, (int)floor($seedMaxOpenP * $maxMul));
                }
                if ($debugDecisionTrace) {
                    $line['_trace'] = [
                        'had_pack_rule'   => !empty($packRule),
                        'pack_source'     => $packRule['source'] ?? null,
                        'qty_before_pack' => $qBeforePackByOid[$oid] ?? null,
                        'qty_after_pack'  => $qPackedByOid[$oid] ?? null,
                        'final_qty'       => $q,
                        'carton_bumped'   => (bool)($cartonBumpedByOid[$oid] ?? false),
                        'supplier_active' => !empty($meta[$pid]['supplier_id']),
                        'supplier_id'     => $meta[$pid]['supplier_id'] ?? null,
                        'brand_id'        => $meta[$pid]['brand_id'] ?? null,
                        'category'        => $meta[$pid]['category'] ?? null,
                    ];
                }
            }
            $perOutletLines[$oid][] = $line;

            $totLines++;
            $totUnits += $q;
        }
    }

    $elapsedMs = (int)round((microtime(true) - $t0) * 1000);

    // Ensure pack telemetry keys exist in decision index (so artifacts always surface them)
    if ($debugDecisionIndex) {
        ensure_pack_telemetry_keys($decisionIndex);
        if (is_array($preDiag)) {
            // Merge diagnostics under a namespaced key for clarity
            $decisionIndex['preload_diag'] = $preDiag;
        }
    }

    // Persist if live
    $transfers = [];
    if ($live) {
        $transfers = persistTransfers($db, $hubId, $destOutlets, $perOutletLines, $runId, $reservePct, $reserveMin);
    }

    // Build JSON response
    $outById = [];
    foreach ($destOutlets as $o) $outById[$o['outlet_id']] = $o;

    $perOutlet = [];
    foreach ($perOutletLines as $oid => $lines) {
        $sumUnits = 0; $sumWeight = 0;
        foreach ($lines as $ln) { $sumUnits += $ln['qty_sent']; $sumWeight += $ln['total_weight_g']; }
        $freight = null;
        if ($estimateFreight && $sumWeight > 0) {
            $freight = estimate_freight_cost($db, $carrierId, (int)$sumWeight);
        }
        $perOutlet[] = [
            'outlet_id'      => $oid,
            'store_code'     => $outById[$oid]['store_code'] ?? '',
            'name'           => $outById[$oid]['name'] ?? $oid,
            'product_count'  => count($lines),
            'units'          => $sumUnits,
            'total_weight_g' => $sumWeight,
            'transfer_id'    => $transfers[$oid] ?? null,
            'freight_estimate'=> $freight,
            'lines'          => $lines,
        ];
    }

    $resp = [
        'ok'   => true,
        'meta' => [
            'run_id'         => $runId,
            'hub_id'         => $hubId,
            'live'           => (bool)$live,
            'params'         => [
                'limit_products' => $limitProducts,
                'effective_limit_products' => $effectiveLimit,
                'reserve_percent'=> $reservePct,
                'reserve_min'    => $reserveMin,
                'max_per_store'  => $maxPerStore,
                'min_line_qty'   => $minLineQty,
                'prop_share'     => $propShare,
                'target_level'   => $targetLevel,
                'no_send_if_atleast' => $noSendIfAtLeast,
                'alpha_deficiency'   => $alphaDeficiency,
                'round_multiple_over_10' => $roundMultOver10,
                'cover_days' => $coverDays,
                'seed_unclassified_default' => $seedUnclassifiedDefault,
                'max_products_cap' => $maxProductsCap,
                'max_outlets_cap'  => $maxOutletsCap,
                'exclude_outlet_ids' => $excludeIds,
                'exclude_codes'      => $excludeCodes,
                'exclude_name_like'  => $excludeNameLike,
                'brand_supplier_mode' => $filterMode,
                'supplier_flag'       => $supplierFlag,
                'legacy_brand_enabled' => (bool)$filterBrandEnabled,
                'legacy_supplier_auto' => (bool)$filterSupplierAuto,
                'quantize' => [
                    'drop_if_dest_ge' => $noSendIfAtLeast,
                    'singleton_killer'=> true,
                    'snap_multiple'   => $snapMultiple,
                    'snap_delta'      => $snapDelta,
                ],
                'guards' => [
                    'high_stock_factor' => $highStockFactor,
                    'seed_max_open' => $seedMaxOpen,
                    'seed_min_qty'  => $seedMinQty,
                    'singleton_killer_threshold' => $singletonKillerThreshold,
                ],
                'turnover_seed' => [
                    'enabled' => (bool)$turnoverSeedEnabled,
                    'mode' => $turnoverBandsMode,
                    // Effective bands in use for this run (after applying overrides and sanitization)
                    'bands' => $TURNOVER_BANDS,
                ],
                'classification' => [
                    'source' => ($GLOBALS['classificationSource'] ?? 'unified'),
                    'pack_priority' => ($GLOBALS['packPriorityMode'] ?? 'product-first'),
                    'use_overrides' => (bool)($GLOBALS['useOverrides'] ?? 1),
                    'enforce_category_pack' => (bool)($GLOBALS['enforceCategoryPack'] ?? 0),
                    'enforce_min_transfer' => (bool)$enforceCategoryMinTransfer,
                    'enforce_category_seed_default' => (bool)$enforceCategorySeedDefault,
                ]
            ],
            'outlets'        => count($destOutlets),
            'hub_products'   => count($hubProducts),
            'lines'          => $totLines,
            'units'          => $totUnits,
            'elapsed_ms'     => $elapsedMs,
            'queued_unclassified' => $queuedUnclassified,
            'decision_index' => $debugDecisionIndex ? $decisionIndex : null,
        ],
        'per_outlet' => $perOutlet,
    ];

    echo json_encode($resp, JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
    exit;
}

/* ========================= helpers ========================= */

function db(string $host, string $user, string $pass, string $name, int $port): mysqli {
    $m = @new mysqli($host, $user, $pass, $name, $port);
    if ($m->connect_errno) throw new RuntimeException('DB connect failed: '.$m->connect_error);
    $m->set_charset('utf8mb4');
    $m->query("SET time_zone = '+12:00'");
    return $m;
}

function req(string $key): string {
    $v = $_GET[$key] ?? '';
    if (trim($v) === '') throw new InvalidArgumentException("Missing required parameter '$key'");
    return (string)$v;
}

function clampFloat(float $v, float $lo, float $hi): float { return max($lo, min($hi, $v)); }

function dynamic_target(int $surplus): int {
    if ($surplus < 30)  return 8;
    if ($surplus < 100) return 12;
    return 16;
}

function round_to_multiple(int $q, int $m): int {
    if ($m <= 1) return $q;
    return (int)round($q / $m) * $m;
}

/** Parse comma/space separated GET list */
function listParam(string $key): array {
    $v = $_GET[$key] ?? '';
    if ($v === '' || $v === null) return [];
    $parts = preg_split('/[,\s]+/', (string)$v, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_map('trim', $parts));
}

/**
 * Attempt to set per-session safety guards in a portable way.
 */
function setSessionGuards(mysqli $db): void {
    $setIfExists = function(string $varName, string $value) use ($db): void {
        try {
            $stmt = $db->prepare("SHOW VARIABLES LIKE ?");
            $stmt->bind_param('s', $varName);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = ($res && $res->num_rows > 0);
            $stmt->close();
            if ($exists) {
                $db->query("SET SESSION {$varName} = {$value}");
            }
        } catch (\Throwable $e) {}
    };

    $setIfExists('innodb_lock_wait_timeout', '3');
    $setIfExists('max_execution_time', '1500');   // MySQL ms
    $setIfExists('max_statement_time', '1.5');    // MariaDB s
    $setIfExists('transaction_isolation', "'READ-COMMITTED'");
    $setIfExists('tx_isolation', "'READ-COMMITTED'");
}

// -------- Extended helpers for preload + packing --------
/** Build an IN clause and return [placeholders, types, params] */
function makeInClause(array $vals): array {
    $vals = array_values(array_filter($vals, fn($v) => $v !== null && $v !== ''));
    $count = count($vals);
    if ($count === 0) return ['(NULL)', '', []];
    $ph = '(' . implode(',', array_fill(0, $count, '?')) . ')';
    $types = str_repeat('s', $count);
    return [$ph, $types, $vals];
}

/** Bind params dynamically to a mysqli_stmt (by reference) */
function bindParams(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '' || empty($params)) return;
    $bind = [$types];
    foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

/** Chunk helper */
function chunked(array $items, int $size): array { return array_chunk($items, max(1, $size)); }

/** Resolve effective weight in grams with safe fallback */
function resolve_effective_weight(?int $prodAvg, ?int $catAvg, ?int $typeAvg): int {
    foreach ([$prodAvg, $catAvg, $typeAvg] as $g) if ($g !== null && $g > 0) return (int)$g;
    return 50;
}

/** Round qty to multiple with chosen mode */
function round_to_multiple_mode(int $qty, int $m, string $mode): int {
    if ($m <= 0) return $qty;
    switch ($mode) {
        case 'ceil':  return (int)ceil($qty / $m) * $m;
        case 'round': return (int)round($qty / $m) * $m;
        case 'floor':
        default:      return (int)floor($qty / $m) * $m;
    }
}

/** Lightweight schema introspection helpers */
function table_exists(mysqli $db, string $table): bool {
    $tbl = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '" . $tbl . "'");
    return ($res && $res->num_rows > 0);
}

function table_has_column(mysqli $db, string $table, string $column): bool {
    $tbl = $db->real_escape_string($table);
    $col = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `" . $tbl . "` LIKE '" . $col . "'");
    return ($res && $res->num_rows > 0);
}

/** Lightweight schema introspection helpers (for canonical vs legacy schemas) */
// NOTE: duplicate definitions removed to avoid redeclare errors; see single versions above.

/** Estimate freight cost for a given required weight (grams), optionally dims */
function estimate_freight_cost(mysqli $db, int $carrierId, int $reqWeightG, ?int $Lmm = null, ?int $Wmm = null, ?int $Hmm = null): array {
    $reqWeightG = max(0, $reqWeightG);
    $useAdvanced = table_exists($db, 'containers') && table_exists($db, 'carriers');
    if ($useAdvanced) {
        // Advanced path: containers + (legacy) freight_rules by container code if container_id not present
        $hasFrContainerId = table_has_column($db, 'freight_rules', 'container_id');
        if ($hasFrContainerId) {
            $sql = "SELECT c.container_id, c.code, c.kind, c.length_mm, c.width_mm, c.height_mm,
                           fr.max_weight_grams AS rule_cap_g, fr.cost
                    FROM containers c
                    JOIN freight_rules fr ON fr.container_id = c.container_id
                    WHERE c.carrier_id = ? AND (fr.max_weight_grams IS NULL OR fr.max_weight_grams >= ?)
                    ORDER BY fr.cost ASC, COALESCE(fr.max_weight_grams, 99999999) ASC, c.container_id ASC
                    LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ii', $carrierId, $reqWeightG);
        } else {
            $sql = "SELECT c.container_id, c.code, c.kind, c.length_mm, c.width_mm, c.height_mm,
                           fr.max_weight_grams AS rule_cap_g, fr.cost
                    FROM containers c
                    JOIN freight_rules fr ON fr.container = c.code
                    WHERE c.carrier_id = ? AND (fr.max_weight_grams IS NULL OR fr.max_weight_grams >= ?)
                    ORDER BY fr.cost ASC, COALESCE(fr.max_weight_grams, 99999999) ASC, c.container_id ASC
                    LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ii', $carrierId, $reqWeightG);
        }
        $stmt->execute(); $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            return [
                'picked_from'      => 'advanced',
                'carrier_id'       => $carrierId,
                'container_code'   => (string)$row['code'],
                'container_kind'   => $row['kind'] ?? null,
                'rule_cap_g'       => isset($row['rule_cap_g']) ? (int)$row['rule_cap_g'] : null,
                'required_weight_g'=> $reqWeightG,
                'cost'             => isset($row['cost']) ? (float)$row['cost'] : null,
            ];
        }
    }
    // Legacy path: simple freight_rules table (be flexible if 'container' column is missing)
    $hasLegacyContainer = table_has_column($db, 'freight_rules', 'container');
    if ($hasLegacyContainer) {
    $sql = "SELECT container, max_weight_grams AS rule_cap_g, cost
        FROM freight_rules
        WHERE (max_weight_grams IS NULL OR max_weight_grams >= ?)
        ORDER BY cost ASC, COALESCE(max_weight_grams, 99999999) ASC
        LIMIT 1";
    } else {
    $sql = "SELECT NULL AS container, max_weight_grams AS rule_cap_g, cost
        FROM freight_rules
        WHERE (max_weight_grams IS NULL OR max_weight_grams >= ?)
        ORDER BY cost ASC, COALESCE(max_weight_grams, 99999999) ASC
        LIMIT 1";
    }
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $reqWeightG);
    $stmt->execute(); $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row) {
        return [
            'picked_from'      => 'legacy',
            'carrier_id'       => $carrierId,
            'container_code'   => (string)$row['container'],
            'container_kind'   => null,
            'rule_cap_g'       => isset($row['rule_cap_g']) ? (int)$row['rule_cap_g'] : null,
            'required_weight_g'=> $reqWeightG,
            'cost'             => isset($row['cost']) ? (float)$row['cost'] : null,
        ];
    }
    return [
        'picked_from' => 'none',
        'carrier_id'  => $carrierId,
        'cost'        => null,
        'required_weight_g' => $reqWeightG,
    ];
}

/** Apply pack rules safely */
function apply_pack_rules(int $qty, ?array $rule): int {
    if ($qty <= 0 || !$rule) return max(0, $qty);
    $q = $qty;
    if (!empty($rule['pack_size'])) {
        $q = round_to_multiple_mode($q, (int)$rule['pack_size'], $rule['rounding_mode'] ?? 'floor');
    }
    if (!empty($rule['outer_multiple'])) {
        $outer = (int)$rule['outer_multiple'];
        if (!empty($rule['enforce_outer'])) $q = round_to_multiple_mode($q, $outer, 'floor');
        else $q = round_to_multiple_mode($q, $outer, 'round');
    }
    return max(0, $q);
}

/** Prefer: product > brand > category; returns unified rule or null */
function pick_pack_rule3(?array $prodRule, ?array $brandRule, ?array $catRule): ?array {
    foreach ([$prodRule, $brandRule, $catRule] as $rule) {
        if ($rule) {
            return [
                'pack_size'      => isset($rule['pack_size']) ? (int)$rule['pack_size'] : null,
                'outer_multiple' => isset($rule['outer_multiple']) ? (int)$rule['outer_multiple'] : null,
                'rounding_mode'  => $rule['rounding_mode'] ?? 'floor',
                'enforce_outer'  => !empty($rule['enforce_outer']),
            ];
        }
    }
    return null;
}

/** Preload classification/meta + pack rules (prod/brand/cat) + freight rules */
function preload_product_meta(mysqli $db, array $productIds, int $seedUnclassifiedDefault): array {
    $productIds = array_values(array_unique(array_filter($productIds)));
    $meta = [];
    $prodAvg = [];
    $typeByPid = [];
    $catByPid = [];
    $brandByPid = [];
    $brandNameByPid = [];
    $supplierByPid = [];
    $supplierNameByPid = [];
    $queued = 0;
    // Lightweight diagnostics to surface data vs rules vs join coverage
    $diag = [
        'products_total' => 0,
        'products_with_type' => 0,
        'products_with_cat' => 0,
        'products_with_brand' => 0,
        'products_with_active_supplier' => 0,
        'product_pack_rules_loaded' => 0,
        'brand_pack_rules_loaded' => 0,
        'supplier_pack_rules_loaded' => 0,
        'category_pack_rules_loaded' => 0,
        'cat_rules_key' => 'none', // id|code|both|none
        'had_chosen_pack_by_source' => [
            'product' => 0,
            'brand' => 0,
            'supplier' => 0,
            'category' => 0,
            'fallback' => 0,
            'none' => 0,
        ],
    ];

    // Freight rules (schema-flexible)
    $freightRules = [];
    $hasFRContainer = table_has_column($db, 'freight_rules', 'container');
    $hasFRMaxUnits  = table_has_column($db, 'freight_rules', 'max_units');
    $selFR = [];
    $selFR[] = $hasFRContainer ? 'container' : "NULL AS container";
    $selFR[] = 'max_weight_grams';
    $selFR[] = $hasFRMaxUnits ? 'max_units' : "NULL AS max_units";
    $selFR[] = 'cost';
    $sqlFR = "SELECT " . implode(', ', $selFR) . " FROM freight_rules ORDER BY cost ASC";
    $resFR = $db->query($sqlFR);
    if ($resFR) $freightRules = $resFR->fetch_all(MYSQLI_ASSOC);

    if (empty($productIds)) {
        return ['meta'=>$meta, 'freight_rules'=>$freightRules, 'queued'=>0, 'diag'=>$diag];
    }

    // Classification + product avg + brand
    foreach (chunked($productIds, 400) as $ids) {
        [$ph, $types, $params] = makeInClause($ids);
        // Select classification source based on flag: unified | canonical | auto
        $classTable = 'product_classification_unified';
        if (($GLOBALS['classificationSource'] ?? 'unified') === 'canonical') {
            $classTable = 'product_classification_canonical';
        } elseif (($GLOBALS['classificationSource'] ?? 'unified') === 'auto') {
            // Probe canonical existence quickly; fallback to unified
            $probe = $db->query("SHOW TABLES LIKE 'product_classification_canonical'");
            if ($probe && $probe->num_rows > 0) $classTable = 'product_classification_canonical';
        }
    $hasCatId = table_has_column($db, $classTable, 'category_id');
    $catSel = $hasCatId ? 'u.category_id AS category_code' : 'u.category_code';
    $sql = "SELECT p.id, p.avg_weight_grams, p.brand_id, vb.name AS brand_name,
               p.supplier_id, vs.name AS supplier_name, vs.deleted_at AS supplier_deleted_at,
               u.product_type_code, $catSel
        FROM vend_products p
        LEFT JOIN vend_brands vb ON vb.id = p.brand_id
        LEFT JOIN vend_suppliers vs ON vs.id = p.supplier_id
        LEFT JOIN $classTable u ON u.product_id = p.id
        WHERE p.id IN $ph";
        $stmt = $db->prepare($sql);
        bindParams($stmt, $types, $params);
        $stmt->execute(); $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $pid = (string)$row['id'];
            $prodAvg[$pid] = isset($row['avg_weight_grams']) ? (int)$row['avg_weight_grams'] : null;
            $typeByPid[$pid] = $row['product_type_code'] ?? null;
            $catByPid[$pid]  = $row['category_code'] ?? null;
            $brandByPid[$pid] = $row['brand_id'] ?? null;
            $brandNameByPid[$pid] = $row['brand_name'] ?? '';
            // Respect vend_suppliers.deleted_at semantics: active when '' or NULL; otherwise treat as deleted
            $supDel = $row['supplier_deleted_at'] ?? null;
            // Treat null, empty string, and '0000-00-00 00:00:00' as active
            $supActive = ($supDel === '' || $supDel === null || $supDel === '0000-00-00 00:00:00');
            $supplierByPid[$pid] = $supActive ? ($row['supplier_id'] ?? null) : null;
            $supplierNameByPid[$pid] = $supActive ? ($row['supplier_name'] ?? '') : '';
            $meta[$pid] = [
                'type' => $typeByPid[$pid],
                'category' => $catByPid[$pid],
                'brand_id' => $brandByPid[$pid],
                'brand_name' => $brandNameByPid[$pid],
                'supplier_id' => $supplierByPid[$pid],
                'supplier_name' => $supplierNameByPid[$pid],
                'effective_weight' => null,
                'default_seed_qty' => null,
                'pack' => null,
            ];
        }
        $stmt->close();
    }

    $cats  = array_values(array_unique(array_filter(array_map(fn($p) => $catByPid[(string)$p] ?? null, $productIds))));
    $types = array_values(array_unique(array_filter(array_map(fn($p) => $typeByPid[(string)$p] ?? null, $productIds))));
    $brandIds = array_values(array_unique(array_filter(array_map(fn($p) => $brandByPid[(string)$p] ?? null, $productIds))));
    $supplierIds = array_values(array_unique(array_filter(array_map(fn($p) => $supplierByPid[(string)$p] ?? null, $productIds))));
    // Fill early diags
    $diag['products_total'] = count($productIds);
    $diag['products_with_type'] = count($types);
    $diag['products_with_cat'] = count($cats);
    $diag['products_with_brand'] = count($brandIds);
    $diag['products_with_active_supplier'] = count($supplierIds);

    // category_weights (prefer canonical ID; also accept legacy code)
    $catW = [];
    if (!empty($cats)) {
        foreach (chunked($cats, 400) as $ids) {
            [$ph, $t, $p] = makeInClause($ids);
            if (table_has_column($db, 'category_weights', 'category_id')) {
                $sql = "SELECT category_id AS cat_key, avg_weight_grams FROM category_weights WHERE category_id IN $ph";
                $stmt = $db->prepare($sql);
                bindParams($stmt, $t, $p);
                $stmt->execute(); $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) $catW[(string)$row['cat_key']] = isset($row['avg_weight_grams']) ? (int)$row['avg_weight_grams'] : null;
                $stmt->close();
            }
            if (table_has_column($db, 'category_weights', 'category_code')) {
                $sql = "SELECT category_code AS cat_key, avg_weight_grams FROM category_weights WHERE category_code IN $ph";
                $stmt = $db->prepare($sql);
                bindParams($stmt, $t, $p);
                $stmt->execute(); $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) if (!array_key_exists((string)$row['cat_key'], $catW)) $catW[(string)$row['cat_key']] = isset($row['avg_weight_grams']) ? (int)$row['avg_weight_grams'] : null;
                $stmt->close();
            }
        }
    }

    // product_types
    $typeDefaults = [];
    if (!empty($types)) {
        foreach (chunked($types, 400) as $ids) {
            [$ph, $t, $p] = makeInClause($ids);
            $sql = "SELECT code, default_seed_qty, avg_weight_grams FROM product_types WHERE code IN $ph";
            $stmt = $db->prepare($sql);
            bindParams($stmt, $t, $p);
            $stmt->execute(); $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $typeDefaults[$row['code']] = [
                    'default_seed_qty' => isset($row['default_seed_qty']) ? (int)$row['default_seed_qty'] : 0,
                    'avg_weight_grams' => isset($row['avg_weight_grams']) ? (int)$row['avg_weight_grams'] : null,
                ];
            }
            $stmt->close();
        }
    }

    // Product-level pack rules
    $prodPack = [];
    foreach (chunked($productIds, 400) as $ids) {
        [$ph, $t, $p] = makeInClause($ids);
        $sql = "SELECT scope_id AS product_id, pack_size, outer_multiple, enforce_outer, rounding_mode
                FROM pack_rules WHERE scope='product' AND scope_id IN $ph";
        $stmt = $db->prepare($sql);
        bindParams($stmt, $t, $p);
        $stmt->execute(); $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $prodPack[(string)$row['product_id']] = [
                'pack_size' => $row['pack_size'] !== null ? (int)$row['pack_size'] : null,
                'outer_multiple' => $row['outer_multiple'] !== null ? (int)$row['outer_multiple'] : null,
                'enforce_outer' => !empty($row['enforce_outer']),
                'rounding_mode' => $row['rounding_mode'] ?? 'floor',
            ];
        }
        $stmt->close();
    }

    // Product-level pack fallback via v_effective_pack_rules (if present)
    if (table_exists($db, 'v_effective_pack_rules')) {
        foreach (chunked($productIds, 400) as $ids) {
            [$ph, $t, $p] = makeInClause($ids);
            $sql = "SELECT product_id, pack_size, transfer_enabled FROM v_effective_pack_rules WHERE product_id IN $ph";
            $stmt = $db->prepare($sql);
            bindParams($stmt, $t, $p);
            $stmt->execute(); $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $pidKey = (string)$row['product_id'];
                if (!isset($prodPack[$pidKey]) && (int)($row['transfer_enabled'] ?? 1) === 1) {
                    $ps = isset($row['pack_size']) ? (int)$row['pack_size'] : null;
                    if ($ps !== null && $ps > 1) {
                        $prodPack[$pidKey] = [
                            'pack_size'      => $ps,
                            'outer_multiple' => null,
                            'enforce_outer'  => false,
                            'rounding_mode'  => 'floor',
                        ];
                    }
                }
            }
            $stmt->close();
        }
    }

    // Brand-level pack rules
    $brandPack = [];
    if (!empty($brandIds)) {
        foreach (chunked($brandIds, 400) as $ids) {
            [$ph, $t, $p] = makeInClause($ids);
            $sql = "SELECT scope_id AS brand_id, pack_size, outer_multiple, enforce_outer, rounding_mode
                    FROM pack_rules WHERE scope='brand' AND scope_id IN $ph";
            $stmt = $db->prepare($sql);
            bindParams($stmt, $t, $p);
            $stmt->execute(); $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $brandPack[(string)$row['brand_id']] = [
                    'pack_size'      => $row['pack_size'] !== null ? (int)$row['pack_size'] : null,
                    'outer_multiple' => $row['outer_multiple'] !== null ? (int)$row['outer_multiple'] : null,
                    'enforce_outer'  => !empty($row['enforce_outer']),
                    'rounding_mode'  => $row['rounding_mode'] ?? 'floor',
                ];
            }
            $stmt->close();
        }
    }

    // Supplier-level pack rules (optional)
    $supplierPack = [];
    if (!empty($supplierIds)) {
        foreach (chunked($supplierIds, 400) as $ids) {
            [$ph, $t, $p] = makeInClause($ids);
            $sql = "SELECT scope_id AS supplier_id, pack_size, outer_multiple, enforce_outer, rounding_mode
                    FROM pack_rules WHERE scope='supplier' AND scope_id IN $ph";
            $stmt = $db->prepare($sql);
            bindParams($stmt, $t, $p);
            $stmt->execute(); $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $supplierPack[(string)$row['supplier_id']] = [
                    'pack_size'      => $row['pack_size'] !== null ? (int)$row['pack_size'] : null,
                    'outer_multiple' => $row['outer_multiple'] !== null ? (int)$row['outer_multiple'] : null,
                    'enforce_outer'  => !empty($row['enforce_outer']),
                    'rounding_mode'  => $row['rounding_mode'] ?? 'floor',
                ];
            }
            $stmt->close();
        }
    }

    // Category-level pack rules (supports canonical schema and legacy code)
    $catPack = [];
    if (!empty($cats)) {
        foreach (chunked($cats, 400) as $ids) {
            [$ph, $t, $p] = makeInClause($ids);
            $hasCatIdCol = table_has_column($db, 'category_pack_rules', 'category_id');
            $hasCatCodeCol = table_has_column($db, 'category_pack_rules', 'category_code');
            if ($hasCatIdCol && $hasCatCodeCol) { $diag['cat_rules_key'] = 'both'; }
            elseif ($hasCatIdCol) { $diag['cat_rules_key'] = 'id'; }
            elseif ($hasCatCodeCol) { $diag['cat_rules_key'] = 'code'; }
            if ($hasCatIdCol) {
                // Determine available pack columns dynamically
                $hasUPS = table_has_column($db, 'category_pack_rules', 'unit_pack_size');
                $hasCM  = table_has_column($db, 'category_pack_rules', 'carton_multiple');
                $hasDPS = table_has_column($db, 'category_pack_rules', 'default_pack_size');
                $hasDOM = table_has_column($db, 'category_pack_rules', 'default_outer_multiple');
                $hasRM  = table_has_column($db, 'category_pack_rules', 'rounding_mode');
                // Always alias fallbacks to expected keys to avoid undefined index warnings
                $selPS  = $hasUPS ? 'unit_pack_size' : ($hasDPS ? 'default_pack_size AS unit_pack_size' : 'NULL AS unit_pack_size');
                $selOM  = $hasCM  ? 'carton_multiple' : ($hasDOM ? 'default_outer_multiple AS carton_multiple' : 'NULL AS carton_multiple');
                $selRM  = $hasRM  ? 'rounding_mode' : "'floor' AS rounding_mode";
                $sql = "SELECT category_id AS cat_key, $selPS, $selOM, $selRM
                        FROM category_pack_rules WHERE category_id IN $ph";
                $stmt = $db->prepare($sql);
                bindParams($stmt, $t, $p);
                $stmt->execute(); $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $catPack[(string)$row['cat_key']] = [
                        'pack_size'      => $row['unit_pack_size'] !== null ? (int)$row['unit_pack_size'] : null,
                        'outer_multiple' => $row['carton_multiple'] !== null ? (int)$row['carton_multiple'] : null,
                        'enforce_outer'  => false,
                        'rounding_mode'  => $row['rounding_mode'] ?? 'floor',
                    ];
                }
                $stmt->close();
            }
            if ($hasCatCodeCol) {
                // Determine available pack columns dynamically
                $hasUPS = table_has_column($db, 'category_pack_rules', 'unit_pack_size');
                $hasCM  = table_has_column($db, 'category_pack_rules', 'carton_multiple');
                $hasDPS = table_has_column($db, 'category_pack_rules', 'default_pack_size');
                $hasDOM = table_has_column($db, 'category_pack_rules', 'default_outer_multiple');
                $hasRM  = table_has_column($db, 'category_pack_rules', 'rounding_mode');
                $selPS  = $hasUPS ? 'unit_pack_size AS pack_size' : ($hasDPS ? 'default_pack_size AS pack_size' : 'NULL AS pack_size');
                $selOM  = $hasCM  ? 'carton_multiple AS outer_multiple' : ($hasDOM ? 'default_outer_multiple AS outer_multiple' : 'NULL AS outer_multiple');
                $selRM  = $hasRM  ? 'rounding_mode' : "'floor' AS rounding_mode";
                $sql = "SELECT category_code AS cat_key, $selPS, $selOM, $selRM
                        FROM category_pack_rules WHERE category_code IN $ph";
                $stmt = $db->prepare($sql);
                bindParams($stmt, $t, $p);
                $stmt->execute(); $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $k = (string)$row['cat_key'];
                    if (!isset($catPack[$k])) {
                        $catPack[$k] = [
                            'pack_size'      => $row['pack_size'] !== null ? (int)$row['pack_size'] : null,
                            'outer_multiple' => $row['outer_multiple'] !== null ? (int)$row['outer_multiple'] : null,
                            'enforce_outer'  => false,
                            'rounding_mode'  => $row['rounding_mode'] ?? 'floor',
                        ];
                    }
                }
                $stmt->close();
            }

            // Also accept category-scoped rules from pack_rules (existing data has many rows)
            $sql = "SELECT scope_id AS cat_key, pack_size, outer_multiple, enforce_outer, rounding_mode
                    FROM pack_rules WHERE scope='category' AND scope_id IN $ph";
            $stmt = $db->prepare($sql);
            bindParams($stmt, $t, $p);
            $stmt->execute(); $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $k = (string)$row['cat_key'];
                if (!isset($catPack[$k])) {
                    $catPack[$k] = [
                        'pack_size'      => $row['pack_size'] !== null ? (int)$row['pack_size'] : null,
                        'outer_multiple' => $row['outer_multiple'] !== null ? (int)$row['outer_multiple'] : null,
                        'enforce_outer'  => !empty($row['enforce_outer']),
                        'rounding_mode'  => $row['rounding_mode'] ?? 'floor',
                    ];
                }
            }
            $stmt->close();
        }
    }
    // Rule load counts
    $diag['product_pack_rules_loaded'] = count($prodPack);
    $diag['brand_pack_rules_loaded'] = count($brandPack);
    $diag['supplier_pack_rules_loaded'] = count($supplierPack);
    $diag['category_pack_rules_loaded'] = count($catPack);

    // Optional carton specs hydration (if table exists)
    $cartonByProd = [];
    $cartonByCat  = [];
    if (table_exists($db, 'carton_specs')) {
        if (!empty($productIds)) {
            foreach (chunked($productIds, 400) as $ids) {
                [$ph, $t, $p] = makeInClause($ids);
                $sql = "SELECT scope_id AS product_id, units_per_carton, is_mandatory FROM carton_specs WHERE scope='product' AND scope_id IN $ph";
                $stmt = $db->prepare($sql);
                bindParams($stmt, $t, $p);
                $stmt->execute(); $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $cartonByProd[(string)$row['product_id']] = [
                        'units_per_carton' => (int)$row['units_per_carton'],
                        'carton_mandatory' => ((int)$row['is_mandatory'] === 1),
                    ];
                }
                $stmt->close();
            }
        }
        if (!empty($cats)) {
            foreach (chunked($cats, 400) as $ids) {
                [$ph, $t, $p] = makeInClause($ids);
                $sql = "SELECT scope_id AS cat_key, units_per_carton, is_mandatory FROM carton_specs WHERE scope='category' AND scope_id IN $ph";
                $stmt = $db->prepare($sql);
                bindParams($stmt, $t, $p);
                $stmt->execute(); $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $cartonByCat[(string)$row['cat_key']] = [
                        'units_per_carton' => (int)$row['units_per_carton'],
                        'carton_mandatory' => ((int)$row['is_mandatory'] === 1),
                    ];
                }
                $stmt->close();
            }
        }
    }

    // Fill meta (weights, seed qtys, pack)
    foreach ($productIds as $pidRaw) {
        $pid = (string)$pidRaw;
        $t = $typeByPid[$pid] ?? null;
        $c = $catByPid[$pid] ?? null;
    $bid = $brandByPid[$pid] ?? null;
    $sid = $supplierByPid[$pid] ?? null;
        $bname = $brandNameByPid[$pid] ?? '';

        $pAvg = $prodAvg[$pid] ?? null;
        $cAvg = ($c && array_key_exists($c, $catW)) ? $catW[$c] : null;
        $tAvg = ($t && isset($typeDefaults[$t])) ? $typeDefaults[$t]['avg_weight_grams'] : null;

        $seed = ($t && isset($typeDefaults[$t])) ? (int)$typeDefaults[$t]['default_seed_qty'] : (int)$seedUnclassifiedDefault;

        if (!isset($meta[$pid])) $meta[$pid] = ['type'=>null,'category'=>null,'brand_id'=>null,'brand_name'=>null,'effective_weight'=>null,'default_seed_qty'=>null,'pack'=>null];

        $meta[$pid]['effective_weight'] = resolve_effective_weight($pAvg, $cAvg, $tAvg);
        $meta[$pid]['default_seed_qty'] = $seed;

    $brandRule = ($bid && isset($brandPack[$bid])) ? $brandPack[$bid] : null;
    $supplierRule = ($sid && isset($supplierPack[$sid])) ? $supplierPack[$sid] : null;
        // Pack selection priority can be controlled by GET flag
        $catRule = ($c && isset($catPack[$c])) ? $catPack[$c] : null;
        // Decide pack rule based on priority + useOverrides
    $chosen = null; $source = null;
        $priority = ($GLOBALS['packPriorityMode'] ?? 'product-first');
        $allowOverrides = (int)($GLOBALS['useOverrides'] ?? 1) === 1;
        if ($priority === 'category-first') {
            if ($catRule) { $chosen = $catRule; $source = 'category'; }
            if ($allowOverrides && !$chosen && $brandRule) { $chosen = $brandRule; $source = 'brand'; }
            if ($allowOverrides && !$chosen && $supplierRule) { $chosen = $supplierRule; $source = 'supplier'; }
            if ($allowOverrides && !$chosen && isset($prodPack[$pid])) { $chosen = $prodPack[$pid]; $source = 'product'; }
        } else { // product-first
            if (isset($prodPack[$pid])) { $chosen = $prodPack[$pid]; $source = 'product'; }
            if ($allowOverrides && !$chosen && $brandRule) { $chosen = $brandRule; $source = 'brand'; }
            if ($allowOverrides && !$chosen && $supplierRule) { $chosen = $supplierRule; $source = 'supplier'; }
            if (!$chosen && $catRule) { $chosen = $catRule; $source = 'category'; }
        }
        if ($chosen) {
            $meta[$pid]['pack'] = [
                'pack_size'      => isset($chosen['pack_size']) ? (int)$chosen['pack_size'] : null,
                'outer_multiple' => isset($chosen['outer_multiple']) ? (int)$chosen['outer_multiple'] : null,
                'rounding_mode'  => $chosen['rounding_mode'] ?? 'floor',
                'enforce_outer'  => !empty($chosen['enforce_outer']) || ((int)($GLOBALS['enforceCategoryPack'] ?? 0) === 1 && $source === 'category'),
                'enforce_reason' => !empty($chosen['enforce_outer']) ? 'db' : (((int)($GLOBALS['enforceCategoryPack'] ?? 0) === 1 && $source === 'category') ? 'engine' : null),
                'source'         => $source,
            ];
            if (isset($diag['had_chosen_pack_by_source'][$source])) {
                $diag['had_chosen_pack_by_source'][$source]++;
            }
        } else {
            $meta[$pid]['pack'] = null;
            $diag['had_chosen_pack_by_source']['none']++;
        }

        // Hard fallback: any brand name containing 'DISPOSVAPE' ⇒ box of 10
        if (!$meta[$pid]['pack'] && $bname !== '' && stripos($bname, 'disposvape') !== false) {
            $meta[$pid]['pack'] = [
                'pack_size'      => 10,
                'outer_multiple' => 10,
                'rounding_mode'  => 'round',
                'enforce_outer'  => true,
                'source'         => 'fallback',
                'enforce_reason' => 'fallback',
            ];
            $diag['had_chosen_pack_by_source']['fallback']++;
        }

        // Attach carton preferences: product-specific takes precedence over category
        if (isset($cartonByProd[$pid])) {
            $meta[$pid]['carton'] = $cartonByProd[$pid];
        } elseif ($c && isset($cartonByCat[$c])) {
            $meta[$pid]['carton'] = $cartonByCat[$c];
        }
    }

    // Queue unclassified products (missing type)
    $ins = $db->prepare("INSERT IGNORE INTO product_categorization_queue (product_id, status, attempt_count) VALUES (?, 'queued', 0)");
    foreach ($productIds as $pidRaw) {
        $pid = (string)$pidRaw;
        if (empty($typeByPid[$pid])) {
            $ins->bind_param('s', $pid);
            if ($ins->execute()) { $queued++; }
        }
    }
    $ins->close();

    return ['meta'=>$meta, 'freight_rules'=>$freightRules, 'queued'=>$queued, 'diag'=>$diag];
}

/** Outlets (destinations) */
function fetchDestOutlets(mysqli $db, string $hubId): array {
    $sql = "
        SELECT id AS outlet_id, name,
               COALESCE(store_code, UPPER(LEFT(name,3))) AS store_code,
               turn_over_rate AS turnover_rate
        FROM vend_outlets
        WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')
          AND id <> ?
          AND is_warehouse = 0
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $hubId);
    $stmt->execute(); $res = $stmt->get_result();
    $rows = []; while ($r = $res->fetch_assoc()) $rows[]=$r;
    $stmt->close();
    return $rows;
}

/** Hub products with stock with flexible brand/supplier gating */
function fetchHubProducts(
    mysqli $db,
    string $hubId,
    int $limit,
    string $filterMode,
    string $supplierFlag,
    int $legacyBrandEnabled,
    int $legacySupplierFlag,
    string $excludeNameLike
): array {
    $sfCol = match($supplierFlag){
        'transferring'  => 'vs.automatic_transferring',
        'transfersales' => 'vs.automatic_transferring_based_on_sales_data',
        default         => 'vs.automatic_ordering'
    };

    $sql = "SELECT vi.product_id,
                   GREATEST(0, vi.inventory_level) AS wh_qty,
                   vp.name AS product_name
            FROM vend_inventory vi
            INNER JOIN vend_products  vp ON vi.product_id = vp.id
            LEFT  JOIN vend_brands    vb ON vp.brand_id    = vb.id
        LEFT  JOIN vend_suppliers vs ON vp.supplier_id = vs.id
            WHERE vi.outlet_id = ?
              AND vi.inventory_level > 0
              AND vp.has_inventory = 1
              AND vp.is_active = 1
              AND vp.active = 1
              AND (vp.is_deleted = 0 OR vp.is_deleted IS NULL)
              AND (vp.deleted_at IS NULL OR vp.deleted_at = '0000-00-00 00:00:00')";

    // Modern gating takes precedence
    switch ($filterMode) {
        case 'brand':
            $sql .= " AND COALESCE(vb.enable_store_transfers,0)=1";
            break;
        case 'supplier':
            $sql .= " AND (COALESCE($sfCol,0)=1 AND (vs.deleted_at IS NULL OR vs.deleted_at='' OR vs.deleted_at='0000-00-00 00:00:00'))";
            break;
        case 'and':
            $sql .= " AND (COALESCE(vb.enable_store_transfers,0)=1 AND COALESCE($sfCol,0)=1 AND (vs.deleted_at IS NULL OR vs.deleted_at='' OR vs.deleted_at='0000-00-00 00:00:00'))";
            break;
        case 'or':
            $sql .= " AND ((COALESCE(vb.enable_store_transfers,0)=1) OR (COALESCE($sfCol,0)=1 AND (vs.deleted_at IS NULL OR vs.deleted_at='' OR vs.deleted_at='0000-00-00 00:00:00')))";
            break;
        case 'none':
        default:
            // Legacy gating (optional)
            if ($legacyBrandEnabled) {
                $sql .= " AND COALESCE(vb.enable_store_transfers,0)=1";
            }
            if ($legacySupplierFlag) {
                $sql .= " AND (COALESCE($sfCol,0)=1 AND (vs.deleted_at IS NULL OR vs.deleted_at='' OR vs.deleted_at='0000-00-00 00:00:00'))";
            }
            break;
    }

    $types = 's';
    $bind  = [$hubId];

    // Exclude by name pattern
    if ($excludeNameLike !== '') {
        $sql .= " AND vp.name NOT LIKE ?";
        $types .= 's';
        $bind[] = $excludeNameLike;
    }

    if ($limit > 0) $sql .= " LIMIT " . (int)$limit;

    $stmt = $db->prepare($sql);
    bindParams($stmt, $types, $bind);
    $stmt->execute(); $res = $stmt->get_result();
    $rows = []; while ($r=$res->fetch_assoc()) $rows[]=$r;
    $stmt->close();
    return $rows;
}

/** Returns matrix[product_id][outlet_id] = qty (chunked by products) */
function fetchDestStocksMatrix(mysqli $db, array $destIds, array $productIds): array {
    $matrix = [];
    if (empty($destIds) || empty($productIds)) return $matrix;

    $chunk = 1000;
    for ($i=0; $i<count($productIds); $i+=$chunk) {
        $slice = array_slice($productIds, $i, $chunk);
        $phO = implode(',', array_fill(0, count($destIds), '?'));
        $phP = implode(',', array_fill(0, count($slice), '?'));
        $types = str_repeat('s', count($destIds) + count($slice));
        $sql = "SELECT outlet_id, product_id, GREATEST(0, inventory_level) AS qty
                FROM vend_inventory
                WHERE outlet_id IN ($phO) AND product_id IN ($phP)";
        $stmt = $db->prepare($sql);
        $params = array_merge($destIds, $slice);
        bindParams($stmt, $types, $params);
        $stmt->execute(); $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $matrix[(string)$r['product_id']][(string)$r['outlet_id']] = (int)$r['qty'];
        }
        $stmt->close();
    }
    return $matrix;
}

/** Smart post-allocation smoothing / rounding */
function smart_quantize_allocations(
    array &$alloc,
    array $destStocks,
    array $seeded,
    int $maxPerStore,
    int $minLineQty,
    int $dropIfDestAtLeast,  // usually your noSend threshold
    int $snapMultiple,       // e.g. 10
    int $snapDelta,          // e.g. 1 (so 9→10, 11→10)
    int &$remaining,
    bool $preservePacks,
    array &$decisionIndex,
    int $singletonKillerThreshold,
    int $seedMinQty
): void {
    foreach ($alloc as $oid => $q0) {
        $q = (int)$q0;
        $have = (int)($destStocks[$oid] ?? 0);

        // If dest already has plenty, send nothing
        if ($dropIfDestAtLeast > 0 && $have >= $dropIfDestAtLeast) {
            $remaining += $q; $alloc[$oid] = 0; continue;
        }

        if ($q <= 0) { $alloc[$oid] = 0; continue; }

        // singleton killer: drop tiny lines unless seeded
        if ($q > 0 && $q <= $singletonKillerThreshold && empty($seeded[$oid])) {
            $remaining += $q; $alloc[$oid] = 0; $decisionIndex['singleton_killed'] = ($decisionIndex['singleton_killed'] ?? 0) + 1; continue;
        }

        // Snap near multiples (if we aren't required to preserve strict packs)
        if (!$preservePacks && $snapMultiple > 1 && $snapDelta >= 1) {
            $r = $q % $snapMultiple;

            // bump up if within +snapDelta of the next multiple (e.g. 9→10)
            if ($r >= $snapMultiple - $snapDelta) {
                $need = $snapMultiple - $r;
                $bump = min($need, $remaining, max(0, $maxPerStore - $q));
                if ($bump > 0) { $q += $bump; $remaining -= $bump; }
            }
            // trim down if within -snapDelta of the previous multiple (e.g. 11→10)
            elseif ($r <= $snapDelta) {
                $drop = min($r, $q);
                if ($q - $drop < $minLineQty && empty($seeded[$oid])) {
                    // dropping would violate min line — just zero it
                    $remaining += $q; $q = 0; $decisionIndex['snap_down_zeroed_line'] = ($decisionIndex['snap_down_zeroed_line'] ?? 0) + 1;
                } else {
                    $q -= $drop; $remaining += $drop; $decisionIndex['snap_down'] = ($decisionIndex['snap_down'] ?? 0) + 1;
                }
            }
        }

        // Re-enforce min line for non-seed openings
        if ($q > 0 && $q < $minLineQty && empty($seeded[$oid])) {
            $remaining += $q; $q = 0; $decisionIndex['post_snap_below_min_zeroed'] = ($decisionIndex['post_snap_below_min_zeroed'] ?? 0) + 1;
        }

        // Final clamp
        if ($q > $maxPerStore) $q = $maxPerStore;

        $alloc[$oid] = $q;
    }
}

/** Trim excess allocations to match available surplus without breaking min-line constraints */
function trim_excess_allocations(array &$alloc, array $seeded, int $minLineQty, int $seedMinQty, int $excess, array &$decisionIndex): void {
    if ($excess <= 0) return;
    // Prefer trimming non-seeded lines above minLineQty first
    arsort($alloc, SORT_NUMERIC);
    foreach ($alloc as $oid => $q) {
        if ($excess <= 0) break;
        $isSeed = !empty($seeded[$oid]);
        $minAllowed = $isSeed ? $seedMinQty : $minLineQty;
        if ($q <= $minAllowed) continue;
        $drop = min($excess, $q - $minAllowed);
        if ($drop > 0) {
            $alloc[$oid] -= $drop;
            $excess -= $drop;
            $decisionIndex['excess_trimmed'] = ($decisionIndex['excess_trimmed'] ?? 0) + $drop;
        }
    }
}

/**
 * Map outlet turnover rate to a seeding profile.
 * Returns [band, seed_min_multiplier, seed_max_multiplier].
 * - Higher turnover: increase seed_min and seed_max to accelerate traction.
 * - Lower turnover: keep seed_min baseline; optionally reduce seed_max to limit openings.
 */
function turnover_seed_profile(float $turnoverRate): array {
    $BANDS = get_turnover_bands_config();
    // Guard invalids
    if (!is_finite($turnoverRate)) $turnoverRate = 5.0;
    // Determine band based on threshold_ge
    $band = 'low';
    foreach (['ultra','high','mid'] as $label) {
        $th = (float)($BANDS[$label]['threshold_ge'] ?? INF);
        if ($turnoverRate >= $th) { $band = $label; break; }
    }
    // Pull multipliers for chosen band
    $minMul = (float)($BANDS[$band]['min_mul'] ?? 1.0);
    $maxMul = (float)($BANDS[$band]['max_mul'] ?? 1.0);
    return [$band, $minMul, $maxMul];
}

/**
 * Build effective turnover bands config based on mode and optional overrides provided via GET.
 * Supports params like:
 *   turnover-bands-mode=default|override
 *   tb-ultra-min-mul, tb-ultra-max-mul, tb-ultra-threshold
 *   tb-high-min-mul,  tb-high-max-mul,  tb-high-threshold
 *   tb-mid-min-mul,   tb-mid-max-mul,   tb-mid-threshold
 *   tb-low-min-mul,   tb-low-max-mul,   tb-low-threshold
 */
function build_turnover_bands_config(string $mode): array {
    // Defaults
    $bands = [
        'ultra' => ['min_mul'=>1.7, 'max_mul'=>1.6, 'threshold_ge'=>8.0],
        'high'  => ['min_mul'=>1.4, 'max_mul'=>1.4, 'threshold_ge'=>6.0],
        'mid'   => ['min_mul'=>1.2, 'max_mul'=>1.2, 'threshold_ge'=>4.0],
        'low'   => ['min_mul'=>1.0, 'max_mul'=>0.8, 'threshold_ge'=>0.0],
    ];

    if ($mode !== 'override') { return $bands; }

    // Helper to read and clamp
    $getF = function(string $key, float $def, float $min, float $max): float {
        if (!isset($_GET[$key])) return $def;
        $v = (float)$_GET[$key];
        if (!is_finite($v)) return $def;
        return max($min, min($max, $v));
    };

    // Apply overrides (each optional)
    foreach (['ultra','high','mid','low'] as $label) {
        $bands[$label]['min_mul'] = $getF('tb-'.$label.'-min-mul', (float)$bands[$label]['min_mul'], 0.0, 5.0);
        $bands[$label]['max_mul'] = $getF('tb-'.$label.'-max-mul', (float)$bands[$label]['max_mul'], 0.0, 5.0);
        $bands[$label]['threshold_ge'] = $getF('tb-'.$label.'-threshold', (float)$bands[$label]['threshold_ge'], 0.0, 100.0);
    }

    // Ensure thresholds are monotonic: ultra>=high>=mid>=low
    $bands['high']['threshold_ge']  = max($bands['high']['threshold_ge'],  $bands['mid']['threshold_ge']);
    $bands['ultra']['threshold_ge'] = max($bands['ultra']['threshold_ge'], $bands['high']['threshold_ge']);

    return $bands;
}

// Global storage for effective turnover bands for this request
function set_turnover_bands_config(array $bands): void {
    $GLOBALS['__TURNOVER_BANDS_EFFECTIVE__'] = $bands;
}
function get_turnover_bands_config(): array {
    $b = $GLOBALS['__TURNOVER_BANDS_EFFECTIVE__'] ?? null;
    if (is_array($b)) return $b;
    // default fallback
    return [
        'ultra' => ['min_mul'=>1.7, 'max_mul'=>1.6, 'threshold_ge'=>8.0],
        'high'  => ['min_mul'=>1.4, 'max_mul'=>1.4, 'threshold_ge'=>6.0],
        'mid'   => ['min_mul'=>1.2, 'max_mul'=>1.2, 'threshold_ge'=>4.0],
        'low'   => ['min_mul'=>1.0, 'max_mul'=>0.8, 'threshold_ge'=>0.0],
    ];
}

/** Ensure pack telemetry counters exist in decision index (default 0 for visibility) */
function ensure_pack_telemetry_keys(array &$di): void {
    $keys = [
        'pack_source_category',
        'pack_source_brand',
        'pack_source_supplier',
        'pack_source_product',
        'pack_source_fallback',
        'pack_enforce_category_outer',
        'lines_pack_source_category',
        'lines_pack_source_brand',
        'lines_pack_source_supplier',
        'lines_pack_source_product',
        'lines_pack_source_fallback',
        'lines_pack_engine_enforced_outer',
        // Cartonization default key for visibility in artifacts
        'carton_bumped',
    ];
    foreach ($keys as $k) {
        if (!array_key_exists($k, $di)) { $di[$k] = 0; }
    }
}

/** Compute a category-aware guard profile for a product based on its meta.
 * Inputs: $meta: ['type','category','brand_name',...], $defaults with keys:
 *   min_line_qty, seed_min_qty, seed_max_open, high_stock_factor, singleton_killer_threshold
 * Returns same keys with optional '_profile' label for observability.
 */
function compute_guard_profile(array $meta, array $defaults): array {
    $type = strtolower((string)($meta['type'] ?? ''));
    $cat  = strtolower((string)($meta['category'] ?? ''));
    $brand= strtolower((string)($meta['brand_name'] ?? ''));

    // Start from global defaults
    $gp = [
        'min_line_qty' => (int)($defaults['min_line_qty'] ?? 1),
        'seed_min_qty' => (int)($defaults['seed_min_qty'] ?? 2),
        'seed_max_open'=> (int)($defaults['seed_max_open'] ?? 10),
        'high_stock_factor' => (float)($defaults['high_stock_factor'] ?? 1.5),
        'singleton_killer_threshold' => (int)($defaults['singleton_killer_threshold'] ?? 1),
        '_profile' => 'default',
    ];

    // Heuristics:
    // • Pods/Coils: stricter oversend prevention, higher min-line for viability, higher no-singleton threshold
    // • Disposables: slightly looser min-line (outer packs matter), allow small openings; similar high-stock factor
    // • Liquids/Accessories: default profile
    // These adjust only guardrails, not core demand logic.

    if ($type !== '') {
        if (strpos($type, 'pod') !== false || strpos($type, 'coil') !== false) {
            $gp['min_line_qty'] = max($gp['min_line_qty'], 4);
            $gp['seed_min_qty'] = max($gp['seed_min_qty'], 3);
            $gp['singleton_killer_threshold'] = max($gp['singleton_killer_threshold'], 2);
            $gp['high_stock_factor'] = max(1.3, min(3.0, (float)$gp['high_stock_factor']));
            $gp['_profile'] = 'pods_coils';
        } elseif (strpos($type, 'disp') !== false || strpos($brand, 'disposvape') !== false || strpos($cat, 'disposable') !== false) {
            $gp['min_line_qty'] = max($gp['min_line_qty'], 3);
            $gp['seed_min_qty'] = max($gp['seed_min_qty'], 2);
            $gp['singleton_killer_threshold'] = max($gp['singleton_killer_threshold'], 1);
            $gp['high_stock_factor'] = max(1.5, min(3.5, (float)$gp['high_stock_factor']));
            $gp['_profile'] = 'disposables';
        }
    } else {
        // No type: rely on category hints
        if (strpos($cat, 'coil') !== false || strpos($cat, 'pod') !== false) {
            $gp['min_line_qty'] = max($gp['min_line_qty'], 4);
            $gp['seed_min_qty'] = max($gp['seed_min_qty'], 3);
            $gp['singleton_killer_threshold'] = max($gp['singleton_killer_threshold'], 2);
            $gp['_profile'] = 'pods_coils';
        } elseif (strpos($cat, 'disp') !== false) {
            $gp['min_line_qty'] = max($gp['min_line_qty'], 3);
            $gp['seed_min_qty'] = max($gp['seed_min_qty'], 2);
            $gp['_profile'] = 'disposables';
        }
    }

    // Hard brand heuristics
    if (strpos($brand, 'disposvape') !== false) {
        $gp['min_line_qty'] = max($gp['min_line_qty'], 10); // outer box
        $gp['_profile'] = 'disposables';
    }

    // Clamp to sane ranges
    $gp['min_line_qty'] = max(1, min(50, (int)$gp['min_line_qty']));
    $gp['seed_min_qty'] = max(0, min(50, (int)$gp['seed_min_qty']));
    $gp['seed_max_open']= max(0, min(100, (int)$gp['seed_max_open']));
    $gp['high_stock_factor'] = max(1.0, min(5.0, (float)$gp['high_stock_factor']));
    $gp['singleton_killer_threshold'] = max(1, min(10, (int)$gp['singleton_killer_threshold']));

    return $gp;
}

/** Inserts one parent per outlet + child lines; returns outlet_id => transfer_id */
function persistTransfers(
    mysqli $db,
    string $hubId,
    array $destOutlets,
    array $perOutletLines,    // outlet_id => [ lines... ]
    string $runId,
    float $reservePct,
    int $reserveMin
): array {
    $result = [];

        $sqlParent = "
                INSERT INTO stock_transfers
                    (date_created, status, micro_status,
                     outlet_from, outlet_to,
                     transfer_type, source_module, algorithm_version,
                     run_id, product_count, total_quantity, total_weight_grams,
                     transfer_created_by_user, created_by_system, notes)
                VALUES
                    (NOW(), 3, 'planned',
                     ?, ?, 'rebalance', 'adhoc_balance_http', '1.0',
                     ?, ?, ?, ?, 18, 'adhoc_rebalance_http', ?)
        ";

    $sqlChild = "
        INSERT INTO stock_products_to_transfer
          (transfer_id, product_id, donor_outlet_id, qty_to_transfer,
           min_qty_to_remain, weight_per_unit_grams, total_weight_grams,
           created_at, updated_at)
        VALUES
          (?, ?, ?, ?, 0, ?, ?, NOW(), NOW())
    ";

    foreach ($perOutletLines as $oid => $lines) {
        if (empty($lines)) continue;

        $productCount = count($lines);
        $totalQty = 0; $totalWeight = 0;
        foreach ($lines as $ln) { $totalQty += $ln['qty_sent']; $totalWeight += $ln['total_weight_g']; }

        $notes = sprintf(
            "Ad-hoc rebalance %s | reserve=max(%.0f%%,%d) | products=%d units=%d",
            $runId, $reservePct*100, $reserveMin, $productCount, $totalQty
        );

        // Parent
        $stmtP = $db->prepare($sqlParent);
        $stmtP->bind_param('sssiiis', $hubId, $oid, $runId, $productCount, $totalQty, $totalWeight, $notes);
        if (!$stmtP->execute()) {
            $err = $stmtP->error;
            $stmtP->close();
            throw new RuntimeException("Parent insert failed for outlet {$oid}: {$err}");
        }
        $transferId = (int)$stmtP->insert_id;
        $stmtP->close();

        // Children
        $stmtC = $db->prepare($sqlChild);
        foreach ($lines as $ln) {
            $pid = (string)$ln['product_id'];
            $qty = (int)$ln['qty_sent'];
            $wpu = (int)$ln['weight_per_unit_g'];
            $twg = (int)$ln['total_weight_g'];
            $stmtC->bind_param('issiii', $transferId, $pid, $hubId, $qty, $wpu, $twg);
            if (!$stmtC->execute()) {
                $err = $stmtC->error;
                $stmtC->close();
                throw new RuntimeException("Child insert failed (transfer_id={$transferId}, product={$pid}): {$err}");
            }
        }
        $stmtC->close();

        $result[$oid] = $transferId;
    }

    return $result;
}
