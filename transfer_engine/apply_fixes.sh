#!/bin/bash
# Complete fix for all type hint issues

cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

echo "╔══════════════════════════════════════════════════════════╗"
echo "║   APPLYING ALL FIXES - TYPE HINTS                       ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

echo "Running type hint fix script..."
php fix_type_hints.php

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ All fixes applied!"
    echo ""
    echo "Now running tests..."
    echo "════════════════════════════════════════════════════════════"
    ./run_tests.sh
else
    echo ""
    echo "❌ Fix script failed. Please check errors above."
    exit 1
fi
