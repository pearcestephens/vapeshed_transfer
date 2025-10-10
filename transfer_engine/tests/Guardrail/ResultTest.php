<?php
declare(strict_types=1);

namespace Tests\Guardrail;

use PHPUnit\Framework\TestCase;
use Unified\Guardrail\Result;
use Unified\Guardrail\Severity;

/**
 * Tests for Result value object
 * 
 * @package Tests\Guardrail
 * @since Phase 2.2
 */
final class ResultTest extends TestCase
{
    /**
     * @test
     */
    public function constructsWithValidData(): void
    {
        $result = new Result(
            code: 'GR_TEST',
            status: 'PASS',
            severity: 'INFO',
            reason: 'test_passed',
            message: 'Test passed successfully',
            meta: ['foo' => 'bar'],
            duration_ms: 12.34
        );

        $this->assertSame('GR_TEST', $result->code);
        $this->assertSame('PASS', $result->status);
        $this->assertSame('INFO', $result->severity);
        $this->assertSame('test_passed', $result->reason);
        $this->assertSame('Test passed successfully', $result->message);
        $this->assertSame(['foo' => 'bar'], $result->meta);
        $this->assertSame(12.34, $result->duration_ms);
    }

    /**
     * @test
     */
    public function rejectsInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status: INVALID');

