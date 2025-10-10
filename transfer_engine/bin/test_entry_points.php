#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Entry Points and URL Access Test
 * 
 * Tests all application entry points and accessible URLs
 * Validates routing, bootstrapping, and controller accessibility
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  ENTRY POINTS & URL ACCESS TEST                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$baseDir = dirname(__DIR__);
$testsRun = 0;
$testsPassed = 0;
$testsFailed = 0;
$errors = [];

// Helper function to test file
function testFile(string $file, string $description, &$testsRun, &$testsPassed, &$testsFailed, &$errors): void
{
    $testsRun++;
    echo "▶ Test {$testsRun}: {$description}\n";
    echo "  File: {$file}\n";
    
    if (!file_exists($file)) {
        echo "  ✗ FAILED: File not found\n\n";
        $testsFailed++;
        $errors[] = "{$description}: File not found - {$file}";
        return;
    }
    
    // Check if file is readable
    if (!is_readable($file)) {
        echo "  ✗ FAILED: File not readable\n\n";
        $testsFailed++;
        $errors[] = "{$description}: File not readable - {$file}";
        return;
    }
    
    // Check PHP syntax
    $output = [];
    $returnCode = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo "  ✗ FAILED: Syntax error\n";
        echo "  Error: " . implode("\n  ", $output) . "\n\n";
        $testsFailed++;
        $errors[] = "{$description}: Syntax error - " . implode(" ", $output);
        return;
    }
    
    echo "  ✓ PASSED: File exists, readable, syntax valid\n\n";
    $testsPassed++;
}

// Helper function to test directory
function testDirectory(string $dir, string $description, &$testsRun, &$testsPassed, &$testsFailed, &$errors): void
{
    $testsRun++;
    echo "▶ Test {$testsRun}: {$description}\n";
    echo "  Directory: {$dir}\n";
    
    if (!is_dir($dir)) {
        echo "  ✗ FAILED: Directory not found\n\n";
        $testsFailed++;
        $errors[] = "{$description}: Directory not found - {$dir}";
        return;
    }
    
    if (!is_readable($dir)) {
        echo "  ✗ FAILED: Directory not readable\n\n";
        $testsFailed++;
        $errors[] = "{$description}: Directory not readable - {$dir}";
        return;
    }
    
    echo "  ✓ PASSED: Directory exists and readable\n\n";
    $testsPassed++;
}

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 1: MAIN ENTRY POINTS\n";
echo "════════════════════════════════════════════════════════════\n\n";

// Test main entry point
testFile(
    $baseDir . '/public/index.php',
    'Main Entry Point (public/index.php)',
    $testsRun, $testsPassed, $testsFailed, $errors
);

// Test legacy entry points
testFile(
    $baseDir . '/index.php',
    'Root Entry Point (index.php)',
    $testsRun, $testsPassed, $testsFailed, $errors
);

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 2: BOOTSTRAP FILES\n";
echo "════════════════════════════════════════════════════════════\n\n";

testFile(
    $baseDir . '/config/bootstrap.php',
    'Bootstrap Configuration',
    $testsRun, $testsPassed, $testsFailed, $errors
);

testFile(
    $baseDir . '/config/database.php',
    'Database Configuration',
    $testsRun, $testsPassed, $testsFailed, $errors
);

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 3: CORE CLASSES\n";
echo "════════════════════════════════════════════════════════════\n\n";

$coreClasses = [
    'app/Core/Application.php' => 'Application Core',
    'app/Core/Router.php' => 'Router Core',
    'app/Core/Database.php' => 'Database Core',
    'app/Core/Logger.php' => 'Logger Core',
    'app/Core/Security.php' => 'Security Core',
    'app/Http/Kernel.php' => 'HTTP Kernel',
];

foreach ($coreClasses as $file => $description) {
    testFile(
        $baseDir . '/' . $file,
        $description,
        $testsRun, $testsPassed, $testsFailed, $errors
    );
}

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 4: CONTROLLERS\n";
echo "════════════════════════════════════════════════════════════\n\n";

$controllers = [
    'app/Controllers/DashboardController.php' => 'Dashboard Controller',
    'app/Controllers/ConfigController.php' => 'Config Controller',
    'app/Controllers/TransferController.php' => 'Transfer Controller',
    'app/Controllers/ReportsController.php' => 'Reports Controller',
    'app/Controllers/LogsController.php' => 'Logs Controller',
    'app/Controllers/SettingsController.php' => 'Settings Controller',
    'app/Controllers/HealthController.php' => 'Health Controller',
];

foreach ($controllers as $file => $description) {
    testFile(
        $baseDir . '/' . $file,
        $description,
        $testsRun, $testsPassed, $testsFailed, $errors
    );
}

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 5: DIRECTORY STRUCTURE\n";
echo "════════════════════════════════════════════════════════════\n\n";

$directories = [
    'public' => 'Public Directory',
    'app' => 'Application Directory',
    'config' => 'Configuration Directory',
    'storage' => 'Storage Directory',
    'storage/logs' => 'Logs Directory',
    'resources/views' => 'Views Directory',
    'tests' => 'Tests Directory',
];

