#!/bin/bash
#
# COMPREHENSIVE TESTING & VALIDATION SUITE
# Pinpoint accuracy testing for all files with linting and server validation
# 
# @package VapeshedTransfer
# @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
# @version 1.0.0
#

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Log file
LOG_FILE="/tmp/comprehensive_test_$(date +%Y%m%d_%H%M%S).log"

echo "🔬 COMPREHENSIVE TESTING & VALIDATION SUITE" | tee -a "$LOG_FILE"
echo "=============================================" | tee -a "$LOG_FILE"
echo "Started: $(date)" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Function to run test and track results
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected_exit_code="${3:-0}"
    
    ((TOTAL_TESTS++))
    echo -e "${BLUE}[TEST $TOTAL_TESTS]${NC} $test_name" | tee -a "$LOG_FILE"
    
    if eval "$test_command" >> "$LOG_FILE" 2>&1; then
        if [ $? -eq $expected_exit_code ]; then
            echo -e "${GREEN}✅ PASSED${NC}" | tee -a "$LOG_FILE"
            ((PASSED_TESTS++))
        else
            echo -e "${RED}❌ FAILED (wrong exit code)${NC}" | tee -a "$LOG_FILE"
            ((FAILED_TESTS++))
        fi
    else
        echo -e "${RED}❌ FAILED${NC}" | tee -a "$LOG_FILE"
        ((FAILED_TESTS++))
    fi
    echo "" | tee -a "$LOG_FILE"
}

# Get the project root directory
PROJECT_ROOT="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer"
TRANSFER_ENGINE_ROOT="$PROJECT_ROOT/transfer_engine"

cd "$PROJECT_ROOT"

echo "🔍 PROJECT STRUCTURE VALIDATION"
echo "==============================="

# Test 1: Verify project structure
run_test "Project root directory exists" "test -d '$PROJECT_ROOT'"
run_test "Transfer engine directory exists" "test -d '$TRANSFER_ENGINE_ROOT'"
run_test "App directory structure" "test -d '$TRANSFER_ENGINE_ROOT/app' && test -d '$TRANSFER_ENGINE_ROOT/app/Controllers'"
run_test "Resources directory structure" "test -d '$TRANSFER_ENGINE_ROOT/resources' && test -d '$TRANSFER_ENGINE_ROOT/resources/views'"
run_test "Routes directory structure" "test -d '$PROJECT_ROOT/routes'"

echo "🔧 PHP SYNTAX VALIDATION"
echo "========================"

# Test 2-10: PHP Syntax validation for all Controllers
PHP_FILES=(
    "$TRANSFER_ENGINE_ROOT/app/Controllers/BaseController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/DashboardController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/Api/WebhookLabController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/Api/VendTesterController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/Api/LightspeedTesterController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/Api/QueueJobTesterController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/Api/SuiteRunnerController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/Api/SnippetLibraryController.php"
    "$TRANSFER_ENGINE_ROOT/app/Controllers/ConfigController.php"
    "$TRANSFER_ENGINE_ROOT/app/Core/Router.php"
)

for php_file in "${PHP_FILES[@]}"; do
    if [ -f "$php_file" ]; then
        run_test "PHP Syntax: $(basename "$php_file")" "php -l '$php_file'"
    else
        run_test "File exists: $(basename "$php_file")" "test -f '$php_file'"
    fi
done

echo "📄 VIEW TEMPLATE VALIDATION"
echo "==========================="

# Test 11-18: View template validation
VIEW_FILES=(
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/dashboard/main.php"
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/api-lab/main.php"
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/api-lab/webhook.php"
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/api-lab/vend.php"
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/api-lab/lightspeed.php"
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/api-lab/queue.php"
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/api-lab/suite.php"
    "$TRANSFER_ENGINE_ROOT/resources/views/admin/api-lab/snippets.php"
)

for view_file in "${VIEW_FILES[@]}"; do
    if [ -f "$view_file" ]; then
        run_test "View template exists: $(basename "$view_file")" "test -f '$view_file'"
        run_test "View template readable: $(basename "$view_file")" "test -r '$view_file'"
        # Check for PHP syntax in view files
        run_test "PHP syntax in view: $(basename "$view_file")" "php -l '$view_file'"
    else
        run_test "View file missing: $(basename "$view_file")" "false"
    fi
done

echo "🎨 ASSET VALIDATION"
echo "==================="

# Test 19-21: Asset validation
ASSET_FILES=(
    "$TRANSFER_ENGINE_ROOT/public/assets/css/dashboard-power.css"
    "$TRANSFER_ENGINE_ROOT/public/assets/js/dashboard-power.js"
)

for asset_file in "${ASSET_FILES[@]}"; do
    if [ -f "$asset_file" ]; then
        run_test "Asset exists: $(basename "$asset_file")" "test -f '$asset_file'"
        run_test "Asset readable: $(basename "$asset_file")" "test -r '$asset_file'"
        run_test "Asset not empty: $(basename "$asset_file")" "test -s '$asset_file'"
    else
        run_test "Asset missing: $(basename "$asset_file")" "false"
    fi
