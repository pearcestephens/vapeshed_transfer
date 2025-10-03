<?php
declare(strict_types=1);

namespace App\Engines\Pricing\Config;

final class PricingConfig
{
    public function __construct(private array $cfg) {}
    public function historyDays(): int { return $this->cfg['windows']['history_days']; }
    public function minActiveDays(): int { return $this->cfg['windows']['minimum_active_days']; }
    public function fallbackElasticity(): float { return (float)$this->cfg['defaults']['elasticity']; }
    public function softCapPct(): float { return (float)$this->cfg['defaults']['retention_soft_cap_pct']; }
    public function hardCapPct(): float { return (float)$this->cfg['defaults']['retention_hard_cap_pct']; }
    public function penaltyFactor(): float { return (float)$this->cfg['defaults']['retention_penalty_factor']; }
    public function retentionFloor(): float { return (float)$this->cfg['defaults']['retention_floor']; }
    public function maxMarkupMultiple(): float { return (float)$this->cfg['defaults']['max_markup_multiple']; }
    public function minMarginPct(): float { return (float)$this->cfg['defaults']['min_margin_pct']; }
    public function priceIncrement(): float { return (float)$this->cfg['defaults']['round_to_increment']; }
    public function candidateSteps(): int { return (int)$this->cfg['defaults']['candidate_steps']; }
}
