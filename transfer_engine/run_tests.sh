#!/bin/bash
# Quick test runner to verify namespace fixes

cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

echo "════════════════════════════════════════════════════════════"
echo "   NAMESPACE FIX VERIFICATION"
echo "════════════════════════════════════════════════════════════"
echo ""

echo "Step 1: Running quick verification test..."
echo "─────────────────────────────────────────────────────────────"
php tests/quick_verify.php

echo ""
echo ""
echo "════════════════════════════════════════════════════════════"
echo ""

if [ $? -eq 0 ]; then
    echo "✅ Quick verification PASSED!"
    echo ""
    echo "Step 2: Running comprehensive test suite..."
    echo "─────────────────────────────────────────────────────────────"
    php tests/comprehensive_phase_test.php
    
    if [ $? -eq 0 ]; then
        echo ""
        echo "════════════════════════════════════════════════════════════"
        echo "   ✅ ALL TESTS PASSED - NAMESPACE FIX SUCCESSFUL!"
        echo "════════════════════════════════════════════════════════════"
        echo ""
        echo "Next steps:"
        echo "  1. Review test results above"
        echo "  2. Deploy Phase 8, 9, 10 components to production"
        echo "  3. Configure monitoring dashboards"
        echo "  4. Enable metrics collection"
        echo ""
        exit 0
    else
        echo ""
        echo "⚠ Comprehensive tests had issues. Review output above."
        exit 1
    fi
else
    echo "❌ Quick verification FAILED"
    echo "   Check the error messages above"
    echo "   Common issues:"
    echo "     - Missing storage directory"
    echo "     - File permissions"
    echo "     - PHP version (requires PHP 8.0+)"
    exit 1
fi
