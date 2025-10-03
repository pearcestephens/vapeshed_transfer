<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Services\Allocator\BalancedStockAllocator;

/**
 * Transfer Engine Service
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Core transfer engine with proportional allocation algorithm
 * 
 * Refactored from original engine.php with enterprise patterns:
 * - Clean separation of concerns
 * - Proper error handling and logging
 * - Configuration validation
 * - Algorithm optimization and safety guards
 */
class TransferEngineService
{
    private Logger $logger;
    private array $config;
    private bool $killSwitchActive = false;
    private ?string $runId = null;
    private ?string $streamPath = null;
    private array $profile = [];
    private array $outletPerf = [];
    private array $outletMeta = [];
    private array $decisionTrace = [];
    
    public function __construct()
    {
        $this->logger = new Logger();
        $this->checkKillSwitch();
    }
    
    private function checkKillSwitch(): void
    {
        $killFile = STORAGE_PATH . '/KILL_SWITCH';
        $this->killSwitchActive = file_exists($killFile);
        
        if ($this->killSwitchActive) {
            $this->logger->warning('Kill switch is active - forcing dry run mode');
        }
    }
    
    /**
     * Execute transfer with given configuration
     */
    public function executeTransfer(array $config, array $products = []): array
    {
        $startTime = microtime(true);
        // Allow client to provide a run_id to enable SSE subscription before execution begins
        if (!empty($config['run_id']) && is_string($config['run_id']) && preg_match('/^run_[A-Za-z0-9_\-]+$/', $config['run_id'])) {
            $this->runId = $config['run_id'];
        } else {
            $this->runId = $this->generateRunId();
        }
        $this->streamPath = STORAGE_PATH . '/runs/' . $this->runId . '.stream';
        $this->ensureDir(dirname($this->streamPath));
        $this->profile = [];
    $this->decisionTrace = [];
        
        $this->logger->info("Transfer execution started", [
            'run_id' => $this->runId,
            'dry_run' => $config['dry'] ?? true
        ]);
        $this->emitProgress([ 'stage' => 'start', 'message' => 'Initializing engine...', 'ts' => date('c') ]);
        
        try {
            // Validate and coerce configuration
            $t0 = microtime(true);
            $validatedConfig = $this->validateConfiguration($config);
            $this->markProfile('validate_config', $t0);
            $this->config = $validatedConfig;
            
            // Force dry run if kill switch is active
            if ($this->killSwitchActive && !$validatedConfig['dry']) {
                $validatedConfig['dry'] = true;
                $this->logger->warning('Kill switch forced dry run mode');
            }
            
            // Get eligible outlets
            $this->emitProgress([ 'stage' => 'outlets', 'message' => 'Loading outlets...', 'ts' => date('c') ]);
            $t1 = microtime(true);
            $outlets = $this->getEligibleOutlets($validatedConfig);
            // In test mode, if outlets are unavailable, synthesize a tiny set BEFORE initializing meta/perf
            if (empty($outlets) && !empty($validatedConfig['test_mode'])) {
                $outlets = [
                    ['outlet_id' => 'OUT-1', 'name' => 'Demo Outlet 1'],
                    ['outlet_id' => 'OUT-2', 'name' => 'Demo Outlet 2'],
                    ['outlet_id' => 'OUT-3', 'name' => 'Demo Outlet 3'],
                ];
            }
            $this->outletMeta = [];
            foreach ($outlets as $o) {
                $oid = $o['outlet_id'];
                $this->outletMeta[$oid] = [
                    'name' => $o['name'] ?? $oid,
                    'store_code' => $o['store_code'] ?? null,
                    'tier' => $o['tier'] ?? null,
                ];
                // initialize perf bucket
                $this->outletPerf[$oid] = [
                    'store_code' => $o['store_code'] ?? null,
                    'name' => $o['name'] ?? $oid,
                    'demand_calls' => 0,
                    'demand_ms' => 0.0,
                    'allocation_lines' => 0,
                    'allocation_qty' => 0,
                ];
            }
            $this->markProfile('load_outlets', $t1);
            
            if (empty($outlets)) {
                throw new \Exception('No eligible outlets found for transfer');
            }
            
            // Get products for transfer
                        $this->emitProgress([ 'stage' => 'products', 'message' => 'Loading products...', 'ts' => date('c') ]);
                        $t2 = microtime(true);
                        $transferProducts = $this->getTransferProducts($validatedConfig, $products);
                        $this->markProfile('load_products', $t2);
                        // In test mode, if product list is empty, synthesize minimal products
                        if (empty($transferProducts) && !empty($validatedConfig['test_mode'])) {
                                $transferProducts = [
                                        [ 'product_id' => 'P-DEMO-1', 'warehouse_stock' => 80,
                                            'outlet_stocks' => [ 'OUT-1' => 2, 'OUT-2' => 15, 'OUT-3' => 0 ],
                                            'sales_velocity' => [ 'OUT-1' => 5, 'OUT-2' => 3, 'OUT-3' => 4 ],
                                            'turnover_rate' => [] ],
                                        [ 'product_id' => 'P-DEMO-2', 'warehouse_stock' => 55,
                                            'outlet_stocks' => [ 'OUT-1' => 0, 'OUT-2' => 0, 'OUT-3' => 1 ],
                                            'sales_velocity' => [ 'OUT-1' => 2, 'OUT-2' => 6, 'OUT-3' => 1 ],
                                            'turnover_rate' => [] ],
                                ];
                        }
            
            if (empty($transferProducts)) {
                throw new \Exception('No eligible products found for transfer');
            }
            
            // Execute allocation algorithm
            $this->emitProgress([ 'stage' => 'allocate', 'message' => 'Calculating allocations...', 'ts' => date('c') ]);
            $t3 = microtime(true);
            $allocations = $this->calculateAllocations($outlets, $transferProducts, $validatedConfig);
            $this->markProfile('calculate_allocations', $t3);
            
            // Generate transfer results
            $result = [
                'run_id' => $this->runId,
                'timestamp' => date('c'),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'config' => $validatedConfig,
                'outlets' => $outlets,
                'products' => $transferProducts,
                'allocations' => $allocations,
                'summary' => $this->generateSummary($allocations),
                'dry_run' => $validatedConfig['dry'],
                'kill_switch_active' => $this->killSwitchActive,
                'test_mode' => !empty($validatedConfig['test_mode']),
                'profile' => $this->profile,
                'outlet_perf' => $this->formatOutletPerf(),
                'decision_trace' => $this->decisionTrace
            ];
            
            // Save snapshot if not dry run
            if (!$validatedConfig['dry']) {
                $this->saveTransferSnapshot($this->runId, $result);
            }
            
            $this->logger->info("Transfer execution completed successfully", [
                'run_id' => $this->runId,
                'execution_time_ms' => $result['execution_time_ms']
            ]);
            $this->emitProgress([
                'stage' => 'done',
                'message' => 'Run complete',
                'done' => true,
                'summary' => $result['summary'],
                'profile' => $this->profile,
                'outlet_perf_top' => array_slice($this->formatOutletPerf(), 0, 10),
                'ts' => date('c')
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error("Transfer execution failed", [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->emitProgress([ 'stage' => 'error', 'message' => $e->getMessage(), 'error' => true, 'ts' => date('c') ]);
            
            throw $e;
        }
    }
    
    private function validateConfiguration(array $config): array
    {
        $defaults = [
            'dry' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'warehouse_web_outlet_id' => WAREHOUSE_WEB_OUTLET_ID,
            'skip_outlets' => '',
            'min_lines' => 5,
            // 0 means uncapped (subject to tier caps if enabled)
            'max_per_product' => 30,
            'min_cap_per_outlet' => 2,
            'top_k_outlets' => 0,
            'dynamic_top_k' => 0,
            'reserve_percent' => 0.12,
            'reserve_min_units' => 3,
            // New: absolute reserve floor to avoid hub drain for very large stock
            'reserve_floor_units' => 50,
            // New: allocation mode (default to stock-only balancing)
            'mode' => 'balance_stock_only',
            // Stock-only allocator knobs
            'seed_qty_zero' => 3,
            'topup_low_to' => 10,
            'mid_topup' => 5,
            'proportional_share' => 0.45,
            // Limit how many distinct SKUs per store in a single run
            'max_skus_per_store' => 25,
            // Hub product gating
            'brand_supplier_mode' => 'and', // none|brand|supplier|and|or
            'supplier_flag' => 'transferring', // transferring|ordering|transfersales
            'exclude_name_like' => 'TEST%',
            'hub_products_limit' => 0,
            // Additional limits for hub pick (effective limit = min(non-zero limits))
            'limit_products' => 0,
            'max_products_cap' => 2000,
            // Optional rounding/smoothing knobs (not used unless smoothing is enabled)
            'min_line_qty' => 3,
            'no_send_if_atleast' => 0,
            'snap_multiple' => 10,
            'snap_delta' => 1,
            // Exclude by store codes, CSV
            'exclude_codes' => '',
            'turnover_min_pct' => 3,
            'turnover_max_pct' => 10,
            'default_turnover_pct' => 7,
            'weight_method' => 'power',
            'weight_gamma' => 1.8,
            'weight_epsilon' => 1.0,
            'softmax_tau' => 6.0,
            'weight_mix_beta' => 0.8,
            'compare_mode' => 'quartiles',
            // New: Tier + Seeding + Lines caps
            'enable_tier_caps' => 1,
            'tier_caps' => [ 'A' => 6, 'B' => 4, 'C' => 3 ],
            // Optional: explicit outlet tiers mapping outlet_id => 'A'|'B'|'C'
            'outlet_tiers' => [],
            // Seed behaviour
            'seed_on_new' => 1,
            'seed_on_high_stock' => 1,
            'high_stock_threshold' => 500,
            'seed_qty_default' => 1,
            'seed_qty_high_stock' => 2,
            'seed_qty_by_tier' => [ 'A' => 3, 'B' => 1, 'C' => 1 ],
            // If true, when seeding or high-stock/new, do not restrict by top-K
            'disable_top_k_on_seed' => 1,
            // Prevent too many shipping lines per outlet in a single run
            'max_lines_per_store' => 80,
            // Velocity threshold to consider product effectively "no sales"
            'velocity_seed_threshold' => 0.1,
        ];
        
        $validated = array_merge($defaults, $config);
        
        // Validate required fields
        if (empty($validated['warehouse_id'])) {
            throw new \Exception('Warehouse ID is required');
        }
        
        // Sanitize and validate numeric fields
        $validated['dry'] = (int)$validated['dry'];
        $validated['dry'] = (int)$validated['dry'];
        $validated['min_lines'] = max(1, (int)$validated['min_lines']);
        // Allow 0 to represent uncapped
        $validated['max_per_product'] = max(0, (int)$validated['max_per_product']);
        $validated['min_cap_per_outlet'] = max(0, (int)$validated['min_cap_per_outlet']);
        $validated['top_k_outlets'] = max(0, (int)$validated['top_k_outlets']);
        $validated['dynamic_top_k'] = max(0, (int)($validated['dynamic_top_k'] ?? 0));
        $validated['reserve_percent'] = max(0.0, min(0.90, (float)$validated['reserve_percent']));
        $validated['reserve_min_units'] = max(0, (int)$validated['reserve_min_units']);
        $validated['reserve_floor_units'] = max(0, (int)$validated['reserve_floor_units']);
        // Stock-only knobs
        $validated['seed_qty_zero'] = max(0, (int)$validated['seed_qty_zero']);
        $validated['topup_low_to'] = max(0, (int)$validated['topup_low_to']);
        $validated['mid_topup'] = max(0, (int)$validated['mid_topup']);
        $validated['proportional_share'] = max(0.0, min(1.0, (float)$validated['proportional_share']));
        $validated['max_skus_per_store'] = max(1, (int)$validated['max_skus_per_store']);
    $validated['enable_tier_caps'] = (int)$validated['enable_tier_caps'];
        $validated['seed_on_new'] = (int)$validated['seed_on_new'];
        $validated['seed_on_high_stock'] = (int)$validated['seed_on_high_stock'];
        $validated['high_stock_threshold'] = max(0, (int)$validated['high_stock_threshold']);
        $validated['seed_qty_default'] = max(0, (int)$validated['seed_qty_default']);
        $validated['seed_qty_high_stock'] = max(0, (int)$validated['seed_qty_high_stock']);
        $validated['disable_top_k_on_seed'] = (int)$validated['disable_top_k_on_seed'];
        $validated['max_lines_per_store'] = max(1, (int)$validated['max_lines_per_store']);
        $validated['velocity_seed_threshold'] = max(0.0, (float)$validated['velocity_seed_threshold']);
        // Hub gating sanitization and GET fallbacks
        $validated['brand_supplier_mode'] = strtolower((string)($config['brand_supplier_mode'] ?? ($_GET['brand-supplier-mode'] ?? $validated['brand_supplier_mode'])));
        if (!in_array($validated['brand_supplier_mode'], ['none','brand','supplier','and','or'], true)) {
            $validated['brand_supplier_mode'] = 'and';
        }
        $validated['supplier_flag'] = strtolower((string)($config['supplier_flag'] ?? ($_GET['supplier-flag'] ?? $validated['supplier_flag'])));
        if (!in_array($validated['supplier_flag'], ['transferring','ordering','transfersales'], true)) {
            $validated['supplier_flag'] = 'transferring';
        }
        $validated['exclude_name_like'] = (string)($config['exclude_name_like'] ?? ($_GET['exclude-name-like'] ?? ''));
        $validated['hub_products_limit'] = max(0, (int)($config['hub_products_limit'] ?? 0));
    $validated['limit_products'] = max(0, (int)($config['limit_products'] ?? ($_GET['limit-products'] ?? 0)));
    $validated['max_products_cap'] = max(10, (int)($config['max_products_cap'] ?? ($_GET['max-products'] ?? 2000)));
    $validated['min_line_qty'] = max(1, (int)($config['min_line_qty'] ?? ($_GET['min-line-qty'] ?? 3)));
    $validated['no_send_if_atleast'] = max(0, (int)($config['no_send_if_atleast'] ?? ($_GET['no-send-if-atleast'] ?? 0)));
    $validated['snap_multiple'] = max(1, (int)($config['snap_multiple'] ?? ($_GET['snap-multiple'] ?? 10)));
    $validated['snap_delta'] = max(0, (int)($config['snap_delta'] ?? ($_GET['snap-delta'] ?? 1)));
    // Exclude codes CSV -> array of uppercase codes
    $exCodesRaw = (string)($config['exclude_codes'] ?? ($_GET['exclude-codes'] ?? ''));
    $validated['exclude_codes'] = array_values(array_filter(array_map(function($x){ return strtoupper(trim($x)); }, explode(',', $exCodesRaw)), function($x){ return $x !== ''; }));

        // Harmonize SKU-per-store limit with existing line cap: enforce the stricter one
        if (!empty($validated['max_skus_per_store'])) {
            $validated['max_lines_per_store'] = (int)min(
                (int)$validated['max_lines_per_store'],
                (int)$validated['max_skus_per_store']
            );
        }

        // Normalize arrays if passed as JSON strings
        if (is_string($validated['tier_caps'])) {
            $maybe = json_decode($validated['tier_caps'], true);
            if (is_array($maybe)) { $validated['tier_caps'] = $maybe; }
        }
        if (is_string($validated['outlet_tiers'])) {
            $maybe = json_decode($validated['outlet_tiers'], true);
            if (is_array($maybe)) { $validated['outlet_tiers'] = $maybe; }
        }
        if (is_string($validated['seed_qty_by_tier'])) {
            $maybe = json_decode($validated['seed_qty_by_tier'], true);
            if (is_array($maybe)) { $validated['seed_qty_by_tier'] = $maybe; }
        }
        
        // Validate weight method
        if (!in_array($validated['weight_method'], ['power', 'softmax'])) {
            $validated['weight_method'] = 'power';
        }
        
        return $validated;
    }
    
    private function getEligibleOutlets(array $config): array
    {
        // In controlled test/demo scenarios, allow explicit outlets injection
        if (!empty($config['override_outlets']) && is_array($config['override_outlets'])) {
            return array_map(function($o) {
                return [
                    'outlet_id' => $o['outlet_id'],
                    'name' => $o['name'] ?? ($o['outlet_id'] ?? 'Outlet'),
                    'store_code' => $o['store_code'] ?? null,
                    'turnover_rate' => isset($o['turnover_rate']) ? (float)$o['turnover_rate'] : null,
                    'tier' => $o['tier'] ?? null,
                ];
            }, $config['override_outlets']);
        }

        // Get outlets from database (using legacy CIS functions if available)
        if (function_exists('get_outlets')) {
            $allOutlets = get_outlets();
        } else {
            // Fallback to direct database query
            $allOutlets = $this->queryOutlets();
        }
        
        // Normalize optional include filters
    $skipOutlets = array_filter(array_map('trim', explode(',', (string)($config['skip_outlets'] ?? ''))));
        $onlyIds = [];
        if (!empty($config['only_outlet_ids'])) {
            if (is_array($config['only_outlet_ids'])) {
                $onlyIds = $config['only_outlet_ids'];
            } else {
                $onlyIds = array_filter(array_map('trim', explode(',', (string)$config['only_outlet_ids'])));
            }
        }
        $onlyCodes = [];
        if (!empty($config['only_outlet_codes'])) {
            if (is_array($config['only_outlet_codes'])) {
                $onlyCodes = $config['only_outlet_codes'];
            } else {
                $onlyCodes = array_filter(array_map('trim', explode(',', (string)$config['only_outlet_codes'])));
            }
        }

    $eligibleOutlets = [];

    $wid = (string)($config['warehouse_id'] ?? (defined('WAREHOUSE_ID') ? WAREHOUSE_ID : ''));
    foreach ($allOutlets as $raw) {
            // Normalize fields across different providers
            $oid = $raw['outlet_id'] ?? $raw['id'] ?? null;
            if (!$oid) { continue; }
            $name = $raw['name'] ?? ($raw['outlet_name'] ?? ('Outlet ' . $oid));
            $storeCode = $raw['store_code'] ?? $raw['code'] ?? ($raw['short_code'] ?? null);
            $isWarehouse = (int)($raw['is_warehouse'] ?? 0);
            $turnover = $raw['turnover_rate'] ?? ($raw['turn_over_rate'] ?? null);
            if ($turnover !== null) { $turnover = (float)$turnover; }

            // Skip deleted/out-of-service outlets; treat zero-date anomalies as not deleted
            $deletedAt = $raw['deleted_at'] ?? ($raw['deletedAt'] ?? null);
            if ($deletedAt !== null) {
                $d = trim((string)$deletedAt);
                if ($d !== '' && stripos($d, '0000-00-00') !== 0) {
                    // Non-empty and not a zero-date => consider deleted
                    continue;
                }
            }

            // Skip warehouse and specified outlets
            if (($wid !== '' && $oid === $wid) || $isWarehouse === 1) {
                continue;
            }
            if (in_array($oid, $skipOutlets, true)) {
                continue;
            }
            // Apply include filters if present
            if (!empty($onlyIds) && !in_array($oid, $onlyIds, true)) {
                continue;
            }
            if (!empty($onlyCodes)) {
                if ($storeCode === null || !in_array($storeCode, $onlyCodes, true)) {
                    continue;
                }
            }
            // Exclude by codes list if provided
            if (!empty($config['exclude_codes'])) {
                $codeUp = $storeCode !== null ? strtoupper($storeCode) : '';
                if ($codeUp !== '' && in_array($codeUp, (array)$config['exclude_codes'], true)) {
                    continue;
                }
            }

            $eligibleOutlets[] = [
                'outlet_id' => $oid,
                'name' => $name,
                'store_code' => $storeCode,
                'turnover_rate' => $turnover,
                'tier' => null,
            ];
        }

        // Assign outlet tiers if mapping provided
        $eligibleOutlets = $this->assignOutletTiers($eligibleOutlets, $config);

        return $eligibleOutlets;
    }
    
    private function getTransferProducts(array $config, array $requestedProducts): array
    {
        // Implementation of product selection logic
        // This would integrate with the existing CIS product functions
        
        if (function_exists('get_transfer_products')) {
            return get_transfer_products($config, $requestedProducts);
        }
        
        // Fallback implementation: if caller provided products, trust them
        if (!empty($requestedProducts)) {
            return $requestedProducts;
        }
        return $this->queryTransferProducts($config, $requestedProducts);
    }
    
    private function calculateAllocations(array $outlets, array $products, array $config): array
    {
        $allocations = [];
        $this->emitProgress([ 'stage' => 'allocate_init', 'message' => 'Allocating per product...', 'count' => count($products) ]);
        
        foreach ($products as $idx => $product) {
            $tpStart = microtime(true);
            $productAllocations = $this->allocateProduct($product, $outlets, $config);
            $this->markProfile('allocate_product_' . ($product['product_id'] ?? $idx), $tpStart);
            
            if (!empty($productAllocations)) {
                $allocations[$product['product_id']] = $productAllocations;
            }

            $this->emitProgress([
                'stage' => 'allocate_progress',
                'message' => 'Allocated ' . ($product['product_id'] ?? 'item'),
                'product_id' => $product['product_id'] ?? null,
                'index' => $idx + 1,
                'total' => count($products)
            ]);
        }
        
        return $allocations;
    }
    
    private function allocateProduct(array $product, array $outlets, array $config): array
    {
        // Fast-path: stock-only balancing mode (ignore velocity, simple thresholds)
        if (($config['mode'] ?? '') === 'balance_stock_only') {
            $allocator = new BalancedStockAllocator([
                'reserve_min_units'   => (int)$config['reserve_min_units'],
                'reserve_percent'     => (float)$config['reserve_percent'],
                'seed_qty_zero'       => (int)$config['seed_qty_zero'],
                'topup_low_to'        => (int)$config['topup_low_to'],
                'mid_topup'           => (int)$config['mid_topup'],
                // Map existing per-product per-store cap
                'max_per_store'       => (int)$config['max_per_product'],
                'proportional_share'  => (float)$config['proportional_share'],
                'default_turnover_pct'=> (float)($config['default_turnover_pct'] ?? 7),
            ]);

            $rows = $allocator->allocate($product, $outlets, [] /*weights*/, $config);

            // Apply per-store line cap within this run and trace decisions
            $filtered = [];
            $pid = $product['product_id'] ?? 'unknown';
            foreach ($rows as $r) {
                $oid = $r['outlet_id'];
                // Skip outlet if already at line cap
                if (isset($this->outletPerf[$oid]) && $this->outletPerf[$oid]['allocation_lines'] >= $config['max_lines_per_store']) {
                    // Trace skipped due to line cap
                    $this->traceOutlet($pid, $this->outletMetaToOutlet($oid), [
                        'reason' => 'skip_line_cap',
                        'dest_stock' => (int)($product['outlet_stocks'][$oid] ?? 0),
                        'demand' => 0.0,
                        'proportion' => 0.0,
                        'allocated_qty' => 0,
                    ]);
                    continue;
                }

                // Record allocation and bump perf counters
                $filtered[] = $r;
                if (isset($this->outletPerf[$oid])) {
                    $this->outletPerf[$oid]['allocation_lines']++;
                    $this->outletPerf[$oid]['allocation_qty'] += (int)$r['quantity'];
                }

                // Trace as allocated in stock-only path
                $this->traceOutlet($pid, $this->outletMetaToOutlet($oid), [
                    'reason' => 'allocated_stock_only',
                    'dest_stock' => (int)($product['outlet_stocks'][$oid] ?? 0),
                    'demand' => 0.0,
                    'proportion' => 0.0,
                    'allocated_qty' => (int)$r['quantity'],
                ]);
            }

            return $filtered;
        }

        $availableQty = $product['warehouse_stock'] ?? 0;
        $reserveQty = max(
            $config['reserve_min_units'],
            $config['reserve_floor_units'],
            (int)($availableQty * $config['reserve_percent'])
        );
        
        $allocatableQty = max(0, $availableQty - $reserveQty);
        
        // Determine product status for seeding rules
        $isNew = !empty($product['is_new']);
        $restockedRecently = !empty($product['restocked_recently']);
        $highStock = ($availableQty >= $config['high_stock_threshold']);
        $shouldSeed = (
            ($config['seed_on_new'] && ($isNew || $restockedRecently)) ||
            ($config['seed_on_high_stock'] && $highStock)
        );
        
        // Exclusive/allowed outlets per product
        $allowedOutlets = null; // null means all
        if (!empty($product['allowed_outlet_ids']) && is_array($product['allowed_outlet_ids'])) {
            $allowedOutlets = array_flip($product['allowed_outlet_ids']);
        } elseif (!empty($product['exclusive_outlet_ids']) && is_array($product['exclusive_outlet_ids'])) {
            $allowedOutlets = array_flip($product['exclusive_outlet_ids']);
        }
        if ($allowedOutlets !== null) {
            // Filter outlets based on allowed list
            $outlets = array_values(array_filter($outlets, function($o) use ($allowedOutlets) {
                return isset($allowedOutlets[$o['outlet_id']]);
            }));
        }
        
        // If not enough for normal min_lines but seeding is requested, continue to seed
        if ($allocatableQty < $config['min_lines'] && !$shouldSeed) {
            $pid = $product['product_id'] ?? 'unknown';
            $this->decisionTrace[$pid]['product'] = [
                'warehouse_stock' => (int)$availableQty,
                'reserve_kept' => (int)$reserveQty,
                'allocatable' => (int)$allocatableQty,
            ];
            return [];
        }
        
        // Pre-compute per-outlet tier and existing per-run lines for line-cap checks
        $perOutletAlreadyLines = [];
        foreach ($outlets as $o) {
            $oid = $o['outlet_id'];
            $perOutletAlreadyLines[$oid] = (int)($this->outletPerf[$oid]['allocation_lines'] ?? 0);
        }

        // Helper: resolve tier for an outlet
        $resolveTier = function(array $o): string {
            $tier = (string)($o['tier'] ?? '');
            if ($tier !== '') return strtoupper($tier);
            return 'B';
        };

        // Helper: quantity cap per store for this product considering tier caps and max_per_product
        $capLimitGlobal = ($config['max_per_product'] === 0) ? PHP_INT_MAX : (int)$config['max_per_product'];
        $tierCaps = (array)($config['tier_caps'] ?? ['A'=>6,'B'=>4,'C'=>3]);
        $tierSeed = (array)($config['seed_qty_by_tier'] ?? ['A'=>3,'B'=>1,'C'=>1]);

        // Seeding pass: ensure baseline visibility when new/restocked/high-stock or velocity very low
        $seededAlloc = []; // outlet_id => qty
        if ($shouldSeed || $this->isVelocityBelowThreshold($product, $outlets, $config)) {
            $pid = $product['product_id'] ?? 'unknown';
            foreach ($outlets as $o) {
                if ($allocatableQty <= 0) break;
                $oid = $o['outlet_id'];
                // Respect per-store line limit
                if ($perOutletAlreadyLines[$oid] >= $config['max_lines_per_store']) { continue; }
                $tier = $resolveTier($o);
                $seedBase = ($shouldSeed && ($isNew || $restockedRecently))
                    ? (int)($tierSeed[$tier] ?? $config['seed_qty_default'])
                    : (int)max($config['seed_qty_default'], $config['seed_qty_high_stock']);
                if ($seedBase <= 0) { continue; }
                $capTier = (int)($tierCaps[$tier] ?? 3);
                $maxSend = min($seedBase, $capTier, $capLimitGlobal, $allocatableQty);
                if ($maxSend <= 0) { continue; }

                $seededAlloc[$oid] = $maxSend;
                $allocatableQty -= $maxSend;
                $perOutletAlreadyLines[$oid] += 1;
                // Trace as allocated (seed flag)
                $this->traceOutlet($pid, $this->outletMetaToOutlet($oid), [
                    'reason' => 'allocated',
                    'dest_stock' => (int)($product['outlet_stocks'][$oid] ?? 0),
                    'demand' => 0.0,
                    'proportion' => 0.0,
                    'allocated_qty' => (int)$maxSend,
                    'seed' => true,
                ]);
                if (isset($this->outletPerf[$oid])) {
                    $this->outletPerf[$oid]['allocation_lines']++;
                    $this->outletPerf[$oid]['allocation_qty'] += (int)$maxSend;
                }
            }
        }

        // Calculate demand for each outlet
        $demands = [];
        $totalDemand = 0;
        $initialDemand = [];
        
        foreach ($outlets as $outlet) {
            $tD0 = microtime(true);
            $demand = $this->calculateOutletDemand($product, $outlet, $config);
            $tD1 = microtime(true);
            $oid = $outlet['outlet_id'];
            if (isset($this->outletPerf[$oid])) {
                $this->outletPerf[$oid]['demand_calls']++;
                $this->outletPerf[$oid]['demand_ms'] += round(($tD1 - $tD0) * 1000, 4);
            }
            $initialDemand[$oid] = $demand;
            if ($demand > 0) {
                $demands[$oid] = $demand;
                $totalDemand += $demand;
            } else {
                // Trace zero-demand reason
                $pid = $product['product_id'] ?? 'unknown';
                $this->traceOutlet($pid, $outlet, [
                    'reason' => 'zero_demand',
                    'dest_stock' => (int)($product['outlet_stocks'][$oid] ?? 0),
                    'demand' => 0,
                ]);
            }
        }
        
        if ($totalDemand === 0) {
            $pid = $product['product_id'] ?? 'unknown';
            $this->decisionTrace[$pid]['product'] = [
                'warehouse_stock' => (int)$availableQty,
                'reserve_kept' => (int)$reserveQty,
                'allocatable' => (int)$allocatableQty,
            ];
            // If we seeded already, return those allocations
            if (!empty($seededAlloc)) {
                $rows = [];
                foreach ($seededAlloc as $oid => $qty) {
                    $rows[] = [
                        'outlet_id' => $oid,
                        'product_id' => $product['product_id'],
                        'quantity' => (int)$qty,
                        'demand_score' => 0.0,
                        'proportion' => 0.0,
                        'capped' => false
                    ];
                }
                return $rows;
            }
            return [];
        }
        
        // Optionally restrict to top-K demand outlets (skip this if we are in seed mode and configured to disable)
        if (empty($seededAlloc) || empty($config['disable_top_k_on_seed'])) {
            if (!empty($config['dynamic_top_k']) && $config['dynamic_top_k'] > 0) {
            // Heuristic: derive k from warehouse stock tiers
                $k = 0;
                if ($availableQty >= 160) { $k = 5; }
                elseif ($availableQty >= 120) { $k = 4; }
                elseif ($availableQty >= 80) { $k = 3; }
                if ($k > 0) { $config['top_k_outlets'] = $k; }
            }
            if (!empty($config['top_k_outlets']) && $config['top_k_outlets'] > 0 && count($demands) > $config['top_k_outlets']) {
                arsort($demands, SORT_NUMERIC);
                $kept = array_slice($demands, 0, $config['top_k_outlets'], true);
                $filtered = array_diff_key($demands, $kept);
                // Trace filtered-out outlets
                $pid = $product['product_id'] ?? 'unknown';
                foreach ($filtered as $foid => $fd) {
                    $this->traceOutlet($pid, $this->outletMetaToOutlet($foid), [
                        'reason' => 'filtered_top_k',
                        'dest_stock' => (int)($product['outlet_stocks'][$foid] ?? 0),
                        'demand' => (float)$initialDemand[$foid],
                    ]);
                }
                $demands = $kept;
                $totalDemand = array_sum($demands);
            }
        }

        // Proportional allocation with algorithm constraints
        $allocations = [];
        $totalAllocated = 0;
        $pid = $product['product_id'] ?? 'unknown';
        // Trace product-level stocks
        $this->decisionTrace[$pid]['product'] = [
            'warehouse_stock' => (int)$availableQty,
            'reserve_kept' => (int)$reserveQty,
            'allocatable' => (int)$allocatableQty,
        ];
        
        // If we seeded, prime allocations with those and compute remaining per-outlet headroom
        $alreadyAllocatedPerOutlet = $seededAlloc;
        if (!empty($seededAlloc)) {
            foreach ($seededAlloc as $oid => $q) {
                if ($q <= 0) continue;
                $allocations[] = [
                    'outlet_id' => $oid,
                    'product_id' => $product['product_id'],
                    'quantity' => (int)$q,
                    'demand_score' => 0.0,
                    'proportion' => 0.0,
                    'capped' => false,
                    'seed' => true,
                ];
                $totalAllocated += (int)$q;
            }
        }

        foreach ($demands as $outletId => $demand) {
            // Skip if per-store line limit reached already
            if ($perOutletAlreadyLines[$outletId] >= $config['max_lines_per_store']) { continue; }

            $proportionalQty = ($demand / max(1e-9, $totalDemand)) * $allocatableQty;
            // Respect per-tier caps and global per-product cap (0 means uncapped)
            $tier = $resolveTier($this->outletMetaToOutlet($outletId));
            $capTier = (int)($tierCaps[$tier] ?? 3);
            $already = (int)($alreadyAllocatedPerOutlet[$outletId] ?? 0);
            $headroomTier = max(0, $capTier - $already);
            $capLimit = min($capLimitGlobal, $headroomTier);
            if ($capLimit <= 0) {
                // No headroom for this outlet due to tier cap
                continue;
            }
            $allocatedQty = min((int)floor($proportionalQty), $capLimit, max(0, $allocatableQty - $totalAllocated));
            $isCapped = ($allocatedQty >= $capLimit) && ($proportionalQty > $capLimit);
            $candidateQty = $allocatedQty;
            // Trace near-miss and below min cap
            if (empty($seededAlloc) && $candidateQty < $config['min_cap_per_outlet']) {
                $this->traceOutlet($pid, $this->outletMetaToOutlet($outletId), [
                    'reason' => 'below_min_cap',
                    'near_miss' => $candidateQty > 0,
                    'dest_stock' => (int)($product['outlet_stocks'][$outletId] ?? 0),
                    'demand' => (float)$demand,
                    'proportion' => $totalDemand > 0 ? ($demand / $totalDemand) : 0.0,
                    'proportional_qty' => (float)$proportionalQty,
                    'candidate_qty' => (int)$candidateQty,
                    'required_min' => (int)$config['min_cap_per_outlet'],
                ]);
            }

            if ($allocatedQty >= $config['min_cap_per_outlet'] && $allocatedQty > 0) {
                $allocations[] = [
                    'outlet_id' => $outletId,
                    'product_id' => $product['product_id'],
                    'quantity' => $allocatedQty,
                    'demand_score' => $demand,
                    'proportion' => $demand / $totalDemand,
                    'capped' => $isCapped
                ];
                
                $totalAllocated += $allocatedQty;
                $alreadyAllocatedPerOutlet[$outletId] = ($alreadyAllocatedPerOutlet[$outletId] ?? 0) + $allocatedQty;
                if (isset($this->outletPerf[$outletId])) {
                    $this->outletPerf[$outletId]['allocation_lines']++;
                    $this->outletPerf[$outletId]['allocation_qty'] += $allocatedQty;
                }
                // Trace allocation
                $this->traceOutlet($pid, $this->outletMetaToOutlet($outletId), [
                    'reason' => 'allocated',
                    'dest_stock' => (int)($product['outlet_stocks'][$outletId] ?? 0),
                    'demand' => (float)$demand,
                    'proportion' => $totalDemand > 0 ? ($demand / $totalDemand) : 0.0,
                    'allocated_qty' => (int)$allocatedQty,
                    'capped' => $isCapped,
                ]);
            }
        }
        
        return $allocations;
    }

    /**
     * Helper: add an outlet decision trace entry
     */
    private function traceOutlet(string $productId, array $outlet, array $data): void
    {
        if (!isset($this->decisionTrace[$productId])) {
            $this->decisionTrace[$productId] = [ 'product' => [], 'outlets' => [] ];
        }
        $oid = $outlet['outlet_id'] ?? (string)($outlet['id'] ?? '');
        if ($oid === '') { return; }
        $code = $outlet['store_code'] ?? null;
        $name = $outlet['name'] ?? $oid;
        $entry = array_merge([
            'outlet_id' => $oid,
            'store_code' => $code,
            'name' => $name,
        ], $data);
        $this->decisionTrace[$productId]['outlets'][] = $entry;
    }

    /**
     * Helper: construct minimal outlet array from meta if available
     */
    private function outletMetaToOutlet(string $outletId): array
    {
        $meta = $this->outletMeta[$outletId] ?? [];
        return [
            'outlet_id' => $outletId,
            'name' => $meta['name'] ?? $outletId,
            'store_code' => $meta['store_code'] ?? null,
            'tier' => $meta['tier'] ?? null,
        ];
    }
    
    private function calculateOutletDemand(array $product, array $outlet, array $config): float
    {
        // Sophisticated demand calculation using multiple factors
        $currentStock = $product['outlet_stocks'][$outlet['outlet_id']] ?? 0;
        $salesVelocity = $product['sales_velocity'][$outlet['outlet_id']] ?? 0;
        // Prefer per-product turnover; fall back to outlet's overall turnover if provided; else default
        $turnoverRate = $product['turnover_rate'][$outlet['outlet_id']] ?? (
            $outlet['turnover_rate'] ?? $config['default_turnover_pct']
        );
        
        // Base demand calculation
        $targetStock = $salesVelocity * ($turnoverRate / 100);
        $shortage = max(0, $targetStock - $currentStock);
        
        // Weight the demand based on configuration
        if ($config['weight_method'] === 'softmax') {
            return $this->applySoftmaxWeight($shortage, $config);
        } else {
            return $this->applyPowerWeight($shortage, $config);
        }
    }
    
    private function applyPowerWeight(float $shortage, array $config): float
    {
        if ($shortage <= 0) return 0;
        
        $gamma = $config['weight_gamma'];
        $epsilon = $config['weight_epsilon'];
        
        return pow($shortage + $epsilon, $gamma);
    }
    
    private function applySoftmaxWeight(float $shortage, array $config): float
    {
        if ($shortage <= 0) return 0;
        
        $tau = $config['softmax_tau'];
        return exp($shortage / $tau);
    }
    
    private function generateSummary(array $allocations): array
    {
        $totalProducts = count($allocations);
        $totalLines = 0;
        $totalQuantity = 0;
        $outletCount = [];
        
        foreach ($allocations as $productAllocations) {
            foreach ($productAllocations as $allocation) {
                $totalLines++;
                $totalQuantity += $allocation['quantity'];
                $outletCount[$allocation['outlet_id']] = true;
            }
        }
        
        return [
            'total_products' => $totalProducts,
            'total_lines' => $totalLines,
            'total_quantity' => $totalQuantity,
            'outlets_affected' => count($outletCount)
        ];
    }

    /**
     * Assign tiers to outlets based on config mapping. Supports keys by outlet_id or store_code.
     * Defaults to 'B' when not specified.
     * @param array $outlets
     * @param array $config
     * @return array
     */
    private function assignOutletTiers(array $outlets, array $config): array
    {
        $map = (array)($config['outlet_tiers'] ?? []);
        if (empty($map)) { return $outlets; }
        foreach ($outlets as &$o) {
            $tier = null;
            $oid = (string)($o['outlet_id'] ?? '');
            $code = (string)($o['store_code'] ?? '');
            if ($oid !== '' && isset($map[$oid])) {
                $tier = (string)$map[$oid];
            } elseif ($code !== '' && isset($map[$code])) {
                $tier = (string)$map[$code];
            }
            if ($tier !== null && $tier !== '') {
                $o['tier'] = strtoupper($tier);
            }
        }
        unset($o);
        return $outlets;
    }

    /**
     * Determines if product velocities are effectively below threshold for seeding override.
     */
    private function isVelocityBelowThreshold(array $product, array $outlets, array $config): bool
    {
        $th = (float)($config['velocity_seed_threshold'] ?? 0.1);
        if ($th <= 0) return false;
        $count = 0; $low = 0;
        foreach ($outlets as $o) {
            $oid = $o['outlet_id'];
            $v = (float)($product['sales_velocity'][$oid] ?? 0.0);
            $count++;
            if ($v <= $th) { $low++; }
        }
        if ($count === 0) return false;
        // If majority are below threshold, treat as low velocity
        return ($low / $count) >= 0.5;
    }
    
    private function generateRunId(): string
    {
        return 'run_' . date('Ymd_His') . '_' . mt_rand(100000, 999999);
    }

    private function emitProgress(array $payload): void
    {
        if (!$this->streamPath) return;
        $payload['run_id'] = $this->runId;
        $line = json_encode($payload, JSON_UNESCAPED_SLASHES);
        @file_put_contents($this->streamPath, $line . "\n", FILE_APPEND | LOCK_EX);
        @chmod($this->streamPath, 0644);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    private function markProfile(string $label, float $startTs): void
    {
        $this->profile[$label] = round((microtime(true) - $startTs) * 1000, 2);
    }
    
    private function saveTransferSnapshot(string $runId, array $result): void
    {
        $snapshotPath = STORAGE_PATH . '/snapshots/' . $runId . '.json';
        
        // Ensure snapshot directory exists
        $snapshotDir = dirname($snapshotPath);
        if (!is_dir($snapshotDir)) {
            mkdir($snapshotDir, 0755, true);
        }
        
        file_put_contents(
            $snapshotPath,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        
        @chmod($snapshotPath, 0644);
    }

    private function formatOutletPerf(): array
    {
        $rows = [];
        foreach ($this->outletPerf as $oid => $p) {
            $rows[] = [
                'outlet_id' => $oid,
                'store_code' => $p['store_code'],
                'name' => $p['name'],
                'demand_calls' => $p['demand_calls'],
                'demand_ms' => round((float)$p['demand_ms'], 3),
                'demand_avg_ms' => $p['demand_calls'] > 0 ? round($p['demand_ms'] / $p['demand_calls'], 3) : 0.0,
                'allocation_lines' => $p['allocation_lines'],
                'allocation_qty' => $p['allocation_qty'],
            ];
        }
        usort($rows, function ($a, $b) {
            // Sort by demand_ms desc, then allocation_qty desc
            if ($a['demand_ms'] === $b['demand_ms']) {
                return $b['allocation_qty'] <=> $a['allocation_qty'];
            }
            return $b['demand_ms'] <=> $a['demand_ms'];
        });
        return $rows;
    }
    
    private function queryOutlets(): array
    {
        // Fallback database query implementation using DatabaseManager
        // Detect likely outlet table and normalize fields
        try {
            \VapeshedTransfer\Database\DatabaseManager::getInstance();
        } catch (\Throwable $e) {
            // Database not configured; no fallback available
            return [];
        }

        $db = \VapeshedTransfer\Database\DatabaseManager::getInstance();

        $table = null;
        if ($db->tableExists('vend_outlets')) {
            $table = 'vend_outlets';
        } elseif ($db->tableExists('outlets')) {
            $table = 'outlets';
        } else {
            return [];
        }

        // Inspect available columns
        $cols = array_map(static function($c){ return strtolower((string)$c['COLUMN_NAME']); }, $db->getTableColumns($table));
        $has = function(string $c) use ($cols): bool { return in_array(strtolower($c), $cols, true); };

        // Resolve optional column expressions
        $exprStore = 'NULL AS store_code';
        foreach (['code','store_code','short_code','mnemonic','abbreviation'] as $c) {
            if ($has($c)) { $exprStore = "o.`{$c}` AS store_code"; break; }
        }
        if ($exprStore === 'NULL AS store_code') {
            // try name-based code fallback: first 3 letters uppercased
            $exprStore = "UPPER(LEFT(o.name,3)) AS store_code";
        }

        $exprIsWh = '0 AS is_warehouse';
        if ($has('is_warehouse')) {
            $exprIsWh = 'o.is_warehouse AS is_warehouse';
        } elseif ($has('type')) {
            $exprIsWh = "CASE WHEN o.type IN ('warehouse','hub') THEN 1 ELSE 0 END AS is_warehouse";
        }

        $exprTor = 'NULL AS turnover_rate';
        foreach (['turn_over_rate','turnOverRate','turnover_rate','tor'] as $c) {
            if ($has($c)) { $exprTor = "o.`{$c}` AS turnover_rate"; break; }
        }

        $sql = "SELECT 
                    o.id AS outlet_id,
                    o.name AS name,
                    {$exprStore},
                    {$exprIsWh},
                    {$exprTor}
                FROM {$table} o";

        $rows = [];
        try {
            $res = $db->query($sql);
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = [
                        'outlet_id' => (string)$r['outlet_id'],
                        'name' => (string)($r['name'] ?? ''),
                        'store_code' => $r['store_code'] !== null ? (string)$r['store_code'] : null,
                        'is_warehouse' => (int)($r['is_warehouse'] ?? 0),
                        'turnover_rate' => isset($r['turnover_rate']) && $r['turnover_rate'] !== null ? (float)$r['turnover_rate'] : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Silently fail to allow legacy functions to handle data
            return [];
        }

        return $rows;
    }
    
    private function queryTransferProducts(array $config, array $requestedProducts): array
    {
        // Fallback product query implementation using DatabaseManager and Vend tables
        try {
            \VapeshedTransfer\Database\DatabaseManager::getInstance();
        } catch (\Throwable $e) {
            return [];
        }

        $db = \VapeshedTransfer\Database\DatabaseManager::getInstance();

        // Ensure required tables exist
        $hasInv = $db->tableExists('vend_inventory');
        $hasProd = $db->tableExists('vend_products');
        if (!$hasInv || !$hasProd) {
            return [];
        }

        // Prefer config warehouse_id, fallback to constant WAREHOUSE_ID
        $warehouseId = (string)($config['warehouse_id'] ?? (defined('WAREHOUSE_ID') ? WAREHOUSE_ID : ''));
        if ($warehouseId === '') {
            return [];
        }

        // Optional whitelist provided via requestedProducts argument
        $whitelist = [];
        if (!empty($requestedProducts)) {
            foreach ($requestedProducts as $p) {
                if (!empty($p['product_id'])) { $whitelist[(string)$p['product_id']] = true; }
            }
        }

        // Detect inventory quantity column dynamically
        $invCols = array_map(static function($c){ return strtolower((string)$c['COLUMN_NAME']); }, $db->getTableColumns('vend_inventory'));
        $hasInvCol = function(string $c) use ($invCols): bool { return in_array(strtolower($c), $invCols, true); };
        $qtyCol = 'inventory_level';
        foreach (['inventory_level','current_amount','on_hand','onhand','quantity','qty'] as $c) {
            if ($hasInvCol($c)) { $qtyCol = $c; break; }
        }

        // Build candidate products at warehouse with optional brand/supplier gating
        // Effective limit: take the smallest positive among provided limits
        $limits = [];
        foreach (['hub_products_limit','limit_products','max_products_cap'] as $k) {
            $v = isset($config[$k]) ? (int)$config[$k] : 0;
            if ($v > 0) { $limits[] = $v; }
        }
        $effectiveLimit = 0;
        if (!empty($limits)) { $effectiveLimit = min($limits); }
        $rows = $this->fetchHubProducts(
            $db,
            $warehouseId,
            (int)$effectiveLimit,
            (string)$config['brand_supplier_mode'],
            (string)$config['supplier_flag'],
            (string)$config['exclude_name_like'],
            $qtyCol
        );
        $wh = [];
        foreach ($rows as $r) {
            $pid = (string)$r['product_id'];
            if (!empty($whitelist) && !isset($whitelist[$pid])) { continue; }
            $wh[$pid] = (int)$r['wh_qty'];
        }

        if (empty($wh)) {
            return [];
        }

        // Load per-outlet inventory for all destination outlets
        $outlets = $this->getEligibleOutlets($config);
        if (empty($outlets)) { return []; }
        $destIds = array_values(array_map(static fn($o) => $o['outlet_id'], $outlets));

        // Chunk destination outlets to avoid parameter limits
        $products = [];
        $prodIds = array_keys($wh);
        $prodPlaceholders = implode(',', array_fill(0, count($prodIds), '?'));

        // Map product -> outlet stocks
        $stocks = [];
        $types = str_repeat('s', count($destIds) + count($prodIds));
        $params = array_merge($destIds, $prodIds);
        $phOutlets = implode(',', array_fill(0, count($destIds), '?'));
        $sqlStocks = "SELECT vi.product_id, vi.outlet_id, vi.`{$qtyCol}` AS inventory_level
                      FROM vend_inventory vi
                      WHERE vi.outlet_id IN ($phOutlets)
                        AND vi.product_id IN ($prodPlaceholders)";
        try {
            $res = $db->query($sqlStocks, $params, $types);
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $pid = (string)$r['product_id'];
                    $oid = (string)$r['outlet_id'];
                    $stocks[$pid][$oid] = (int)$r['inventory_level'];
                }
            }
        } catch (\Throwable $e) {
            // If bulk fails (e.g., max placeholders), fall back to per-outlet queries
            foreach ($destIds as $oid) {
                try {
                    $res2 = $db->query(
                        "SELECT vi.product_id, vi.`{$qtyCol}` AS inventory_level FROM vend_inventory vi WHERE vi.outlet_id = ? AND vi.product_id IN ($prodPlaceholders)",
                        array_merge([$oid], $prodIds),
                        's' . str_repeat('s', count($prodIds))
                    );
                    if ($res2) {
                        while ($r2 = $res2->fetch_assoc()) {
                            $pid = (string)$r2['product_id'];
                            $stocks[$pid][$oid] = (int)$r2['inventory_level'];
                        }
                    }
                } catch (\Throwable $e2) {
                    // give up on stocks for this outlet
                }
            }
        }

        // Attempt to compute sales velocity if sales tables exist; otherwise default to 0
        $salesVelocity = [];
        $hasSalesLines = $db->tableExists('vend_sales_products') || $db->tableExists('vend_sale_products') || $db->tableExists('vend_sales_line_items');
        $hasSales = $db->tableExists('vend_sales');
        if ($hasSales && $hasSalesLines) {
            $lineTable = $db->tableExists('vend_sales_products') ? 'vend_sales_products' : ($db->tableExists('vend_sale_products') ? 'vend_sale_products' : 'vend_sales_line_items');
            // Detect column names dynamically
            $salesCols = array_map(static function($c){ return strtolower((string)$c['COLUMN_NAME']); }, $db->getTableColumns('vend_sales'));
            $lineCols  = array_map(static function($c){ return strtolower((string)$c['COLUMN_NAME']); }, $db->getTableColumns($lineTable));
            $hasS = function(array $cols, string $name): bool { return in_array(strtolower($name), $cols, true); };

            $tsCol = null;
            foreach (['processed_at','processedat','created_at','createdat','register_sale_time','sold_at','date','sale_date','closed_at'] as $c) {
                if ($hasS($salesCols, $c)) { $tsCol = $c; break; }
            }
            $outletCol = $hasS($salesCols, 'outlet_id') ? 'outlet_id' : null;
            $statusCol = $hasS($salesCols, 'status') ? 'status' : ($hasS($salesCols, 'state') ? 'state' : null);
            $qtyCol = $hasS($lineCols, 'quantity') ? 'quantity' : ($hasS($lineCols, 'qty') ? 'qty' : null);
            $saleIdCol = $hasS($lineCols, 'sale_id') ? 'sale_id' : ($hasS($lineCols, 'register_sale_id') ? 'register_sale_id' : null);
            $prodIdCol = $hasS($lineCols, 'product_id') ? 'product_id' : null;

            if ($tsCol && $outletCol && $qtyCol && $saleIdCol && $prodIdCol) {
                $since = date('Y-m-d H:i:s', time() - 30 * 86400);
                $conds = ["s.`{$tsCol}` >= ?", "sp.`{$prodIdCol}` IN ($prodPlaceholders)"];
                $paramsVel = [$since];
                $typesVel = 's';
                // Optional status filter
                if ($statusCol) {
                    $conds[] = "COALESCE(s.`{$statusCol}`,'') NOT IN ('VOID','CANCELLED','voided')";
                }
                $paramsVel = array_merge($paramsVel, $prodIds);
                $typesVel .= str_repeat('s', count($prodIds));
                $where = implode(' AND ', $conds);
                $sqlVel = "SELECT s.`{$outletCol}` AS outlet_id, sp.`{$prodIdCol}` AS product_id, SUM(sp.`{$qtyCol}`) AS qty
                           FROM vend_sales s
                           INNER JOIN {$lineTable} sp ON sp.`{$saleIdCol}` = s.id
                           WHERE {$where}
                           GROUP BY s.`{$outletCol}`, sp.`{$prodIdCol}`";
                try {
                    $res = $db->query($sqlVel, $paramsVel, $typesVel);
                    if ($res) {
                        while ($r = $res->fetch_assoc()) {
                            $pid = (string)$r['product_id'];
                            $oid = (string)$r['outlet_id'];
                            $salesVelocity[$pid][$oid] = (float)$r['qty'];
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore velocity if schema differs
                }
            }
        }

        // Assemble products
        foreach ($wh as $pid => $whQty) {
            $products[] = [
                'product_id' => $pid,
                'warehouse_stock' => (int)$whQty,
                'outlet_stocks' => $stocks[$pid] ?? [],
                'sales_velocity' => $salesVelocity[$pid] ?? [],
                'turnover_rate' => [],
            ];
        }

        return $products;
    }

    /**
     * Hub products with flexible brand/supplier gating
     * AND = keep only if brand allows AND supplier allows (recommended)
     *
     * @param \VapeshedTransfer\Database\DatabaseManager $db
     * @param string $hubId
     * @param int $limit
     * @param string $filterMode none|brand|supplier|and|or
     * @param string $supplierFlag transferring|ordering|transfersales
     * @param string $excludeNameLike pattern for NOT LIKE (optional)
     * @param string $qtyCol inventory qty column name
     * @return array<int, array{product_id:string, wh_qty:int, product_name:?string}>
     */
    private function fetchHubProducts(
        \VapeshedTransfer\Database\DatabaseManager $db,
        string $hubId,
        int $limit,
        string $filterMode,
        string $supplierFlag,
        string $excludeNameLike,
        string $qtyCol = 'inventory_level'
    ): array {
        $sfCol = match ($supplierFlag) {
            'ordering'      => 'vs.automatic_ordering',
            'transfersales' => 'vs.automatic_transferring_based_on_sales_data',
            default         => 'vs.automatic_transferring',
        };

        $sql = "SELECT vi.product_id,
                       GREATEST(0, vi.`{$qtyCol}`) AS wh_qty,
                       vp.name AS product_name
                FROM vend_inventory vi
                INNER JOIN vend_products  vp ON vi.product_id = vp.id
                LEFT  JOIN vend_brands    vb ON vp.brand_id    = vb.id
                LEFT  JOIN vend_suppliers vs ON vp.supplier_id = vs.id
                WHERE vi.outlet_id = ?
                  AND vi.`{$qtyCol}` > 0
                  AND COALESCE(vp.has_inventory,1) = 1
                  AND COALESCE(vp.is_active,1) = 1
                  AND COALESCE(vp.active,1) = 1
                  AND (COALESCE(vp.is_deleted,0) = 0)
                  AND (vp.deleted_at IS NULL OR vp.deleted_at = '0000-00-00 00:00:00')";

        switch ($filterMode) {
            case 'brand':
                $sql .= " AND COALESCE(vb.enable_store_transfers,0)=1";
                break;
            case 'supplier':
                $sql .= " AND COALESCE($sfCol,0)=1";
                break;
            case 'and':
                $sql .= " AND (COALESCE(vb.enable_store_transfers,0)=1 AND COALESCE($sfCol,0)=1)";
                break;
            case 'or':
                $sql .= " AND (COALESCE(vb.enable_store_transfers,0)=1 OR COALESCE($sfCol,0)=1)";
                break;
            case 'none':
            default:
                // no gating
                break;
        }

        $types = 's';
        $bind  = [$hubId];
        if ($excludeNameLike !== '') {
            $sql   .= " AND vp.name NOT LIKE ?";
            $types .= 's';
            $bind[] = $excludeNameLike;
        }
        if ($limit > 0) { $sql .= " LIMIT " . (int)$limit; }

        try {
            $res = $db->query($sql, $bind, $types);
        } catch (\Throwable $e) {
            return [];
        }
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = [
                    'product_id' => (string)$r['product_id'],
                    'wh_qty' => (int)$r['wh_qty'],
                    'product_name' => $r['product_name'] ?? null,
                ];
            }
        }
        return $rows;
    }
}