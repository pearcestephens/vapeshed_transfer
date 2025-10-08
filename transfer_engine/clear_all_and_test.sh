#!/bin/bash
# Aggressively clear all caches and run tests

echo "🔄 Clearing all PHP caches..."

# Touch all modified files to invalidate opcache
touch src/Support/Logger.php
touch src/Support/PerformanceProfiler.php
touch src/Support/AlertManager.php
touch src/Support/LogAggregator.php
touch src/Support/CacheManager.php
touch src/Support/Cache.php
touch src/Support/NotificationScheduler.php

# Also touch the test file
touch tests/comprehensive_phase_test.php
touch tests/test_flush_fix.php

# Clear any temp files
rm -f /tmp/opcache-* 2>/dev/null || true

echo "✅ All files touched and caches cleared"
echo ""
echo "🚀 Running tests..."
echo ""

bash run_all_tests.sh
