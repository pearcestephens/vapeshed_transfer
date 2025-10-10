<?php
declare(strict_types=1);

namespace Unified\Tests\Security;

use App\Core\Security;
use PHPUnit\Framework\TestCase;

/**
 * Security Headers Tests
 * 
 * Tests for CSP nonce generation, security header application,
 * and HSTS enforcement
 * 
 * @covers \App\Core\Security
 * @group security
 * @group csp
 * @group headers
 */
final class SecurityHeadersTest extends TestCase
{
    private array $originalSession;
    private array $originalServer;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Backup superglobals
        $this->originalSession = $_SESSION ?? [];
        $this->originalServer = $_SERVER ?? [];
        
        // Reset session
        $_SESSION = [];
    }
    
    protected function tearDown(): void
    {
        // Restore superglobals
        $_SESSION = $this->originalSession;
        $_SERVER = $this->originalServer;
        
        parent::tearDown();
    }
    
    /**
     * @test
     * @group security
     * @group csp
     */
    public function it_generates_csp_nonce(): void
    {
        // Start output buffering to capture headers
        if (!headers_sent()) {
            ob_start();
        }
        
        Security::applyHeaders();
        
        // Nonce should be in session
        $this->assertArrayHasKey('csp_nonce', $_SESSION);
        
        $nonce = $_SESSION['csp_nonce'];
        
        // Nonce should be base64 encoded (24 bytes = 32 chars)
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]{32}$/', $nonce);
        
        // Decoded nonce should be 24 bytes
        $decoded = base64_decode($nonce, true);
        $this->assertNotFalse($decoded, 'Nonce should be valid base64');
        $this->assertSame(24, strlen($decoded), 'Nonce should be 24 bytes');
        
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    
    /**
     * @test
     * @group security
     */
    public function it_provides_nonce_via_getter(): void
    {
        $_SESSION['csp_nonce'] = 'test_nonce_12345678901234567890';
        
        $nonce = Security::getCspNonce();
        
        $this->assertSame('test_nonce_12345678901234567890', $nonce);
    }
    
    /**
     * @test
     * @group security
     */
    public function it_returns_empty_string_when_nonce_missing(): void
    {
        $_SESSION = []; // No nonce
        
        $nonce = Security::getCspNonce();
        
        $this->assertSame('', $nonce);
    }
    
    /**
     * @test
     * @group headers
     * @group csp
     */
    public function it_applies_csp_with_nonce(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        // Find CSP header
        $cspHeader = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Security-Policy:') === 0) {
                $cspHeader = $header;
                break;
            }
        }
        
        $this->assertNotNull($cspHeader, 'CSP header should be present');
        
        // Verify nonce is in CSP
        $nonce = $_SESSION['csp_nonce'] ?? '';
        $this->assertNotEmpty($nonce);
        $this->assertStringContainsString("'nonce-{$nonce}'", $cspHeader);
        
        // Verify unsafe-inline is NOT in script-src
        $this->assertStringNotContainsString("'unsafe-inline'", $cspHeader);
    }
    
    /**
     * @test
     * @group headers
     */
    public function it_applies_hsts_on_https(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        $_SERVER['HTTPS'] = 'on';
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        // Find HSTS header
        $hstsHeader = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Strict-Transport-Security:') === 0) {
                $hstsHeader = $header;
                break;
            }
        }
        
        $this->assertNotNull($hstsHeader, 'HSTS header should be present on HTTPS');
        $this->assertStringContainsString('max-age=31536000', $hstsHeader);
        $this->assertStringContainsString('includeSubDomains', $hstsHeader);
        $this->assertStringContainsString('preload', $hstsHeader);
    }
    
    /**
     * @test
     * @group headers
     */
    public function it_does_not_apply_hsts_on_http(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SERVER_PORT'] = '80';
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        // HSTS should not be present
        $hstsFound = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Strict-Transport-Security:') === 0) {
                $hstsFound = true;
                break;
            }
        }
        
        $this->assertFalse($hstsFound, 'HSTS should not be sent on HTTP');
    }
    
    /**
     * @test
     * @group headers
     */
    public function it_applies_all_security_headers(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        $_SERVER['HTTPS'] = 'on';
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        $headersString = implode("\n", $headers);
        
        // Check all required headers are present
        $this->assertStringContainsString('X-Frame-Options: SAMEORIGIN', $headersString);
        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $headersString);
        $this->assertStringContainsString('Referrer-Policy: strict-origin-when-cross-origin', $headersString);
        $this->assertStringContainsString('Permissions-Policy:', $headersString);
        $this->assertStringContainsString('Content-Security-Policy:', $headersString);
    }
    
    /**
     * @test
     * @group csp
     */
    public function it_includes_cdn_in_csp(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        $cspHeader = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Security-Policy:') === 0) {
                $cspHeader = $header;
                break;
            }
        }
        
        $this->assertNotNull($cspHeader);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $cspHeader);
    }
    
    /**
     * @test
     * @group csp
     */
    public function it_includes_upgrade_insecure_requests_on_https(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        $_SERVER['HTTPS'] = 'on';
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        $cspHeader = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Security-Policy:') === 0) {
                $cspHeader = $header;
                break;
            }
        }
        
        $this->assertNotNull($cspHeader);
        $this->assertStringContainsString('upgrade-insecure-requests', $cspHeader);
    }
    
    /**
     * @test
     * @group csp
     */
    public function it_excludes_upgrade_insecure_requests_on_http(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SERVER_PORT'] = '80';
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        $cspHeader = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Security-Policy:') === 0) {
                $cspHeader = $header;
                break;
            }
        }
        
        $this->assertNotNull($cspHeader);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $cspHeader);
    }
    
    /**
     * @test
     * @group csp
     */
    public function nonce_is_unique_per_request(): void
    {
        if (!headers_sent()) {
            ob_start();
        }
        
        Security::applyHeaders();
        $nonce1 = $_SESSION['csp_nonce'];
        
        unset($_SESSION['csp_nonce']); // Simulate new request
        
        Security::applyHeaders();
        $nonce2 = $_SESSION['csp_nonce'];
        
        $this->assertNotEquals($nonce1, $nonce2, 'Nonces should be unique per request');
        
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    
    /**
     * @test
     * @group headers
     */
    public function it_blocks_dangerous_permissions(): void
    {
        if (headers_sent()) {
            $this->markTestSkipped('Headers already sent');
        }
        
        ob_start();
        Security::applyHeaders();
        $headers = xdebug_get_headers();
        ob_end_clean();
        
        $permissionsHeader = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Permissions-Policy:') === 0) {
                $permissionsHeader = $header;
                break;
            }
        }
        
        $this->assertNotNull($permissionsHeader);
        $this->assertStringContainsString('geolocation=()', $permissionsHeader);
        $this->assertStringContainsString('microphone=()', $permissionsHeader);
        $this->assertStringContainsString('camera=()', $permissionsHeader);
    }
}
