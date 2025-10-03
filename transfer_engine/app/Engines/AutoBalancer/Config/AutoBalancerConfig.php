<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Config;

final class AutoBalancerConfig
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function velocityDays(): int { return $this->config['windows']['velocity_days']; }
    public function trendDays(): int { return $this->config['windows']['trend_days']; }

    public function lowStockDays(): int { return $this->config['thresholds']['low_stock_days']; }
    public function overstockDays(): int { return $this->config['thresholds']['overstock_days']; }
    public function targetDaysMin(): int { return $this->config['thresholds']['target_days_min']; }
    public function sourceKeepDays(): int { return $this->config['thresholds']['source_keep_days']; }
    public function highDemandMultiplier(): float { return (float)$this->config['thresholds']['high_demand_multiplier']; }
    public function minTransferValue(): float { return (float)$this->config['thresholds']['min_transfer_value']; }

    public function urgentCap(): int { return $this->config['plan_caps']['urgent']; }
    public function highCap(): int { return $this->config['plan_caps']['high']; }
    public function normalCap(): int { return $this->config['plan_caps']['normal']; }

    public function inventoryBatchLimit(): int { return $this->config['performance']['inventory_batch_limit']; }

    public function dryRunDefault(): bool { return $this->config['features']['dry_run_default']; }
    public function metricsEnabled(): bool { return $this->config['features']['enable_metrics']; }
    public function insightsEnabled(): bool { return $this->config['features']['enable_insights'] ?? false; }
    public function insightsDir(): string { return $this->config['paths']['insights_dir'] ?? (__DIR__.'/../../../../storage/runs'); }
}
