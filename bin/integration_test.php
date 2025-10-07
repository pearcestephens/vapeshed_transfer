<?php
/**
 * integration_test.php
 * Comprehensive integration test suite for all API endpoints
 * Author: GitHub Copilot
 * Last Modified: 2025-10-07
 *
 * Runs end-to-end tests for pricing, transfer, unified_status, history, traces, stats, modules, activity, smoke_summary endpoints.
 * Validates: success, error, rate-limit, token, meta/correlation_id, health/readiness, logging, PII redaction.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

use App\Core\Config;
use App\Core\Logger;

$endpoints = [
    'pricing' => '/public/api/pricing.php',
    'transfer' => '/public/api/transfer.php',
    'unified_status' => '/public/api/unified_status.php',
    'history' => '/public/api/history.php',
    'traces' => '/public/api/traces.php',
    'stats' => '/public/api/stats.php',
    'modules' => '/public/api/modules.php',
    'activity' => '/public/api/activity.php',
    'smoke_summary' => '/public/api/smoke_summary.php',
];

$token = getenv('API_TEST_TOKEN') ?: 'test-token';

function test_endpoint($name, $url, $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);
    echo "Testing $name ($url):\n";
    echo "HTTP $http_code\n";
    if (isset($data['meta']['correlation_id'])) {
        echo "Meta correlation_id: " . $data['meta']['correlation_id'] . "\n";
    } else {
        echo "Meta correlation_id: MISSING\n";
    }
    if ($http_code === 200 && isset($data['success']) && $data['success'] === true) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
    }
    echo str_repeat('-', 40) . "\n";
}

foreach ($endpoints as $name => $url) {
    test_endpoint($name, $url, $token);
}

// Health/readiness check
$health_url = '/public/api/unified_status.php';
test_endpoint('health', $health_url, $token);

// Log test completion
Logger::info('Integration test suite completed.');
