#!/bin/bash
#
# NUCLEAR TESTING SUITE - PHASE 3 API LAB
# Every test we can possibly throw at the API Testing Laboratory
#
# @package VapeshedTransfer
# @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
# @version 1.0.0
#

set +e  # Don't exit on errors, we want to see all failures

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m'

PROJECT_ROOT="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer"
TRANSFER_ENGINE="$PROJECT_ROOT/transfer_engine"

TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
WARNING_TESTS=0

LOG_FILE="/tmp/nuclear_test_$(date +%Y%m%d_%H%M%S).log"

echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║${WHITE}        🚀 NUCLEAR TESTING SUITE - PHASE 3 API LAB ${WHITE}           ${CYAN}║${NC}"
echo -e "${CYAN}║${WHITE}        THROW EVERYTHING AT IT - NO MERCY TESTING  ${WHITE}           ${CYAN}║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Project Root:${NC} $PROJECT_ROOT"
echo -e "${BLUE}Test Log:${NC} $LOG_FILE"
echo -e "${BLUE}Started:${NC} $(date)"
echo ""

cd "$PROJECT_ROOT"

# Test result function
test_result() {
    local test_name="$1"
    local result="$2"
    local details="${3:-}"
    
    ((TOTAL_TESTS++))
    
    if [ "$result" = "PASS" ]; then
        ((PASSED_TESTS++))
        echo -e "${GREEN}✅ PASS${NC} - $test_name"
        echo "PASS - $test_name" >> "$LOG_FILE"
    elif [ "$result" = "FAIL" ]; then
        ((FAILED_TESTS++))
        echo -e "${RED}❌ FAIL${NC} - $test_name"
        [ -n "$details" ] && echo -e "   ${RED}↳${NC} $details"
        echo "FAIL - $test_name - $details" >> "$LOG_FILE"
    elif [ "$result" = "WARN" ]; then
        ((WARNING_TESTS++))
        echo -e "${YELLOW}⚠️  WARN${NC} - $test_name"
        [ -n "$details" ] && echo -e "   ${YELLOW}↳${NC} $details"
        echo "WARN - $test_name - $details" >> "$LOG_FILE"
    fi
}

echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 1: FILE EXISTENCE & INTEGRITY${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Test all critical files exist
FILES_TO_TEST=(
    "transfer_engine/app/Controllers/Api/WebhookLabController.php"
    "transfer_engine/app/Controllers/Api/VendTesterController.php"
    "transfer_engine/app/Controllers/Api/LightspeedTesterController.php"
    "transfer_engine/app/Controllers/Api/QueueJobTesterController.php"
    "transfer_engine/app/Controllers/Api/SuiteRunnerController.php"
    "transfer_engine/app/Controllers/Api/SnippetLibraryController.php"
    "transfer_engine/resources/views/admin/api-lab/main.php"
    "transfer_engine/resources/views/admin/api-lab/webhook.php"
    "transfer_engine/resources/views/admin/api-lab/vend.php"
    "transfer_engine/resources/views/admin/api-lab/lightspeed.php"
    "transfer_engine/resources/views/admin/api-lab/queue.php"
    "transfer_engine/resources/views/admin/api-lab/suite.php"
    "transfer_engine/resources/views/admin/api-lab/snippets.php"
    "routes/admin.php"
)

for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ]; then
        test_result "File exists: $(basename $file)" "PASS"
    else
        test_result "File exists: $(basename $file)" "FAIL" "File not found at $file"
    fi
done

# Test files are readable
for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ] && [ -r "$file" ]; then
        test_result "File readable: $(basename $file)" "PASS"
    else
        test_result "File readable: $(basename $file)" "FAIL" "Cannot read file"
    fi
done

# Test files are not empty
for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ] && [ -s "$file" ]; then
        test_result "File not empty: $(basename $file)" "PASS"
    else
        test_result "File not empty: $(basename $file)" "FAIL" "File is empty"
    fi
done

