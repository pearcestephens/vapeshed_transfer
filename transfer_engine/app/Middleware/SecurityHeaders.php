<?php
/**
 * Security Headers Middleware
 *
 * Implements comprehensive security headers for:
 * - Content Security Policy (CSP)
 * - HTTP Strict Transport Security (HSTS)
 * - X-Frame-Options
 * - X-Content-Type-Options
 * - X-XSS-Protection
 * - Referrer-Policy
 * - Permissions-Policy
 * - Cross-Origin policies
 *
 * @category   Middleware
 * @package    VapeshedTransfer
 * @subpackage Security
 * @version    1.0.0
 */

namespace App\Middleware;

/**
 * Security Headers Middleware
 */
class SecurityHeaders
{
    /**
     * Security header configurations
     *
     * @var array
     */
    private $headers = [];

    /**
     * CSP directives
     *
     * @var array
     */
    private $cspDirectives = [];

    /**
     * Environment (production, staging, development)
     *
     * @var string
     */
    private $environment;

    /**
     * Constructor
     *
     * @param string $environment Environment name
     */
    public function __construct(string $environment = 'production')
    {
        $this->environment = $environment;
        $this->initializeDefaults();
    }

    /**
     * Initialize default security headers
     *
     * @return void
     */
    private function initializeDefaults(): void
    {
        // HSTS (HTTP Strict Transport Security)
        if ($this->environment === 'production') {
            $this->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        }

        // Prevent clickjacking
        $this->headers['X-Frame-Options'] = 'SAMEORIGIN';

        // Prevent MIME-type sniffing
        $this->headers['X-Content-Type-Options'] = 'nosniff';

        // XSS Protection (legacy but still useful)
        $this->headers['X-XSS-Protection'] = '1; mode=block';

        // Referrer Policy
        $this->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

        // Permissions Policy (formerly Feature Policy)
        $this->headers['Permissions-Policy'] = implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()'
        ]);

        // Cross-Origin Policies
        $this->headers['Cross-Origin-Opener-Policy'] = 'same-origin';
        $this->headers['Cross-Origin-Embedder-Policy'] = 'require-corp';
        $this->headers['Cross-Origin-Resource-Policy'] = 'same-origin';

        // Initialize CSP directives
        $this->initializeCSP();
    }

    /**
     * Initialize Content Security Policy
     *
     * @return void
     */
    private function initializeCSP(): void
    {
        $this->cspDirectives = [
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'",
                "'unsafe-inline'",  // Required for inline scripts (minimize usage)
                'https://cdn.jsdelivr.net',  // Chart.js
                'https://cdnjs.cloudflare.com'
            ],
            'style-src' => [
                "'self'",
                "'unsafe-inline'",  // Required for inline styles
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com'
            ],
            'img-src' => [
                "'self'",
                'data:',  // Data URLs for images
                'https:'
            ],
            'font-src' => [
                "'self'",
                'data:',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com'
            ],
            'connect-src' => [
                "'self'",
                'https://staff.vapeshed.co.nz'
            ],
            'frame-src' => ["'none'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'upgrade-insecure-requests' => []
        ];

        // In development, allow eval for debugging
        if ($this->environment === 'development') {
            $this->cspDirectives['script-src'][] = "'unsafe-eval'";
        }
    }

    /**
     * Apply security headers to response
     *
     * @param array $additionalHeaders Optional additional headers
     * @return void
     */
    public function apply(array $additionalHeaders = []): void
    {
        // Merge additional headers
        $headers = array_merge($this->headers, $additionalHeaders);

        // Generate and add CSP header
        $headers['Content-Security-Policy'] = $this->buildCSP();

        // Send headers
        foreach ($headers as $name => $value) {
            if (!headers_sent()) {
                header("{$name}: {$value}");
            }
        }
    }

    /**
     * Build Content Security Policy string
     *
     * @return string CSP header value
     */
    private function buildCSP(): string
    {
        $policies = [];

        foreach ($this->cspDirectives as $directive => $sources) {
            if (empty($sources)) {
                $policies[] = $directive;
            } else {
                $policies[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $policies);
    }

    /**
     * Add CSP directive source
     *
     * @param string $directive CSP directive (e.g., 'script-src')
     * @param string $source Source to add (e.g., 'https://example.com')
     * @return self
     */
    public function addCSPSource(string $directive, string $source): self
    {
        if (!isset($this->cspDirectives[$directive])) {
            $this->cspDirectives[$directive] = [];
        }

        if (!in_array($source, $this->cspDirectives[$directive])) {
            $this->cspDirectives[$directive][] = $source;
        }

        return $this;
    }

    /**
     * Remove CSP directive source
     *
     * @param string $directive CSP directive
     * @param string $source Source to remove
     * @return self
     */
    public function removeCSPSource(string $directive, string $source): self
    {
        if (isset($this->cspDirectives[$directive])) {
            $key = array_search($source, $this->cspDirectives[$directive]);
            if ($key !== false) {
                unset($this->cspDirectives[$directive][$key]);
                $this->cspDirectives[$directive] = array_values($this->cspDirectives[$directive]);
            }
        }

        return $this;
    }

    /**
     * Set custom header
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Remove header
     *
     * @param string $name Header name
     * @return self
     */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Get CSP nonce for inline scripts
     *
     * @return string Nonce value
     */
    public function getNonce(): string
    {
        static $nonce = null;

        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
            $this->addCSPSource('script-src', "'nonce-{$nonce}'");
            $this->addCSPSource('style-src', "'nonce-{$nonce}'");
        }

        return $nonce;
    }

    /**
     * Enable report-only mode for CSP (testing)
     *
     * @param string $reportUri URI to send violation reports
     * @return self
     */
    public function enableCSPReportOnly(string $reportUri = ''): self
    {
        $this->removeHeader('Content-Security-Policy');
        
        if ($reportUri) {
            $this->cspDirectives['report-uri'] = [$reportUri];
        }

        $this->headers['Content-Security-Policy-Report-Only'] = $this->buildCSP();

        return $this;
    }

    /**
     * Set CORS headers for API endpoints
     *
     * @param array $options CORS configuration
     * @return self
     */
    public function setCORS(array $options = []): self
    {
        $defaults = [
            'origin' => '*',
            'methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'headers' => 'Content-Type, Authorization, X-Requested-With',
            'credentials' => false,
            'max_age' => 86400
        ];

        $config = array_merge($defaults, $options);

        $this->headers['Access-Control-Allow-Origin'] = $config['origin'];
        $this->headers['Access-Control-Allow-Methods'] = $config['methods'];
        $this->headers['Access-Control-Allow-Headers'] = $config['headers'];
        $this->headers['Access-Control-Max-Age'] = (string)$config['max_age'];

        if ($config['credentials']) {
            $this->headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $this;
    }

    /**
     * Set strict security headers for sensitive operations
     *
     * @return self
     */
    public function strict(): self
    {
        $this->headers['X-Frame-Options'] = 'DENY';
        $this->headers['Cross-Origin-Resource-Policy'] = 'same-site';
        $this->removeCSPSource('script-src', "'unsafe-inline'");
        $this->removeCSPSource('style-src', "'unsafe-inline'");

        return $this;
    }

    /**
     * Set relaxed security headers for public content
     *
     * @return self
     */
    public function relaxed(): self
    {
        $this->headers['X-Frame-Options'] = 'SAMEORIGIN';
        $this->headers['Cross-Origin-Resource-Policy'] = 'cross-origin';

        return $this;
    }

    /**
     * Get all configured headers
     *
     * @return array Headers
     */
    public function getHeaders(): array
    {
        $headers = $this->headers;
        $headers['Content-Security-Policy'] = $this->buildCSP();
        return $headers;
    }

    /**
     * Get CSP violation report handler
     *
     * @return callable Handler function
     */
    public static function getCSPReportHandler(): callable
    {
        return function() {
            $report = file_get_contents('php://input');
            
            if ($report) {
                $data = json_decode($report, true);
                
                if ($data && isset($data['csp-report'])) {
                    $violation = $data['csp-report'];
                    
                    // Log the violation
                    error_log('CSP Violation: ' . json_encode($violation));
                    
                    // Optionally store in database for analysis
                    // $db->insert('csp_violations', $violation);
                }
            }
            
            http_response_code(204);
        };
    }

    /**
     * Create preset for different page types
     *
     * @param string $type Page type (admin, api, public)
     * @return self New instance with preset
     */
    public static function preset(string $type): self
    {
        $instance = new self();

        switch ($type) {
            case 'admin':
                $instance->strict();
                break;

            case 'api':
                $instance->setCORS([
                    'origin' => 'https://staff.vapeshed.co.nz',
                    'methods' => 'GET, POST, PUT, DELETE',
                    'credentials' => true
                ]);
                $instance->setHeader('X-Content-Type-Options', 'nosniff');
                break;

            case 'public':
                $instance->relaxed();
                break;
        }

        return $instance;
    }

    /**
     * Test headers for compliance
     *
     * @return array Test results
     */
    public function test(): array
    {
        $results = [
            'passed' => [],
            'failed' => [],
            'warnings' => []
        ];

        // Check HSTS
        if (isset($this->headers['Strict-Transport-Security'])) {
            $results['passed'][] = 'HSTS enabled';
        } else {
            $results['warnings'][] = 'HSTS not enabled (recommended for production)';
        }

        // Check CSP
        if ($this->buildCSP()) {
            $results['passed'][] = 'CSP configured';
            
            // Check for unsafe-inline
            if (in_array("'unsafe-inline'", $this->cspDirectives['script-src'] ?? [])) {
                $results['warnings'][] = 'CSP allows unsafe-inline scripts';
            }
            if (in_array("'unsafe-eval'", $this->cspDirectives['script-src'] ?? [])) {
                $results['warnings'][] = 'CSP allows unsafe-eval';
            }
        } else {
            $results['failed'][] = 'No CSP configured';
        }

        // Check X-Frame-Options
        if (isset($this->headers['X-Frame-Options'])) {
            $results['passed'][] = 'X-Frame-Options set';
        } else {
            $results['failed'][] = 'X-Frame-Options missing';
        }

        // Check X-Content-Type-Options
        if (isset($this->headers['X-Content-Type-Options'])) {
            $results['passed'][] = 'X-Content-Type-Options set';
        } else {
            $results['failed'][] = 'X-Content-Type-Options missing';
        }

        return $results;
    }
}
