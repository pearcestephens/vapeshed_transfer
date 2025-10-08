#!/bin/bash
# MASTER FIX - Applies all remaining fixes

cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

echo "╔══════════════════════════════════════════════════════════╗"
echo "║   MASTER FIX - APPLYING ALL CORRECTIONS                 ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

echo "Step 1: Fixing NeuroContext::wrap() parameter order..."
echo "─────────────────────────────────────────────────────────────"
php fix_neurocontext.php

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ NeuroContext fix failed. Stopping."
    exit 1
fi

echo ""
echo "✅ All fixes applied successfully!"
echo ""
echo "Running full test suite..."
echo "════════════════════════════════════════════════════════════"
echo ""

./run_tests.sh
