<?php
/**
 * PHP LINTING AND SERVER CODE VALIDATION SUITE
 * Pinpoint accuracy testing for all PHP files with detailed analysis
 * 
 * @package VapeshedTransfer
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 1.0.0
 */

declare(strict_types=1);

class ComprehensivePHPValidator
{
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $errors = [];
    private array $warnings = [];
    private string $logFile;
    
    public function __construct()
    {
        $this->logFile = '/tmp/php_validation_' . date('Ymd_His') . '.log';
        $this->log("🔬 PHP LINTING AND SERVER CODE VALIDATION SUITE");
        $this->log("================================================");
        $this->log("Started: " . date('Y-m-d H:i:s'));
        $this->log("");
    }
    
    public function runAllTests(): bool
    {
        echo "\n🔍 STARTING COMPREHENSIVE PHP VALIDATION\n";
        echo "=========================================\n";
        
        // Get project root
        $projectRoot = dirname(__DIR__);
        $transferEngineRoot = $projectRoot . '/transfer_engine';
        
        // Test categories
        $this->validateProjectStructure($projectRoot, $transferEngineRoot);
        $this->validateControllers($transferEngineRoot);
        $this->validateViews($transferEngineRoot);
        $this->validateAPIEndpoints($transferEngineRoot);
        $this->validateRoutes($projectRoot);
        $this->validateAssets($transferEngineRoot);
        $this->validateSecurity($projectRoot);
        $this->validatePerformance($projectRoot);
        $this->validateCodeQuality($projectRoot);
        $this->validateServerCompatibility($projectRoot);
        
        return $this->generateReport();
    }
    
    private function validateProjectStructure(string $projectRoot, string $transferEngineRoot): void
    {
        echo "📁 PROJECT STRUCTURE VALIDATION\n";
        echo "===============================\n";
        
        $this->runTest("Project root exists", fn() => is_dir($projectRoot));
        $this->runTest("Transfer engine root exists", fn() => is_dir($transferEngineRoot));
        $this->runTest("App directory exists", fn() => is_dir($transferEngineRoot . '/app'));
        $this->runTest("Controllers directory exists", fn() => is_dir($transferEngineRoot . '/app/Controllers'));
        $this->runTest("API Controllers directory exists", fn() => is_dir($transferEngineRoot . '/app/Controllers/Api'));
        $this->runTest("Resources directory exists", fn() => is_dir($transferEngineRoot . '/resources'));
        $this->runTest("Views directory exists", fn() => is_dir($transferEngineRoot . '/resources/views'));
        $this->runTest("Routes directory exists", fn() => is_dir($projectRoot . '/routes'));
        $this->runTest("Public directory exists", fn() => is_dir($transferEngineRoot . '/public'));
        $this->runTest("Assets directory exists", fn() => is_dir($transferEngineRoot . '/public/assets'));
    }
    
    private function validateControllers(string $transferEngineRoot): void
    {
        echo "\n🎮 CONTROLLER VALIDATION\n";
        echo "=======================\n";
        
        $controllers = [
            'BaseController.php',
            'DashboardController.php',
            'ConfigController.php',
            'Api/WebhookLabController.php',
            'Api/VendTesterController.php',
            'Api/LightspeedTesterController.php',
            'Api/QueueJobTesterController.php',
            'Api/SuiteRunnerController.php',
            'Api/SnippetLibraryController.php'
        ];
        
        foreach ($controllers as $controller) {
            $filePath = $transferEngineRoot . '/app/Controllers/' . $controller;
            $this->validatePHPFile($filePath, "Controller: " . basename($controller));
        }
    }
    
