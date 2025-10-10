#!/usr/bin/env bash
set -euo pipefail

LOG_PATH="${APACHE_ERROR_LOG:-/var/log/apache2/error.log}"
SNAP_DIR="${QUICK_DIAL_SNAPSHOT_DIR:-/var/log/cis/snapshots}"
LINES="${LINES:-200}"

mkdir -p "$SNAP_DIR"
TS=$(date +%Y%m%d-%H%M%S)
OUT="$SNAP_DIR/apache-error-$TS.log.gz"

tail -n "$LINES" "$LOG_PATH" | gzip -c > "$OUT"
echo "$OUT"
