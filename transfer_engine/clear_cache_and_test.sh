#!/bin/bash
# Clear PHP opcode cache and run tests

echo "🔄 Clearing PHP opcode cache..."

# Method 1: Touch the file to invalidate cache
touch src/Support/PerformanceProfiler.php

# Method 2: If using opcache, try to reset it via CLI
php -r "if(function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset\n'; } else { echo 'No opcache\n'; }"

echo "✅ Cache cleared"
echo ""
echo "🚀 Running tests..."
bash run_all_tests.sh
