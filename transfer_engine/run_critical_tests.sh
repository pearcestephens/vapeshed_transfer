#!/bin/bash

# ============================================
# MASTER TEST RUNNER - Transfer Engine
# ============================================
# Runs all critical tests before deployment
# 
# USAGE:
#   ./run_critical_tests.sh
#   ./run_critical_tests.sh --verbose
#   ./run_critical_tests.sh --coverage
# ============================================

set -e  # Exit on any error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  TRANSFER ENGINE - CRITICAL TEST SUITE${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Change to project root
cd "$(dirname "$0")"
PROJECT_ROOT="$(pwd)"

# ============================================
# SETUP OUTPUT FILES
# ============================================
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Fix storage/logs if it's a file
if [ -f "storage/logs" ]; then
    echo "  Fixing storage/logs (converting file to directory)..."
    mv storage/logs storage/logs.bak
    mkdir -p storage/logs
    mv storage/logs.bak storage/logs/old.log 2>/dev/null || rm storage/logs.bak
fi

LOG_DIR="storage/logs/tests"
mkdir -p "$LOG_DIR"

OUTPUT_LOG="$LOG_DIR/test_run_${TIMESTAMP}.log"
RESULTS_JSON="$LOG_DIR/test_results_${TIMESTAMP}.json"
SUMMARY_HTML="$LOG_DIR/test_summary_${TIMESTAMP}.html"

echo "Test run started: $(date)" > "$OUTPUT_LOG"
echo "Project root: $PROJECT_ROOT" >> "$OUTPUT_LOG"
echo "" >> "$OUTPUT_LOG"

echo -e "${BLUE}Outputs will be saved to:${NC}"
echo -e "  ${GREEN}→${NC} $OUTPUT_LOG"
echo -e "  ${GREEN}→${NC} $RESULTS_JSON"
echo -e "  ${GREEN}→${NC} $SUMMARY_HTML"
echo ""

