#!/bin/bash
# Setup Cron Jobs for Pilot Program
# Configures automated daily transfer calculations and health checks

echo "Setting up cron jobs for Vapeshed Transfer Engine..."
echo ""

# Get the installation path
INSTALL_PATH="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer"

# Backup existing crontab
crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt 2>/dev/null
echo "✓ Existing crontab backed up"

# Create new cron entries
echo ""
echo "Cron jobs to be added:"
echo ""
echo "1. Daily Transfer Calculation (6:00 AM)"
echo "   0 6 * * * cd ${INSTALL_PATH} && php bin/daily_transfer_run.php >> logs/cron_\$(date +\\%Y\\%m\\%d).log 2>&1"
echo ""
echo "2. Health Check (Every 15 minutes)"
echo "   */15 * * * * cd ${INSTALL_PATH} && php bin/health_check.php >> logs/health_\$(date +\\%Y\\%m\\%d).log 2>&1"
echo ""
echo "3. Daily Report Generation (7:00 AM)"
echo "   0 7 * * * cd ${INSTALL_PATH} && php bin/generate_daily_report.php --save --email >> logs/report_\$(date +\\%Y\\%m\\%d).log 2>&1"
echo ""

read -p "Add these cron jobs? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Get current crontab
    crontab -l > /tmp/new_crontab.txt 2>/dev/null || touch /tmp/new_crontab.txt
    
    # Add new jobs
    echo "" >> /tmp/new_crontab.txt
    echo "# Vapeshed Transfer Engine - Pilot Program" >> /tmp/new_crontab.txt
    echo "0 6 * * * cd ${INSTALL_PATH} && php bin/daily_transfer_run.php >> logs/cron_\$(date +\\%Y\\%m\\%d).log 2>&1" >> /tmp/new_crontab.txt
    echo "*/15 * * * * cd ${INSTALL_PATH} && php bin/health_check.php >> logs/health_\$(date +\\%Y\\%m\\%d).log 2>&1" >> /tmp/new_crontab.txt
    echo "0 7 * * * cd ${INSTALL_PATH} && php bin/generate_daily_report.php --save --email >> logs/report_\$(date +\\%Y\\%m\\%d).log 2>&1" >> /tmp/new_crontab.txt
    
    # Install new crontab
    crontab /tmp/new_crontab.txt
    
    echo "✓ Cron jobs installed successfully"
    echo ""
    echo "Current crontab:"
    crontab -l | grep -A 3 "Vapeshed Transfer Engine"
else
    echo "Cron job installation cancelled."
fi

echo ""
echo "Setup complete!"