# Test file sizes are reasonable
for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ]; then
        size=$(wc -c < "$file")
        if [ "$size" -gt 100 ] && [ "$size" -lt 1000000 ]; then
            test_result "File size reasonable: $(basename $file)" "PASS"
        else
            test_result "File size reasonable: $(basename $file)" "WARN" "Size: $size bytes"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 2: PHP SYNTAX & LINTING${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# PHP syntax check for all files
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        if php -l "$file" >/dev/null 2>&1; then
            test_result "PHP syntax: $(basename $file)" "PASS"
        else
            error=$(php -l "$file" 2>&1)
            test_result "PHP syntax: $(basename $file)" "FAIL" "$error"
        fi
    fi
done

# Check for PHP short tags (should not exist)
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        if grep -q "<?=" "$file" 2>/dev/null; then
            test_result "No PHP short tags: $(basename $file)" "WARN" "Found <?= usage"
        else
            test_result "No PHP short tags: $(basename $file)" "PASS"
        fi
    fi
done

# Check for trailing whitespace
for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ]; then
        if grep -q '[[:space:]]$' "$file" 2>/dev/null; then
            test_result "No trailing whitespace: $(basename $file)" "WARN" "Found trailing spaces"
        else
            test_result "No trailing whitespace: $(basename $file)" "PASS"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 3: CODE QUALITY & STANDARDS${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Check for namespace declarations in controllers
CONTROLLERS=(
    "transfer_engine/app/Controllers/Api/WebhookLabController.php"
    "transfer_engine/app/Controllers/Api/VendTesterController.php"
    "transfer_engine/app/Controllers/Api/LightspeedTesterController.php"
    "transfer_engine/app/Controllers/Api/QueueJobTesterController.php"
    "transfer_engine/app/Controllers/Api/SuiteRunnerController.php"
    "transfer_engine/app/Controllers/Api/SnippetLibraryController.php"
)

for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "^namespace " "$controller"; then
            test_result "Has namespace: $(basename $controller)" "PASS"
        else
            test_result "Has namespace: $(basename $controller)" "FAIL" "Missing namespace declaration"
        fi
    fi
done

# Check for class declarations
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "^class " "$controller"; then
            test_result "Has class declaration: $(basename $controller)" "PASS"
        else
            test_result "Has class declaration: $(basename $controller)" "FAIL" "Missing class"
        fi
    fi
done

# Check for constructor methods
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "public function __construct()" "$controller"; then
            test_result "Has constructor: $(basename $controller)" "PASS"
        else
            test_result "Has constructor: $(basename $controller)" "WARN" "No constructor found"
        fi
    fi
done

# Check for docblocks
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "/\*\*" "$controller"; then
            test_result "Has docblocks: $(basename $controller)" "PASS"
        else
            test_result "Has docblocks: $(basename $controller)" "WARN" "No docblocks found"
        fi
    fi
done

# Check line count (should be reasonable)
for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ]; then
        lines=$(wc -l < "$file")
        if [ "$lines" -gt 50 ] && [ "$lines" -lt 5000 ]; then
            test_result "Line count reasonable: $(basename $file)" "PASS" "$lines lines"
        elif [ "$lines" -ge 5000 ]; then
            test_result "Line count reasonable: $(basename $file)" "WARN" "$lines lines (very large)"
        else
            test_result "Line count reasonable: $(basename $file)" "WARN" "$lines lines (very small)"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 4: SECURITY CHECKS${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Check for SQL injection vulnerabilities (basic check)
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        if grep -i "query.*\$_" "$file" | grep -v "prepare\|bind" >/dev/null 2>&1; then
            test_result "No SQL injection risk: $(basename $file)" "WARN" "Possible direct query with user input"
        else
            test_result "No SQL injection risk: $(basename $file)" "PASS"
        fi
    fi
done

# Check for XSS vulnerabilities (basic check)
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        if grep "echo.*\$_" "$file" | grep -v "htmlspecialchars\|htmlentities\|esc_" >/dev/null 2>&1; then
            test_result "No XSS risk: $(basename $file)" "WARN" "Possible unescaped output"
        else
            test_result "No XSS risk: $(basename $file)" "PASS"
        fi
    fi
done

# Check for hardcoded credentials
for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ]; then
        if grep -i "password.*=.*['\"]" "$file" | grep -v "getenv\|\$_POST\|\$_GET" >/dev/null 2>&1; then
            test_result "No hardcoded credentials: $(basename $file)" "FAIL" "Found hardcoded password"
        else
            test_result "No hardcoded credentials: $(basename $file)" "PASS"
        fi
    fi
