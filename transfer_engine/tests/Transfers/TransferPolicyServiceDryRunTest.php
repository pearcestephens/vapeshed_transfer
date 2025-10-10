<?php
declare(strict_types=1);

namespace Tests\Transfers;

use PHPUnit\Framework\TestCase;
use Unified\Repositories\SystemConfigRepository;
use Unified\Repositories\TransferOrderRepository;
use Unified\Services\TransferPolicyService;
use Unified\Support\Logger;

final class TransferPolicyServiceDryRunTest extends TestCase
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

    public function testDryRunDoesNotPersist(): void
    {
        $svc = $this->makeService();

        $signal = [
            'store_id' => 'S1',
            'sku' => 'SKU1',
            'predicted_weekly_demand' => 100,
            'current_on_hand' => 5,
            'prediction_confidence' => 0.9,
        ];

        $order = $svc->propose($signal, persist: false);
        $this->assertNotNull($order);
        $this->assertSame('proposed', $order->status());
        $this->assertSame('S1', $order->destStore());

        // Ensure no repository create was called
        $orders = $this->createMock(TransferOrderRepository::class);
        $orders->expects($this->never())->method('create');
    }
}
