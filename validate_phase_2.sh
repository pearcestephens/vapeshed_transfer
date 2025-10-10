#!/bin/bash

# Phase 2 Validation Runner
# Validates all Phase 2 deliverables

set -e  # Exit on error

echo "🧪 Phase 2 GuardrailChain Enhancement - Validation Runner"
echo "=========================================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0
PASSED=0

# Change to transfer_engine directory
cd "$(dirname "$0")/transfer_engine" || exit 1

echo "📂 Working Directory: $(pwd)"
echo ""

# Function to print status
print_status() {
    local status=$1
    local message=$2
    
    if [ "$status" == "PASS" ]; then
        echo -e "${GREEN}✅ PASS${NC}: $message"
        ((PASSED++))
    elif [ "$status" == "FAIL" ]; then
        echo -e "${RED}❌ FAIL${NC}: $message"
        ((ERRORS++))
    elif [ "$status" == "WARN" ]; then
        echo -e "${YELLOW}⚠️  WARN${NC}: $message"
        ((WARNINGS++))
    else
        echo "ℹ️  INFO: $message"
    fi
}

# Test 1: Check if all source files exist
echo "🔍 Test 1: Verifying source files..."
FILES=(
    "src/Guardrail/Severity.php"
    "src/Guardrail/Result.php"
    "src/Guardrail/GuardrailChain.php"
    "src/Persistence/GuardrailTraceRepository.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        print_status "PASS" "Source file exists: $file"
    else
        print_status "FAIL" "Source file missing: $file"
    fi
done
echo ""

# Test 2: Check if all test files exist
echo "🔍 Test 2: Verifying test files..."
TEST_FILES=(
    "tests/Guardrail/SeverityTest.php"
    "tests/Guardrail/ResultTest.php"
    "tests/Guardrail/GuardrailChainTest.php"
)

for file in "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        print_status "PASS" "Test file exists: $file"
    else
        print_status "FAIL" "Test file missing: $file"
    fi
done
echo ""

# Test 3: PHP Syntax Check
echo "🔍 Test 3: PHP syntax validation..."
SYNTAX_ERRORS=0

for file in "${FILES[@]}" "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        if php -l "$file" > /dev/null 2>&1; then
            print_status "PASS" "Syntax valid: $(basename "$file")"
        else
            print_status "FAIL" "Syntax error: $(basename "$file")"
            SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
        fi
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    print_status "PASS" "All PHP files have valid syntax"
else
    print_status "FAIL" "$SYNTAX_ERRORS files have syntax errors"
fi
echo ""

# Test 4: Check for strict types declaration
echo "🔍 Test 4: Checking strict types declaration..."
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        if grep -q "declare(strict_types=1);" "$file"; then
            print_status "PASS" "Strict types enabled: $(basename "$file")"
        else
            print_status "WARN" "Strict types missing: $(basename "$file")"
        fi
    fi
done
echo ""

# Test 5: Check if composer.json has phpstan
echo "🔍 Test 5: Checking composer.json for PHPStan..."
if [ -f "composer.json" ]; then
    if grep -q "phpstan/phpstan" composer.json; then
        print_status "PASS" "PHPStan dependency found in composer.json"
    else
        print_status "FAIL" "PHPStan dependency missing from composer.json"
    fi
else
    print_status "FAIL" "composer.json not found"
fi
echo ""

# Test 6: Check if phpstan.neon exists
echo "🔍 Test 6: Checking PHPStan configuration..."
if [ -f "phpstan.neon" ]; then
    print_status "PASS" "PHPStan configuration file exists"
    
    # Check if it has level: max
    if grep -q "level: max" phpstan.neon; then
        print_status "PASS" "PHPStan level set to 'max'"
    else
        print_status "WARN" "PHPStan level not set to 'max'"
    fi
else
    print_status "FAIL" "phpstan.neon not found"
fi
echo ""

# Test 7: Check database migration file
echo "🔍 Test 7: Checking database migration..."
MIGRATION_FILE="../database/migrations/002_add_guardrail_trace_enhancements.sql"
if [ -f "$MIGRATION_FILE" ]; then
    print_status "PASS" "Migration file exists"
    
    # Check for key columns
    if grep -q "severity" "$MIGRATION_FILE"; then
        print_status "PASS" "Migration includes 'severity' column"
    else
        print_status "FAIL" "Migration missing 'severity' column"
    fi
    
    if grep -q "reason" "$MIGRATION_FILE"; then
        print_status "PASS" "Migration includes 'reason' column"
    else
        print_status "FAIL" "Migration missing 'reason' column"
    fi
    
    if grep -q "duration_ms" "$MIGRATION_FILE"; then
        print_status "PASS" "Migration includes 'duration_ms' column"
    else
        print_status "FAIL" "Migration missing 'duration_ms' column"
    fi
else
    print_status "FAIL" "Migration file not found"
fi
echo ""

# Test 8: Check documentation files
echo "🔍 Test 8: Checking documentation files..."
DOC_FILES=(
    "../PR_2_GUARDRAIL_DETERMINISTIC_COMPLETE.md"
    "../docs/GUARDRAIL_CHAIN_GUIDE.md"
    "../docs/GUARDRAIL_QUICK_REF.md"
    "../PHASE_2_MANIFEST.md"
    "../PHASE_2_COMPLETE.md"
)

for file in "${DOC_FILES[@]}"; do
    if [ -f "$file" ]; then
        lines=$(wc -l < "$file")
        print_status "PASS" "Documentation exists: $(basename "$file") ($lines lines)"
    else
        print_status "FAIL" "Documentation missing: $(basename "$file")"
    fi
done
echo ""

# Test 9: Check for test count
echo "🔍 Test 9: Counting test methods..."
TEST_COUNT=0

for file in "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        count=$(grep -c "public function test" "$file" || true)
        TEST_COUNT=$((TEST_COUNT + count))
        print_status "PASS" "$(basename "$file"): $count test methods"
    fi
done

if [ $TEST_COUNT -ge 35 ]; then
    print_status "PASS" "Total test methods: $TEST_COUNT (target: 35+)"
else
    print_status "WARN" "Total test methods: $TEST_COUNT (target: 35+)"
fi
echo ""

# Test 10: Check if autoload is configured
echo "🔍 Test 10: Checking PSR-4 autoload configuration..."
if [ -f "composer.json" ]; then
    if grep -q "Unified" composer.json; then
        print_status "PASS" "Unified namespace configured in autoload"
    else
        print_status "WARN" "Unified namespace not in autoload (may be in VapeshedTransfer)"
    fi
fi
echo ""

# Test 11: Run PHPUnit if available
echo "🔍 Test 11: Running PHPUnit tests (if vendor exists)..."
if [ -d "vendor" ] && [ -f "vendor/bin/phpunit" ]; then
    echo "   Running PHPUnit..."
    
    if vendor/bin/phpunit tests/Guardrail/ --no-coverage 2>&1 | tee /tmp/phpunit_output.txt; then
        print_status "PASS" "PHPUnit tests passed"
        
        # Extract test count
        if grep -q "OK" /tmp/phpunit_output.txt; then
            test_summary=$(grep "OK" /tmp/phpunit_output.txt | head -n 1)
            print_status "PASS" "PHPUnit summary: $test_summary"
        fi
    else
        print_status "FAIL" "PHPUnit tests failed - see output above"
        
        # Show last 20 lines of output
        echo ""
        echo "Last 20 lines of PHPUnit output:"
        tail -n 20 /tmp/phpunit_output.txt
    fi
    
    rm -f /tmp/phpunit_output.txt
else
    print_status "WARN" "PHPUnit not available (vendor directory not found)"
    echo "   Run 'composer install' to enable PHPUnit testing"
fi
echo ""

# Test 12: Check LOC count
echo "🔍 Test 12: Counting lines of code..."
TOTAL_LOC=0

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        loc=$(grep -c "^" "$file")
        TOTAL_LOC=$((TOTAL_LOC + loc))
    fi
done

echo "   Source files: $TOTAL_LOC lines"

TEST_LOC=0
for file in "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        loc=$(grep -c "^" "$file")
        TEST_LOC=$((TEST_LOC + loc))
    fi
done

echo "   Test files: $TEST_LOC lines"
echo "   Total code: $((TOTAL_LOC + TEST_LOC)) lines"

if [ $TOTAL_LOC -ge 400 ]; then
    print_status "PASS" "Source LOC: $TOTAL_LOC (target: 400+)"
else
    print_status "WARN" "Source LOC: $TOTAL_LOC (target: 400+)"
fi
echo ""

# Summary
echo "=========================================================="
echo "📊 Validation Summary"
echo "=========================================================="
echo ""
echo -e "${GREEN}✅ Passed: $PASSED${NC}"
echo -e "${YELLOW}⚠️  Warnings: $WARNINGS${NC}"
echo -e "${RED}❌ Errors: $ERRORS${NC}"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}🎉 Phase 2 validation PASSED!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Run 'composer install' to install dependencies (if not done)"
    echo "2. Run 'vendor/bin/phpunit tests/Guardrail/' to run all tests"
    echo "3. Run 'composer phpstan' to run static analysis"
    echo "4. Apply database migration: mysql < database/migrations/002_add_guardrail_trace_enhancements.sql"
    echo "5. Review PR documentation: PR_2_GUARDRAIL_DETERMINISTIC_COMPLETE.md"
    echo ""
    exit 0
else
    echo -e "${RED}❌ Phase 2 validation FAILED!${NC}"
    echo ""
    echo "Please fix the errors above before proceeding."
    echo ""
    exit 1
fi
