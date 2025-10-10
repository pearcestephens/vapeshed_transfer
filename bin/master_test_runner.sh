#!/bin/bash
#
# MASTER TEST RUNNER - PINPOINT ACCURACY TESTING
# Comprehensive testing orchestrator for all system components
# 
# @package VapeshedTransfer
# @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
# @version 1.0.0
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer"
BASE_URL="${TEST_BASE_URL:-http://localhost}"
MASTER_LOG="/tmp/master_test_$(date +%Y%m%d_%H%M%S).log"

# Test suite results
COMPREHENSIVE_RESULT=0
PHP_VALIDATION_RESULT=0
SERVER_CODE_RESULT=0

echo -e "${WHITE}"
echo "╔═══════════════════════════════════════════════════════════════════════════════╗"
echo "║                    🔬 MASTER TEST RUNNER - PINPOINT ACCURACY                  ║"
echo "║                         COMPREHENSIVE SYSTEM VALIDATION                      ║"
echo "╚═══════════════════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${CYAN}Project Root:${NC} $PROJECT_ROOT"
echo -e "${CYAN}Base URL:${NC} $BASE_URL"
echo -e "${CYAN}Master Log:${NC} $MASTER_LOG"
echo -e "${CYAN}Started:${NC} $(date)"
echo ""

# Initialize master log
{
    echo "🔬 MASTER TEST RUNNER - PINPOINT ACCURACY"
    echo "=========================================="
    echo "Project Root: $PROJECT_ROOT"
    echo "Base URL: $BASE_URL"
    echo "Started: $(date)"
    echo ""
} > "$MASTER_LOG"

cd "$PROJECT_ROOT"

# Function to run test suite and capture results
run_test_suite() {
    local suite_name="$1"
    local suite_command="$2"
    local result_var="$3"
    
    echo -e "${BLUE}┌─────────────────────────────────────────────────────────────────┐${NC}"
    echo -e "${BLUE}│                        $suite_name                        │${NC}"
    echo -e "${BLUE}└─────────────────────────────────────────────────────────────────┘${NC}"
    echo ""
    
    echo "Running: $suite_command" | tee -a "$MASTER_LOG"
    
    if eval "$suite_command" 2>&1 | tee -a "$MASTER_LOG"; then
        local exit_code=${PIPESTATUS[0]}
        if [ $exit_code -eq 0 ]; then
            echo -e "${GREEN}✅ $suite_name COMPLETED SUCCESSFULLY${NC}" | tee -a "$MASTER_LOG"
            eval "$result_var=0"
        else
            echo -e "${RED}❌ $suite_name FAILED (exit code: $exit_code)${NC}" | tee -a "$MASTER_LOG"
            eval "$result_var=$exit_code"
        fi
    else
        echo -e "${RED}❌ $suite_name FAILED TO RUN${NC}" | tee -a "$MASTER_LOG"
        eval "$result_var=1"
    fi
    
    echo "" | tee -a "$MASTER_LOG"
}

echo -e "${YELLOW}🚀 STARTING COMPREHENSIVE TESTING SUITE${NC}"
echo "========================================="
echo ""

# PHASE 1: COMPREHENSIVE FILE AND STRUCTURE TESTING
echo -e "${PURPLE}PHASE 1: COMPREHENSIVE STRUCTURE & FILE TESTING${NC}" | tee -a "$MASTER_LOG"
run_test_suite "COMPREHENSIVE TESTS" "bash '$PROJECT_ROOT/bin/comprehensive_test_suite.sh'" "COMPREHENSIVE_RESULT"

# PHASE 2: PHP LINTING AND VALIDATION
echo -e "${PURPLE}PHASE 2: PHP LINTING & VALIDATION TESTING${NC}" | tee -a "$MASTER_LOG"
run_test_suite "PHP VALIDATION" "php '$PROJECT_ROOT/bin/php_validation_suite.php'" "PHP_VALIDATION_RESULT"

