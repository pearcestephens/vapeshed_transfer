<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Config\AutoBalancerConfig;
use App\Engines\AutoBalancer\Entity\Store;
use App\Engines\AutoBalancer\Entity\InventoryItem;

final class Orchestrator
{
    public function __construct(
        private readonly AutoBalancerConfig $config,
        private readonly StoreInventoryProvider $inventoryProvider,
        private readonly SalesVelocityProvider $velocityProvider,
        private readonly SituationAnalyzer $analyzer,
        private readonly OpportunityService $opportunityService,
        private readonly ScoringService $scoringService,
        private readonly PlannerService $planner,
        private readonly ExecutionService $executor,
        private readonly ?InsightsService $insightsService = null
    ) {}

    public function run(bool $dryRun = true): array
    {
        $stores = $this->inventoryProvider->getActiveStores();
        $storeProductMap = []; $storeItems = [];
        foreach ($stores as $store) {
            $items = $this->inventoryProvider->getInventoryForStore($store);
            $storeProductMap[$store->id] = array_map(fn(InventoryItem $i)=>$i->productId, $items);
            $storeItems[$store->id] = $items;
        }
        $velocities = $this->velocityProvider->getVelocities($stores, $storeProductMap);

        // Analyze situations
        foreach ($stores as $store) {
            $storeItems[$store->id] = $this->analyzer->analyze($store, $storeItems[$store->id], $velocities);
        }

        // Build product matrix
        $matrix = $this->opportunityService->buildProductMatrix($stores, $storeItems);

        // Generate opportunities
        $opportunities = [];
        $minValue = $this->config->minTransferValue();
        foreach ($matrix as $pid => $data) {
            $needy = []; $surplus = [];
            foreach ($data['stores'] as $sid => $st) {
                if ($st['is_low'] || ($st['is_high_demand'] && $st['days_of_stock'] < $this->config->targetDaysMin())) { $needy[] = $st + ['outlet_id'=>$sid,'product_id'=>$pid]; }
                if ($st['is_overstock']) { $surplus[] = $st + ['outlet_id'=>$sid,'product_id'=>$pid]; }
            }
            if (!$needy || !$surplus) { continue; }
            foreach ($needy as $n) {
                foreach ($surplus as $s) {
                    if ($n['outlet_id'] === $s['outlet_id']) continue;
                    $score = $this->scoringService->score($n, $s, $data['supply_price']);
                    if ($score['recommended_qty'] <= 0 || $score['transfer_value'] < $minValue) continue;
                    $opportunities[] = [
                        'product_id' => $pid,
                        'from_outlet' => $s['outlet_id'],
                        'to_outlet' => $n['outlet_id'],
                        'recommended_qty' => $score['recommended_qty'],
                        'transfer_value' => $score['transfer_value'],
                        'urgency_score' => $score['urgency_score'],
                        'reason' => $score['reason'],
                        'from_days' => $s['days_of_stock'],
                        'to_days' => $n['days_of_stock'],
                    ];
                }
            }
        }

        $plan = $this->planner->buildPlan($opportunities);
        $exec = $this->executor->execute($plan, $dryRun);

        $insights = null;
        if ($this->config->insightsEnabled() && $this->insightsService) {
            $insights = $this->insightsService->generate($stores, $storeItems);
            // Export to file for downstream personalization pipeline
            $dir = $this->config->insightsDir();
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $path = $dir . '/auto_balancer_insights_latest.json';
            @file_put_contents($path, json_encode($insights, JSON_PRETTY_PRINT));
        }
        return [
            'stores' => count($stores),
            'opportunities' => count($opportunities),
            'plan' => [
                'urgent' => count($plan['urgent'] ?? []),
                'high' => count($plan['high'] ?? []),
                'normal' => count($plan['normal'] ?? [])
            ],
            'execution' => $exec,
            'insights' => $insights ? ['high_demand'=>count($insights['high_demand']), 'low_stock'=>count($insights['low_stock']), 'overstock'=>count($insights['overstock'])] : null
        ];
    }
}
