<?php
declare(strict_types=1);

namespace Tests\Transfers;

use PHPUnit\Framework\TestCase;
use Unified\Repositories\SystemConfigRepository;
use Unified\Repositories\TransferOrderRepository;
use Unified\Services\TransferPolicyService;
use Unified\Support\Logger;

final class TransferPolicyServiceMathTest extends TestCase
{
    private function makeService(): TransferPolicyService
    {
        $logger = new Logger('test');
        $orders = $this->createMock(TransferOrderRepository::class);
        $config = $this->createMock(SystemConfigRepository::class);

        $config->method('get')->willReturnMap([
            ['transfers.safety_stock_days', 7, 7],
            ['transfers.max_move_qty', 200, 200],
            ['transfers.auto_create', false, false],
            ['transfers.duplicate_window_hours', 6, 6],
            ['transfers.default_source_hub', 'HUB_MAIN', 'HUB_MAIN'],
        ]);

        return new TransferPolicyService($orders, $config, $logger);
    }

    public function testConfidenceClampedNoNanInf(): void
    {
        $svc = $this->makeService();

        $signal = [
            'store_id' => 'S1',
            'sku' => 'SKU1',
            'predicted_weekly_demand' => INF, // non-finite
            'current_on_hand' => -10,         // negative
            'lead_time_days' => 0,            // zero
            'forecast_horizon_days' => 0,     // zero
            'prediction_confidence' => 10.0,  // > 1.0
        ];

        // Persist is false so we can inspect object
        $order = $svc->propose($signal, persist: false);
        $this->assertNotNull($order);
        $conf = $order->confidence();
        $this->assertGreaterThanOrEqual(0.0, $conf);
        $this->assertLessThanOrEqual(1.0, $conf);
    }

    public function testPriorityMonotonicity(): void
    {
        $svc = $this->makeService();

        $base = [
            'store_id' => 'S1',
            'sku' => 'SKU1',
            'predicted_weekly_demand' => 70,
            'current_on_hand' => 0,
            'prediction_confidence' => 0.8,
        ];

        $low = $svc->propose($base + ['forecast_horizon_days' => 14], false);
        $mid = $svc->propose($base + ['predicted_weekly_demand' => 100, 'forecast_horizon_days' => 14], false);
        $high = $svc->propose($base + ['predicted_weekly_demand' => 400, 'forecast_horizon_days' => 14], false);

        $this->assertNotNull($low);
        $this->assertNotNull($mid);
        $this->assertNotNull($high);

        $priorities = [
            $low->priority(),
            $mid->priority(),
            $high->priority(),
        ];

        // priority order low < normal < high < critical in severity; ensure monotonic increase
        $map = ['low' => 0, 'normal' => 1, 'high' => 2, 'critical' => 3];
        $this->assertTrue($map[$priorities[0]] <= $map[$priorities[1]]);
        $this->assertTrue($map[$priorities[1]] <= $map[$priorities[2]]);
    }
}
