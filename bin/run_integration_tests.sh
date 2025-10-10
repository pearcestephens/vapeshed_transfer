#!/bin/bash

###############################################################################
# Integration Test Runner
#
# Comprehensive integration testing for Vapeshed Transfer Engine
# Tests live API connections, sync processes, and system integration
#
# Usage: ./bin/run_integration_tests.sh [options]
#
# Options:
#   --sandbox     Run in sandbox mode (no live API calls)
#   --vend-only   Run only Vend API tests
#   --sync-only   Run only Lightspeed sync tests
#   --verbose     Show detailed output
#   --coverage    Generate coverage report
###############################################################################

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_DIR="${PROJECT_ROOT}/tests/Integration"
LOG_FILE="/tmp/integration_tests_$(date +%Y%m%d_%H%M%S).log"

# Default options
SANDBOX_MODE="true"
VEND_ONLY=false
SYNC_ONLY=false
VERBOSE=false
COVERAGE=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --sandbox)
            SANDBOX_MODE="true"
            shift
            ;;
        --live)
            SANDBOX_MODE="false"
            shift
            ;;
        --vend-only)
            VEND_ONLY=true
            shift
            ;;
        --sync-only)
            SYNC_ONLY=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Header
echo -e "${BOLD}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║     ${CYAN}🧪 INTEGRATION TEST SUITE - VAPESHED TRANSFER${NC}${BOLD}          ║${NC}"
echo -e "${BOLD}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Project Root:${NC} ${PROJECT_ROOT}"
echo -e "${BLUE}Test Log:${NC} ${LOG_FILE}"
echo -e "${BLUE}Mode:${NC} $([ "$SANDBOX_MODE" == "true" ] && echo "🟡 SANDBOX" || echo "🔴 LIVE")"
echo -e "${BLUE}Started:${NC} $(date)"
echo ""

# Check for PHPUnit
if ! command -v vendor/bin/phpunit &> /dev/null; then
    echo -e "${RED}✗ PHPUnit not found. Installing...${NC}"
    cd "${PROJECT_ROOT}"
    composer require --dev phpunit/phpunit
    if [ $? -ne 0 ]; then
        echo -e "${RED}✗ Failed to install PHPUnit${NC}"
        exit 1
    fi
fi

# Load environment variables
if [ -f "${PROJECT_ROOT}/.env" ]; then
    echo -e "${YELLOW}→ Loading environment variables${NC}"
    export $(cat "${PROJECT_ROOT}/.env" | grep -v '^#' | xargs)
fi

# Set test environment variables
export SANDBOX_MODE="${SANDBOX_MODE}"
export VEND_SANDBOX_MODE="${SANDBOX_MODE}"

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
SKIPPED_TESTS=0