    private function validateViews(string $transferEngineRoot): void
    {
        echo "\n📄 VIEW TEMPLATE VALIDATION\n";
        echo "==========================\n";
        
        $views = [
            'admin/dashboard/main.php',
            'admin/api-lab/main.php',
            'admin/api-lab/webhook.php',
            'admin/api-lab/vend.php',
            'admin/api-lab/lightspeed.php',
            'admin/api-lab/queue.php',
            'admin/api-lab/suite.php',
            'admin/api-lab/snippets.php'
        ];
        
        foreach ($views as $view) {
            $filePath = $transferEngineRoot . '/resources/views/' . $view;
            $this->validatePHPFile($filePath, "View: " . basename($view));
            $this->validateViewContent($filePath, basename($view));
        }
    }
    
    private function validateAPIEndpoints(string $transferEngineRoot): void
    {
        echo "\n🔌 API ENDPOINT VALIDATION\n";
        echo "=========================\n";
        
        $apiEndpoints = [
            'public/api/health.php',
            'public/api/metrics.php',
            'public/api/stats.php',
            'public/api/modules.php',
            'public/api/activity.php',
            'public/sse.php',
            'public/index.php'
        ];
        
        foreach ($apiEndpoints as $endpoint) {
            $filePath = $transferEngineRoot . '/' . $endpoint;
            if (file_exists($filePath)) {
                $this->validatePHPFile($filePath, "API: " . basename($endpoint));
                $this->validateAPIStructure($filePath, basename($endpoint));
            }
        }
    }
    
    private function validateRoutes(string $projectRoot): void
    {
        echo "\n🛣️ ROUTE VALIDATION\n";
        echo "==================\n";
        
        $routeFile = $projectRoot . '/routes/admin.php';
        if (file_exists($routeFile)) {
            $this->validatePHPFile($routeFile, "Routes: admin.php");
            $this->validateRouteStructure($routeFile);
        }
    }
    
    private function validateAssets(string $transferEngineRoot): void
    {
        echo "\n🎨 ASSET VALIDATION\n";
        echo "==================\n";
        
        $cssFile = $transferEngineRoot . '/public/assets/css/dashboard-power.css';
        $jsFile = $transferEngineRoot . '/public/assets/js/dashboard-power.js';
        
        if (file_exists($cssFile)) {
            $this->validateCSSFile($cssFile);
        }
        
        if (file_exists($jsFile)) {
            $this->validateJavaScriptFile($jsFile);
        }
    }
    
    private function validateSecurity(string $projectRoot): void
    {
        echo "\n🔒 SECURITY VALIDATION\n";
        echo "=====================\n";
        
        $this->runTest("No hardcoded passwords", function() use ($projectRoot) {
            return !$this->searchInFiles($projectRoot, '/password\s*=\s*["\'][^"\']+["\']/i', ['*.php']);
        });
        
        $this->runTest("No database URLs in code", function() use ($projectRoot) {
            return !$this->searchInFiles($projectRoot, '/mysql:\/\/|postgresql:\/\//i', ['*.php']);
        });
        
        $this->runTest("CSRF protection present", function() use ($projectRoot) {
            return $this->searchInFiles($projectRoot, '/csrf/i', ['*.php']);
        });
        
        $this->runTest("XSS protection present", function() use ($projectRoot) {
            return $this->searchInFiles($projectRoot, '/htmlspecialchars|esc_html/i', ['*.php']);
        });
        
        $this->runTest("SQL injection protection", function() use ($projectRoot) {
            return $this->searchInFiles($projectRoot, '/prepare|bindParam|bindValue/i', ['*.php']);
        });
    }
    
    private function validatePerformance(string $projectRoot): void
    {
        echo "\n⚡ PERFORMANCE VALIDATION\n";
        echo "========================\n";
        
        $this->runTest("No large files (>1MB)", function() use ($projectRoot) {
            return !$this->findLargeFiles($projectRoot, 1048576); // 1MB
        });
        
        $this->runTest("No empty PHP files", function() use ($projectRoot) {
            return !$this->findEmptyFiles($projectRoot, '*.php');
        });
        
        $this->runTest("Reasonable line count per file", function() use ($projectRoot) {
            return !$this->findLongFiles($projectRoot, 2000); // Max 2000 lines
        });
    }
    
