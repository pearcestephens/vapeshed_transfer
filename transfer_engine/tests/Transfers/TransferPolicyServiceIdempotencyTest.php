<?php
declare(strict_types=1);

namespace Tests\Transfers;

use PDO;
use PHPUnit\Framework\TestCase;
use Unified\Models\TransferOrder;
use Unified\Repositories\SystemConfigRepository;
use Unified\Repositories\TransferOrderRepository;
use Unified\Services\TransferPolicyService;
use Unified\Support\Logger;

final class TransferPolicyServiceIdempotencyTest extends TestCase
{
    private function makeServiceWithRealRepo(PDO $pdo): TransferPolicyService
    {
        $logger = new Logger('test');
        $orders = new TransferOrderRepository($pdo, $logger);

        $config = $this->createMock(SystemConfigRepository::class);
        $config->method('get')->willReturnMap([
            ['transfers.safety_stock_days', 7, 7],
            ['transfers.max_move_qty', 200, 200],
            ['transfers.auto_create', true, true],
            ['transfers.duplicate_window_hours', 0, 0],
            ['transfers.default_source_hub', 'HUB_MAIN', 'HUB_MAIN'],
        ]);

        return new TransferPolicyService($orders, $config, $logger);
    }

    private function setupSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS transfer_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transfer_id VARCHAR(64) NOT NULL,
            source_hub VARCHAR(64) NOT NULL,
            dest_store VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            priority VARCHAR(16) NOT NULL,
            reason JSON NULL,
            confidence DECIMAL(6,3) DEFAULT 0,
            requested_by VARCHAR(64) NULL,
            idempotency_key CHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_transfer_idem (idempotency_key)
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS transfer_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transfer_id VARCHAR(64) NOT NULL,
            sku VARCHAR(64) NOT NULL,
            qty INT NOT NULL,
            uom VARCHAR(16) NOT NULL,
            rationale JSON NULL
        );');

        $pdo->exec('CREATE TABLE IF NOT EXISTS transfer_order_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transfer_id VARCHAR(64) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            status_from VARCHAR(32) NULL,
            status_to VARCHAR(32) NULL,
            actor VARCHAR(64) NULL,
            note VARCHAR(255) NULL,
            payload JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );');
    }

    public function testIdempotentCreateReturnsSameOrder(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setupSchema($pdo);

        $svc = $this->makeServiceWithRealRepo($pdo);

        $signal = [
            'store_id' => 'S1',
            'sku' => 'SKU1',
            'predicted_weekly_demand' => 100,
            'current_on_hand' => 5,
            'prediction_confidence' => 0.95,
        ];

        $first = $svc->propose($signal, persist: true);
        $this->assertNotNull($first);

        $second = $svc->propose($signal, persist: true);
        $this->assertNotNull($second);

        $this->assertSame($first->transferId(), $second->transferId(), 'Idempotent create should return same transfer');

        // Ensure only one row exists
        $count = (int)$pdo->query('SELECT COUNT(*) FROM transfer_orders')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testDuplicateWindowSuppressionSkipsCreate(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setupSchema($pdo);

        $logger = new Logger('test');
        $orders = new TransferOrderRepository($pdo, $logger);

        $config = $this->createMock(SystemConfigRepository::class);
        $config->method('get')->willReturnMap([
            ['transfers.safety_stock_days', 7, 7],
            ['transfers.max_move_qty', 200, 200],
            ['transfers.auto_create', true, true],
            ['transfers.duplicate_window_hours', 6, 6],
            ['transfers.default_source_hub', 'HUB_MAIN', 'HUB_MAIN'],
        ]);

        $svc = new TransferPolicyService($orders, $config, $logger);

        $signal = [
            'store_id' => 'S1',
            'sku' => 'SKU1',
            'predicted_weekly_demand' => 100,
            'current_on_hand' => 5,
            'prediction_confidence' => 0.95,
        ];

        $first = $svc->propose($signal, persist: true);
        $this->assertNotNull($first);

        // Second within window should be skipped
        $second = $svc->propose($signal, persist: true);
        $this->assertNull($second);

        $count = (int)$pdo->query('SELECT COUNT(*) FROM transfer_orders')->fetchColumn();
        $this->assertSame(1, $count);
    }
}
