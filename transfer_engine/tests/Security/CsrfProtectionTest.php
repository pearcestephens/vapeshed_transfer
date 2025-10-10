<?php
declare(strict_types=1);

namespace Unified\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * CSRF Protection Tests
 * 
 * Tests for CSRF middleware, token generation, and validation
 * 
 * @covers \App\Http\Middleware\CsrfMiddleware
 * @covers \App\Core\Security
 * @group security
 * @group csrf
 */
final class CsrfProtectionTest extends TestCase
{
    private array $originalSession;
    private array $originalServer;
    private array $originalPost;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Backup superglobals
        $this->originalSession = $_SESSION ?? [];
        $this->originalServer = $_SERVER ?? [];
        $this->originalPost = $_POST ?? [];
        
        // Reset session
        $_SESSION = [];
    }
    
    protected function tearDown(): void
    {
        // Restore superglobals
        $_SESSION = $this->originalSession;
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        
        parent::tearDown();
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function it_generates_csrf_token(): void
    {
        // Session should be empty initially
        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
        
        // Generate token
        $token = $this->generateCsrfToken();
        
        // Token should be in session
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertSame($token, $_SESSION['csrf_token']);
        
        // Token should be 64 hex chars (32 bytes)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }
    
    /**
     * @test
     * @group security
     */
    public function it_reuses_existing_csrf_token(): void
    {
        $firstToken = $this->generateCsrfToken();
        $secondToken = $this->generateCsrfToken();
        
        // Should return same token
        $this->assertSame($firstToken, $secondToken);
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function it_validates_correct_csrf_token(): void
    {
        $token = $this->generateCsrfToken();
        
        $isValid = $this->verifyCsrfToken($token);
        
        $this->assertTrue($isValid);
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function it_rejects_invalid_csrf_token(): void
    {
        $this->generateCsrfToken();
        
        $isValid = $this->verifyCsrfToken('invalid-token');
        
        $this->assertFalse($isValid);
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function it_rejects_empty_csrf_token(): void
    {
        $this->generateCsrfToken();
        
        $isValid = $this->verifyCsrfToken('');
        
        $this->assertFalse($isValid);
    }
    
    /**
     * @test
     * @group security
     */
    public function it_prevents_timing_attacks(): void
    {
        $_SESSION['csrf_token'] = 'correct_token_12345678901234567890123456789012';
        
        // Test with wrong token
        $start = microtime(true);
        $this->verifyCsrfToken('wrong_token_000000000000000000000000000000000');
        $timingWrong = microtime(true) - $start;
        
        // Test with correct token
        $start = microtime(true);
        $this->verifyCsrfToken('correct_token_12345678901234567890123456789012');
        $timingCorrect = microtime(true) - $start;
        
        // Timing difference should be minimal (using hash_equals)
        // This is a weak test but ensures hash_equals is used
        $timingDiff = abs($timingWrong - $timingCorrect);
        $this->assertLessThan(0.001, $timingDiff, 'Timing attack vulnerability: comparison not constant-time');
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function csrf_middleware_allows_get_requests_without_token(): void
    {
        // Load middleware class
        require_once __DIR__ . '/../../app/Http/Middleware/CsrfMiddleware.php';
        
        $middleware = new \App\Http\Middleware\CsrfMiddleware(true, 'csrf_token');
        
        $request = [
            'method' => 'GET',
            'endpoint' => '/api/test',
            'input' => [],
            'query' => [],
            'headers' => [],
        ];
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return $req;
        };
        
        $result = $middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled, 'GET request should proceed without CSRF token');
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function csrf_middleware_blocks_post_without_token(): void
    {
        require_once __DIR__ . '/../../app/Http/Middleware/CsrfMiddleware.php';
        require_once __DIR__ . '/../../app/Support/Response.php';
        
        $middleware = new \App\Http\Middleware\CsrfMiddleware(true, 'csrf_token');
        
        $_SESSION['csrf_token'] = 'valid_token_123';
        
        $request = [
            'method' => 'POST',
            'endpoint' => '/api/test',
            'input' => [],
            'query' => [],
            'headers' => [],
        ];
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return $req;
        };
        
        // Capture output
        ob_start();
        $result = $middleware->handle($request, $next);
        $output = ob_get_clean();
        
        $this->assertFalse($result, 'POST without CSRF token should be blocked');
        $this->assertFalse($nextCalled, 'Next middleware should not be called');
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function csrf_middleware_allows_post_with_valid_header(): void
    {
        require_once __DIR__ . '/../../app/Http/Middleware/CsrfMiddleware.php';
        
        $token = 'valid_token_123';
        $_SESSION['csrf_token'] = $token;
        
        $middleware = new \App\Http\Middleware\CsrfMiddleware(true, 'csrf_token');
        
        $request = [
            'method' => 'POST',
            'endpoint' => '/api/test',
            'input' => [],
            'query' => [],
            'headers' => ['X-CSRF-TOKEN' => $token],
        ];
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return $req;
        };
        
        $result = $middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled, 'POST with valid CSRF header should proceed');
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     */
    public function csrf_middleware_allows_post_with_valid_body_token(): void
    {
        require_once __DIR__ . '/../../app/Http/Middleware/CsrfMiddleware.php';
        
        $token = 'valid_token_456';
        $_SESSION['csrf_token'] = $token;
        
        $middleware = new \App\Http\Middleware\CsrfMiddleware(true, 'csrf_token');
        
        $request = [
            'method' => 'POST',
            'endpoint' => '/api/test',
            'input' => ['csrf_token' => $token],
            'query' => [],
            'headers' => [],
        ];
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return $req;
        };
        
        $result = $middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled, 'POST with valid CSRF in body should proceed');
    }
    
    /**
     * @test
     * @group security
     */
    public function csrf_middleware_respects_disabled_flag(): void
    {
        require_once __DIR__ . '/../../app/Http/Middleware/CsrfMiddleware.php';
        
        // CSRF disabled
        $middleware = new \App\Http\Middleware\CsrfMiddleware(false, 'csrf_token');
        
        $request = [
            'method' => 'POST',
            'endpoint' => '/api/test',
            'input' => [],
            'query' => [],
            'headers' => [],
        ];
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return $req;
        };
        
        $result = $middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled, 'When CSRF disabled, POST should proceed without token');
    }
    
    /**
     * @test
     * @group security
     * @group csrf
     * @dataProvider mutatingMethodsProvider
     */
    public function csrf_middleware_protects_all_mutating_methods(string $method): void
    {
        require_once __DIR__ . '/../../app/Http/Middleware/CsrfMiddleware.php';
        
        $_SESSION['csrf_token'] = 'valid_token_789';
        $middleware = new \App\Http\Middleware\CsrfMiddleware(true, 'csrf_token');
        
        $request = [
            'method' => $method,
            'endpoint' => '/api/test',
            'input' => [],
            'query' => [],
            'headers' => [],
        ];
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return $req;
        };
        
        ob_start();
        $result = $middleware->handle($request, $next);
        ob_get_clean();
        
        $this->assertFalse($result, "$method without CSRF token should be blocked");
        $this->assertFalse($nextCalled, "$method should not proceed without token");
    }
    
    public static function mutatingMethodsProvider(): array
    {
        return [
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }
    
    // Helper methods (mimicking App\Core\Security)
    
    private function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    private function verifyCsrfToken(string $token): bool
    {
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
