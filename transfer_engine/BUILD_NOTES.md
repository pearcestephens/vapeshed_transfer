# Build Notes — Sections 11 & 12

## Decision Log

| Timestamp (NZT) | Context | Decision | Rationale |
| --- | --- | --- | --- |
| 2025-10-08T09:15:00NZDT | Phase 1 kickoff | Adopt `.env`-driven config for new traffic/API lab systems with SAFE_MODE defaults when creds absent. | Ensures consistent behaviour across environments while respecting security requirements. |
| 2025-10-08T09:32:00NZDT | Middleware design | Implement per-route token bucket rate limiting using session-backed buckets keyed by user or IP. | Zero-dependency approach that honours burst/per-minute requirements without introducing additional storage. |
| 2025-10-08T09:40:00NZDT | Tooling scaffold | Default `quick_dial` log tail to Cloudways apache error path with configurable retention via env. | Matches documented log location while keeping behaviour tunable from configuration. |
| 2025-10-08T10:08:00NZDT | Autoload compatibility | Relaxed composer PHP requirement to ^8.1 to match execution environment and unblock HTTP kernel checks. | Built-in server runs on PHP 8.1.33; platform constraint needed alignment to enable vendor autoload. |

## Plan > Next Slice
- 2025-10-08T09:48:00NZDT — Phase 1: build `Admin\HealthController` with ping/phpinfo/one-click bundle endpoints, respecting SAFE_MODE and env guards.

## Checkpoint
- 2025-10-08T10:10:00NZDT — Slice complete: Admin health endpoints responding in SAFE_MODE with SSL/DB/queue/Vend probes; composer platform constraint adjusted; url_check.sh currently green against PHP built-in server (SAFE_MODE).
