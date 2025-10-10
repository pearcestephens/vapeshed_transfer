#!/bin/bash

# Quick Test - Helper Methods Only
# Fast verification before full test suite

clear

cd "$(dirname "$0")/.." || exit 1

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  TESTING DATABASE HELPER METHODS                           ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Test the helper methods
php bin/test_helper_methods.php

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  ✓ HELPER METHODS VERIFIED - READY FOR FULL TESTS         ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Next: Run full test suite with:"
    echo "  bash bin/run_advanced_tests.sh"
    echo ""
else
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  ✗ HELPER METHODS TEST FAILED                              ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
fi

exit $EXIT_CODE
