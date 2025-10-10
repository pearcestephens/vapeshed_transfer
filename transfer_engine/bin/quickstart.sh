#!/bin/bash
# QUICK START - Run This First!

cd "$(dirname "$0")/.."

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  ADVANCED TEST SUITE - QUICK START"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "Making scripts executable..."
chmod +x bin/test_database_config.sh 2>/dev/null
chmod +x bin/run_advanced_tests.sh 2>/dev/null

echo "✓ Scripts are executable"
echo ""
echo "Testing database connection..."
bash bin/test_database_config.sh

if [ $? -eq 0 ]; then
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "✅ READY! You can now run:"
    echo ""
    echo "   bash bin/run_advanced_tests.sh       ← Full suite (56 tests)"
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
else
    echo ""
    echo "⚠️  Database connection failed. Please check DATABASE_CONFIG_COMPLETE.md"
    echo ""
fi
