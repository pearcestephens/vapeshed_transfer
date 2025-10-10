<?php
declare(strict_types=1);

namespace Tests\Guardrail;

use PHPUnit\Framework\TestCase;
use Unified\Guardrail\Severity;

/**
 * Tests for Severity enum-like class
 * 
 * @package Tests\Guardrail
 * @since Phase 2.2
 */
final class SeverityTest extends TestCase
{
    /**
     * @test
     */
    public function hasCorrectConstantValues(): void
    {
        $this->assertSame('INFO', Severity::INFO);
        $this->assertSame('WARN', Severity::WARN);
        $this->assertSame('BLOCK', Severity::BLOCK);
    }

    /**
     * @test
     */
    public function isValidRecognizesAllowedValues(): void
    {
        $this->assertTrue(Severity::isValid('INFO'));
        $this->assertTrue(Severity::isValid('WARN'));
        $this->assertTrue(Severity::isValid('BLOCK'));
    }

    /**
     * @test
     */
    public function isValidRejectsInvalidValues(): void
    {
        $this->assertFalse(Severity::isValid('PASS'));
        $this->assertFalse(Severity::isValid('ERROR'));
        $this->assertFalse(Severity::isValid('info')); // Case-sensitive
        $this->assertFalse(Severity::isValid(''));
    }

    /**
     * @test
     */
    public function allReturnsAllSeverityLevels(): void
    {
        $expected = ['INFO', 'WARN', 'BLOCK'];
        $this->assertSame($expected, Severity::all());
    }

    /**
     * @test
     */
    public function fromStatusMapsCorrectly(): void
    {
        $this->assertSame('INFO', Severity::fromStatus('PASS'));
        $this->assertSame('WARN', Severity::fromStatus('WARN'));
        $this->assertSame('BLOCK', Severity::fromStatus('BLOCK'));
    }

    /**
     * @test
     */
    public function fromStatusDefaultsToInfoForUnknownStatus(): void
    {
        $this->assertSame('INFO', Severity::fromStatus('UNKNOWN'));
        $this->assertSame('INFO', Severity::fromStatus(''));
    }

    /**
     * @test
     */
    public function weightReturnsCorrectValues(): void
    {
        $this->assertSame(10, Severity::weight('INFO'));
        $this->assertSame(50, Severity::weight('WARN'));
        $this->assertSame(100, Severity::weight('BLOCK'));
    }

    /**
     * @test
     */
    public function weightReturnsZeroForUnknownSeverity(): void
    {
        $this->assertSame(0, Severity::weight('UNKNOWN'));
        $this->assertSame(0, Severity::weight(''));
    }

    /**
     * @test
     */
    public function weightIsMonotonicWithSeverity(): void
    {
        $infoWeight = Severity::weight('INFO');
        $warnWeight = Severity::weight('WARN');
        $blockWeight = Severity::weight('BLOCK');

        $this->assertLessThan($warnWeight, $infoWeight);
        $this->assertLessThan($blockWeight, $warnWeight);
    }
}
