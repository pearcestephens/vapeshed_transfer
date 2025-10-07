#!/usr/bin/env bash
# cron_http_smoke.sh
# Run the HTTP smoke and append results to storage/logs/smoke.jsonl
set -euo pipefail
BASE_URL=${SMOKE_BASE_URL:-"https://staff.vapeshed.co.nz/transfer-engine"}
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN=${PHP_BIN:-php}
OUT_DIR="$ROOT_DIR/storage/logs"
mkdir -p "$OUT_DIR"
SMOKE_JSON=$(SMOKE_BASE_URL="$BASE_URL" "$PHP_BIN" "$ROOT_DIR/bin/http_smoke.php" || true)
echo "$SMOKE_JSON" | sed 's/\r$//' >> "$OUT_DIR/smoke.jsonl"
STATUS=$(echo "$SMOKE_JSON" | grep -o '"status"\s*:\s*"[A-Z]*"' | head -n1 | sed 's/.*"\([A-Z]*\)"/\1/')
if [ "$STATUS" != "GREEN" ]; then
  echo "[cron_http_smoke] Non-green status: $STATUS" >&2
fi