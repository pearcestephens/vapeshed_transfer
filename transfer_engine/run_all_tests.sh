#!/bin/bash
##
## Run all tests and show summary
##

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         Vapeshed Transfer Engine - Test Suite Runner          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Get the script's directory
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

echo "📍 Working Directory: $DIR"
echo ""

# Test 1: Standalone flush() fix validation
echo "════════════════════════════════════════════════════════════════"
echo "Test 1: CacheManager flush() Fix Validation"
echo "════════════════════════════════════════════════════════════════"
echo ""

if [ -f "tests/test_flush_fix.php" ]; then
    php tests/test_flush_fix.php
    FLUSH_TEST_RESULT=$?
    echo ""
else
    echo "⚠️  Standalone test not found: tests/test_flush_fix.php"
    FLUSH_TEST_RESULT=1
fi

# Test 2: Comprehensive test suite
echo "════════════════════════════════════════════════════════════════"
echo "Test 2: Comprehensive Phase 8, 9, 10 Test Suite"
echo "════════════════════════════════════════════════════════════════"
echo ""

if [ -f "tests/comprehensive_phase_test.php" ]; then
    php tests/comprehensive_phase_test.php
    COMPREHENSIVE_TEST_RESULT=$?
    echo ""
else
    echo "⚠️  Comprehensive test not found: tests/comprehensive_phase_test.php"
    COMPREHENSIVE_TEST_RESULT=1
fi

# Summary
echo "════════════════════════════════════════════════════════════════"
echo "Test Summary"
echo "════════════════════════════════════════════════════════════════"
echo ""

if [ $FLUSH_TEST_RESULT -eq 0 ]; then
    echo "✅ Standalone flush() test: PASSED"
else
    echo "❌ Standalone flush() test: FAILED (exit code: $FLUSH_TEST_RESULT)"
fi

if [ $COMPREHENSIVE_TEST_RESULT -eq 0 ]; then
    echo "✅ Comprehensive test suite: PASSED"
else
    echo "❌ Comprehensive test suite: FAILED (exit code: $COMPREHENSIVE_TEST_RESULT)"
fi

echo ""

# Overall result
if [ $FLUSH_TEST_RESULT -eq 0 ] && [ $COMPREHENSIVE_TEST_RESULT -eq 0 ]; then
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║                    🎉 ALL TESTS PASSED! 🎉                     ║"
    echo "║                                                                ║"
    echo "║  ✅ Phase 8 Complete  ✅ Phase 9 Complete  ✅ Phase 10 Complete ║"
    echo "║                                                                ║"
    echo "║              Production Ready - 100% Pass Rate                 ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    exit 0
else
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║                    ⚠️  TESTS FAILED ⚠️                         ║"
    echo "║                                                                ║"
    echo "║            Review output above for failure details             ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    exit 1
fi