    private function validateCodeQuality(string $projectRoot): void
    {
        echo "\n📊 CODE QUALITY VALIDATION\n";
        echo "==========================\n";
        
        $this->runTest("Documentation coverage", function() use ($projectRoot) {
            return $this->checkDocumentationCoverage($projectRoot) > 50;
        });
        
        $this->runTest("Consistent naming conventions", function() use ($projectRoot) {
            return $this->checkNamingConventions($projectRoot);
        });
        
        $this->runTest("No debug statements", function() use ($projectRoot) {
            return !$this->searchInFiles($projectRoot, '/var_dump|print_r|var_export|die\(|exit\(/i', ['*.php']);
        });
    }
    
    private function validateServerCompatibility(string $projectRoot): void
    {
        echo "\n🌐 SERVER COMPATIBILITY VALIDATION\n";
        echo "==================================\n";
        
        $this->runTest("PHP version compatibility", function() {
            return version_compare(PHP_VERSION, '8.0.0', '>=');
        });
        
        $this->runTest("Required extensions available", function() {
            $required = ['json', 'mysqli', 'curl', 'mbstring'];
            foreach ($required as $ext) {
                if (!extension_loaded($ext)) {
                    return false;
                }
            }
            return true;
        });
        
        $this->runTest("Memory limit adequate", function() {
            $memoryLimit = ini_get('memory_limit');
            $bytes = $this->convertToBytes($memoryLimit);
            return $bytes >= 128 * 1024 * 1024; // Minimum 128MB
        });
    }
    
    private function validatePHPFile(string $filePath, string $description): void
    {
        if (!file_exists($filePath)) {
            $this->runTest("$description exists", fn() => false);
            return;
        }
        
        // File exists
        $this->runTest("$description exists", fn() => true);
        
        // Readable
        $this->runTest("$description readable", fn() => is_readable($filePath));
        
        // PHP syntax check
        $this->runTest("$description syntax", function() use ($filePath) {
            $output = [];
            $returnCode = 0;
            exec("php -l " . escapeshellarg($filePath), $output, $returnCode);
            return $returnCode === 0;
        });
        
        // Class/namespace validation
        $this->validatePHPStructure($filePath, $description);
    }
    
    private function validatePHPStructure(string $filePath, string $description): void
    {
        $content = file_get_contents($filePath);
        
        // Check for opening PHP tag
        $this->runTest("$description has opening PHP tag", function() use ($content) {
            return strpos($content, '<?php') !== false;
        });
        
        // Check for namespace declaration (if it's a class file)
        if (strpos($filePath, '/Controllers/') !== false || strpos($filePath, '/Core/') !== false) {
            $this->runTest("$description has namespace", function() use ($content) {
                return preg_match('/namespace\s+[\w\\\\]+;/', $content);
            });
        }
        
        // Check for class declaration (if it's a controller)
        if (strpos($filePath, 'Controller.php') !== false) {
            $this->runTest("$description has class declaration", function() use ($content) {
                return preg_match('/class\s+\w+/', $content);
            });
        }
        
        // Check for docblock
        $this->runTest("$description has file docblock", function() use ($content) {
            return strpos($content, '/**') !== false;
        });
    }
    
    private function validateViewContent(string $filePath, string $viewName): void
    {
        if (!file_exists($filePath)) return;
        
        $content = file_get_contents($filePath);
        
        // Check for proper HTML structure in views
        $this->runTest("$viewName has proper HTML", function() use ($content) {
            return strpos($content, '<div') !== false || strpos($content, 'class=') !== false;
        });
        
        // Check for XSS protection in views
        $this->runTest("$viewName uses XSS protection", function() use ($content) {
            return preg_match('/htmlspecialchars|esc_html|\$[a-zA-Z_]+\s*\|\s*e/', $content);
        });
    }
    
