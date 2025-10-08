#!/bin/bash
# Fix all Logger instantiations in test files

cd "$(dirname "$0")"

echo "🔧 Fixing Logger instantiations in test files..."

# Fix comprehensive_phase_test.php
sed -i "s/new Logger(storage_path('logs'))/new Logger('test', storage_path('logs'))/g" tests/comprehensive_phase_test.php

echo "✅ Fixed tests/comprehensive_phase_test.php"
echo ""
echo "Changed all instances of:"
echo "  new Logger(storage_path('logs'))"
echo "To:"
echo "  new Logger('test', storage_path('logs'))"
echo ""
echo "✅ Logger fixes complete! Run tests again."
