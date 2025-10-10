# Sections 11 & 12 Specification
**CIS Staff Portal - Web Traffic Monitoring & API Testing Suite**

**Version**: 1.0.0  
**Date**: October 9, 2025  
**Status**: IMPLEMENTATION IN PROGRESS  
**Branch**: feat/sections-11-12-phase1-3  
**Installation Path**: `/home/master/applications/jcepnzzkmj/public_html/assets/services/neuro/`

---

## PHASE 0: DISCOVERY & PLANNING

### 0.A Scope Map

#### Section 11: Web Traffic & Site Monitoring
**Purpose**: Real-time monitoring, performance analytics, traffic analysis, error tracking, and site health checks

**Sub-Components**:
1. **11.1 Traffic Monitor** - Live visitor tracking, RPS monitoring, endpoint health
2. **11.2 Performance Analytics** - Page load times, API response times, slow query detection
3. **11.3 Traffic Sources** - Geo mapping, browser/device analytics, bot detection
4. **11.4 Error Tracking** - 404/500 monitoring, stack trace analysis, error grouping
5. **11.5 Site Health Check** - SSL, DB, PHP-FPM, Queue, Disk, Vend API connectivity

#### Section 12: API Testing & Debugging
**Purpose**: Comprehensive API testing laboratory for Vend integration, webhooks, and internal endpoints

**Sub-Components**:
1. **12.1 Webhook Test Lab** - Event simulation, JSON editor, response viewer, code snippets
2. **12.2 Vend API Tester** - Endpoint selector, query builder, auth test, history/replay
3. **12.3 Lightspeed Sync Tester** - Transfer→Consignment, PO→Consignment, stock sync, webhooks
4. **12.4 Queue Job Tester** - Job dispatch, status monitoring, stress testing, job cancellation
5. **12.5 API Endpoint Tester** - Suite runner for Transfer(9), PO(9), Inventory(5), Webhook(3)
6. **12.6 Code Snippet Library** - Copy-paste examples with "Try it" integration

---

### 0.B URL & Endpoint Contract Table

| Section | Route Pattern | Controller | Admin Auth | Environment Variable |
|---------|--------------|------------|------------|---------------------|
| **Section 11: Traffic Monitoring** |
| Traffic Dashboard | `/admin/traffic` | `TrafficController@index` | ✅ Required | `TRAFFIC_MONITOR_ENABLED` |
| Live Feed (SSE) | `/admin/traffic/live` | `TrafficController@liveFeed` | ✅ Required | - |
| Traffic Stats | `/admin/traffic/stats` | `TrafficController@stats` | ✅ Required | - |
| Performance Metrics | `/admin/performance` | `PerformanceController@index` | ✅ Required | - |
| Slow Queries | `/admin/performance/slow-queries` | `PerformanceController@slowQueries` | ✅ Required | `QUERY_LOG_ENABLED` |
| Error Tracking | `/admin/errors` | `ErrorController@index` | ✅ Required | - |
| Error Detail | `/admin/errors/{id}` | `ErrorController@show` | ✅ Required | - |
| Create Redirect | `/admin/errors/create-redirect` | `ErrorController@createRedirect` | ✅ Required | - |
| Health Check | `/admin/health/one-click` | `HealthController@oneClick` | ✅ Required | - |
| Health Components | `/admin/health/checks` | `HealthController@checks` | ✅ Required | - |
| Apache Logs | `/admin/logs/apache-error-tail` | `LogsController@apacheTail` | ✅ Required | `APACHE_ERROR_LOG_PATH` |
| PHP-FPM Logs | `/admin/logs/phpfpm-tail` | `LogsController@phpFpmTail` | ✅ Required | `PHP_FPM_LOG_PATH` |
| **Section 12: API Testing** |
| Webhook Lab | `/admin/api-test/webhook` | `ApiTestController@webhook` | ✅ Required | - |
| Webhook Send | `/admin/api-test/webhook/send` | `ApiTestController@sendWebhook` | ✅ Required | - |
| Vend API Tester | `/admin/api-test/vend` | `ApiTestController@vend` | ✅ Required | `VEND_API_URL` |
| Vend Test Endpoint | `/admin/api-test/vend/test` | `ApiTestController@testVend` | ✅ Required | `VEND_API_TOKEN` |
| Sync Tester | `/admin/api-test/sync` | `ApiTestController@sync` | ✅ Required | - |
| Sync Execute | `/admin/api-test/sync/execute` | `ApiTestController@executeSync` | ✅ Required | - |
| Queue Tester | `/admin/api-test/queue` | `ApiTestController@queue` | ✅ Required | `QUEUE_CONNECTION` |
| Queue Dispatch | `/admin/api-test/queue/dispatch` | `ApiTestController@dispatchJob` | ✅ Required | - |
| Endpoint Tester | `/admin/api-test/endpoints` | `ApiTestController@endpoints` | ✅ Required | - |
| Endpoint Run | `/admin/api-test/endpoints/run` | `ApiTestController@runTest` | ✅ Required | - |
| Snippet Library | `/admin/api-test/snippets` | `ApiTestController@snippets` | ✅ Required | - |

