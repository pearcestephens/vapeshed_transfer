# Build Notes — Sections 11 & 12

## Decision Log

| Timestamp (NZT) | Context | Decision | Rationale |
| --- | --- | --- | --- |
| 2025-10-08T09:15:00NZDT | Phase 1 kickoff | Adopt `.env`-driven config for new traffic/API lab systems with SAFE_MODE defaults when creds absent. | Ensures consistent behaviour across environments while respecting security requirements. |
| 2025-10-08T09:32:00NZDT | Middleware design | Implement per-route token bucket rate limiting using session-backed buckets keyed by user or IP. | Zero-dependency approach that honours burst/per-minute requirements without introducing additional storage. |
| 2025-10-08T09:40:00NZDT | Tooling scaffold | Default `quick_dial` log tail to Cloudways apache error path with configurable retention via env. | Matches documented log location while keeping behaviour tunable from configuration. |
| 2025-10-08T10:08:00NZDT | Autoload compatibility | Relaxed composer PHP requirement to ^8.1 to match execution environment and unblock HTTP kernel checks. | Built-in server runs on PHP 8.1.33; platform constraint needed alignment to enable vendor autoload. |

## Plan > Next Slice
- 2025-10-08T10:50:00NZDT — Phase 2: implement traffic metrics aggregator service (requests/second, visitor window) backing Section 11.1.
- 2025-10-08T11:20:00NZDT — Phase 2: wire SSE/live feed scaffolding and dashboard widgets for Section 11.1/11.2.

## Checkpoint
- 2025-10-08T10:10:00NZDT — Slice complete: Admin health endpoints responding in SAFE_MODE with SSL/DB/queue/Vend probes; composer platform constraint adjusted; url_check.sh currently green against PHP built-in server (SAFE_MODE).
- 2025-10-08T10:45:00NZDT — Slice complete: Admin layout shell, sidebar/footer, and static bundles deployed with probe endpoint; kernel bypasses static assets; assets verified via curl under SAFE_MODE.
## Phase 1 — Admin Layout Scaffolding (Sidebar/Footer) ✅

### Files added/updated
- app/Http/Controllers/Admin/LayoutController.php
- app/Http/Middleware/StaticBundleHeaders.php
- app/Config/admin.php
- resources/views/admin/layout.php
- public/admin/assets/app.css
- public/admin/assets/app.js
- config/urls.php (added endpoints: admin/layout, admin/assets/probe)
- app/Http/Kernel.php (bypass /public/admin/assets/* from auth/rate-limit)

### Behavior
- Standalone admin shell (header + sidebar + footer), responsive and WCAG-friendly.
- No legacy includes; only uses /public/admin/assets/app.css|js.
- Long-cache + ETag for static bundles (via middleware or web server).
- SAFE_MODE enforced for protected endpoints; assets & probe stay public.

### Verify
```bash
curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8080/public/admin/assets/app.css"   # 200
curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8080/public/admin/assets/app.js"    # 200
curl -s "http://127.0.0.1:8080/index.php?endpoint=admin/assets/probe" | jq .                  # {"css":200,"js":200,…}
curl -i "http://127.0.0.1:8080/index.php?endpoint=admin/layout" | head -n 20                  # 401 under SAFE_MODE
```
