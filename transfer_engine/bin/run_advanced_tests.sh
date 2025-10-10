#!/bin/bash
#################################################################################
# ADVANCED TEST SUITE RUNNER
#################################################################################
# Purpose: Execute comprehensive test suites with performance tracking
# Covers: Basic, Security, Integration, Performance, Chaos testing
# Author: Transfer Engine QA Team
# Version: 2.0
#################################################################################

# Note: Not using 'set -e' to allow script to continue through all test suites
# even if individual suites have warnings or non-critical errors

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_DIR="storage/logs/tests"
REPORT_FILE="${LOG_DIR}/advanced_test_report_${TIMESTAMP}.txt"

# Create log directory
mkdir -p "$LOG_DIR"

#################################################################################
# FUNCTIONS
#################################################################################

print_header() {
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║  ADVANCED TEST SUITE RUNNER                                ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_section() {
    echo ""
    echo -e "${BLUE}▶ $1${NC}"
    echo "────────────────────────────────────────────────────────────"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${MAGENTA}ℹ $1${NC}"
}

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$REPORT_FILE"
}

run_test_suite() {
    local suite_name=$1
    local suite_description=$2
    
    print_section "Running ${suite_name} Tests"
    print_info "$suite_description"
    
    local start_time=$(date +%s)
    local exit_code=0
    
    vendor/bin/phpunit --testsuite="$suite_name" 2>&1 | tee -a "$REPORT_FILE" || exit_code=$?
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    # Exit code 0 = all tests passed
    # Exit code 1 = tests ran but some failed
    # Exit code 2+ = could not run tests (error)
    
    if [ $exit_code -eq 0 ]; then
        print_success "${suite_name} tests PASSED (${duration}s)"
        log_message "${suite_name} Suite: PASSED (${duration}s)"
        return 0
    else
        print_error "${suite_name} tests FAILED with exit code ${exit_code} (${duration}s)"
        log_message "${suite_name} Suite: FAILED with exit code ${exit_code} (${duration}s)"
        return 1
    fi
}

#################################################################################
# MAIN EXECUTION
#################################################################################

print_header

log_message "=========================================="
log_message "Advanced Test Suite Execution Started"
log_message "=========================================="

# System info
print_section "System Information"
print_info "PHP Version: $(php -v | head -n 1)"
print_info "PHPUnit Version: $(vendor/bin/phpunit --version)"
print_info "Test Report: $REPORT_FILE"
log_message "PHP: $(php -v | head -n 1)"
log_message "PHPUnit: $(vendor/bin/phpunit --version)"

# Track results
total_suites=0
passed_suites=0
failed_suites=0

overall_start=$(date +%s)

#################################################################################
# TEST SUITE EXECUTION
#################################################################################

# Phase 1: Basic Structure Tests
if run_test_suite "Basic" "Validates core structure without database"; then
    ((passed_suites++))
else
    ((failed_suites++))
fi
((total_suites++))

# Phase 2: Security Penetration Tests
if run_test_suite "Security" "Tests security controls and attack protection"; then
    ((passed_suites++))
else
    ((failed_suites++))
fi
((total_suites++))

# Phase 3: Integration Tests (Requires Database)
print_section "Integration Tests"
print_warning "Requires database connection - may skip if not configured"
if run_test_suite "Integration" "Tests real database operations and workflows"; then
    ((passed_suites++))
else
    print_warning "Integration tests failed - check database configuration"
    ((failed_suites++))
fi
((total_suites++))

# Phase 4: Performance Tests
print_section "Performance Tests"
print_info "Testing load, concurrency, and resource usage"
if run_test_suite "Performance" "Measures performance under load"; then
    ((passed_suites++))
else
    print_warning "Performance tests failed - review metrics"
    ((failed_suites++))
fi
((total_suites++))

# Phase 5: Chaos Engineering Tests
print_section "Chaos Tests"
print_info "Testing resilience and failure handling"
if run_test_suite "Chaos" "Tests system behavior under failure conditions"; then
    ((passed_suites++))
else
    print_warning "Chaos tests failed - system may not be resilient to failures"
    ((failed_suites++))
fi
((total_suites++))

overall_end=$(date +%s)
overall_duration=$((overall_end - overall_start))

#################################################################################
# FINAL REPORT
#################################################################################

print_section "Test Summary"

echo ""
echo "Test Suites Executed: $total_suites"
echo -e "${GREEN}Passed: $passed_suites${NC}"
echo -e "${RED}Failed: $failed_suites${NC}"
echo "Total Duration: ${overall_duration}s"
echo ""

log_message "=========================================="
log_message "Test Summary"
log_message "Total: $total_suites | Passed: $passed_suites | Failed: $failed_suites"
log_message "Duration: ${overall_duration}s"
log_message "=========================================="

# Success rate
if [ "$total_suites" -gt 0 ]; then
    success_rate=$((passed_suites * 100 / total_suites))
    echo "Success Rate: ${success_rate}%"
    log_message "Success Rate: ${success_rate}%"
    
    if [ "$success_rate" -eq 100 ]; then
        print_success "🎉 ALL TEST SUITES PASSED!"
        echo ""
        echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${GREEN}║  🏆 PRODUCTION READY - ALL QUALITY GATES PASSED          ║${NC}"
        echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        exit 0
    elif [ "$success_rate" -ge 80 ]; then
        print_warning "Most tests passed - review failures before deployment"
        exit 1
    else
        print_error "Too many failures - significant issues detected"
        exit 1
    fi
else
    print_error "No test suites executed"
    exit 1
fi

#################################################################################
# ADDITIONAL REPORTING
#################################################################################

print_section "Detailed Reports"
print_info "Full test output: $REPORT_FILE"

if [ -f "storage/logs/tests/junit.xml" ]; then
    print_info "JUnit XML: storage/logs/tests/junit.xml"
fi

if [ -f "storage/logs/tests/testdox.html" ]; then
    print_info "HTML Report: storage/logs/tests/testdox.html"
fi

echo ""
print_info "View detailed metrics in the report file for performance analysis"
echo ""
