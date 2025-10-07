#!/bin/bash
# Quick smoke test runner for validation
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

echo "=== Transfer Engine Smoke Test ==="
echo "Timestamp: $(date)"
echo "Working Directory: $(pwd)"
echo

# Check if we can run the smoke test in include mode
echo "1. Testing smoke test script execution..."
php bin/http_smoke.php

echo
echo "2. Testing simple validation..."
php bin/simple_validation.php

echo
echo "=== Smoke Test Complete ==="