---

### 0.C Data & Telemetry Sources

#### Traffic Monitoring Data Sources:
- **Live Visitor Count**: In-memory counter (Redis if available, fallback to DB rolling window)
- **Requests/Second**: Rolling 5-minute window aggregation
- **Endpoint Health**: Last 100 requests per endpoint with status codes
- **Performance Metrics**: Apache access logs + DB query logs + application timers
- **Error Tracking**: PHP error log + application exception handler + 404 logger
- **Geo Data**: IP geolocation service (configurable provider: MaxMind, IP-API, etc.)
- **Bot Detection**: User-Agent analysis + rate pattern heuristics

#### API Testing Data Sources:
- **Vend API**: Live credentials from `.env` (VEND_API_URL, VEND_API_TOKEN, VEND_DOMAIN_PREFIX)
- **Webhook History**: `webhook_logs` table (payload, response, timing, status)
- **Queue Jobs**: Laravel/Symphony queue tables or custom `jobs` table
- **Test History**: `api_test_history` table (endpoint, params, response, duration)
- **Code Snippets**: Static JSON/PHP files or DB table `code_snippets`

#### Database Tables Required:
```sql
-- Traffic monitoring
CREATE TABLE IF NOT EXISTS traffic_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri VARCHAR(512),
    request_method VARCHAR(10),
    response_status INT,
    response_time_ms INT,
    memory_usage_mb DECIMAL(10,2),
    country_code VARCHAR(2),
    is_bot BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_status (response_status),
    INDEX idx_uri (request_uri(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Error tracking
CREATE TABLE IF NOT EXISTS error_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    error_type VARCHAR(100),
    error_message TEXT,
    file_path VARCHAR(512),
    line_number INT,
    stack_trace TEXT,
    request_uri VARCHAR(512),
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    context JSON,
    occurrences INT DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved BOOLEAN DEFAULT FALSE,
    INDEX idx_type (error_type),
    INDEX idx_resolved (resolved),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Redirects management
CREATE TABLE IF NOT EXISTS redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_path VARCHAR(512) NOT NULL,
    to_path VARCHAR(512) NOT NULL,
    status_code INT DEFAULT 301,
    hits INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_from (from_path(255)),
    INDEX idx_hits (hits)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API test history
CREATE TABLE IF NOT EXISTS api_test_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    test_type VARCHAR(50),
    endpoint VARCHAR(255),
    method VARCHAR(10),
    request_payload JSON,
    response_payload JSON,
    response_status INT,
    response_time_ms INT,
    success BOOLEAN,
    error_message TEXT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_test_type (test_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Webhook logs
CREATE TABLE IF NOT EXISTS webhook_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100),
    payload JSON,
    response_status INT,
    response_body TEXT,
    response_time_ms INT,
    retry_count INT DEFAULT 0,
    success BOOLEAN,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 0.D Risk Register

| Risk | Impact | Mitigation |
|------|--------|-----------|
| **URL Rot** - External API URLs change without notice | HIGH | Store URLs in `.env`, validate before use, health check endpoint |
| **Auth Leakage** - API tokens exposed in logs/UI | CRITICAL | Mask tokens in UI, redact from logs, use secure session storage |
| **Rate Limiting** - Vend API throttles requests | MEDIUM | Implement rate limiter (10 req/min default), queue for bulk tests |
| **Log Growth** - Apache/PHP-FPM logs fill disk | HIGH | Gzip snapshots, rotate daily, limit tail to 200 lines, disk alerts |
| **PII in Logs** - Customer data logged unintentionally | CRITICAL | Redact email/phone/CC, sanitize before storage, GDPR compliance |
| **Long-polling Pitfalls** - SSE connections timeout | MEDIUM | Auto-reconnect logic, heartbeat every 30s, fallback to polling |
| **Memory Exhaustion** - Large log tails crash PHP | HIGH | Stream logs with generators, limit memory to 128MB, pagination |
| **SQL Injection** - User input in traffic queries | CRITICAL | Prepared statements only, validate all inputs, escape for display |
| **XSS in Error Display** - Stack traces contain malicious code | HIGH | HTML entity encode all output, use CSP headers, sanitize JSON |
| **DDoS Amplification** - Traffic monitor itself becomes bottleneck | MEDIUM | Cache aggregates 5min, rate limit dashboard, use Redis for counters |

---

### 0.E Acceptance Criteria

#### Section 11: Web Traffic & Site Monitoring
- [ ] **Traffic Monitor** displays live visitor count (last 5min) with auto-refresh every 10s
- [ ] **RPS Chart** updates in real-time, shows last 60 minutes with color-coded thresholds
- [ ] **Endpoint Health Grid** shows color-coded status (green/yellow/red) for all monitored endpoints
- [ ] **Live Request Feed** streams via SSE with IP, method, URI, status, response time
- [ ] **Performance Analytics** displays p50/p95/p99 for page loads and API calls
- [ ] **Slow Queries** lists queries >1s with EXPLAIN button that shows query plan
- [ ] **Error Tracking** groups similar errors by type+file+line with occurrence count
- [ ] **404 Errors** have "Create Redirect" button that creates DB entry and updates routing
- [ ] **One-Click Health** tests all 6 components (SSL/DB/PHP-FPM/Queue/Disk/Vend) in <5s
- [ ] **Apache Log Tail** streams last 200 lines with download option and auto-scroll
- [ ] **DDoS Heuristics** detects burst (>100 req/min from single IP) and sustained (>500 req/5min)

#### Section 12: API Testing & Debugging
- [ ] **Webhook Lab** sends test webhooks to custom URLs and system endpoints
- [ ] **JSON Editor** validates JSON syntax before send with syntax highlighting
- [ ] **Response Viewer** displays status, headers, body, timing in tabbed interface
- [ ] **Code Snippets** generates cURL, PHP Guzzle, JavaScript Fetch examples
- [ ] **Vend API Tester** authenticates with token, lists available endpoints, builds queries
- [ ] **Query Builder** constructs filter params (after, before, outlet_id) with validation
- [ ] **Test History** stores last 50 tests per user with replay button
- [ ] **Sync Tester** executes Transfer→Consignment with dry-run option
- [ ] **Full Pipeline Test** runs all 5 sync tests sequentially with stop-on-fail option
- [ ] **Queue Job Tester** dispatches test jobs and monitors status in real-time
- [ ] **Stress Mode** dispatches 100 jobs with progress bar and summary statistics
- [ ] **Endpoint Suite Runner** executes all 26 endpoint tests with pass/fail summary

---

### 0.F Phase 1-3 Task Plan

#### PHASE 1: Shared Infrastructure (30 min estimated)
**Goal**: Establish foundation - config, routing, middleware, templates, helpers

**Tasks**:
1. Create `/config/sections_11_12.php` - URLs, thresholds, feature flags
2. Create `/config/security.php` - CSRF, rate limits, auth gates
3. Update `/public/index.php` - Add Section 11/12 routes to GET router
4. Create `/app/Http/Middleware/AdminAuth.php` - Authentication check
5. Create `/app/Http/Middleware/RateLimit.php` - Request throttling
6. Create `/app/Support/Response.php` - JSON envelope helper
7. Create `/app/Support/TrafficLogger.php` - Request logging utility
8. Create `/resources/views/layout/admin_header.php` - Navigation with Section 11/12 links
9. Create `/resources/views/layout/admin_sidebar.php` - Sub-navigation
10. Create `/public/assets/css/sections-11-12.css` - Shared styles
11. Create `/public/assets/js/sections-11-12.js` - Shared JavaScript utilities
12. Create `tools/verify/url_check.sh` - URL verification script
13. Create `tools/quick_dial/apache_tail.sh` - Log snapshot script

**Self-Test**:
- `php -l` on all new PHP files
- `curl -I https://staff.vapeshed.co.nz/admin/traffic` (expect 200 when authed, 401/403 when not)
- `bash tools/verify/url_check.sh` (all URLs return expected codes)
- `bash tools/quick_dial/apache_tail.sh` (creates gzipped snapshot)