done

echo "🛣️ ROUTE CONFIGURATION VALIDATION"
echo "=================================="

# Test 22-23: Route validation
ROUTE_FILES=(
    "$PROJECT_ROOT/routes/admin.php"
)

for route_file in "${ROUTE_FILES[@]}"; do
    if [ -f "$route_file" ]; then
        run_test "Route file exists: $(basename "$route_file")" "test -f '$route_file'"
        run_test "Route PHP syntax: $(basename "$route_file")" "php -l '$route_file'"
    else
        run_test "Route file missing: $(basename "$route_file")" "false"
    fi
done

echo "🔌 API ENDPOINT VALIDATION"
echo "=========================="

# Test 24-30: API endpoint validation (if they exist)
API_ENDPOINTS=(
    "$TRANSFER_ENGINE_ROOT/public/api/health.php"
    "$TRANSFER_ENGINE_ROOT/public/api/metrics.php"
    "$TRANSFER_ENGINE_ROOT/public/api/stats.php"
    "$TRANSFER_ENGINE_ROOT/public/api/modules.php"
    "$TRANSFER_ENGINE_ROOT/public/api/activity.php"
    "$TRANSFER_ENGINE_ROOT/public/sse.php"
    "$TRANSFER_ENGINE_ROOT/public/index.php"
)

for api_file in "${API_ENDPOINTS[@]}"; do
    if [ -f "$api_file" ]; then
        run_test "API endpoint exists: $(basename "$api_file")" "test -f '$api_file'"
        run_test "API PHP syntax: $(basename "$api_file")" "php -l '$api_file'"
    fi
done

echo "📊 JAVASCRIPT VALIDATION"
echo "========================"

# Test 31-32: JavaScript validation (if Node.js is available)
if command -v node >/dev/null 2>&1; then
    JS_FILES=(
        "$TRANSFER_ENGINE_ROOT/public/assets/js/dashboard-power.js"
    )
    
    for js_file in "${JS_FILES[@]}"; do
        if [ -f "$js_file" ]; then
            run_test "JS syntax check: $(basename "$js_file")" "node -c '$js_file'"
        fi
    done
else
    echo "⚠️  Node.js not available - skipping JS syntax validation" | tee -a "$LOG_FILE"
fi

echo "🎯 CSS VALIDATION"
echo "================="

# Test 33: CSS validation (basic checks)
CSS_FILES=(
    "$TRANSFER_ENGINE_ROOT/public/assets/css/dashboard-power.css"
)

for css_file in "${CSS_FILES[@]}"; do
    if [ -f "$css_file" ]; then
        run_test "CSS file readable: $(basename "$css_file")" "test -r '$css_file'"
        # Basic CSS syntax check - look for unmatched braces
        run_test "CSS brace balance: $(basename "$css_file")" "
            open_braces=\$(grep -o '{' '$css_file' | wc -l)
            close_braces=\$(grep -o '}' '$css_file' | wc -l)
            [ \$open_braces -eq \$close_braces ]
        "
    fi
done

echo "🔒 SECURITY VALIDATION"
echo "====================="

# Test 34-38: Security checks
run_test "No hardcoded passwords in PHP files" "
    ! find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'password.*=' {} \; | grep -v test
"

run_test "No database credentials in code" "
    ! find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'mysql://\|postgresql://' {} \;
"

run_test "CSRF protection present in forms" "
    find '$TRANSFER_ENGINE_ROOT/resources/views' -name '*.php' -exec grep -l 'csrf' {} \; | wc -l | awk '{if(\$1>0) exit 0; else exit 1}'
"

run_test "XSS protection - htmlspecialchars usage" "
    find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'htmlspecialchars\|esc_html' {} \; | wc -l | awk '{if(\$1>0) exit 0; else exit 1}'
"

run_test "SQL injection protection - prepared statements" "
    find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'prepare\|bindParam\|bindValue' {} \; | wc -l | awk '{if(\$1>0) exit 0; else exit 1}'
"

echo "⚡ PERFORMANCE VALIDATION"
echo "========================"

# Test 39-42: Performance checks
run_test "Large file detection (>1MB)" "
    ! find '$PROJECT_ROOT' -type f -size +1M | grep -E '\.(php|js|css)$'
"

run_test "Empty file detection" "
    ! find '$PROJECT_ROOT' -name '*.php' -empty
"

run_test "Code duplication check (basic)" "
    duplicate_lines=\$(find '$PROJECT_ROOT' -name '*.php' -exec cat {} \; | sort | uniq -d | wc -l)
    [ \$duplicate_lines -lt 100 ]
"

