#!/bin/bash

# Comprehensive Test Runner for Phases 8, 9, 10
# Run from transfer_engine directory

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "════════════════════════════════════════════════════════════"
echo "  COMPREHENSIVE TEST SUITE - PHASES 8, 9, 10"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Working directory: $(pwd)"
echo "Running: php tests/comprehensive_phase_test.php"
echo ""

php tests/comprehensive_phase_test.php

exit $?
