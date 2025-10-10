#!/bin/bash
# Convenience wrapper to run advanced tests from parent directory
# Usage: bash run_tests.sh

cd "$(dirname "$0")/transfer_engine" || exit 1
bash bin/run_advanced_tests.sh
