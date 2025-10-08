#!/usr/bin/env bash
set -euo pipefail

BASE_URL=${1:-"http://localhost/index.php?endpoint="}

red() { printf "\033[31m%s\033[0m\n" "$1"; }
green() { printf "\033[32m%s\033[0m\n" "$1"; }

declare -a ROUTES=(
  "GET admin/health/ping 200,401"
  "GET admin/health/phpinfo 200,401,403"
  "GET admin/http/one-click-check 200,401"
  "GET admin/traffic/summary 200,401"
  "GET admin/traffic/live 200,401"
  "GET admin/traffic/alerts 200,401"
  "POST admin/traffic/alerts 200,202,401,403"
  "GET admin/errors/top 200,401"
  "POST admin/errors/redirects 200,201,202,401,403"
  "GET admin/api-lab/webhook 200,401"
  "POST admin/api-lab/webhook 200,202,401,403"
  "GET admin/api-lab/vend 200,401"
  "POST admin/api-lab/vend 200,202,401,403"
  "POST admin/api-lab/lightspeed 200,202,401,403"
  "POST admin/api-lab/queue 200,202,401,403"
  "POST admin/api-lab/suite 200,202,401,403"
  "GET admin/api-lab/snippets 200,401"
  "GET admin/logs/apache-error-tail 200,401"
)

failures=0

for definition in "${ROUTES[@]}"; do
  read -r method path statuses <<<"$definition"
  url="${BASE_URL}${path}"
  expected="${statuses//,/ }"

  if [[ "$method" == "GET" && "$path" == "admin/traffic/live" ]]; then
    code=$(curl -sS -m 5 -o /tmp/traffic_live.$$ -w "%{http_code}" "${url}")
    rm -f /tmp/traffic_live.$$
  elif [[ "$method" == "GET" ]]; then
    code=$(curl -sS -o /dev/null -w "%{http_code}" "${url}")
  else
    code=$(curl -sS -o /dev/null -w "%{http_code}" -X "$method" "${url}")
  fi

  if [[ " ${expected} " == *" ${code} "* ]]; then
    green "${method} ${path} -> ${code}"
  else
    red "${method} ${path} -> ${code} (expected ${statuses})"
    failures=$((failures + 1))
  fi
done

if [[ $failures -gt 0 ]]; then
  red "URL verification failed (${failures})"
  exit 1
fi

green "All endpoints responded within expected status codes."
