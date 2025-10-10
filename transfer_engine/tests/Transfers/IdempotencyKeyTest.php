<?php
declare(strict_types=1);

namespace Tests\Transfers;

use PHPUnit\Framework\TestCase;
use Unified\Services\Idempotency\IdempotencyKey;

final class IdempotencyKeyTest extends TestCase
{
    public function testStableValueForSameInputs(): void
    {
        $a = IdempotencyKey::fromSignal('S1', 'SKU123', 10, 14, 7, 'HUB_MAIN')->value();
        $b = IdempotencyKey::fromSignal('S1', 'SKU123', 10, 14, 7, 'HUB_MAIN')->value();
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
    }

    public function testDifferentInputsProduceDifferentKeys(): void
    {
        $a = IdempotencyKey::fromSignal('S1', 'SKU123', 10, 14, 7, 'HUB_MAIN')->value();
        $b = IdempotencyKey::fromSignal('S1', 'SKU123', 11, 14, 7, 'HUB_MAIN')->value();
        $this->assertNotSame($a, $b);
    }

    public function testPurposeImpactsKey(): void
    {
        $a = IdempotencyKey::fromSignal('S1', 'SKU123', 10, 14, 7, 'HUB_MAIN', 'transfer.create')->value();
        $b = IdempotencyKey::fromSignal('S1', 'SKU123', 10, 14, 7, 'HUB_MAIN', 'transfer.preview')->value();
        $this->assertNotSame($a, $b);
    }
}
