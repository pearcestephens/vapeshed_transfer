#!/usr/bin/env bash
set -euo pipefail

# Provide BASE_URL as an env var, or set manually to the full index.php URL for this deployment.
# Example: https://staff.vapeshed.co.nz/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/cis_console/public/index.php
BASE_URL="${BASE_URL:-}"
if [ -z "$BASE_URL" ]; then
  echo "Error: BASE_URL not set. Export BASE_URL to the full index.php URL before running." >&2
  exit 1
fi
ADMIN_TOKEN="${ADMIN_TOKEN:-}"

if [[ -z "$ADMIN_TOKEN" ]]; then
  echo "ADMIN_TOKEN env var required" >&2
  exit 2
fi

echo "[1/3] PHP syntax lint"
find "$(dirname "$0")/../../app" -name '*.php' -print0 | xargs -0 -n1 -P4 php -l >/dev/null
php -l "$(dirname "$0")/../../public/index.php" >/dev/null

echo "[2/3] Endpoint probes"
codes=(
  $(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL?endpoint=admin/health/ping")
  $(curl -s -o /dev/null -w "%{http_code}" -H "X-Admin-Token: bad" "$BASE_URL?endpoint=admin/health/ping")
  $(curl -s -o /dev/null -w "%{http_code}" -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/health/ping")
)
echo "No token: ${codes[0]} (expect 401), bad token: ${codes[1]} (expect 403), good token: ${codes[2]} (expect 200)"

if [[ "${codes[0]}" != "401" || "${codes[1]}" != "403" || "${codes[2]}" != "200" ]]; then
  echo "Auth guard checks failed" >&2
  exit 3
fi

echo "[3/3] SSE quick check"
curl -sS -N -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/traffic/live" | head -n 3 >/dev/null

echo "Phase 1 smoke OK"
