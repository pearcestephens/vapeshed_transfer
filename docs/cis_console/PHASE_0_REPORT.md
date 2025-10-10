# CIS Web Traffic & API Debug Console — PHASE 0

Date: 2025-10-10

This document inventories scope, endpoints, telemetry sources, risks, acceptance criteria, and the plan for Phases 1–3 for the CIS console targeting Section 11 (Web Traffic & Site Monitoring) and Section 12 (API Testing & Debugging).

## 0.A Scope Map (Sections 11 & 12)

- Section 11: Web Traffic & Site Monitoring
  - Live visitor count (last 5 min)
  - Requests/second rolling window
  - Endpoint health grid (JSON + UI)
  - Live request feed via SSE fallback
  - Alerts: error spikes, slow endpoints, burst detection
  - Performance analytics: page load time (avg, p95, p99), per-endpoint latency, slow query hooks
  - Traffic sources: geo, browser/OS, bot detection (configurable)
  - Error tracking: top 404, top 500, error grouping, create redirect
  - Site health check: SSL/DB/PHP-FPM/Queue/Disk/Vend API

- Section 12: API Testing & Debugging
  - Webhook Test Lab (JSON editor, safe send, response viewer)
  - Vend API Tester (auth test, endpoint selector, query builder)
  - Lightspeed Sync Tester (transfer→consignment pipeline tests)
  - Queue Job Tester (dispatch, monitor, stress mode, cancel)
  - API Endpoint Tester (suites + bulk runner)
  - Code Snippet Library (cURL, PHP, JS)

## 0.B URL & Endpoint Contract Table

Base URL pattern (GET routing): `https://staff.vapeshed.co.nz/cis-console/public/index.php?endpoint=<route>`

Admin endpoints require `X-Admin-Token: <token>` header (token sourced from .env ADMIN_TOKEN). Browse/safe-mode controlled via `BROWSE_MODE`.

Examples (Phase 1–2 initial set):
- `admin/health/ping` → 200 JSON `{ success:true, data:{ pong:true } }` (Auth required)
- `admin/health/phpinfo` → 200 HTML (Auth required)
- `admin/health/checks` → 200 JSON with subsystem statuses (Auth required)
- `admin/traffic/live` → text/event-stream (Auth required)
- `admin/logs/apache-error-tail` → 200 JSON or SSE (Auth required; rate-limited; CSRF not required for GET)
- `admin/errors/top404` and `admin/errors/top500` → 200 JSON (Auth required)

Environment variables:
- APP_ENV (dev|stage|prod)
- ADMIN_TOKEN (required in prod)
- BROWSE_MODE (on|off) default on; blocks external requests from testers when on
- APACHE_ERROR_LOG (absolute path)
- QUICK_DIAL_SNAPSHOT_DIR (/var/log/cis/snapshots)
- RATE_LIMIT_REQUESTS, RATE_LIMIT_WINDOW

## 0.C Data & Telemetry Sources

- Request metrics: file-backed rolling window at `storage/metrics/requests.log` (iso timestamps); middleware appends per request.
- Error logs: `APACHE_ERROR_LOG` (configurable) with snapshot gzips at `QUICK_DIAL_SNAPSHOT_DIR`.
- Health checks: PHP runtime info, disk space, optional DB connection ping (skipped if DSN missing), SSL check via stream context when configured.
- SSE: `/admin/traffic/live` pushes computed metrics every few seconds.

## 0.D Risk Register

- URL rot: enforce central route map in `config/urls.php` and verify via `tools/verify/verify_urls.sh`.
- Auth/Secrets: require `X-Admin-Token` on admin routes; never log tokens; .env only.
- Rate limiting: protect log tail, SSE, and tester endpoints; configurable window.
- Log growth: rotate/snapshot logs via quick-dial; avoid reading entire files (tail only).
- PII in logs: redact patterns (emails, tokens); never store request bodies containing secrets.
- Long-polling pitfalls: SSE with retry headers and heartbeat; server timeouts handled.
- SSRF: Webhook/Vend testers respect `BROWSE_MODE=on` to block external hosts; allow only allowlist.

## 0.E Acceptance Criteria (Concrete)

- GET router `?endpoint=` maps to controllers with safe 404/405; no PATH_INFO reliance.
- Admin guards: requests without `X-Admin-Token` get 401; with invalid token get 403.
- Rate limiter blocks >N requests/window on sensitive endpoints (HTTP 429 JSON envelope).
- SSE endpoint streams valid events; curl can consume and display lines.
- Health ping returns 200; phpinfo gated behind admin token.
- Quick-Dial tail returns last lines or snapshot path; gzip file written to `QUICK_DIAL_SNAPSHOT_DIR`.
- URL verification script exits non-zero on any non-2xx/expected status.

## Phases 1–3 Plan (Condensed)

- Phase 1 — Shared Infrastructure
  - Deliver config (.env-driven), router, middleware (CSRF, Auth, RateLimit), Response/Logger, layouts, assets, composer + phpcs, health endpoints, verify scripts, quick-dial script.

- Phase 2 — Section 11 Monitoring
  - Implement request metrics middleware + file-backed rolling store; SSE feed; health checks JSON; error tracking endpoints; minimal UI dashboard grid; alerts thresholds in config.

- Phase 3 — Section 12 API Tools
  - Implement safe Webhook Test Lab (restricted by `BROWSE_MODE`/allowlist); stub Vend/Lightspeed testers with auth tests; simple queue tester (file-based); endpoint suite runner; snippet generator.

## URL Verification Suite

All commands read BASE_URL and ADMIN_TOKEN from environment variables.

```
BASE_URL="https://staff.vapeshed.co.nz/cis-console/public/index.php"
ADMIN_TOKEN="<token>"

# Ping (expect 200)
curl -sS -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/health/ping"

# Phpinfo (expect 200 HTML, short body)
curl -sSI -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/health/phpinfo"

# SSE (connect, show first few lines)
curl -sS -N -H "X-Admin-Token: $ADMIN_TOKEN" "$BASE_URL?endpoint=admin/traffic/live" | head -n 10
```

## Quick-Dial Log Blueprint

- Endpoint: `/admin/logs/apache-error-tail?lines=200` (GET, auth, rate-limited)
- Script: `tools/quick_dial/apache_tail.sh` → gzip snapshots to `QUICK_DIAL_SNAPSHOT_DIR`.
- UI: Button in admin logs view to Tail/Download latest snapshot (implemented in Phase 2 UI).

## Skeleton Artifacts (to be created in Phase 1)

- `cis_console/`
  - `public/index.php` (router)
  - `config/{app.php,urls.php,security.php}`
  - `app/Http/{Kernel.php,Middleware/*.php,Controllers/*}`
  - `app/Support/{Response.php,Logger.php}`
  - `resources/views/layout/{header.php,sidebar.php,footer.php}`
  - `public/assets/{css/app.css,js/app.js}`
  - `tools/verify/verify_urls.sh`
  - `tools/quick_dial/apache_tail.sh`
  - `.env.example`, `composer.json`, `phpcs.xml`