# PHASE 3: SERVER CODE INTEGRATION TESTING
echo -e "${PURPLE}PHASE 3: SERVER CODE INTEGRATION TESTING${NC}" | tee -a "$MASTER_LOG"
run_test_suite "SERVER CODE TESTS" "php '$PROJECT_ROOT/bin/server_code_test_suite.php' '$BASE_URL'" "SERVER_CODE_RESULT"

# PHASE 4: ADDITIONAL PINPOINT ACCURACY TESTS
echo -e "${PURPLE}PHASE 4: PINPOINT ACCURACY VALIDATION${NC}" | tee -a "$MASTER_LOG"

echo -e "${CYAN}🎯 RUNNING PINPOINT ACCURACY TESTS${NC}"

# Test 1: Verify all critical files exist with exact paths
echo -e "${BLUE}[PINPOINT TEST 1]${NC} Critical file existence validation"
CRITICAL_FILES=(
    "$PROJECT_ROOT/transfer_engine/app/Controllers/DashboardController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/WebhookLabController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/VendTesterController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/LightspeedTesterController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/QueueJobTesterController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/SuiteRunnerController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/SnippetLibraryController.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/dashboard/main.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/main.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/webhook.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/vend.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/lightspeed.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/queue.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/suite.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/snippets.php"
    "$PROJECT_ROOT/routes/admin.php"
    "$PROJECT_ROOT/PHASE_3_COMPLETION_SUMMARY.md"
)

MISSING_FILES=0
for file in "${CRITICAL_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo -e "${RED}❌ Missing: $file${NC}" | tee -a "$MASTER_LOG"
        ((MISSING_FILES++))
    else
        echo -e "${GREEN}✅ Found: $(basename "$file")${NC}"
    fi
done

if [ $MISSING_FILES -eq 0 ]; then
    echo -e "${GREEN}✅ All critical files present${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ $MISSING_FILES critical files missing${NC}" | tee -a "$MASTER_LOG"
fi