        new Result(
            code: 'GR_TEST',
            status: 'INVALID',
            severity: 'INFO',
            reason: 'test',
            message: '',
            meta: [],
            duration_ms: 0.0
        );
    }

    /**
     * @test
     */
    public function rejectsInvalidSeverity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid severity: INVALID');

        new Result(
            code: 'GR_TEST',
            status: 'PASS',
            severity: 'INVALID',
            reason: 'test',
            message: '',
            meta: [],
            duration_ms: 0.0
        );
    }

    /**
     * @test
     */
    public function rejectsNegativeDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration must be non-negative: -5');

        new Result(
            code: 'GR_TEST',
            status: 'PASS',
            severity: 'INFO',
            reason: 'test',
            message: '',
            meta: [],
            duration_ms: -5.0
        );
    }

    /**
     * @test
     */
    public function rejectsMetaWithResources(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Meta cannot contain resources');

        $resource = fopen('php://memory', 'r');

        new Result(
            code: 'GR_TEST',
            status: 'PASS',
            severity: 'INFO',
            reason: 'test',
            message: '',
            meta: ['resource' => $resource],
            duration_ms: 0.0
        );

        fclose($resource);
    }

    /**
     * @test
     */
    public function rejectsMetaWithClosures(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Meta cannot contain closures');

        new Result(
            code: 'GR_TEST',
            status: 'PASS',
            severity: 'INFO',
            reason: 'test',
            message: '',
            meta: ['closure' => fn() => 'test'],
            duration_ms: 0.0
        );
    }

    /**
     * @test
     */
    public function createsFromLegacyArrayWithAllFields(): void
    {
        $legacy = [
            'code' => 'GR_LEGACY',
            'status' => 'WARN',
            'severity' => 'WARN',
            'reason' => 'legacy_warn',
            'message' => 'Legacy warning',
            'meta' => ['key' => 'value'],
        ];

        $result = Result::fromLegacy($legacy, 5.67);

        $this->assertSame('GR_LEGACY', $result->code);
        $this->assertSame('WARN', $result->status);
        $this->assertSame('WARN', $result->severity);
        $this->assertSame('legacy_warn', $result->reason);
        $this->assertSame('Legacy warning', $result->message);
        $this->assertSame(['key' => 'value'], $result->meta);
        $this->assertSame(5.67, $result->duration_ms);
    }

    /**
     * @test
     */
    public function createsFromLegacyArrayWithMinimalFields(): void
    {
        $legacy = [
            'code' => 'GR_MIN',
            'status' => 'PASS',
        ];

        $result = Result::fromLegacy($legacy);

        $this->assertSame('GR_MIN', $result->code);
        $this->assertSame('PASS', $result->status);
        $this->assertSame('INFO', $result->severity); // Derived from PASS
        $this->assertSame('passed', $result->reason); // Derived from empty message
        $this->assertSame('', $result->message);
        $this->assertSame([], $result->meta);
        $this->assertSame(0.0, $result->duration_ms);
    }

    /**
     * @test
     */
    public function derivesSeverityFromStatusWhenNotProvided(): void
    {
        $passResult = Result::fromLegacy(['code' => 'GR_1', 'status' => 'PASS']);
        $warnResult = Result::fromLegacy(['code' => 'GR_2', 'status' => 'WARN']);
        $blockResult = Result::fromLegacy(['code' => 'GR_3', 'status' => 'BLOCK']);

        $this->assertSame('INFO', $passResult->severity);
        $this->assertSame('WARN', $warnResult->severity);
        $this->assertSame('BLOCK', $blockResult->severity);
    }

    /**
     * @test
     */
    public function derivesReasonFromMessageWhenNotProvided(): void
    {
        $legacy = [
            'code' => 'GR_TEST',
            'status' => 'BLOCK',
            'message' => 'Below Cost Floor',
        ];

        $result = Result::fromLegacy($legacy);

        $this->assertSame('below_cost_floor', $result->reason);
    }

    /**
     * @test
     */
    public function convertsToArray(): void
    {
        $result = new Result(
            code: 'GR_ARRAY',
            status: 'PASS',
            severity: 'INFO',
            reason: 'test_reason',
            message: 'Test message',
            meta: ['foo' => 'bar'],
            duration_ms: 1.23
        );

        $expected = [
            'code' => 'GR_ARRAY',
            'status' => 'PASS',
            'severity' => 'INFO',
            'reason' => 'test_reason',
            'message' => 'Test message',
            'meta' => ['foo' => 'bar'],
            'duration_ms' => 1.23,
        ];

        $this->assertSame($expected, $result->toArray());
    }

    /**
     * @test
     */
    public function detectsPassingStatus(): void
    {
        $pass = new Result('GR_1', 'PASS', 'INFO', 'passed', '', [], 0.0);
        $warn = new Result('GR_2', 'WARN', 'WARN', 'warned', '', [], 0.0);
        $block = new Result('GR_3', 'BLOCK', 'BLOCK', 'blocked', '', [], 0.0);

        $this->assertTrue($pass->isPassing());
        $this->assertFalse($warn->isPassing());
        $this->assertFalse($block->isPassing());
    }

    /**
     * @test
     */
    public function detectsWarningStatus(): void
    {
        $pass = new Result('GR_1', 'PASS', 'INFO', 'passed', '', [], 0.0);
        $warn = new Result('GR_2', 'WARN', 'WARN', 'warned', '', [], 0.0);
        $block = new Result('GR_3', 'BLOCK', 'BLOCK', 'blocked', '', [], 0.0);

        $this->assertFalse($pass->isWarning());
        $this->assertTrue($warn->isWarning());
        $this->assertFalse($block->isWarning());
    }

    /**
     * @test
     */
    public function detectsBlockingStatus(): void
    {
        $pass = new Result('GR_1', 'PASS', 'INFO', 'passed', '', [], 0.0);
        $warn = new Result('GR_2', 'WARN', 'WARN', 'warned', '', [], 0.0);
        $block = new Result('GR_3', 'BLOCK', 'BLOCK', 'blocked', '', [], 0.0);

        $this->assertFalse($pass->isBlocking());
        $this->assertFalse($warn->isBlocking());
        $this->assertTrue($block->isBlocking());
    }

    /**
     * @test
     */
    public function returnsSeverityWeight(): void
    {
        $info = new Result('GR_1', 'PASS', 'INFO', 'passed', '', [], 0.0);
        $warn = new Result('GR_2', 'WARN', 'WARN', 'warned', '', [], 0.0);
        $block = new Result('GR_3', 'BLOCK', 'BLOCK', 'blocked', '', [], 0.0);

        $this->assertSame(10, $info->severityWeight());
        $this->assertSame(50, $warn->severityWeight());
        $this->assertSame(100, $block->severityWeight());
    }

    /**
     * @test
     */
    public function jsonSerializesCorrectly(): void
    {
        $result = new Result(
            code: 'GR_JSON',
            status: 'WARN',
            severity: 'WARN',
            reason: 'json_test',
            message: 'JSON test',
            meta: ['nested' => ['key' => 'value']],
            duration_ms: 9.87
        );

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertSame('GR_JSON', $decoded['code']);
        $this->assertSame('WARN', $decoded['status']);
        $this->assertSame('WARN', $decoded['severity']);
        $this->assertSame('json_test', $decoded['reason']);
        $this->assertSame('JSON test', $decoded['message']);
        $this->assertSame(['nested' => ['key' => 'value']], $decoded['meta']);
        $this->assertSame(9.87, $decoded['duration_ms']);
    }

    /**
     * @test
     */
    public function handlesEmptyMetaAndMessage(): void
    {
        $result = new Result(
            code: 'GR_EMPTY',
            status: 'PASS',
            severity: 'INFO',
            reason: 'passed',
            message: '',
            meta: [],
            duration_ms: 0.0
        );

        $this->assertSame('', $result->message);
        $this->assertSame([], $result->meta);
    }
}
