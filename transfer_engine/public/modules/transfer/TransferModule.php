<?php
/**
 * Transfer Module Class
 * 
 * Self-contained transfer module with all logic encapsulated
 */
declare(strict_types=1);

class TransferModule
{
    private array $config;
    private PDO $db;
    
    public function __construct()
    {
        $this->config = config('modules.transfer');
        $this->db = db();
    }
    
    /**
     * Render the module view
     */
    public function render(): string
    {
        $data = [
            'pageTitle' => $this->config['name'],
            'moduleIcon' => $this->config['icon'],
            'moduleColor' => $this->config['color'],
            'moduleDescription' => $this->config['description'],
            'moduleActions' => $this->renderActions(),
            'moduleCSS' => 'transfer',
            'moduleJS' => 'transfer',
            'stats' => $this->getStats(),
            'recentTransfers' => $this->getRecentTransfers(),
            'breadcrumbs' => [
                'Transfer Engine' => null
            ]
        ];
        
        return Container::get('view')
            ->setLayout('module')
            ->with($data)
            ->render('modules/transfer/main');
    }
    
    /**
     * Get transfer statistics
     */
    declare(strict_types=1);

    use Unified\Persistence\ReadModels\TransferReadModel;
    use Unified\Support\UiKernel;

    class TransferModule
    {
        private array $config;
        private TransferReadModel $readModel;
        private $logger;

        public function __construct()
        {
            $this->config = config('modules.transfer');

            UiKernel::init();
            $this->logger = UiKernel::logger('ui.transfer');
            $this->readModel = new TransferReadModel($this->logger);
        }

        /** Render the module view */
        public function render(): string
        {
            $stats = $this->readModel->sevenDayStats();
            $recent = $this->readModel->recent();

            $data = [
                'pageTitle' => $this->config['name'],
                'moduleIcon' => $this->config['icon'],
                'moduleColor' => $this->config['color'],
                'moduleDescription' => $this->config['description'],
                'moduleActions' => $this->renderActions(),
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

        /** Render module actions */
        private function renderActions(): string
        {
            return '<button class="btn btn-outline-primary" id="refreshData">'
                . '<i class="fas fa-sync-alt"></i> Refresh</button>'
                . '<button class="btn btn-primary ml-2" id="calculateTransfers">'
                . '<i class="fas fa-calculator"></i> Calculate</button>'
                . '<button class="btn btn-success ml-2" id="executeQueue">'
                . '<i class="fas fa-play"></i> Execute</button>';
        }
    }
    
