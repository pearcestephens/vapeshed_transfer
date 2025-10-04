<?php
/**
 * Transfer Module Class
 * Self-contained UI module using read models and unified services
 */
declare(strict_types=1);

use Unified\Support\UiKernel;
use Unified\Persistence\ReadModels\TransferReadModel;

class TransferModule
{
    private array $config;
    private TransferReadModel $readModel;
    private $logger;

    public function __construct()
    {
        UiKernel::init();
        $this->logger = UiKernel::logger('ui.transfer');
        $this->config = config('modules.transfer') ?? [
            'name' => 'Stock Transfer Engine',
            'icon' => 'exchange-alt',
            'color' => '#6366f1',
            'description' => 'Balance stock across outlets to target DSR and reduce stockouts'
        ];
        $this->readModel = new TransferReadModel($this->logger);
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
            'moduleCSS' => 'transfer',
            'moduleJS' => 'transfer',
            'stats' => $stats,
            'recentTransfers' => $recent,
            'breadcrumbs' => [ 'Transfer Engine' => null ]
        ];

        return Container::get('view')
            ->setLayout('module')
            ->with($data)
            ->render('modules/transfer/main');
    }

    private function actions(): string
    {
        return '<button class="btn btn-outline-primary" id="refreshData"><i class="fas fa-sync-alt"></i> Refresh</button>'
            . '<button class="btn btn-primary ml-2" id="calculateTransfers"><i class="fas fa-calculator"></i> Calculate</button>'
            . '<button class="btn btn-success ml-2" id="executeQueue"><i class="fas fa-play"></i> Execute</button>';
    }
}

