<?php
declare(strict_types=1);

namespace Unified\Neuro;

use Unified\Repositories\SystemConfigRepository;
use Unified\Support\Logger;

/**
 * NeuroCore
 * Provides directive vectors for autonomous agents.
 */
final class NeuroCore
{
    public function __construct(
        private readonly SystemConfigRepository $config,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array<string,float>
     */
    public function directives(): array
    {
        $vector = $this->config->get('neuro.directives', []);
        if (!is_array($vector)) {
            $vector = [];
        }

        $defaults = [
            'exploration_weight' => 0.55,
            'demand_focus_weight' => 0.65,
            'transfer_bias' => 0.45,
        ];

        $directives = $vector + $defaults;

        if (!array_key_exists('transfer_bias', $directives)) {
            $directives['transfer_bias'] = $defaults['transfer_bias'];
            $this->logger->warning('neuro.directives.transfer_bias_missing', []);
        }

        return array_map(static function ($value) {
            return is_numeric($value) ? (float)$value : 0.0;
        }, $directives);
    }
}
