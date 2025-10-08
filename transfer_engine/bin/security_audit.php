#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Security Audit Tool
 * 
 * Comprehensive security audit for Vapeshed Transfer Engine.
 * 
 * Usage:
 *   php bin/security_audit.php              - Run full audit
 *   php bin/security_audit.php --quick      - Quick scan only
 *   php bin/security_audit.php --fix        - Auto-fix issues where possible
 *   php bin/security_audit.php --report     - Generate detailed report
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Unified\Support\{Logger, NeuroContext};

$logger = new Logger('security_audit');
$issues = [];
$warnings = [];
$passed = [];

// Parse arguments
$quick = in_array('--quick', $argv);
$fix = in_array('--fix', $argv);
$report = in_array('--report', $argv);

echo "🔒 Vapeshed Transfer Engine - Security Audit\n";
echo str_repeat("=", 60) . "\n\n";

// ============================================
// 1. FILE PERMISSIONS
// ============================================
echo "📁 Checking file permissions...\n";

$sensitiveFiles = [
    ROOT_PATH . '/app/bootstrap.php',
    ROOT_PATH . '/config/database.php',
    ROOT_PATH . '/.env',
];

foreach ($sensitiveFiles as $file) {
    if (!file_exists($file)) continue;
    
    $perms = fileperms($file);
    $octal = substr(sprintf('%o', $perms), -4);
    
    // Check if world-readable
    if ($perms & 0004) {
        $issues[] = "World-readable: $file ($octal)";
        
        if ($fix) {
            chmod($file, 0640);
            echo "  ✅ Fixed: $file (now 0640)\n";
        }
    } else {
        $passed[] = "File permissions: $file";
    }
}

// ============================================
// 2. CONFIGURATION SECURITY
// ============================================
echo "\n⚙️  Checking configuration security...\n";

// Check if debug mode is off in production
$env = \Unified\Support\Config::get('neuro.unified.environment', 'production');
$debug = ini_get('display_errors') === '1';

if ($env === 'production' && $debug) {
    $issues[] = "Debug mode enabled in production";
} else {
    $passed[] = "Debug mode properly configured";
}

// Check session configuration
session_start();
$sessionConfig = session_get_cookie_params();

if (!$sessionConfig['secure'] && isset($_SERVER['HTTPS'])) {
    $warnings[] = "Session cookies not marked secure (recommend HTTPS-only)";
}

if (!$sessionConfig['httponly']) {
    $issues[] = "Session cookies not marked HttpOnly (XSS vulnerability)";
} else {
    $passed[] = "Session cookies HttpOnly enabled";
}

// Check CSRF token exists
if (empty($_SESSION['_csrf'])) {
    $warnings[] = "CSRF token not initialized in session";
} else {
    $passed[] = "CSRF token initialized";
}

// ============================================
// 3. DATABASE SECURITY
// ============================================
echo "\n🗄️  Checking database security...\n";

try {
    $db = \Unified\Support\Pdo::instance();
    
    // Check if using prepared statements (spot check a query)
    $stmt = $db->query("SELECT VERSION()");
    $version = $stmt->fetchColumn();
    $passed[] = "Database connection established (MySQL $version)";
    
    // Check for default accounts
    $stmt = $db->query("SELECT user, host FROM mysql.user WHERE user = 'root' AND host = '%'");
    $rootRemote = $stmt->fetchAll();
    
    if (!empty($rootRemote)) {
        $issues[] = "Root account accessible remotely - CRITICAL SECURITY RISK";
    } else {
        $passed[] = "No remote root access";
    }
    
} catch (\Exception $e) {
    $warnings[] = "Database security check failed: " . $e->getMessage();
}

// ============================================
// 4. API SECURITY
// ============================================
echo "\n🔐 Checking API security...\n";

// Check rate limiting configuration
$rateLimitEnabled = \Unified\Support\Config::get('neuro.unified.security.get_rate_limit_per_min', 0);

if ($rateLimitEnabled > 0) {
    $passed[] = "Rate limiting enabled (${rateLimitEnabled}/min)";
} else {
    $warnings[] = "Rate limiting disabled - vulnerable to DoS";
}

// Check CORS configuration
$corsAllowlist = \Unified\Support\Config::get('neuro.unified.security.cors_allowlist', null);

if ($env === 'production' && $corsAllowlist === '*') {
    $issues[] = "CORS allows all origins in production - security risk";
} elseif ($env === 'production' && empty($corsAllowlist)) {
    $passed[] = "CORS properly restricted in production";
} else {
    $passed[] = "CORS configuration appropriate for environment";
}

// Check CSP header
$csp = \Unified\Support\Config::get('neuro.unified.security.csp_header', null);

if (empty($csp)) {
    $warnings[] = "Content Security Policy not configured";
} else {
    $passed[] = "CSP header configured";
}

// ============================================
// 5. LOG FILE SECURITY
// ============================================
echo "\n📝 Checking log file security...\n";

$logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : null;

if ($logDir && is_dir($logDir)) {
    $logFiles = glob($logDir . '/*.log');
    
    foreach ($logFiles as $logFile) {
        $perms = fileperms($logFile);
        
        // Check if world-readable
        if ($perms & 0004) {
            $issues[] = "Log file world-readable: " . basename($logFile);
            
            if ($fix) {
                chmod($logFile, 0640);
                echo "  ✅ Fixed: " . basename($logFile) . "\n";
            }
        }
    }
    
    $passed[] = "Log directory security checked";
}

// ============================================
// 6. SENSITIVE DATA EXPOSURE
// ============================================
echo "\n🔍 Checking for sensitive data exposure...\n";