done

# Check for CSRF token validation
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "validateCsrfToken\|csrf\|_token" "$controller"; then
            test_result "CSRF protection present: $(basename $controller)" "PASS"
        else
            test_result "CSRF protection present: $(basename $controller)" "WARN" "No CSRF validation found"
        fi
    fi
done

# Check for error handling
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "try.*catch\|throw\|Exception" "$controller"; then
            test_result "Error handling present: $(basename $controller)" "PASS"
        else
            test_result "Error handling present: $(basename $controller)" "WARN" "No error handling found"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 5: FUNCTIONALITY CHECKS${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Check for required methods in controllers
REQUIRED_METHODS=("__construct")

for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        for method in "${REQUIRED_METHODS[@]}"; do
            if grep -q "function $method" "$controller"; then
                test_result "Has $method: $(basename $controller)" "PASS"
            else
                test_result "Has $method: $(basename $controller)" "WARN" "Method not found"
            fi
        done
    fi
done

# Check for response methods
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "successResponse\|errorResponse\|return.*array" "$controller"; then
            test_result "Has response methods: $(basename $controller)" "PASS"
        else
            test_result "Has response methods: $(basename $controller)" "WARN" "No response methods found"
        fi
    fi
done

# Check views have HTML structure
VIEWS=(
    "transfer_engine/resources/views/admin/api-lab/main.php"
    "transfer_engine/resources/views/admin/api-lab/webhook.php"
    "transfer_engine/resources/views/admin/api-lab/vend.php"
    "transfer_engine/resources/views/admin/api-lab/lightspeed.php"
    "transfer_engine/resources/views/admin/api-lab/queue.php"
    "transfer_engine/resources/views/admin/api-lab/suite.php"
    "transfer_engine/resources/views/admin/api-lab/snippets.php"
)

for view in "${VIEWS[@]}"; do
    if [ -f "$view" ]; then
        if grep -q "<html\|<!DOCTYPE" "$view"; then
            test_result "Has HTML structure: $(basename $view)" "PASS"
        else
            test_result "Has HTML structure: $(basename $view)" "WARN" "No HTML declaration"
        fi
    fi
done

# Check views have proper closing tags
for view in "${VIEWS[@]}"; do
    if [ -f "$view" ]; then
        open_divs=$(grep -o "<div" "$view" | wc -l)
        close_divs=$(grep -o "</div>" "$view" | wc -l)
        if [ "$open_divs" -eq "$close_divs" ]; then
            test_result "Balanced div tags: $(basename $view)" "PASS"
        else
            test_result "Balanced div tags: $(basename $view)" "WARN" "Open: $open_divs, Close: $close_divs"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 6: DEPENDENCY CHECKS${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Check for use statements
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "^use " "$controller"; then
            test_result "Has use statements: $(basename $controller)" "PASS"
        else
            test_result "Has use statements: $(basename $controller)" "WARN" "No use statements"
        fi
    fi
done

# Check for extends BaseController
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "extends BaseController" "$controller"; then
            test_result "Extends BaseController: $(basename $controller)" "PASS"
        else
            test_result "Extends BaseController: $(basename $controller)" "WARN" "Not extending BaseController"
        fi
    fi
done

# Check for Logger usage
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "Logger\|logger" "$controller"; then
            test_result "Uses Logger: $(basename $controller)" "PASS"
        else
            test_result "Uses Logger: $(basename $controller)" "WARN" "No logger usage"
        fi
    fi
done

# Check for Security class usage
for controller in "${CONTROLLERS[@]}"; do
    if [ -f "$controller" ]; then
        if grep -q "Security\|security" "$controller"; then
            test_result "Uses Security: $(basename $controller)" "PASS"
        else
            test_result "Uses Security: $(basename $controller)" "WARN" "No security class usage"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 7: PERFORMANCE CHECKS${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Check file sizes (should be optimized)
for file in "${FILES_TO_TEST[@]}"; do
    if [ -f "$file" ]; then
        size=$(wc -c < "$file")
        size_kb=$((size / 1024))
        if [ "$size_kb" -lt 100 ]; then
            test_result "File size optimized: $(basename $file)" "PASS" "${size_kb}KB"
        elif [ "$size_kb" -lt 500 ]; then
            test_result "File size optimized: $(basename $file)" "WARN" "${size_kb}KB (large)"
        else
            test_result "File size optimized: $(basename $file)" "FAIL" "${size_kb}KB (too large)"
        fi
    fi
done

# Check for nested loops (performance concern)
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        nested=$(grep -c "foreach.*foreach\|for.*for\|while.*while" "$file" 2>/dev/null || echo 0)
        if [ "$nested" -eq 0 ]; then
            test_result "No nested loops: $(basename $file)" "PASS"
        else
            test_result "No nested loops: $(basename $file)" "WARN" "Found $nested nested loops"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 8: CODE COMPLEXITY${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Count functions per file
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        func_count=$(grep -c "function " "$file" 2>/dev/null || echo 0)
        if [ "$func_count" -gt 0 ] && [ "$func_count" -lt 50 ]; then
            test_result "Function count: $(basename $file)" "PASS" "$func_count functions"
        elif [ "$func_count" -ge 50 ]; then
            test_result "Function count: $(basename $file)" "WARN" "$func_count functions (complex)"
        else
            test_result "Function count: $(basename $file)" "WARN" "No functions found"
        fi
    fi
done

# Check cyclomatic complexity (basic - count if/else/switch)
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        complexity=$(grep -c "if \|else\|switch\|case\|catch" "$file" 2>/dev/null || echo 0)
        if [ "$complexity" -lt 50 ]; then
            test_result "Cyclomatic complexity: $(basename $file)" "PASS" "Score: $complexity"
        elif [ "$complexity" -lt 100 ]; then
            test_result "Cyclomatic complexity: $(basename $file)" "WARN" "Score: $complexity (moderate)"
        else
            test_result "Cyclomatic complexity: $(basename $file)" "WARN" "Score: $complexity (high)"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 9: ROUTE INTEGRATION${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Check routes file has proper structure
ROUTES_FILE="routes/admin.php"
if [ -f "$ROUTES_FILE" ]; then
    test_result "Routes file exists" "PASS"
    
    # Check for API Lab routes
    if grep -q "/admin/api-lab" "$ROUTES_FILE"; then
        test_result "API Lab routes defined" "PASS"
    else
        test_result "API Lab routes defined" "FAIL" "No API Lab routes found"
    fi
    
    # Check for webhook routes
    if grep -q "webhook" "$ROUTES_FILE"; then
        test_result "Webhook routes defined" "PASS"
    else
        test_result "Webhook routes defined" "WARN" "No webhook routes"
    fi
    
    # Check for Vend routes
    if grep -q "vend" "$ROUTES_FILE"; then
        test_result "Vend routes defined" "PASS"
    else
        test_result "Vend routes defined" "WARN" "No vend routes"
    fi
    
    # Check for Lightspeed routes
    if grep -q "lightspeed" "$ROUTES_FILE"; then
        test_result "Lightspeed routes defined" "PASS"
    else
        test_result "Lightspeed routes defined" "WARN" "No lightspeed routes"
    fi
    
    # Check for queue routes
    if grep -q "queue" "$ROUTES_FILE"; then
        test_result "Queue routes defined" "PASS"
    else
        test_result "Queue routes defined" "WARN" "No queue routes"
    fi
    
    # Check for suite routes
    if grep -q "suite" "$ROUTES_FILE"; then
        test_result "Suite routes defined" "PASS"
    else
        test_result "Suite routes defined" "WARN" "No suite routes"
    fi
    
    # Check for snippet routes
    if grep -q "snippet" "$ROUTES_FILE"; then
        test_result "Snippet routes defined" "PASS"
    else
        test_result "Snippet routes defined" "WARN" "No snippet routes"
    fi
else
    test_result "Routes file exists" "FAIL" "Routes file not found"
fi

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}CATEGORY 10: DOCUMENTATION QUALITY${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"

# Check for README or documentation
if [ -f "PHASE_3_COMPLETE.md" ]; then
    test_result "Phase 3 documentation exists" "PASS"
else
    test_result "Phase 3 documentation exists" "WARN" "No PHASE_3_COMPLETE.md"
fi

if [ -f "PROJECT_STATUS.md" ]; then
    test_result "Project status documentation exists" "PASS"
else
    test_result "Project status documentation exists" "WARN" "No PROJECT_STATUS.md"
fi

# Check for inline comments
for file in "${FILES_TO_TEST[@]}"; do
    if [[ "$file" == *.php ]] && [ -f "$file" ]; then
        comments=$(grep -c "^\s*//" "$file" 2>/dev/null || echo 0)
        if [ "$comments" -gt 5 ]; then
            test_result "Has inline comments: $(basename $file)" "PASS" "$comments comments"
        elif [ "$comments" -gt 0 ]; then
            test_result "Has inline comments: $(basename $file)" "WARN" "Only $comments comments"
        else
            test_result "Has inline comments: $(basename $file)" "WARN" "No inline comments"
        fi
    fi
done

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}FINAL SUMMARY${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Calculate percentages
if [ $TOTAL_TESTS -gt 0 ]; then
    PASS_PERCENT=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    FAIL_PERCENT=$((FAILED_TESTS * 100 / TOTAL_TESTS))
    WARN_PERCENT=$((WARNING_TESTS * 100 / TOTAL_TESTS))
else
    PASS_PERCENT=0
    FAIL_PERCENT=0
    WARN_PERCENT=0
fi

echo -e "${WHITE}Total Tests Run:${NC} $TOTAL_TESTS"
echo -e "${GREEN}✅ Passed:${NC} $PASSED_TESTS (${PASS_PERCENT}%)"
echo -e "${RED}❌ Failed:${NC} $FAILED_TESTS (${FAIL_PERCENT}%)"
echo -e "${YELLOW}⚠️  Warnings:${NC} $WARNING_TESTS (${WARN_PERCENT}%)"
echo ""

# Overall status
if [ $FAILED_TESTS -eq 0 ] && [ $WARNING_TESTS -eq 0 ]; then
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║${WHITE}        🎉 PERFECT SCORE - ALL TESTS PASSED! 🎉                 ${GREEN}║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
elif [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${YELLOW}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║${WHITE}        ✅ ALL TESTS PASSED (with warnings)                     ${YELLOW}║${NC}"
    echo -e "${YELLOW}╚════════════════════════════════════════════════════════════════╝${NC}"
elif [ $FAILED_TESTS -lt 5 ]; then
    echo -e "${YELLOW}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║${WHITE}        ⚠️  MOSTLY PASSING (minor failures)                     ${YELLOW}║${NC}"
    echo -e "${YELLOW}╚════════════════════════════════════════════════════════════════╝${NC}"
else
    echo -e "${RED}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║${WHITE}        ❌ ISSUES DETECTED - REVIEW NEEDED                      ${RED}║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════════════╝${NC}"
fi

echo ""
echo -e "${BLUE}Full log:${NC} $LOG_FILE"
echo -e "${BLUE}Completed:${NC} $(date)"
echo ""

# Exit code based on results
if [ $FAILED_TESTS -eq 0 ]; then
    exit 0
else
    exit 1
fi
