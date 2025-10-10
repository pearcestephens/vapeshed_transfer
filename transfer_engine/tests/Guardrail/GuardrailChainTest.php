<?php
declare(strict_types=1);

namespace Tests\Guardrail;

use PHPUnit\Framework\TestCase;
use Unified\Guardrail\GuardrailChain;
use Unified\Guardrail\GuardrailInterface;
use Unified\Guardrail\Result;
use Unified\Support\Logger;

/**
 * Tests for GuardrailChain deterministic execution
 * 
 * @package Tests\Guardrail
 * @since Phase 2.2
 */
final class GuardrailChainTest extends TestCase
{
    private GuardrailChain $chain;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(Logger::class);
        $this->chain = new GuardrailChain($this->logger);
    }

    /**
     * @test
     */
    public function executesEmptyChainSuccessfully(): void
    {
        $result = $this->chain->evaluate([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('final_status', $result);
        $this->assertArrayHasKey('blocked_by', $result);
        $this->assertArrayHasKey('total_duration_ms', $result);
        $this->assertArrayHasKey('score_hint', $result);

        $this->assertCount(0, $result['results']);
        $this->assertSame('PASS', $result['final_status']);
        $this->assertNull($result['blocked_by']);
        $this->assertSame(1.0, $result['score_hint']);
    }

    /**
     * @test
     */
    public function executesRailsInAlphabeticalOrderByCode(): void
    {
        // Register rails in non-alphabetical order
        $this->chain->register($this->createMockRail('GR_ZEBRA', 'PASS'));
        $this->chain->register($this->createMockRail('GR_ALPHA', 'PASS'));
        $this->chain->register($this->createMockRail('GR_MIDDLE', 'PASS'));

        $result = $this->chain->evaluate([]);

        $this->assertCount(3, $result['results']);

        // Verify alphabetical execution order
        $this->assertSame('GR_ALPHA', $result['results'][0]->code);
        $this->assertSame('GR_MIDDLE', $result['results'][1]->code);
        $this->assertSame('GR_ZEBRA', $result['results'][2]->code);
    }

    /**
     * @test
     */
    public function deterministicOrderingWithIdenticalSignals(): void
    {
        $ctx = ['price' => 100];

        // Execute chain 5 times with identical context
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $chain = new GuardrailChain($this->logger);
            $chain->register($this->createMockRail('GR_C', 'PASS'));
            $chain->register($this->createMockRail('GR_A', 'PASS'));
            $chain->register($this->createMockRail('GR_B', 'PASS'));

            $results[] = $chain->evaluate($ctx);
        }

        // All results should have identical order
        for ($i = 1; $i < 5; $i++) {
            $this->assertSame(
                array_map(fn($r) => $r->code, $results[0]['results']),
                array_map(fn($r) => $r->code, $results[$i]['results']),
                'Guardrail execution order must be deterministic'
            );
        }
    }

    /**
     * @test
     */
    public function allPassingRailsResultsInPassStatus(): void
    {
        $this->chain->register($this->createMockRail('GR_1', 'PASS'));
        $this->chain->register($this->createMockRail('GR_2', 'PASS'));
        $this->chain->register($this->createMockRail('GR_3', 'PASS'));

        $result = $this->chain->evaluate([]);

        $this->assertSame('PASS', $result['final_status']);
        $this->assertNull($result['blocked_by']);
        $this->assertGreaterThanOrEqual(0.8, $result['score_hint']);
    }

    /**
     * @test
     */
    public function singleWarningResultsInWarnStatus(): void
    {
        $this->chain->register($this->createMockRail('GR_1', 'PASS'));
        $this->chain->register($this->createMockRail('GR_2', 'WARN'));
        $this->chain->register($this->createMockRail('GR_3', 'PASS'));

        $result = $this->chain->evaluate([]);

        $this->assertSame('WARN', $result['final_status']);
        $this->assertNull($result['blocked_by']);
        $this->assertGreaterThan(0.0, $result['score_hint']);
        $this->assertLessThan(0.8, $result['score_hint']);
    }

    /**
     * @test
     */
    public function multipleWarningsLowerScoreHint(): void
    {
        $this->chain->register($this->createMockRail('GR_1', 'WARN'));
        $this->chain->register($this->createMockRail('GR_2', 'WARN'));
        $this->chain->register($this->createMockRail('GR_3', 'WARN'));

        $result = $this->chain->evaluate([]);

        $this->assertSame('WARN', $result['final_status']);
        $this->assertLessThan(0.5, $result['score_hint']);
    }

    /**
     * @test
     */
    public function blockingRailShortCircuitsExecution(): void
    {
        $this->chain->register($this->createMockRail('GR_A', 'PASS'));
        $this->chain->register($this->createMockRail('GR_B', 'BLOCK'));
        $this->chain->register($this->createMockRail('GR_C', 'PASS')); // Should not execute

        $result = $this->chain->evaluate([]);

        $this->assertSame('BLOCK', $result['final_status']);
        $this->assertSame('GR_B', $result['blocked_by']);
        $this->assertSame(0.0, $result['score_hint']);
        $this->assertCount(2, $result['results']); // Only A and B executed
    }

    /**
     * @test
     */
    public function preservesPriorResultsBeforeBlock(): void
    {
        $this->chain->register($this->createMockRail('GR_FIRST', 'PASS'));
        $this->chain->register($this->createMockRail('GR_SECOND', 'WARN'));
        $this->chain->register($this->createMockRail('GR_THIRD', 'BLOCK'));
        $this->chain->register($this->createMockRail('GR_FOURTH', 'PASS')); // Not executed

        $result = $this->chain->evaluate([]);

        $this->assertCount(3, $result['results']);
        $this->assertTrue($result['results'][0]->isPassing());
        $this->assertTrue($result['results'][1]->isWarning());
        $this->assertTrue($result['results'][2]->isBlocking());
    }

    /**
     * @test
     */
    public function firstBlockWins(): void
    {
        $this->chain->register($this->createMockRail('GR_A', 'PASS'));
        $this->chain->register($this->createMockRail('GR_B', 'BLOCK'));
        $this->chain->register($this->createMockRail('GR_C', 'BLOCK')); // Not executed

        $result = $this->chain->evaluate([]);

        $this->assertSame('GR_B', $result['blocked_by']);
        $this->assertCount(2, $result['results']);
    }

    /**
     * @test
     */
    public function collectsTimingForEachRail(): void
    {
        $this->chain->register($this->createMockRail('GR_1', 'PASS'));
        $this->chain->register($this->createMockRail('GR_2', 'PASS'));
        $this->chain->register($this->createMockRail('GR_3', 'PASS'));

        $result = $this->chain->evaluate([]);

        foreach ($result['results'] as $railResult) {
            $this->assertInstanceOf(Result::class, $railResult);
            $this->assertGreaterThanOrEqual(0.0, $railResult->duration_ms);
        }

        $this->assertGreaterThanOrEqual(0.0, $result['total_duration_ms']);
    }

    /**
     * @test
     */
    public function totalDurationIncludesAllExecutedRails(): void
    {
        $this->chain->register($this->createMockRail('GR_1', 'PASS'));
        $this->chain->register($this->createMockRail('GR_2', 'PASS'));

        $result = $this->chain->evaluate([]);

        $sumOfRailDurations = array_sum(array_map(fn($r) => $r->duration_ms, $result['results']));

        $this->assertGreaterThanOrEqual($sumOfRailDurations, $result['total_duration_ms']);
    }

    /**
     * @test
     */
    public function resultsAreResultObjects(): void
    {
        $this->chain->register($this->createMockRail('GR_1', 'PASS'));
        $this->chain->register($this->createMockRail('GR_2', 'WARN'));
        $this->chain->register($this->createMockRail('GR_3', 'BLOCK'));

        $result = $this->chain->evaluate([]);

        foreach ($result['results'] as $railResult) {
            $this->assertInstanceOf(Result::class, $railResult);
            $this->assertIsString($railResult->code);
            $this->assertIsString($railResult->status);
            $this->assertIsString($railResult->severity);
            $this->assertIsString($railResult->reason);
            $this->assertIsArray($railResult->meta);
            $this->assertIsFloat($railResult->duration_ms);
        }
    }

    /**
     * @test
     */
    public function resultsAreSerializable(): void
    {
        $this->chain->register($this->createMockRail('GR_1', 'PASS'));
        $this->chain->register($this->createMockRail('GR_2', 'WARN'));

        $result = $this->chain->evaluate([]);

        // Convert results to arrays for JSON serialization
        $serializable = [
            'results' => array_map(fn($r) => $r->toArray(), $result['results']),
            'final_status' => $result['final_status'],
            'blocked_by' => $result['blocked_by'],
            'total_duration_ms' => $result['total_duration_ms'],
            'score_hint' => $result['score_hint'],
        ];

        $json = json_encode($serializable);
        $this->assertNotFalse($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('results', $decoded);
        $this->assertCount(2, $decoded['results']);
    }

    /**
     * @test
     */
    public function logsChainResult(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'guardrail.chain.result',
                $this->callback(function ($context) {
                    return isset($context['final_status'])
                        && isset($context['blocked_by'])
                        && isset($context['total_rails'])
                        && isset($context['executed_rails'])
                        && isset($context['score_hint']);
                })
            );

        $this->chain->register($this->createMockRail('GR_1', 'PASS'));
        $this->chain->evaluate([]);
    }

    /**
     * @test
     */
    public function logsWarningWhenBlocked(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'guardrail.chain.blocked',
                $this->callback(function ($context) {
                    return $context['code'] === 'GR_BLOCKER'
                        && isset($context['reason'])
                        && isset($context['message']);
                })
            );

        $this->chain->register($this->createMockRail('GR_BLOCKER', 'BLOCK'));
        $this->chain->evaluate([]);
    }

    /**
     * @test
     */
    public function handlesRailsWithComplexMeta(): void
    {
        $rail = $this->createMock(GuardrailInterface::class);
        $rail->method('evaluate')->willReturn([
            'code' => 'GR_COMPLEX',
            'status' => 'PASS',
            'message' => 'Complex meta test',
            'meta' => [
                'nested' => ['key' => 'value'],
                'array' => [1, 2, 3],
                'number' => 42.5,
                'bool' => true,
            ],
        ]);

        $this->chain->register($rail);
        $result = $this->chain->evaluate([]);

        $this->assertIsArray($result['results'][0]->meta);
        $this->assertArrayHasKey('nested', $result['results'][0]->meta);
    }

    // ==================== Helper Methods ====================

    /**
     * Create a mock guardrail that returns a specific status.
     */
    private function createMockRail(string $code, string $status): GuardrailInterface
    {
        $rail = $this->createMock(GuardrailInterface::class);
        $rail->method('evaluate')->willReturn([
            'code' => $code,
            'status' => $status,
            'message' => ucfirst(strtolower($status)) . ' message for ' . $code,
            'meta' => ['code' => $code, 'status' => $status],
        ]);

        // Make the rail expose its code via reflection (for sorting)
        return new class($code, $status, $rail) implements GuardrailInterface {
            protected string $code;

            public function __construct(
                string $code,
                private string $status,
                private GuardrailInterface $mock
            ) {
                $this->code = $code;
            }

            public function evaluate(array $ctx, Logger $logger): array
            {
                return $this->mock->evaluate($ctx, $logger);
            }
        };
    }
}