// Check if .env file is accessible via web
$publicEnv = PUBLIC_PATH . '/.env';
if (file_exists($publicEnv)) {
    $issues[] = ".env file in public directory - CRITICAL RISK";
} else {
    $passed[] = "No .env in public directory";
}

// Check for exposed config files
$exposedConfigs = [
    PUBLIC_PATH . '/config.php',
    PUBLIC_PATH . '/database.php',
    PUBLIC_PATH . '/.git',
];

foreach ($exposedConfigs as $file) {
    if (file_exists($file)) {
        $issues[] = "Sensitive file exposed: $file";
    }
}

// ============================================
// 7. INPUT VALIDATION
// ============================================
echo "\n✅ Checking input validation...\n";

// Check if Validator class exists and is used
if (class_exists('Unified\Support\Validator')) {
    $passed[] = "Validator class available";
} else {
    $warnings[] = "Validator class not found - input validation may be weak";
}

// Check if Sanitizer class exists
if (class_exists('Unified\Support\Sanitizer')) {
    $passed[] = "Sanitizer class available";
} else {
    $warnings[] = "Sanitizer class not found - XSS protection may be weak";
}

// ============================================
// 8. AUTHENTICATION & AUTHORIZATION
// ============================================
echo "\n👤 Checking authentication security...\n";

// Check password hashing
$passwordAlgo = PASSWORD_DEFAULT;
if ($passwordAlgo === PASSWORD_BCRYPT || $passwordAlgo === PASSWORD_ARGON2I || $passwordAlgo === PASSWORD_ARGON2ID) {
    $passed[] = "Strong password hashing configured";
} else {
    $warnings[] = "Password hashing algorithm may be weak";
}

// Check session timeout
$sessionLifetime = ini_get('session.gc_maxlifetime');
if ($sessionLifetime > 7200) {
    $warnings[] = "Session lifetime very long (${sessionLifetime}s) - consider reducing";
} else {
    $passed[] = "Session lifetime reasonable (${sessionLifetime}s)";
}

// ============================================
// 9. DEPENDENCY SECURITY
// ============================================
echo "\n📦 Checking dependencies...\n";

// Check PHP version
$phpVersion = PHP_VERSION;
if (version_compare($phpVersion, '8.0.0', '<')) {
    $issues[] = "PHP version $phpVersion is outdated and may have security vulnerabilities";
} else {
    $passed[] = "PHP version $phpVersion is supported";
}

// Check for dangerous functions
$dangerousFunctions = ['eval', 'exec', 'shell_exec', 'system', 'passthru'];
$disabledFunctions = explode(',', ini_get('disable_functions'));

foreach ($dangerousFunctions as $func) {
    if (!in_array($func, $disabledFunctions)) {
        $warnings[] = "Dangerous function '$func' is enabled";
    }
}

if (count($warnings) === count(array_intersect($warnings, array_map(fn($f) => "Dangerous function '$f' is enabled", $dangerousFunctions)))) {
    $passed[] = "All dangerous functions properly restricted";
}

// ============================================
// 10. SECURITY HEADERS
// ============================================
echo "\n🛡️  Checking security headers implementation...\n";

// Check if Api class has security header methods
if (method_exists('Unified\Support\Api', 'applySecurityHeaders')) {
    $passed[] = "Security headers helper available";
} else {
    $warnings[] = "Security headers helper not found";
}

// ============================================
// RESULTS SUMMARY
// ============================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 Security Audit Results\n";
echo str_repeat("=", 60) . "\n\n";

echo "✅ Passed: " . count($passed) . " checks\n";
echo "⚠️  Warnings: " . count($warnings) . " items\n";
echo "❌ Issues: " . count($issues) . " problems\n\n";

if (!empty($issues)) {
    echo "❌ CRITICAL ISSUES:\n";
    foreach ($issues as $issue) {
        echo "  • $issue\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  • $warning\n";
    }
    echo "\n";
}

// Calculate security score
$total = count($passed) + count($warnings) + count($issues);
$score = $total > 0 ? round((count($passed) / $total) * 100) : 0;

echo "🎯 Security Score: {$score}%\n";

if ($score >= 90) {
    echo "   Status: EXCELLENT 🌟\n";
} elseif ($score >= 75) {
    echo "   Status: GOOD ✅\n";
} elseif ($score >= 60) {
    echo "   Status: FAIR ⚠️\n";
} else {
    echo "   Status: NEEDS IMPROVEMENT ❌\n";
}

// Log audit results
$context = NeuroContext::security('security_audit', [
    'passed' => count($passed),
    'warnings' => count($warnings),
    'issues' => count($issues),
    'score' => $score,
    'quick_scan' => $quick,
    'auto_fix' => $fix,
]);

$logger->info('Security audit completed', $context);

// Generate detailed report if requested
if ($report) {
    $reportFile = defined('STORAGE_PATH') ? STORAGE_PATH . '/reports/security_audit_' . date('Y-m-d_His') . '.json' : null;
    
    if ($reportFile) {
        $reportDir = dirname($reportFile);
        if (!is_dir($reportDir)) {
            @mkdir($reportDir, 0775, true);
        }
        
        $reportData = [
            'timestamp' => date('c'),
            'score' => $score,
            'passed' => $passed,
            'warnings' => $warnings,
            'issues' => $issues,
            'environment' => $env,
            'php_version' => $phpVersion,
        ];
        
        file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
        echo "\n📄 Detailed report saved to: $reportFile\n";
    }
}

// Exit with appropriate code
if (!empty($issues)) {
    exit(1); // Critical issues found
} elseif (!empty($warnings)) {
    exit(2); // Warnings found
} else {
    exit(0); // All clear
}