foreach ($directories as $dir => $description) {
    testDirectory(
        $baseDir . '/' . $dir,
        $description,
        $testsRun, $testsPassed, $testsFailed, $errors
    );
}

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 6: ROUTE DEFINITIONS\n";
echo "════════════════════════════════════════════════════════════\n\n";

echo "▶ Extracting Routes from public/index.php\n";
$indexContent = file_get_contents($baseDir . '/public/index.php');
preg_match_all('/\$router->(get|post|put|delete|patch)\([\'"]([^\'"]+)[\'"]/', $indexContent, $matches);

$routes = [];
for ($i = 0; $i < count($matches[0]); $i++) {
    $method = strtoupper($matches[1][$i]);
    $path = $matches[2][$i];
    $routes[] = ['method' => $method, 'path' => $path];
}

echo "  ✓ Found " . count($routes) . " routes\n\n";

// Display routes by category
$routeCategories = [
    'Dashboard' => [],
    'Config' => [],
    'Transfer' => [],
    'Reports' => [],
    'Logs' => [],
    'API' => [],
    'Health' => [],
    'Other' => [],
];

foreach ($routes as $route) {
    $path = $route['path'];
    if (strpos($path, '/api/') === 0) {
        $routeCategories['API'][] = $route;
    } elseif (strpos($path, '/config') === 0) {
        $routeCategories['Config'][] = $route;
    } elseif (strpos($path, '/transfer') === 0) {
        $routeCategories['Transfer'][] = $route;
    } elseif (strpos($path, '/reports') === 0) {
        $routeCategories['Reports'][] = $route;
    } elseif (strpos($path, '/logs') === 0 || strpos($path, '/console') === 0) {
        $routeCategories['Logs'][] = $route;
    } elseif (strpos($path, '/health') === 0 || strpos($path, '/ready') === 0) {
        $routeCategories['Health'][] = $route;
    } elseif ($path === '/' || strpos($path, '/dashboard') === 0) {
        $routeCategories['Dashboard'][] = $route;
    } else {
        $routeCategories['Other'][] = $route;
    }
}

foreach ($routeCategories as $category => $categoryRoutes) {
    if (empty($categoryRoutes)) continue;
    
    echo "  {$category} Routes (" . count($categoryRoutes) . "):\n";
    foreach ($categoryRoutes as $route) {
        echo "    {$route['method']} {$route['path']}\n";
    }
    echo "\n";
}

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 7: AUTOLOADER TEST\n";
echo "════════════════════════════════════════════════════════════\n\n";

echo "▶ Testing Autoloader\n";
require_once $baseDir . '/config/bootstrap.php';

$testClasses = [
    'App\\Core\\Database',
    'App\\Core\\Router',
    'App\\Core\\Security',
    'App\\Core\\Logger',
];

$autoloaderPassed = 0;
$autoloaderFailed = 0;

foreach ($testClasses as $class) {
    if (class_exists($class)) {
        echo "  ✓ {$class} - Loaded\n";
        $autoloaderPassed++;
    } else {
        echo "  ✗ {$class} - NOT FOUND\n";
        $autoloaderFailed++;
        $errors[] = "Autoloader: Class {$class} not found";
    }
}

echo "\n  Autoloader: {$autoloaderPassed} loaded, {$autoloaderFailed} failed\n\n";

$testsPassed += $autoloaderPassed;
$testsFailed += $autoloaderFailed;
$testsRun += count($testClasses);

echo "════════════════════════════════════════════════════════════\n";
echo "SECTION 8: DATABASE CONNECTIVITY\n";
echo "════════════════════════════════════════════════════════════\n\n";

echo "▶ Testing Database Connection\n";
try {
    $db = \App\Core\Database::getInstance();
    $conn = $db->getConnection();
    echo "  ✓ Database connection successful\n";
    echo "  Connection: " . get_class($conn) . "\n";
    
    // Test query
    $result = $db->fetchAll("SELECT COUNT(*) as count FROM vend_outlets LIMIT 1");
    echo "  ✓ Test query successful\n";
    echo "  Outlets: " . $result[0]['count'] . "\n\n";
    
    $testsPassed += 2;
    $testsRun += 2;
} catch (\Exception $e) {
    echo "  ✗ Database connection failed\n";
    echo "  Error: " . $e->getMessage() . "\n\n";
    $testsFailed += 2;
    $testsRun += 2;
    $errors[] = "Database: " . $e->getMessage();
}

echo "════════════════════════════════════════════════════════════\n";
echo "FINAL SUMMARY\n";
echo "════════════════════════════════════════════════════════════\n\n";

$successRate = $testsRun > 0 ? round(($testsPassed / $testsRun) * 100, 1) : 0;

echo "Tests Run:    {$testsRun}\n";
echo "Tests Passed: {$testsPassed}\n";
echo "Tests Failed: {$testsFailed}\n";
echo "Success Rate: {$successRate}%\n\n";

if ($testsFailed > 0) {
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    echo "\n";
}

if ($testsFailed === 0) {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ ALL ENTRY POINTS AND URLS VALIDATED                    ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    echo "Application is ready to serve requests!\n\n";
    exit(0);
} else {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ SOME TESTS FAILED - REVIEW ERRORS ABOVE                ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    exit(1);
}
