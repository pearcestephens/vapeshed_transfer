#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-}"
if [[ -z "$BASE_URL" ]]; then
  echo "Set BASE_URL to the full index.php URL for the console (e.g., https://<host>/<path>/cis_console/public/index.php)" >&2
  exit 2
fi
ADMIN_TOKEN="${ADMIN_TOKEN:-}"

if [[ -z "$ADMIN_TOKEN" ]]; then
  echo "ADMIN_TOKEN env var required" >&2
  exit 2
fi

curl -sS -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/health/ping" | jq . >/dev/null
curl -sSI -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/health/phpinfo" >/dev/null
curl -sS -N -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/traffic/live" | head -n 3 >/dev/null

echo "URL verification OK"
