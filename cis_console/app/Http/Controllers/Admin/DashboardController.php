<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Controllers\Admin;

final class DashboardController
{
    private array $app;
    private array $security;

    public function __construct(array $app, array $security)
    {
        $this->app = $app;
        $this->security = $security;
    }

    public function index(): void
    {
        $views = $this->app['paths']['views'] ?? (__DIR__ . '/../../../../resources/views');
        include $views . '/layout/header.php';
        include $views . '/layout/sidebar.php';
        include $views . '/pages/dashboard.php';
        include $views . '/layout/footer.php';
    }

    public function monitoring(): void
    {
        $views = $this->app['paths']['views'] ?? (__DIR__ . '/../../../../resources/views');
        include $views . '/layout/header.php';
        include $views . '/layout/sidebar.php';
        include $views . '/pages/monitoring.php';
        include $views . '/layout/footer.php';
    }
}