#### PHASE 2: Section 11 Implementation (60 min estimated)
**Goal**: Build all 5 traffic monitoring components with real-time updates

**Tasks**:
1. Create `/app/Controllers/TrafficController.php` - Traffic monitor logic
2. Create `/app/Controllers/PerformanceController.php` - Performance analytics
3. Create `/app/Controllers/ErrorController.php` - Error tracking and redirects
4. Create `/app/Controllers/HealthController.php` - Site health checks
5. Create `/app/Services/TrafficMonitor.php` - Visitor counting, RPS calculation
6. Create `/app/Services/PerformanceAnalyzer.php` - Metric aggregation, slow queries
7. Create `/app/Services/ErrorTracker.php` - Error grouping, stack trace analysis
8. Create `/app/Services/HealthChecker.php` - Component health tests
9. Create `/resources/views/traffic/index.php` - Traffic dashboard UI
10. Create `/resources/views/performance/index.php` - Performance analytics UI
11. Create `/resources/views/errors/index.php` - Error tracking UI
12. Create `/resources/views/health/index.php` - Health check UI
13. Create `/public/assets/js/traffic-monitor.js` - Live feed SSE, charts
14. Create `/public/assets/js/performance-charts.js` - Chart.js integration
15. Create `/public/assets/css/traffic-dashboard.css` - Dashboard styling

