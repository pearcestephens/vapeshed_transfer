<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Config\AutoBalancerConfig;

final class PlannerService
{
    public function __construct(private readonly AutoBalancerConfig $config) {}

    public function buildPlan(array $opportunities): array
    {
        if (empty($opportunities)) { return []; }
        usort($opportunities, fn($a,$b)=> $b['urgency_score'] <=> $a['urgency_score']);
        $urgent = []; $high=[]; $normal=[];
        foreach ($opportunities as $opp) {
            if ($opp['urgency_score'] >= 80) { $urgent[] = $opp; }
            elseif ($opp['urgency_score'] >= 50) { $high[] = $opp; }
            else { $normal[] = $opp; }
        }
        return [
            'urgent' => array_slice($urgent,0,$this->config->urgentCap()),
            'high' => array_slice($high,0,$this->config->highCap()),
            'normal' => array_slice($normal,0,$this->config->normalCap()),
        ];
    }
}
