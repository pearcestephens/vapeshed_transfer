#!/bin/bash
# backup_and_rollback.sh
# Backs up all modified API files and provides rollback
# Author: GitHub Copilot
# Last Modified: 2025-10-07

set -e
BACKUP_DIR="/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/storage/backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

FILES=(
    "public/api/pricing.php"
    "public/api/transfer.php"
    "public/api/unified_status.php"
    "public/api/history.php"
    "public/api/traces.php"
    "public/api/stats.php"
    "public/api/modules.php"
    "public/api/activity.php"
    "public/api/smoke_summary.php"
    "src/Support/Api.php"
)

for f in "${FILES[@]}"; do
    cp "/home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/$f" "$BACKUP_DIR/$(basename $f)"
done

echo "Backup complete: $BACKUP_DIR"
echo "To rollback, copy files from backup dir to original locations."