**Self-Test**:
- Generate synthetic load: `ab -n 1000 -c 10 https://staff.vapeshed.co.nz/`
- Verify RPS chart updates in dashboard
- Trigger 404: `curl https://staff.vapeshed.co.nz/nonexistent`
- Verify error appears in tracking panel
- Click "Create Redirect" and verify DB insert
- Run "One Click Health" and verify all 6 checks pass/fail correctly
- Verify SSE connection: Browser DevTools → Network → EventStream active

#### PHASE 3: Section 12 Implementation (60 min estimated)
**Goal**: Build all 6 API testing tools with history and replay

**Tasks**:
1. Create `/app/Controllers/ApiTestController.php` - API test orchestration
2. Create `/app/Services/WebhookTester.php` - Webhook simulation
3. Create `/app/Services/VendApiTester.php` - Vend API integration
4. Create `/app/Services/SyncTester.php` - Lightspeed sync tests
5. Create `/app/Services/QueueTester.php` - Queue job management
6. Create `/app/Services/EndpointTester.php` - Endpoint suite runner
7. Create `/resources/views/api-test/webhook.php` - Webhook lab UI
8. Create `/resources/views/api-test/vend.php` - Vend tester UI
9. Create `/resources/views/api-test/sync.php` - Sync tester UI
10. Create `/resources/views/api-test/queue.php` - Queue tester UI
11. Create `/resources/views/api-test/endpoints.php` - Endpoint tester UI
12. Create `/resources/views/api-test/snippets.php` - Code snippet library UI
13. Create `/public/assets/js/api-test.js` - Test execution, history management
14. Create `/public/assets/js/code-editor.js` - Monaco/ACE editor integration
15. Create `/public/assets/css/api-test.css` - Testing UI styling

**Self-Test**:
- Send test webhook to `/admin/api-test/webhook/send` with sample payload
- Verify response viewer displays status, headers, body
- Test Vend API auth with valid token
- Verify endpoint list populates from Vend API docs
- Execute Transfer→Consignment sync test (dry-run)
- Verify test history stores payload and response
- Dispatch 100 queue jobs in stress mode
- Verify progress bar updates and summary shows completion
- Run full endpoint suite (26 tests)
- Verify pass/fail summary with detailed error messages
- Copy code snippet and verify "Try it" prefills tester

