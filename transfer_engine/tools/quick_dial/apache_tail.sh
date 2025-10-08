#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
LOG_FILE=${1:-"${ROOT_DIR}/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log"}
DEFAULT_LINES=${LOG_TAIL_LINES_DEFAULT:-200}
MAX_LINES=${LOG_TAIL_MAX:-500}
SNAPSHOT_DIR=${LOG_SNAPSHOT_DIR:-/var/log/cis/snapshots}
RETENTION_DAYS=${LOG_SNAPSHOT_RETENTION_DAYS:-30}
TIMESTAMP=$(date +%Y%m%d-%H%M%S)

if [[ ! -f "${LOG_FILE}" ]]; then
  echo "Log file not found: ${LOG_FILE}" >&2
  exit 1
fi

mkdir -p "${SNAPSHOT_DIR}"

LINES=${DEFAULT_LINES}
if (( LINES > MAX_LINES )); then
  LINES=${MAX_LINES}
fi

echo "=== Tail (last ${LINES} lines) of ${LOG_FILE} ==="
tail -n "${LINES}" "${LOG_FILE}"

echo "=== Snapshotting latest ${MAX_LINES} lines to ${SNAPSHOT_DIR} ==="
snapshot_file="${SNAPSHOT_DIR}/apache-error-${TIMESTAMP}.log.gz"
tail -n "${MAX_LINES}" "${LOG_FILE}" | gzip -c > "${snapshot_file}"
echo "Snapshot written to ${snapshot_file}"

if command -v find >/dev/null 2>&1; then
  find "${SNAPSHOT_DIR}" -type f -name 'apache-error-*.log.gz' -mtime +"${RETENTION_DAYS}" -print -delete || true
fi
