#!/bin/bash

# Helper Methods Test Runner
# Quick verification of Database helper methods before full test suite

clear

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  HELPER METHODS VERIFICATION                               ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

cd "$(dirname "$0")/.." || exit 1

# Run helper methods test
php bin/test_helper_methods.php

echo ""
echo "▶ Now running full test suite..."
echo ""
sleep 2

# Run full test suite
bash bin/run_advanced_tests.sh