# Test execution function
run_test_suite() {
    local suite_name=$1
    local test_file=$2

    echo ""
    echo -e "${BOLD}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${BOLD}  ${CYAN}${suite_name}${NC}"
    echo -e "${BOLD}═══════════════════════════════════════════════════════════════${NC}"

    if [ ! -f "${test_file}" ]; then
        echo -e "${RED}✗ Test file not found: ${test_file}${NC}"
        return 1
    fi

    # Build PHPUnit command
    local phpunit_cmd="vendor/bin/phpunit"
    local phpunit_args="--testdox"

    if [ "$VERBOSE" = true ]; then
        phpunit_args="${phpunit_args} --verbose"
    fi

    if [ "$COVERAGE" = true ]; then
        phpunit_args="${phpunit_args} --coverage-html coverage/"
    fi

    # Execute tests
    cd "${PROJECT_ROOT}"
    
    if [ "$VERBOSE" = true ]; then
        $phpunit_cmd $phpunit_args "${test_file}" 2>&1 | tee -a "${LOG_FILE}"
    else
        $phpunit_cmd $phpunit_args "${test_file}" >> "${LOG_FILE}" 2>&1
    fi

    local exit_code=$?

    # Parse results from log
    local tests_run=$(grep -oP 'Tests: \K\d+' "${LOG_FILE}" | tail -1)
    local assertions=$(grep -oP 'Assertions: \K\d+' "${LOG_FILE}" | tail -1)
    local failures=$(grep -oP 'Failures: \K\d+' "${LOG_FILE}" | tail -1)
    local errors=$(grep -oP 'Errors: \K\d+' "${LOG_FILE}" | tail -1)
    local skipped=$(grep -oP 'Skipped: \K\d+' "${LOG_FILE}" | tail -1)

    # Default values if not found
    tests_run=${tests_run:-0}
    assertions=${assertions:-0}
    failures=${failures:-0}
    errors=${errors:-0}
    skipped=${skipped:-0}

    # Calculate passed
    local passed=$((tests_run - failures - errors - skipped))

    # Update totals
    TOTAL_TESTS=$((TOTAL_TESTS + tests_run))
    PASSED_TESTS=$((PASSED_TESTS + passed))
    FAILED_TESTS=$((FAILED_TESTS + failures + errors))
    SKIPPED_TESTS=$((SKIPPED_TESTS + skipped))

    # Display results
    echo ""
    echo -e "${BLUE}Tests Run:${NC} ${tests_run}"
    echo -e "${BLUE}Assertions:${NC} ${assertions}"
    echo -e "${GREEN}✓ Passed:${NC} ${passed}"
    [ $failures -gt 0 ] && echo -e "${RED}✗ Failures:${NC} ${failures}"
    [ $errors -gt 0 ] && echo -e "${RED}✗ Errors:${NC} ${errors}"
    [ $skipped -gt 0 ] && echo -e "${YELLOW}⊘ Skipped:${NC} ${skipped}"

    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ Suite Passed${NC}"
        return 0
    else
        echo -e "${RED}✗ Suite Failed${NC}"
        return 1
    fi
}

# Run test suites based on options
suite_results=()

if [ "$VEND_ONLY" = false ] && [ "$SYNC_ONLY" = false ]; then
    # Run all tests
    echo -e "${CYAN}Running all integration tests...${NC}"
    
    run_test_suite "Vend API Integration Tests" "${TEST_DIR}/VendApiTest.php"
    suite_results+=($?)
    
    run_test_suite "Lightspeed Sync Integration Tests" "${TEST_DIR}/LightspeedSyncTest.php"
    suite_results+=($?)
    
elif [ "$VEND_ONLY" = true ]; then
    echo -e "${CYAN}Running Vend API tests only...${NC}"
    run_test_suite "Vend API Integration Tests" "${TEST_DIR}/VendApiTest.php"
    suite_results+=($?)
    
elif [ "$SYNC_ONLY" = true ]; then
    echo -e "${CYAN}Running Lightspeed Sync tests only...${NC}"
    run_test_suite "Lightspeed Sync Integration Tests" "${TEST_DIR}/LightspeedSyncTest.php"
    suite_results+=($?)
fi

# Final summary
echo ""
echo -e "${BOLD}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BOLD}  ${CYAN}FINAL SUMMARY${NC}"
echo -e "${BOLD}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${BLUE}Total Tests:${NC} ${TOTAL_TESTS}"
echo -e "${GREEN}✓ Passed:${NC} ${PASSED_TESTS}"
echo -e "${RED}✗ Failed:${NC} ${FAILED_TESTS}"
echo -e "${YELLOW}⊘ Skipped:${NC} ${SKIPPED_TESTS}"

if [ ${TOTAL_TESTS} -gt 0 ]; then
    PASS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    echo -e "${BLUE}Pass Rate:${NC} ${PASS_RATE}%"
fi

echo ""
echo -e "${BLUE}Full Log:${NC} ${LOG_FILE}"
echo -e "${BLUE}Completed:${NC} $(date)"
echo ""

# Overall result
OVERALL_RESULT=0
for result in "${suite_results[@]}"; do
    if [ $result -ne 0 ]; then
        OVERALL_RESULT=1
        break
    fi
done

if [ $OVERALL_RESULT -eq 0 ]; then
    echo -e "${BOLD}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}║                    ${GREEN}✓ ALL TESTS PASSED${NC}${BOLD}                        ║${NC}"
    echo -e "${BOLD}╚════════════════════════════════════════════════════════════════╝${NC}"
    exit 0
else
    echo -e "${BOLD}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}║                    ${RED}✗ TESTS FAILED${NC}${BOLD}                            ║${NC}"
    echo -e "${BOLD}╚════════════════════════════════════════════════════════════════╝${NC}"
    exit 1
fi
