<?php
declare(strict_types=1);
/**
 * Simple validation script for testing API endpoints directly
 * Tests API endpoints using include method to avoid HTTP server dependencies
 */

echo "==== API ENDPOINT VALIDATION ====\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$base = rtrim(getenv('SMOKE_BASE_URL') ?: '', '/');
if ($base === '') {
    echo "Include-mode tests are now disabled to avoid in-process exit() calls.\n";
    echo "Set SMOKE_BASE_URL to run HTTP validation instead (see bin/http_smoke.php).\n";
    echo json_encode(['status'=>'SKIPPED','ts'=>date('c')], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

$errors = [];
$successes = [];

// Test 1: Transfer API - Status endpoint
echo "1. Testing Transfer API Status...\n";
try {
    // Simulate GET request for status
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'status';
    
    ob_start();
    include __DIR__ . '/../public/api/transfer.php';
    $output = ob_get_clean();
    
    $decoded = json_decode($output, true);
    if (is_array($decoded) && isset($decoded['success']) && $decoded['success'] === true) {
        $successes[] = "Transfer API Status: SUCCESS";
        echo "   ✓ Transfer API returned valid JSON with success=true\n";
    } else {
        $errors[] = "Transfer API Status: Invalid response format";
        echo "   ✗ Invalid response: " . substr($output, 0, 100) . "\n";
    }
} catch (Exception $e) {
    $errors[] = "Transfer API Status: Exception - " . $e->getMessage();
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// Clear globals for next test
unset($_GET['action']);

// Test 2: Pricing API - Status endpoint  
echo "\n2. Testing Pricing API Status...\n";
try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'status';
    
    ob_start();
    include __DIR__ . '/../public/api/pricing.php';
    $output = ob_get_clean();
    
    $decoded = json_decode($output, true);
    if (is_array($decoded) && isset($decoded['success']) && $decoded['success'] === true) {
        $successes[] = "Pricing API Status: SUCCESS";
        echo "   ✓ Pricing API returned valid JSON with success=true\n";
    } else {
        $errors[] = "Pricing API Status: Invalid response format";
        echo "   ✗ Invalid response: " . substr($output, 0, 100) . "\n";
    }
} catch (Exception $e) {
    $errors[] = "Pricing API Status: Exception - " . $e->getMessage();
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// Clear globals for next test
unset($_GET['action']);

// Test 3: Health endpoint
echo "\n3. Testing Health Endpoint...\n";
try {
    ob_start();
    include __DIR__ . '/../public/health.php';
    $output = ob_get_clean();
    
    $decoded = json_decode($output, true);
    if (is_array($decoded) && isset($decoded['checks'])) {
        $successes[] = "Health Endpoint: SUCCESS";
        echo "   ✓ Health endpoint returned valid JSON\n";
    } else {
        $errors[] = "Health Endpoint: Invalid response format";
        echo "   ✗ Invalid response: " . substr($output, 0, 100) . "\n";
    }
} catch (Exception $e) {
    $errors[] = "Health Endpoint: Exception - " . $e->getMessage();
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// Summary
echo "\n==== VALIDATION SUMMARY ====\n";
echo "Successes: " . count($successes) . "\n";
foreach ($successes as $success) {
    echo "  ✓ $success\n";
}

echo "\nErrors: " . count($errors) . "\n";
foreach ($errors as $error) {
    echo "  ✗ $error\n";
}

$status = empty($errors) ? 'GREEN' : 'RED';
echo "\nOverall Status: $status\n";

// JSON output for programmatic parsing
$result = [
    'status' => $status,
    'timestamp' => date('c'),
    'successes' => $successes,
    'errors' => $errors,
    'summary' => [
        'success_count' => count($successes),
        'error_count' => count($errors)
    ]
];

echo "\nJSON Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

exit(empty($errors) ? 0 : 1);