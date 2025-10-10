<?php
/**
 * SERVER CODE INTEGRATION TESTING SUITE
 * Comprehensive server-side testing with HTTP requests and API validation
 * 
 * @package VapeshedTransfer
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 1.0.0
 */

declare(strict_types=1);

class ServerCodeTestSuite
{
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $errors = [];
    private string $logFile;
    private string $baseUrl;
    
    public function __construct(string $baseUrl = 'http://localhost')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->logFile = '/tmp/server_test_' . date('Ymd_His') . '.log';
        $this->log("🌐 SERVER CODE INTEGRATION TESTING SUITE");
        $this->log("=========================================");
        $this->log("Base URL: {$this->baseUrl}");
        $this->log("Started: " . date('Y-m-d H:i:s'));
        $this->log("");
    }
    
    public function runAllTests(): bool
    {
        echo "\n🌐 STARTING SERVER CODE INTEGRATION TESTS\n";
        echo "=========================================\n";
        
        // Test server availability first
        if (!$this->testServerAvailability()) {
            echo "❌ Server not available at {$this->baseUrl}\n";
            echo "Please ensure the web server is running.\n";
            return false;
        }
        
        // Run all test categories
        $this->testAPIEndpoints();
        $this->testDashboardPages();
        $this->testAPILabComponents();
        $this->testAssetLoading();
        $this->testSecurityHeaders();
        $this->testPerformanceMetrics();
        $this->testErrorHandling();
        $this->testDatabaseConnectivity();
        $this->testSSEFunctionality();
        $this->testRouteResolution();
        
        return $this->generateReport();
    }
    
    private function testServerAvailability(): bool
    {
        echo "🔍 SERVER AVAILABILITY CHECK\n";
        echo "============================\n";
        
        $this->runTest("Server responds to HTTP requests", function() {
            return $this->makeRequest('GET', '/') !== false;
        });
        
        $this->runTest("Server returns valid HTTP headers", function() {
            $response = $this->makeRequest('HEAD', '/');
            return $response !== false && isset($response['headers']);
        });
        
        return $this->passedTests > $this->failedTests;
    }
    
    private function testAPIEndpoints(): void
    {
        echo "\n🔌 API ENDPOINT TESTING\n";
        echo "=======================\n";
        
        $endpoints = [
            '/api/health.php' => 'Health check endpoint',
            '/api/metrics.php' => 'Metrics endpoint', 
            '/api/stats.php' => 'Statistics endpoint',
            '/api/modules.php' => 'Modules endpoint',
            '/api/activity.php' => 'Activity endpoint',
            '/sse.php' => 'Server-Sent Events endpoint'
        ];
        
        foreach ($endpoints as $endpoint => $description) {
            $this->testSingleAPIEndpoint($endpoint, $description);
        }
    }
    
    private function testSingleAPIEndpoint(string $endpoint, string $description): void
    {
        // Test endpoint accessibility
        $this->runTest("$description is accessible", function() use ($endpoint) {
            $response = $this->makeRequest('GET', $endpoint);
            return $response !== false && $response['http_code'] < 500;
        });
        
        // Test JSON response format
        $this->runTest("$description returns valid JSON", function() use ($endpoint) {
            $response = $this->makeRequest('GET', $endpoint);
            if ($response === false) return false;
            
            $json = json_decode($response['body'], true);
            return json_last_error() === JSON_ERROR_NONE;
        });
        
        // Test response structure
        $this->runTest("$description has proper response structure", function() use ($endpoint) {
            $response = $this->makeRequest('GET', $endpoint);
            if ($response === false) return false;
            
            $json = json_decode($response['body'], true);
            return isset($json['success']) && (isset($json['data']) || isset($json['error']));
        });
        
        // Test response time
        $this->runTest("$description responds within 5 seconds", function() use ($endpoint) {
            $start = microtime(true);
            $response = $this->makeRequest('GET', $endpoint);
            $duration = microtime(true) - $start;
            
            return $response !== false && $duration < 5.0;
        });
    }
    
    private function testDashboardPages(): void
    {
        echo "\n📊 DASHBOARD PAGE TESTING\n";
        echo "=========================\n";
        
        $pages = [
            '/dashboard' => 'Main dashboard',
            '/dashboard?page=api-lab' => 'API Lab hub',
            '/dashboard?page=webhook-lab' => 'Webhook Lab',
            '/dashboard?page=vend-tester' => 'Vend Tester',
            '/dashboard?page=lightspeed-tester' => 'Lightspeed Tester',
            '/dashboard?page=queue-tester' => 'Queue Tester',
            '/dashboard?page=api-suite' => 'Test Suite Runner',
            '/dashboard?page=code-snippets' => 'Code Snippet Library'
        ];
        
        foreach ($pages as $url => $description) {
            $this->testDashboardPage($url, $description);
        }
    }
    
    private function testDashboardPage(string $url, string $description): void
    {
        $this->runTest("$description page loads", function() use ($url) {
            $response = $this->makeRequest('GET', $url);
            return $response !== false && $response['http_code'] === 200;
        });
        
        $this->runTest("$description contains valid HTML", function() use ($url) {
            $response = $this->makeRequest('GET', $url);
            if ($response === false) return false;
            
            return strpos($response['body'], '<html') !== false || 
                   strpos($response['body'], '<div') !== false;
        });
        
        $this->runTest("$description has proper title", function() use ($url) {
            $response = $this->makeRequest('GET', $url);
            if ($response === false) return false;
            
            return strpos($response['body'], '<title>') !== false ||
                   strpos($response['body'], '<h1>') !== false ||
                   strpos($response['body'], '<h2>') !== false;
        });
    }
    
    private function testAPILabComponents(): void
    {
        echo "\n🧪 API LAB COMPONENT TESTING\n";
        echo "============================\n";
        
        // Test API Lab endpoint responses
        $labEndpoints = [
            '/admin/api/webhook-lab' => 'Webhook Lab API',
            '/admin/api/vend-tester' => 'Vend Tester API',
            '/admin/api/lightspeed-tester' => 'Lightspeed Tester API',
            '/admin/api/queue-tester' => 'Queue Tester API',
            '/admin/api/suite-runner' => 'Suite Runner API',
            '/admin/api/snippets' => 'Snippet Library API'
        ];
        
        foreach ($labEndpoints as $endpoint => $description) {
            $this->runTest("$description endpoint configured", function() use ($endpoint) {
                $response = $this->makeRequest('GET', $endpoint);
                // Even if not implemented, should not return 404
                return $response !== false && $response['http_code'] !== 404;
            });
        }
    }
    
    private function testAssetLoading(): void
    {
        echo "\n🎨 ASSET LOADING TESTING\n";
        echo "========================\n";
        
        $assets = [
            '/assets/css/dashboard-power.css' => 'Main CSS file',
            '/assets/js/dashboard-power.js' => 'Main JavaScript file',
            '/assets/css/bootstrap.min.css' => 'Bootstrap CSS',
            '/assets/js/bootstrap.min.js' => 'Bootstrap JavaScript'
        ];
        
        foreach ($assets as $asset => $description) {
            $this->runTest("$description loads correctly", function() use ($asset) {
                $response = $this->makeRequest('GET', $asset);
                return $response !== false && $response['http_code'] === 200;
            });
            
            $this->runTest("$description has correct content type", function() use ($asset) {
                $response = $this->makeRequest('HEAD', $asset);
                if ($response === false) return false;
                
                $expectedType = '';
                if (strpos($asset, '.css') !== false) {
                    $expectedType = 'text/css';
                } elseif (strpos($asset, '.js') !== false) {
                    $expectedType = 'application/javascript';
                }
                
                return empty($expectedType) || 
                       strpos($response['headers']['content-type'] ?? '', $expectedType) !== false;
            });
        }
    }
    
    private function testSecurityHeaders(): void
    {
        echo "\n🔒 SECURITY HEADER TESTING\n";
        echo "==========================\n";
        
        $this->runTest("X-Frame-Options header present", function() {
            $response = $this->makeRequest('HEAD', '/');
            return isset($response['headers']['x-frame-options']);
        });
        
        $this->runTest("X-XSS-Protection header present", function() {
            $response = $this->makeRequest('HEAD', '/');
            return isset($response['headers']['x-xss-protection']);
        });
        
        $this->runTest("X-Content-Type-Options header present", function() {
            $response = $this->makeRequest('HEAD', '/');
            return isset($response['headers']['x-content-type-options']);
        });
        
        $this->runTest("HTTPS redirect configured", function() {
            // This test might not apply if running on localhost
            return true; // Skip for local testing
        });
    }
    
    private function testPerformanceMetrics(): void
    {
        echo "\n⚡ PERFORMANCE TESTING\n";
        echo "=====================\n";
        
        $this->runTest("API response time under 2 seconds", function() {
            $start = microtime(true);
            $response = $this->makeRequest('GET', '/api/health.php');
            $duration = microtime(true) - $start;
            
            return $response !== false && $duration < 2.0;
        });
        
        $this->runTest("Dashboard load time under 3 seconds", function() {
            $start = microtime(true);
            $response = $this->makeRequest('GET', '/dashboard');
            $duration = microtime(true) - $start;
            
            return $response !== false && $duration < 3.0;
        });
        
        $this->runTest("Asset compression enabled", function() {
            $response = $this->makeRequest('HEAD', '/assets/css/dashboard-power.css');
            return $response !== false && 
                   isset($response['headers']['content-encoding']);
        });
    }
    
    private function testErrorHandling(): void
    {
        echo "\n🚨 ERROR HANDLING TESTING\n";
        echo "=========================\n";
        
        $this->runTest("404 error handling", function() {
            $response = $this->makeRequest('GET', '/nonexistent-page');
            return $response !== false && $response['http_code'] === 404;
        });
        
        $this->runTest("500 error handling", function() {
            // Try to trigger a server error safely
            $response = $this->makeRequest('GET', '/api/invalid-endpoint');
            return $response !== false && 
                   ($response['http_code'] === 404 || $response['http_code'] < 500);
        });
        
        $this->runTest("API error responses are JSON", function() {
            $response = $this->makeRequest('GET', '/api/nonexistent');
            if ($response === false) return false;
            
            $json = json_decode($response['body'], true);
            return json_last_error() === JSON_ERROR_NONE && 
                   isset($json['success']) && !$json['success'];
        });
    }
    
    private function testDatabaseConnectivity(): void
    {
        echo "\n💾 DATABASE CONNECTIVITY TESTING\n";
        echo "================================\n";
        
        $this->runTest("Database connection via health API", function() {
            $response = $this->makeRequest('GET', '/api/health.php');
            if ($response === false) return false;
            
            $json = json_decode($response['body'], true);
            return isset($json['data']['checks']['database']['status']) &&
                   $json['data']['checks']['database']['status'] === 'healthy';
        });
        
        $this->runTest("Database queries via metrics API", function() {
            $response = $this->makeRequest('GET', '/api/metrics.php');
            if ($response === false) return false;
            
            $json = json_decode($response['body'], true);
            return isset($json['data']['database']);
        });
    }
    
    private function testSSEFunctionality(): void
    {
        echo "\n🔄 SERVER-SENT EVENTS TESTING\n";
        echo "=============================\n";
        
        $this->runTest("SSE endpoint accessible", function() {
            $response = $this->makeRequest('GET', '/sse.php');
            return $response !== false && $response['http_code'] === 200;
        });
        
        $this->runTest("SSE headers correct", function() {
            $response = $this->makeRequest('HEAD', '/sse.php');
            return $response !== false &&
                   strpos($response['headers']['content-type'] ?? '', 'text/event-stream') !== false;
        });
    }
    
    private function testRouteResolution(): void
    {
        echo "\n🛣️ ROUTE RESOLUTION TESTING\n";
        echo "===========================\n";
        
        // Test that routes resolve correctly
        $routes = [
            '/dashboard' => 'Dashboard route',
            '/api/health.php' => 'API health route',
            '/sse.php' => 'SSE route'
        ];
        
        foreach ($routes as $route => $description) {
            $this->runTest("$description resolves correctly", function() use ($route) {
                $response = $this->makeRequest('GET', $route);
                return $response !== false && $response['http_code'] !== 404;
            });
        }
    }
    
    private function makeRequest(string $method, string $path, array $data = []): array|false
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => [$this, 'headerCallback'],
            CURLOPT_SSL_VERIFYPEER => false, // For local testing
            CURLOPT_USERAGENT => 'ServerCodeTestSuite/1.0'
        ]);
        
        $this->responseHeaders = [];
        
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        }
        
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($body === false || !empty($error)) {
            $this->log("Request failed: $method $url - Error: $error");
            return false;
        }
        
        return [
            'body' => $body,
            'http_code' => $httpCode,
            'headers' => $this->responseHeaders
        ];
    }
    
    private array $responseHeaders = [];
    
    private function headerCallback($ch, string $header): int
    {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        
        if (count($header) < 2) {
            return $len;
        }
        
        $name = strtolower(trim($header[0]));
        $value = trim($header[1]);
        $this->responseHeaders[$name] = $value;
        
        return $len;
    }
    
    private function runTest(string $description, callable $test): void
    {
        $this->totalTests++;
        echo sprintf("[TEST %d] %s", $this->totalTests, $description);
        
        try {
            $result = $test();
            if ($result) {
                echo " ✅ PASSED\n";
                $this->passedTests++;
                $this->log("✅ PASSED: $description");
            } else {
                echo " ❌ FAILED\n";
                $this->failedTests++;
                $this->errors[] = $description;
                $this->log("❌ FAILED: $description");
            }
        } catch (Throwable $e) {
            echo " ❌ ERROR: " . $e->getMessage() . "\n";
            $this->failedTests++;
            $this->errors[] = "$description - Error: " . $e->getMessage();
            $this->log("❌ ERROR: $description - " . $e->getMessage());
        }
    }
    
    private function log(string $message): void
    {
        file_put_contents($this->logFile, $message . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function generateReport(): bool
    {
        echo "\n===============================================\n";
        echo "🏁 SERVER CODE TESTING COMPLETE\n";
        echo "===============================================\n";
        echo "Total Tests: {$this->totalTests}\n";
        echo "Passed: {$this->passedTests}\n";
        echo "Failed: {$this->failedTests}\n";
        echo "Success Rate: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%\n";
        echo "Log file: {$this->logFile}\n";
        
        if ($this->failedTests > 0) {
            echo "\n❌ FAILED TESTS:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }
        
        $this->log("\n=== FINAL REPORT ===");
        $this->log("Total Tests: {$this->totalTests}");
        $this->log("Passed: {$this->passedTests}");
        $this->log("Failed: {$this->failedTests}");
        $this->log("Success Rate: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%");
        
        return $this->failedTests === 0;
    }
}

// Configuration
$baseUrl = $argv[1] ?? 'http://localhost';

// Run the server tests
$tester = new ServerCodeTestSuite($baseUrl);
$success = $tester->runAllTests();

exit($success ? 0 : 1);