---

### 0.G Quick-Dial Log Blueprint

**Purpose**: One-click access to Apache/PHP-FPM error logs with rate limiting and CSRF protection

**Components**:

1. **Controller Endpoint**: `/admin/logs/apache-error-tail?lines=200`
   - Method: GET
   - Auth: Required (admin only)
   - Rate Limit: 10 requests per minute per user
   - CSRF: Token required in header
   - Response: JSON with log lines array

2. **Shell Script**: `tools/quick_dial/apache_tail.sh`
   ```bash
   #!/bin/bash
   # Apache Error Log Tail with Snapshot
   LOG_PATH="/var/log/apache2/error.log"
   SNAPSHOT_DIR="/var/log/cis/snapshots"
   LINES=${1:-200}
   
   mkdir -p "$SNAPSHOT_DIR"
   TIMESTAMP=$(date +%Y%m%d_%H%M%S)
   
   tail -n "$LINES" "$LOG_PATH" | gzip > "$SNAPSHOT_DIR/apache_error_${TIMESTAMP}.log.gz"
   tail -n "$LINES" "$LOG_PATH"
   ```

3. **UI Button**: In `/resources/views/admin/logs.php`
   ```html
   <button onclick="viewApacheLogs()" class="btn btn-primary">
       <i class="fas fa-file-alt"></i> View Apache Error Log
   </button>
   <button onclick="downloadApacheLogs()" class="btn btn-secondary">
       <i class="fas fa-download"></i> Download Log Snapshot
   </button>
   ```

4. **JavaScript Handler**:
   ```javascript
   async function viewApacheLogs() {
       showLoading();
       const response = await fetch('/admin/logs/apache-error-tail?lines=200', {
           headers: { 'X-CSRF-Token': getCsrfToken() }
       });
       const data = await response.json();
       displayLogsInModal(data.logs);
       hideLoading();
   }
   ```

---

### 0.H URL Verification Suite

**Script**: `tools/verify/url_check.sh`

```bash
#!/bin/bash
# URL Verification Suite for Sections 11 & 12

BASE_URL="https://staff.vapeshed.co.nz"
TOKEN="test_auth_token"

echo "🔍 URL Verification Suite - Sections 11 & 12"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check_url() {
    URL=$1
    EXPECTED=$2
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN" "$URL")
    
    if [ "$STATUS" -eq "$EXPECTED" ]; then
        echo "✅ $URL → $STATUS"
    else
        echo "❌ $URL → Expected $EXPECTED, got $STATUS"
    fi
}

echo ""
echo "Section 11: Traffic Monitoring"
check_url "$BASE_URL/admin/traffic" 200
check_url "$BASE_URL/admin/traffic/live" 200
check_url "$BASE_URL/admin/performance" 200
check_url "$BASE_URL/admin/errors" 200
check_url "$BASE_URL/admin/health/one-click" 200

echo ""
echo "Section 12: API Testing"
check_url "$BASE_URL/admin/api-test/webhook" 200
check_url "$BASE_URL/admin/api-test/vend" 200
check_url "$BASE_URL/admin/api-test/sync" 200
check_url "$BASE_URL/admin/api-test/queue" 200
check_url "$BASE_URL/admin/api-test/endpoints" 200

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Verification Complete"
```

---

## ARTIFACTS SUMMARY (Phase 0)

**Deliverables**:
- ✅ Scope Map for Section 11 (5 components) & Section 12 (6 components)
- ✅ URL & Endpoint Contract Table (20 routes mapped)
- ✅ Data & Telemetry Sources (5 DB tables, multiple log sources)
- ✅ Risk Register (10 critical risks with mitigations)
- ✅ Acceptance Criteria (22 testable requirements)
- ✅ Phase 1-3 Task Plan (43 tasks, 150 min estimated)
- ✅ Quick-Dial Log Blueprint (4 components)
- ✅ URL Verification Suite (bash script template)

**Next Steps**:
→ Execute PHASE 1: Shared Infrastructure (13 files)

---

**Document Status**: PHASE 0 COMPLETE ✅  
**Timestamp**: 2025-10-09 12:00:00 UTC  
**Next Phase**: PHASE 1 - Shared Infrastructure
