#!/bin/bash
# Quick permissions fix for all deployment scripts
cd "$(dirname "$0")"
chmod +x deploy_and_test.sh
chmod +x run_critical_tests.sh
echo "✓ Scripts are now executable"
echo ""
echo "Run deployment:"
echo "  ./deploy_and_test.sh"
