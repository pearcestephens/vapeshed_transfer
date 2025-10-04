<?php
/**
 * Pricing Module Class
 */
declare(strict_types=1);

use Unified\Support\UiKernel;
use Unified\Persistence\ReadModels\PricingReadModel;

class PricingModule
{
    private array $config;
    private PricingReadModel $readModel;
    private $logger;

    public function __construct()
    {
        UiKernel::init();
        $this->logger = UiKernel::logger('ui.pricing');
        $this->config = config('modules.pricing') ?? [
            'name' => 'Pricing Intelligence',
            'icon' => 'tags',
            'color' => '#ec4899',
            'description' => 'Competitive pricing with market intelligence'
        ];
        $this->readModel = new PricingReadModel($this->logger);
    }

    public function render(): string
    {
        $stats = $this->readModel->sevenDayStats();
        $recent = $this->readModel->recent();

        $data = [
            'pageTitle' => $this->config['name'],
            'moduleIcon' => $this->config['icon'],
            'moduleColor' => $this->config['color'],
            'moduleDescription' => $this->config['description'],
            'moduleActions' => $this->actions(),
            'moduleCSS' => 'pricing',
            'moduleJS' => 'pricing',
            'stats' => $stats,
            'recentProposals' => $recent,
            'breadcrumbs' => [ 'Pricing Intelligence' => null ]
        ];

        return Container::get('view')
            ->setLayout('module')
            ->with($data)
            ->render('modules/pricing/main');
    }

    private function actions(): string
    {
        return '<button class="btn btn-outline-primary" id="refreshPricing"><i class="fas fa-sync"></i> Refresh</button>'
            . '<button class="btn btn-primary ml-2" id="runPricing"><i class="fas fa-play"></i> Run</button>'
            . '<button class="btn btn-success ml-2" id="applyAutoPricing" disabled><i class="fas fa-magic"></i> Apply Auto</button>';
    }
}
