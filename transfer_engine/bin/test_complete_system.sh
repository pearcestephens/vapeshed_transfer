#!/bin/bash

# Complete System Validation Test
# Tests helper methods, entry points, and full test suite

clear

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  COMPLETE SYSTEM VALIDATION                                ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

cd "$(dirname "$0")/.." || exit 1

EXIT_CODE=0

# Step 1: Test Helper Methods
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "STEP 1: HELPER METHODS VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

php bin/test_helper_methods.php
HELPER_EXIT=$?

if [ $HELPER_EXIT -ne 0 ]; then
    echo ""
    echo "✗ Helper methods test failed!"
    EXIT_CODE=1
else
    echo ""
    echo "✓ Helper methods test passed!"
fi

echo ""
sleep 1

# Step 2: Test Entry Points
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "STEP 2: ENTRY POINTS & URL VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

php bin/test_entry_points.php
ENTRY_EXIT=$?

if [ $ENTRY_EXIT -ne 0 ]; then
    echo ""
    echo "✗ Entry points test failed!"
    EXIT_CODE=1
else
    echo ""
    echo "✓ Entry points test passed!"
fi

echo ""
sleep 1

# Step 3: Full Test Suite
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "STEP 3: FULL TEST SUITE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

bash bin/run_advanced_tests.sh
SUITE_EXIT=$?

if [ $SUITE_EXIT -ne 0 ]; then
    echo ""
    echo "✗ Test suite failed!"
    EXIT_CODE=1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "COMPLETE SYSTEM VALIDATION - FINAL RESULTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

if [ $HELPER_EXIT -eq 0 ]; then
    echo "  ✓ Helper Methods:  PASSED"
else
    echo "  ✗ Helper Methods:  FAILED"
fi

if [ $ENTRY_EXIT -eq 0 ]; then
    echo "  ✓ Entry Points:    PASSED"
else
    echo "  ✗ Entry Points:    FAILED"
fi

if [ $SUITE_EXIT -eq 0 ]; then
    echo "  ✓ Test Suite:      PASSED"
else
    echo "  ✗ Test Suite:      FAILED"
fi

echo ""

if [ $EXIT_CODE -eq 0 ]; then
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  🎉 ALL VALIDATIONS PASSED - PRODUCTION READY             ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    echo "System Status: ✓ FULLY OPERATIONAL"
    echo ""
    echo "✓ Helper methods working"
    echo "✓ Entry points accessible"
    echo "✓ All controllers loadable"
    echo "✓ Database connectivity verified"
    echo "✓ Routes defined and valid"
    echo "✓ Test suite passing (87.5%+)"
    echo ""
else
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  ⚠ SOME VALIDATIONS FAILED - REVIEW ABOVE                ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
fi

exit $EXIT_CODE
