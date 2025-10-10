<?php
declare(strict_types=1);

namespace Unified\Tests\Security;

use PHPUnit\Framework\TestCase;
use Unified\Security\EgressGuard;
use InvalidArgumentException;
use RuntimeException;

/**
 * SSRF Protection Tests
 * 
 * @covers \Unified\Security\EgressGuard
 */
final class EgressGuardTest extends TestCase
{
    /**
     * @test
     * @group security
     * @group ssrf
     */
    public function it_blocks_loopback_addresses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private/reserved address');
        
        EgressGuard::assertUrlAllowed('http://127.0.0.1/admin');
    }

    /**
     * @test
     * @group security
     * @group ssrf
     */
    public function it_blocks_private_networks(): void
    {
        $privateUrls = [
            'http://192.168.1.1/',
            'http://10.0.0.1/',
            'http://172.16.0.1/',
        ];
        
        foreach ($privateUrls as $url) {
            try {
                EgressGuard::assertUrlAllowed($url);
                $this->fail(sprintf('Expected %s to be blocked', $url));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('private/reserved', $e->getMessage());
            }
        }
    }

    /**
     * @test
     * @group security
     * @group ssrf
     */
    public function it_blocks_cloud_metadata_endpoint(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private/reserved address');
        
        // AWS metadata endpoint
        EgressGuard::assertUrlAllowed('http://169.254.169.254/latest/meta-data/');
    }

    /**
     * @test
     * @group security
     * @group ssrf
     */
    public function it_blocks_ipv6_loopback(): void
    {
        $this->expectException(RuntimeException::class);
        
        EgressGuard::assertUrlAllowed('http://[::1]/admin');
    }

    /**
     * @test
     * @group security
     */
    public function it_rejects_invalid_url_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL format');
        
        EgressGuard::assertUrlAllowed('not a valid url');
    }

    /**
     * @test
     * @group security
     */
    public function it_rejects_non_http_schemes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('http or https');
        
        EgressGuard::assertUrlAllowed('file:///etc/passwd');
    }

    /**
     * @test
     * @group security
     */
    public function it_rejects_ftp_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        EgressGuard::assertUrlAllowed('ftp://evil.com/');
    }

    /**
     * @test
     * @group security
     * @group integration
     */
    public function it_allows_public_https_urls(): void
    {
        // This test requires DNS resolution and may fail in isolated environments
        if (!function_exists('dns_get_record')) {
            $this->markTestSkipped('DNS functions not available');
        }
        
        $this->expectNotToPerformAssertions();
        
        // These should succeed (public internet addresses)
        try {
            EgressGuard::assertUrlAllowed('https://www.google.com/');
        } catch (RuntimeException $e) {
            $this->markTestSkipped('DNS resolution failed: ' . $e->getMessage());
        }
    }

    /**
     * @test
     * @group security
     */
    public function it_enforces_allowlist_when_provided(): void
    {
        $allowHosts = ['api.example.com', 'trusted-partner.com'];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not in allowlist');
        
        EgressGuard::assertUrlAllowed('https://evil.com/', $allowHosts);
    }

    /**
     * @test
     * @group security
     */
    public function it_allows_hosts_in_allowlist(): void
    {
        $allowHosts = ['api.example.com'];
        
        // Should not throw when host is in allowlist
        // (may throw for DNS resolution failure in test env, that's acceptable)
        try {
            EgressGuard::assertUrlAllowed('https://api.example.com/endpoint', $allowHosts);
        } catch (RuntimeException $e) {
            // If it fails, it should be DNS-related, not allowlist
            $this->assertStringNotContainsString('not in allowlist', $e->getMessage());
        }
        
        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * @group security
     */
    public function it_is_case_insensitive_for_allowlist(): void
    {
        $allowHosts = ['API.EXAMPLE.COM'];
        
        try {
            EgressGuard::assertUrlAllowed('https://api.example.com/', $allowHosts);
        } catch (RuntimeException $e) {
            // Should not fail on allowlist check
            $this->assertStringNotContainsString('not in allowlist', $e->getMessage());
        }
        
        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * @group security
     */
    public function it_provides_non_throwing_check(): void
    {
        $this->assertFalse(EgressGuard::isUrlAllowed('http://127.0.0.1/'));
        $this->assertFalse(EgressGuard::isUrlAllowed('http://192.168.1.1/'));
        $this->assertFalse(EgressGuard::isUrlAllowed('file:///etc/passwd'));
    }

    /**
     * @test
     * @group security
     */
    public function it_provides_detailed_check_result(): void
    {
        $result = EgressGuard::checkUrl('http://127.0.0.1/admin');
        
        $this->assertFalse($result['allowed']);
        $this->assertNotNull($result['reason']);
        $this->assertStringContainsString('private/reserved', $result['reason']);
    }

    /**
     * @test
     * @group security
     */
    public function it_handles_dns_resolution_failure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DNS resolution failed');
        
        // Use a domain that should never resolve
        EgressGuard::assertUrlAllowed('https://this-domain-absolutely-should-not-exist-12345.invalid/');
    }

    /**
     * @test
     * @group security
     * @dataProvider linkLocalAddressProvider
     */
    public function it_blocks_link_local_addresses(string $url): void
    {
        $this->expectException(RuntimeException::class);
        EgressGuard::assertUrlAllowed($url);
    }

    public static function linkLocalAddressProvider(): array
    {
        return [
            'IPv4 link-local' => ['http://169.254.1.1/'],
            'IPv6 link-local' => ['http://[fe80::1]/'],
        ];
    }
}