    private function validateAPIStructure(string $filePath, string $apiName): void
    {
        $content = file_get_contents($filePath);
        
        // Check for JSON response structure
        $this->runTest("$apiName returns JSON", function() use ($content) {
            return strpos($content, 'application/json') !== false || 
                   strpos($content, 'json_encode') !== false;
        });
        
        // Check for error handling
        $this->runTest("$apiName has error handling", function() use ($content) {
            return preg_match('/try\s*{|catch\s*\(|\$error|\$exception/i', $content);
        });
    }
    
    private function validateRouteStructure(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        // Check for route definitions
        $this->runTest("Routes file has route definitions", function() use ($content) {
            return preg_match('/return\s*\[|\'controller\'|\'view\'/', $content);
        });
    }
    
    private function validateCSSFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        $this->runTest("CSS file readable", fn() => true);
        
        // Check CSS syntax - balanced braces
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        $this->runTest("CSS has balanced braces", fn() => $openBraces === $closeBraces);
        
        // Check for CSS variables or modern features
        $this->runTest("CSS uses modern features", function() use ($content) {
            return strpos($content, '--') !== false || // CSS variables
                   strpos($content, 'grid') !== false ||
                   strpos($content, 'flex') !== false;
        });
    }
    
    private function validateJavaScriptFile(string $filePath): void
    {
        $this->runTest("JavaScript file readable", fn() => is_readable($filePath));
        
        $content = file_get_contents($filePath);
        
        // Check for ES6+ features
        $this->runTest("JavaScript uses modern syntax", function() use ($content) {
            return strpos($content, 'const ') !== false ||
                   strpos($content, 'let ') !== false ||
                   strpos($content, '=>') !== false ||
                   strpos($content, 'class ') !== false;
        });
        
        // Check for error handling
        $this->runTest("JavaScript has error handling", function() use ($content) {
            return strpos($content, 'try {') !== false ||
                   strpos($content, 'catch') !== false;
        });
    }
    
    private function searchInFiles(string $directory, string $pattern, array $extensions): bool
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filename = $file->getFilename();
                $matches = false;
                
                foreach ($extensions as $ext) {
                    if (fnmatch($ext, $filename)) {
                        $matches = true;
                        break;
                    }
                }
                
                if ($matches) {
                    $content = file_get_contents($file->getPathname());
                    if (preg_match($pattern, $content)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    private function findLargeFiles(string $directory, int $maxSize): bool
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getSize() > $maxSize) {
                if (preg_match('/\.(php|js|css)$/', $file->getFilename())) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function findEmptyFiles(string $directory, string $pattern): bool
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                if ($file->getSize() === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function findLongFiles(string $directory, int $maxLines): bool
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.php$/', $file->getFilename())) {
                $lineCount = count(file($file->getPathname()));
                if ($lineCount > $maxLines) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function checkDocumentationCoverage(string $directory): float
    {
        $totalFiles = 0;
        $documentedFiles = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.php$/', $file->getFilename())) {
                $totalFiles++;
                $content = file_get_contents($file->getPathname());
                if (strpos($content, '/**') !== false) {
                    $documentedFiles++;
                }
            }
        }
        
        return $totalFiles > 0 ? ($documentedFiles / $totalFiles) * 100 : 0;
    }
    
    private function checkNamingConventions(string $directory): bool
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/Controller\.php$/', $file->getFilename())) {
                // Controller files should be PascalCase
                if (!preg_match('/^[A-Z][a-zA-Z0-9]*Controller\.php$/', $file->getFilename())) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower($value[strlen($value) - 1]);
        $numericValue = (int) $value;
        
        switch ($unit) {
            case 'g':
                $numericValue *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $numericValue *= 1024 * 1024;
                break;
            case 'k':
                $numericValue *= 1024;
                break;
        }
        
        return $numericValue;
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
        echo "🏁 PHP VALIDATION SUITE COMPLETE\n";
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

// Run the validation suite
$validator = new ComprehensivePHPValidator();
$success = $validator->runAllTests();

exit($success ? 0 : 1);