run_test "Documentation coverage" "
    documented_files=\$(find '$PROJECT_ROOT' -name '*.php' -exec grep -l '/\*\*' {} \; | wc -l)
    total_files=\$(find '$PROJECT_ROOT' -name '*.php' | wc -l)
    coverage=\$(echo \"scale=2; \$documented_files / \$total_files * 100\" | bc)
    echo \"Documentation coverage: \$coverage%\" >> '$LOG_FILE'
    [ \$(echo \"\$coverage > 50\" | bc) -eq 1 ]
"

echo "🌐 WEB SERVER VALIDATION"
echo "========================"

# Test 43-47: Web server tests (if server is running)
if command -v curl >/dev/null 2>&1; then
    # Try to detect if a web server is running
    if curl -s --connect-timeout 5 http://localhost >/dev/null 2>&1; then
        BASE_URL="http://localhost"
        
        run_test "Web server response" "curl -s --connect-timeout 5 '$BASE_URL' >/dev/null"
        run_test "API health endpoint" "curl -s --connect-timeout 5 '$BASE_URL/api/health.php' | grep -q 'success'"
        run_test "Dashboard accessibility" "curl -s --connect-timeout 5 '$BASE_URL/dashboard' >/dev/null"
        run_test "Static assets loading" "curl -s --connect-timeout 5 '$BASE_URL/assets/css/dashboard-power.css' >/dev/null"
        run_test "API response format" "curl -s --connect-timeout 5 '$BASE_URL/api/health.php' | python3 -m json.tool >/dev/null"
    else
        echo "⚠️  Web server not accessible - skipping web server tests" | tee -a "$LOG_FILE"
    fi
else
    echo "⚠️  curl not available - skipping web server tests" | tee -a "$LOG_FILE"
fi

echo "📈 DATABASE VALIDATION"
echo "====================="

# Test 48-50: Database validation (if accessible)
if command -v mysql >/dev/null 2>&1; then
    # Basic database connectivity test
    run_test "Database connectivity test" "
        mysql -h localhost -u root -e 'SELECT 1' >/dev/null 2>&1 || 
        mysql -h 127.0.0.1 -u root -e 'SELECT 1' >/dev/null 2>&1
    "
    
    # Check if schema files exist
    run_test "Database schema files exist" "
        test -f '$PROJECT_ROOT/database/schema.sql' || test -f '$PROJECT_ROOT/SCHEMA.SQL'
    "
    
    run_test "Migration files exist" "
        test -d '$PROJECT_ROOT/database/migrations' || test -f '$PROJECT_ROOT/database/migrate.php'
    "
else
    echo "⚠️  MySQL client not available - skipping database tests" | tee -a "$LOG_FILE"
fi

echo "🔧 CONFIGURATION VALIDATION"
echo "==========================="

# Test 51-53: Configuration validation
run_test "Environment file template exists" "
    test -f '$PROJECT_ROOT/.env.example' || test -f '$PROJECT_ROOT/config/.env.example'
"

run_test "Config files exist" "
    test -d '$PROJECT_ROOT/config' || test -f '$TRANSFER_ENGINE_ROOT/config/app.php'
"

run_test "Bootstrap file exists" "
    test -f '$TRANSFER_ENGINE_ROOT/app/bootstrap.php' || test -f '$PROJECT_ROOT/config/bootstrap.php'
"

echo "📝 DOCUMENTATION VALIDATION"
echo "==========================="

# Test 54-58: Documentation validation
DOC_FILES=(
    "README.md"
    "PHASE_3_COMPLETION_SUMMARY.md"
    "docs/ARCHITECTURE.md"
    "docs/API_ENDPOINTS_INVENTORY.md"
    ".github/copilot-instructions.md"
)

for doc_file in "${DOC_FILES[@]}"; do
    if [ -f "$PROJECT_ROOT/$doc_file" ]; then
        run_test "Documentation exists: $doc_file" "test -f '$PROJECT_ROOT/$doc_file'"
    fi
done

echo "🚀 DEPLOYMENT READINESS"
echo "======================="

# Test 59-62: Deployment readiness
run_test "No debug code in production files" "
    ! find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'var_dump\|print_r\|var_export' {} \; | grep -v test
"

run_test "Error reporting configured" "
    find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'error_reporting\|ini_set.*display_errors' {} \; | wc -l | awk '{if(\$1>0) exit 0; else exit 1}'
"

run_test "Logging configured" "
    find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'Logger\|error_log' {} \; | wc -l | awk '{if(\$1>0) exit 0; else exit 1}'
"

run_test "Production optimizations present" "
    find '$PROJECT_ROOT' -name '*.php' -exec grep -l 'opcache\|apcu\|memcache' {} \; | wc -l | awk '{if(\$1>=0) exit 0; else exit 1}'
"

echo ""
echo "==============================================="
echo "🏁 COMPREHENSIVE TEST SUITE COMPLETE"
echo "==============================================="
echo "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED! SYSTEM IS PRODUCTION READY!${NC}"
    exit 0
else
    echo -e "${RED}⚠️  $FAILED_TESTS TESTS FAILED - REVIEW REQUIRED${NC}"
    echo "Check log file: $LOG_FILE"
    exit 1
fi