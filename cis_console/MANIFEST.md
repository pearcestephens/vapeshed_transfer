# CIS Console — Files Created (Phase 1 Skeleton)

- .env.example — environment template
- config/app.php — app env + paths
- config/urls.php — endpoint map
- config/security.php — admin token, rate limits, log paths
- public/index.php — GET router (?endpoint=...)
- app/Http/Kernel.php — router kernel
- app/Http/Middleware/AuthMiddleware.php — X-Admin-Token guard
- app/Http/Middleware/RateLimitMiddleware.php — simple file-based rate limiter
- app/Http/Controllers/Admin/HealthController.php — ping/phpinfo/checks
- app/Http/Controllers/Admin/TrafficController.php — SSE live
- app/Http/Controllers/Admin/LogsController.php — apache error tail
- app/Support/Response.php — JSON envelope helper
- resources/views/layout/{header,sidebar,footer}.php — base layout skeleton
- public/assets/css/app.css — minimal styles
- public/assets/js/app.js — placeholder
- tools/verify/verify_urls.sh — URL suite
- tools/quick_dial/apache_tail.sh — snapshot creator