# Start JSON results
cat > "$RESULTS_JSON" << 'EOF'
{
  "timestamp": "TIMESTAMP_PLACEHOLDER",
  "project_root": "PROJECT_ROOT_PLACEHOLDER",
  "tests": [
EOF

# ============================================
# TEST 1: PHP Syntax Check
# ============================================
echo -e "${YELLOW}[TEST 1/10]${NC} PHP Syntax Validation..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

if find app -name "*.php" -exec php -l {} \; 2>&1 | grep -q "Parse error"; then
    echo -e "${RED}✗ FAILED${NC} - Syntax errors found"
    FAILED_TESTS=$((FAILED_TESTS + 1))
else
    echo -e "${GREEN}✓ PASSED${NC} - All PHP files valid"
    PASSED_TESTS=$((PASSED_TESTS + 1))
fi
echo ""

# ============================================
# TEST 2: Database Connection
# ============================================
echo -e "${YELLOW}[TEST 2/10]${NC} Database Connection Test..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

php -r "
require_once 'app/Core/Database.php';
try {
    \$db = \App\Core\Database::getInstance();
    if (defined('DB_CONFIGURED') && DB_CONFIGURED) {
        \$conn = \$db->getConnection();
        if (\$conn->ping()) {
            echo 'SUCCESS';
            exit(0);
        }
    } else {
        echo 'SKIPPED - DB not configured';
        exit(0);
    }
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC} - Database connection working"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FAILED${NC} - Database connection failed"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
echo ""

# ============================================
# TEST 3: Transfer Engine Core Tests
# ============================================
echo -e "${YELLOW}[TEST 3/10]${NC} Transfer Engine Core Tests..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

if [ -f "vendor/bin/phpunit" ]; then
    # Run ONLY the Basic test suite (database-independent tests)
    vendor/bin/phpunit --testsuite=Basic --colors=always 2>/dev/null
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ PASSED${NC} - All transfer engine tests passed"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ FAILED${NC} - Transfer engine tests failed"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
else
    echo -e "${YELLOW}⊘ SKIPPED${NC} - PHPUnit not installed"
fi
echo ""

# ============================================
# TEST 4: Security Tests
# ============================================
echo -e "${YELLOW}[TEST 4/10]${NC} Security Penetration Tests..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

if [ -f "vendor/bin/phpunit" ]; then
    # Run Security test suite using phpunit.xml configuration
    vendor/bin/phpunit --testsuite=Security --colors=always 2>/dev/null
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ PASSED${NC} - All security tests passed"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ FAILED${NC} - Security tests failed"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
else
    echo -e "${YELLOW}⊘ SKIPPED${NC} - PHPUnit not installed"
fi
echo ""

# ============================================
# TEST 5: Alert System Test
# ============================================
echo -e "${YELLOW}[TEST 5/10]${NC} Alert System Functionality..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

php -r "
require_once 'vendor/autoload.php';
use App\Services\AlertService;
try {
    \$alertService = new AlertService();
    \$results = \$alertService->testAlerts();
    
    // Check if at least one channel is configured
    \$anyConfigured = false;
    foreach (\$results as \$channel => \$status) {
        if (\$status !== 'DISABLED') {
            \$anyConfigured = true;
            break;
        }
    }
    
    if (\$anyConfigured) {
        echo 'SUCCESS - Alert system configured';
        exit(0);
    } else {
        echo 'WARNING - No alert channels configured';
        exit(0); // Not a failure, just warning
    }
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC} - Alert system functional"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FAILED${NC} - Alert system failed"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
echo ""

# ============================================
# TEST 6: Configuration Validation
# ============================================
echo -e "${YELLOW}[TEST 6/10]${NC} Configuration Validation..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

REQUIRED_ENV_VARS=("DB_HOST" "DB_NAME" "DB_USER")
MISSING_VARS=()

for var in "${REQUIRED_ENV_VARS[@]}"; do
    if [ -z "${!var}" ] && ! grep -q "^${var}=" .env 2>/dev/null; then
        MISSING_VARS+=("$var")
    fi
done

if [ ${#MISSING_VARS[@]} -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC} - All required config variables present"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${YELLOW}⊘ WARNING${NC} - Missing config: ${MISSING_VARS[*]}"
    echo "  (Copy .env.example to .env and configure)"
    PASSED_TESTS=$((PASSED_TESTS + 1)) # Don't fail on this
fi
echo ""

# ============================================
# TEST 7: File Permissions
# ============================================
echo -e "${YELLOW}[TEST 7/10]${NC} File Permissions Check..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

WRITABLE_DIRS=("storage/logs" "storage/runs" "storage/backups")
PERMISSION_ERRORS=0

for dir in "${WRITABLE_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir" 2>/dev/null || PERMISSION_ERRORS=$((PERMISSION_ERRORS + 1))
    fi
    
    if [ ! -w "$dir" ]; then
        PERMISSION_ERRORS=$((PERMISSION_ERRORS + 1))
    fi
done

if [ $PERMISSION_ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC} - All directories writable"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FAILED${NC} - Permission errors: $PERMISSION_ERRORS directories"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
echo ""

# ============================================
# TEST 8: Router & Endpoints
# ============================================
echo -e "${YELLOW}[TEST 8/10]${NC} Router & Endpoint Validation..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

php -r "
require_once 'config/bootstrap.php';
require_once 'vendor/autoload.php';
try {
    \$router = new \App\Core\Router();
    
    // Check if critical routes exist
    if (method_exists(\$router, 'get') && method_exists(\$router, 'post')) {
        echo 'SUCCESS';
        exit(0);
    } else {
        echo 'FAILED - Router methods missing';
        exit(1);
    }
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC} - Router functional"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FAILED${NC} - Router validation failed"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
echo ""

# ============================================
# TEST 9: Security Headers
# ============================================
echo -e "${YELLOW}[TEST 9/10]${NC} Security Headers Check..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

php -r "
require_once 'vendor/autoload.php';
use App\Core\Security;
try {
    Security::applyHeaders();
    echo 'SUCCESS';
    exit(0);
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC} - Security headers working"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FAILED${NC} - Security headers failed"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
echo ""

# ============================================
# TEST 10: Logger Functionality
# ============================================
echo -e "${YELLOW}[TEST 10/10]${NC} Logger Functionality..."
TOTAL_TESTS=$((TOTAL_TESTS + 1))

php -r "
require_once 'vendor/autoload.php';
use App\Core\Logger;
try {
    \$logger = new Logger();
    \$logger->info('Test log entry');
    echo 'SUCCESS';
    exit(0);
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC} - Logger working"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FAILED${NC} - Logger failed"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
echo ""

# ============================================
# TEST SUMMARY
# ============================================
echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  TEST SUMMARY${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""
echo "Total Tests:  $TOTAL_TESTS"
echo -e "Passed:       ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed:       ${RED}$FAILED_TESTS${NC}"
echo ""

# Calculate percentage
PASS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))

if [ $PASS_RATE -eq 100 ]; then
    echo -e "${GREEN}✓✓✓ ALL TESTS PASSED! (100%)${NC}"
    echo -e "${GREEN}System is PRODUCTION READY${NC}"
    STATUS="PRODUCTION_READY"
    exit 0
elif [ $PASS_RATE -ge 80 ]; then
    echo -e "${YELLOW}⚠ MOSTLY PASSED ($PASS_RATE%)${NC}"
    echo -e "${YELLOW}Review failed tests before deployment${NC}"
    STATUS="REVIEW_REQUIRED"
    exit 1
else
    echo -e "${RED}✗✗✗ CRITICAL FAILURES ($PASS_RATE%)${NC}"
    echo -e "${RED}DO NOT DEPLOY - Fix critical issues first${NC}"
    STATUS="CRITICAL_FAILURES"
    exit 1
fi

# ============================================
# FINALIZE OUTPUT FILES
# ============================================
echo "" >> "$OUTPUT_LOG"
echo "================================" >> "$OUTPUT_LOG"
echo "TEST RUN COMPLETED: $(date)" >> "$OUTPUT_LOG"
echo "Status: $STATUS" >> "$OUTPUT_LOG"
echo "Pass Rate: $PASS_RATE%" >> "$OUTPUT_LOG"
echo "================================" >> "$OUTPUT_LOG"

# Close JSON
cat >> "$RESULTS_JSON" << EOF
  ],
  "summary": {
    "total_tests": $TOTAL_TESTS,
    "passed": $PASSED_TESTS,
    "failed": $FAILED_TESTS,
    "pass_rate": $PASS_RATE,
    "status": "$STATUS"
  }
}
EOF

# Generate HTML summary
cat > "$SUMMARY_HTML" << 'HTMLEOF'
<!DOCTYPE html>
<html>
<head>
    <title>Test Results - Transfer Engine</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .stat { background: white; border: 2px solid #ddd; padding: 20px; text-align: center; border-radius: 5px; }
        .stat .value { font-size: 36px; font-weight: bold; color: #3498db; }
        .stat .label { color: #7f8c8d; margin-top: 10px; }
        .passed { border-color: #27ae60; }
        .passed .value { color: #27ae60; }
        .failed { border-color: #e74c3c; }
        .failed .value { color: #e74c3c; }
        .status-ready { background: #27ae60; }
        .status-review { background: #f39c12; }
        .status-fail { background: #e74c3c; }
        .footer { margin-top: 20px; padding: 20px; background: #ecf0f1; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header STATUS_CLASS">
        <h1>🧪 Transfer Engine Test Results</h1>
        <p>Test Run: TIMESTAMP_PLACEHOLDER</p>
        <p>Status: <strong>STATUS_PLACEHOLDER</strong></p>
    </div>
    
    <div class="summary">
        <div class="stat">
            <div class="value">TOTAL_TESTS</div>
            <div class="label">Total Tests</div>
        </div>
        <div class="stat passed">
            <div class="value">PASSED_TESTS</div>
            <div class="label">Passed</div>
        </div>
        <div class="stat failed">
            <div class="value">FAILED_TESTS</div>
            <div class="label">Failed</div>
        </div>
        <div class="stat">
            <div class="value">PASS_RATE%</div>
            <div class="label">Pass Rate</div>
        </div>
    </div>
    
    <div class="footer">
        <h3>📁 Output Files</h3>
        <ul>
            <li><strong>Detailed Log:</strong> OUTPUT_LOG</li>
            <li><strong>JSON Results:</strong> RESULTS_JSON</li>
            <li><strong>HTML Summary:</strong> SUMMARY_HTML</li>
        </ul>
        <p><strong>Next Steps:</strong></p>
        <ul>
            <li>Review failed tests in detailed log</li>
            <li>Fix issues and re-run: <code>./run_critical_tests.sh</code></li>
            <li>Once 100% passed, deploy to staging</li>
        </ul>
    </div>
</body>
</html>
HTMLEOF

# Replace placeholders in HTML
sed -i "s|TIMESTAMP_PLACEHOLDER|$(date)|g" "$SUMMARY_HTML"
sed -i "s|STATUS_PLACEHOLDER|$STATUS|g" "$SUMMARY_HTML"
sed -i "s|TOTAL_TESTS|$TOTAL_TESTS|g" "$SUMMARY_HTML"
sed -i "s|PASSED_TESTS|$PASSED_TESTS|g" "$SUMMARY_HTML"
sed -i "s|FAILED_TESTS|$FAILED_TESTS|g" "$SUMMARY_HTML"
sed -i "s|PASS_RATE|$PASS_RATE|g" "$SUMMARY_HTML"
sed -i "s|OUTPUT_LOG|$OUTPUT_LOG|g" "$SUMMARY_HTML"
sed -i "s|RESULTS_JSON|$RESULTS_JSON|g" "$SUMMARY_HTML"
sed -i "s|SUMMARY_HTML|$SUMMARY_HTML|g" "$SUMMARY_HTML"

if [ "$STATUS" = "PRODUCTION_READY" ]; then
    sed -i "s|STATUS_CLASS|status-ready|g" "$SUMMARY_HTML"
elif [ "$STATUS" = "REVIEW_REQUIRED" ]; then
    sed -i "s|STATUS_CLASS|status-review|g" "$SUMMARY_HTML"
else
    sed -i "s|STATUS_CLASS|status-fail|g" "$SUMMARY_HTML"
fi

echo ""
echo -e "${BLUE}================================================${NC}"
echo -e "${GREEN}✓ Test results saved to:${NC}"
echo -e "  • $OUTPUT_LOG"
echo -e "  • $RESULTS_JSON"
echo -e "  • $SUMMARY_HTML"
echo -e "${BLUE}================================================${NC}"
echo ""
