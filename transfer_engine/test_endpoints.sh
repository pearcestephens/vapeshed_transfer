#!/bin/bash
# Test API endpoints with simple validation script
set -e

echo "=== Testing API Endpoints ==="
echo "Timestamp: $(date)"
echo "Working Directory: $(pwd)"
echo

cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

echo "Running API endpoint validation..."
php bin/test_api_endpoints.php

echo
echo "=== API Test Complete ==="