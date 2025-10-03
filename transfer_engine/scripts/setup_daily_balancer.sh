#!/bin/bash

# AUTOMATED DAILY STOCK BALANCER CRON SETUP
# Sets up automated daily execution of the stock balancer

echo "🔧 Setting up Automated Daily Stock Balancer..."

# Create the cron job file
CRON_FILE="/tmp/vapeshed_daily_balancer_cron"
SCRIPT_PATH="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/CORE_PROJECTS/TRANSFER_ENGINE/daily_stock_balancer.php"
LOG_PATH="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/logs/daily_balancer.log"

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_PATH")"

# Create cron job entry
cat > "$CRON_FILE" << EOF
# The Vape Shed Automated Daily Stock Balancer
# Runs every day at 6:00 AM to analyze stock and generate transfers
0 6 * * * /usr/bin/php $SCRIPT_PATH >> $LOG_PATH 2>&1

# Alternative times for testing/debugging (commented out)
# 0 */4 * * * /usr/bin/php $SCRIPT_PATH >> $LOG_PATH 2>&1  # Every 4 hours
# */30 * * * * /usr/bin/php $SCRIPT_PATH >> $LOG_PATH 2>&1  # Every 30 minutes (testing only)
EOF

echo "📋 Cron job configuration created:"
cat "$CRON_FILE"

echo ""
echo "🚀 To activate the daily stock balancer:"
echo "1. Install the cron job: crontab $CRON_FILE"
echo "2. Verify installation: crontab -l"
echo "3. Check logs: tail -f $LOG_PATH"
echo ""
echo "⚙️  Configuration options in daily_stock_balancer.php:"
echo "   - enable_auto_execution: true/false (dry run mode)"
echo "   - critical_stock_threshold: 3 units"
echo "   - low_stock_threshold: 8 units" 
echo "   - max_daily_transfers: 500 transfers"
echo ""
echo "📊 Monitor transfers in CIS system:"
echo "   - Check daily_transfers table"
echo "   - Review transfer_batches for batch status"
echo "   - Monitor stock_alerts for ongoing issues"
echo ""
echo "✅ Setup complete! Daily stock balancer ready for activation."