# Test 2: Verify PHP syntax for all controllers
echo -e "${BLUE}[PINPOINT TEST 2]${NC} PHP syntax validation for controllers"
SYNTAX_ERRORS=0
for file in "${CRITICAL_FILES[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        if ! php -l "$file" >/dev/null 2>&1; then
            echo -e "${RED}❌ Syntax error in: $(basename "$file")${NC}" | tee -a "$MASTER_LOG"
            ((SYNTAX_ERRORS++))
        fi
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ All PHP files have valid syntax${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ $SYNTAX_ERRORS PHP syntax errors found${NC}" | tee -a "$MASTER_LOG"
fi

# Test 3: Verify line counts match expected ranges
echo -e "${BLUE}[PINPOINT TEST 3]${NC} Code complexity validation"
declare -A EXPECTED_LINES=(
    ["WebhookLabController.php"]=400
    ["VendTesterController.php"]=400  
    ["LightspeedTesterController.php"]=400
    ["QueueJobTesterController.php"]=400
    ["SuiteRunnerController.php"]=400
    ["SnippetLibraryController.php"]=400
    ["webhook.php"]=1200
    ["vend.php"]=1100
    ["lightspeed.php"]=1300
    ["queue.php"]=1000
    ["suite.php"]=1400
    ["snippets.php"]=1200
)

LINE_COUNT_ISSUES=0
for filename in "${!EXPECTED_LINES[@]}"; do
    file_path=$(find "$PROJECT_ROOT" -name "$filename" -type f | head -1)
    if [ -f "$file_path" ]; then
        actual_lines=$(wc -l < "$file_path")
        expected_lines=${EXPECTED_LINES[$filename]}
        min_lines=$((expected_lines - 100))
        max_lines=$((expected_lines + 500))
        
        if [ $actual_lines -lt $min_lines ] || [ $actual_lines -gt $max_lines ]; then
            echo -e "${YELLOW}⚠️  $filename: $actual_lines lines (expected ~$expected_lines)${NC}" | tee -a "$MASTER_LOG"
            ((LINE_COUNT_ISSUES++))
        else
            echo -e "${GREEN}✅ $filename: $actual_lines lines${NC}"
        fi
    fi
done

if [ $LINE_COUNT_ISSUES -eq 0 ]; then
    echo -e "${GREEN}✅ All files have appropriate complexity${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${YELLOW}⚠️  $LINE_COUNT_ISSUES files have unexpected line counts${NC}" | tee -a "$MASTER_LOG"
fi

# Test 4: Verify class and function definitions
echo -e "${BLUE}[PINPOINT TEST 4]${NC} Class and function definition validation"
CLASS_ISSUES=0

# Check controllers have proper class definitions
CONTROLLER_FILES=(
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/WebhookLabController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/VendTesterController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/LightspeedTesterController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/QueueJobTesterController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/SuiteRunnerController.php"
    "$PROJECT_ROOT/transfer_engine/app/Controllers/Api/SnippetLibraryController.php"
)

for controller in "${CONTROLLER_FILES[@]}"; do
    if [ -f "$controller" ]; then
        class_name=$(basename "$controller" .php)
        if ! grep -q "class $class_name" "$controller"; then
            echo -e "${RED}❌ Missing class definition in: $(basename "$controller")${NC}" | tee -a "$MASTER_LOG"
            ((CLASS_ISSUES++))
        else
            echo -e "${GREEN}✅ Class definition found in: $(basename "$controller")${NC}"
        fi
    fi
done

if [ $CLASS_ISSUES -eq 0 ]; then
    echo -e "${GREEN}✅ All classes properly defined${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ $CLASS_ISSUES class definition issues found${NC}" | tee -a "$MASTER_LOG"
fi

# Test 5: Verify view templates have required elements
echo -e "${BLUE}[PINPOINT TEST 5]${NC} View template content validation"
VIEW_ISSUES=0

VIEW_FILES=(
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/webhook.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/vend.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/lightspeed.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/queue.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/suite.php"
    "$PROJECT_ROOT/transfer_engine/resources/views/admin/api-lab/snippets.php"
)

for view in "${VIEW_FILES[@]}"; do
    if [ -f "$view" ]; then
        view_name=$(basename "$view")
        
        # Check for essential HTML elements
        if ! grep -q '<div' "$view"; then
            echo -e "${RED}❌ Missing HTML structure in: $view_name${NC}" | tee -a "$MASTER_LOG"
            ((VIEW_ISSUES++))
        elif ! grep -q 'class=' "$view"; then
            echo -e "${RED}❌ Missing CSS classes in: $view_name${NC}" | tee -a "$MASTER_LOG"
            ((VIEW_ISSUES++))
        else
            echo -e "${GREEN}✅ HTML structure valid in: $view_name${NC}"
        fi
    fi
done

if [ $VIEW_ISSUES -eq 0 ]; then
    echo -e "${GREEN}✅ All view templates properly structured${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ $VIEW_ISSUES view template issues found${NC}" | tee -a "$MASTER_LOG"
fi

echo ""

# FINAL RESULTS COMPILATION
echo -e "${WHITE}"
echo "╔═══════════════════════════════════════════════════════════════════════════════╗"
echo "║                           🏁 FINAL TEST RESULTS                              ║"
echo "╚═══════════════════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

{
    echo ""
    echo "=== FINAL TEST RESULTS ==="
    echo "=========================="
    echo "Completed: $(date)"
    echo ""
    echo "TEST SUITE RESULTS:"
    echo "==================="
} >> "$MASTER_LOG"

TOTAL_FAILURES=0

echo -e "${CYAN}Test Suite Results:${NC}"
echo "==================="

if [ $COMPREHENSIVE_RESULT -eq 0 ]; then
    echo -e "${GREEN}✅ COMPREHENSIVE TESTS: PASSED${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ COMPREHENSIVE TESTS: FAILED${NC}" | tee -a "$MASTER_LOG"
    ((TOTAL_FAILURES++))
fi

if [ $PHP_VALIDATION_RESULT -eq 0 ]; then
    echo -e "${GREEN}✅ PHP VALIDATION: PASSED${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ PHP VALIDATION: FAILED${NC}" | tee -a "$MASTER_LOG"
    ((TOTAL_FAILURES++))
fi

if [ $SERVER_CODE_RESULT -eq 0 ]; then
    echo -e "${GREEN}✅ SERVER CODE TESTS: PASSED${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ SERVER CODE TESTS: FAILED${NC}" | tee -a "$MASTER_LOG"
    ((TOTAL_FAILURES++))
fi

echo ""
echo -e "${CYAN}Pinpoint Accuracy Results:${NC}"
echo "=========================="

PINPOINT_FAILURES=0
[ $MISSING_FILES -gt 0 ] && ((PINPOINT_FAILURES++))
[ $SYNTAX_ERRORS -gt 0 ] && ((PINPOINT_FAILURES++))
[ $CLASS_ISSUES -gt 0 ] && ((PINPOINT_FAILURES++))
[ $VIEW_ISSUES -gt 0 ] && ((PINPOINT_FAILURES++))

if [ $PINPOINT_FAILURES -eq 0 ]; then
    echo -e "${GREEN}✅ PINPOINT ACCURACY: ALL CHECKS PASSED${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${RED}❌ PINPOINT ACCURACY: $PINPOINT_FAILURES ISSUES FOUND${NC}" | tee -a "$MASTER_LOG"
    ((TOTAL_FAILURES++))
fi

echo ""
echo -e "${CYAN}Overall System Status:${NC}"
echo "====================="

if [ $TOTAL_FAILURES -eq 0 ]; then
    echo -e "${GREEN}"
    echo "🎉 CONGRATULATIONS! SYSTEM PASSES ALL TESTS WITH PINPOINT ACCURACY!"
    echo "=================================================================="
    echo "✅ All comprehensive tests passed"
    echo "✅ All PHP validation checks passed"  
    echo "✅ All server code tests passed"
    echo "✅ All pinpoint accuracy validations passed"
    echo ""
    echo "🚀 SYSTEM IS PRODUCTION READY AND FULLY VALIDATED!"
    echo -e "${NC}"
    
    {
        echo ""
        echo "🎉 CONGRATULATIONS! SYSTEM PASSES ALL TESTS WITH PINPOINT ACCURACY!"
        echo "=================================================================="
        echo "FINAL STATUS: PRODUCTION READY ✅"
        echo "ALL VALIDATION CHECKS: PASSED ✅"
        echo "PINPOINT ACCURACY: ACHIEVED ✅"
    } >> "$MASTER_LOG"
    
    exit 0
else
    echo -e "${RED}"
    echo "⚠️  TESTING COMPLETED WITH ISSUES REQUIRING ATTENTION"
    echo "====================================================="
    echo "❌ Total test suite failures: $TOTAL_FAILURES"
    echo "❌ Pinpoint accuracy issues: $PINPOINT_FAILURES"
    echo ""
    echo "🔍 Review the detailed logs for specific issues:"
    echo "   - Master log: $MASTER_LOG"
    echo ""
    echo "🛠️  Address the identified issues before deployment."
    echo -e "${NC}"
    
    {
        echo ""
        echo "⚠️  TESTING COMPLETED WITH ISSUES"
        echo "================================="
        echo "FINAL STATUS: ISSUES FOUND ❌"
        echo "Total failures: $TOTAL_FAILURES"
        echo "Pinpoint issues: $PINPOINT_FAILURES"
        echo "RECOMMENDATION: Address issues before production deployment"
    } >> "$MASTER_LOG"
    
    exit 1
fi