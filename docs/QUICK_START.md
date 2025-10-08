# Quick Start Guide: Transfer Engine API

**Version:** 2.0.0  
**Last Updated:** 2025-10-07  
**For:** Developers, Integrators, Frontend Teams

## Getting Started in 5 Minutes

### 1. Environment Setup

Copy the example configuration:
```bash
cd transfer_engine
cp .env.example .env
nano .env  # Edit with your settings
```

Minimal required settings:
```bash
APP_ENV=development
DB_HOST=localhost
DB_NAME=vapeshed_unified
DB_USER=your_user
DB_PASS=your_password
```

### 2. Database Setup

Run migrations:
```bash
php bin/run_migrations.php
```

### 3. Verify Installation

Run health check:
```bash
php public/health.php
```

Expected output:
```json
{
  "status": "healthy",
  "checks": { "database": "connected", "config": "valid" }
}
```

### 4. Test API Endpoints

Run smoke tests:
```bash
# HTTP mode (recommended)
SMOKE_BASE_URL=http://localhost/transfer-engine php bin/http_smoke.php
```

Expected output:
```json
{
  "status": "GREEN",
  "results": {
    "transfer.status": { "ok": true, "meta": true },
    "pricing.status": { "ok": true, "meta": true }
  }
}
```

## API Overview

### Base URL
```
https://staff.vapeshed.co.nz/transfer-engine/api/
```

### Authentication
Optional token authentication via header:
```bash
Authorization: Bearer YOUR_TOKEN
```

Set in `.env`:
```bash
API_TOKEN=your_secret_token_here
```

### Standard Response Format
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "correlation_id": "abc123",
    "method": "GET",
    "endpoint": "transfer.php",
    "path": "/api/transfer.php",
    "ts": 1696412345,
    "duration_ms": 42
  }
}
```

## Core Endpoints

### Transfer API

**Get Status:**
```bash
GET /api/transfer.php?action=status
```

Response:
```json
{
  "success": true,
  "stats": {
    "pending": 3,
    "today": 1,
    "failed": 0,
    "total": 12
  }
}
```

**Get Queue:**
```bash
GET /api/transfer.php?action=queue
```

**Execute Transfers:**
```bash
POST /api/transfer.php?action=execute
Content-Type: application/json

{
  "ids": [101, 102, 103]
}
```

### Pricing API

**Get Status:**
```bash
GET /api/pricing.php?action=status
```

Response:
```json
{
  "success": true,
  "stats": {
    "total": 18,
    "propose": 7,
    "auto": 4,
    "discard": 5,
    "blocked": 2,
    "today": 3
  },
  "auto_apply_status": "manual"
}
```

**Get Candidates:**
```bash
GET /api/pricing.php?action=candidates&limit=50
```

**Apply Proposals:**
```bash
POST /api/pricing.php?action=apply
Content-Type: application/json

{
  "apply_all": false,
  "proposal_ids": [201, 202, 203]
}
```

### Dashboard APIs

**System Status:**
```bash
GET /api/unified_status.php
```

**Dashboard Stats:**
```bash
GET /api/stats.php
```

**Module Status:**
```bash
GET /api/modules.php
```

**Activity Feed:**
```bash
GET /api/activity.php?limit=20&offset=0
```

**History:**
```bash
GET /api/history.php?type=transfer&limit=50
```

**Guardrail Traces:**
```bash
GET /api/traces.php?proposal_id=12345
```

### Utility APIs

**Session Info (CSRF Token):**
```bash
GET /api/session.php
```

Response:
```json
{
  "success": true,
  "data": {
    "csrf_token": "abc123xyz",
    "correlation_id": "def456",
    "ts": 1696412345
  }
}
```

**Diagnostics (Admin):**
```bash
GET /api/diagnostics.php
Authorization: Bearer YOUR_ADMIN_TOKEN
```

Requires: `DIAGNOSTICS_ENABLED=true`

## Real-Time Updates (SSE)

### Connect to Event Stream
```javascript
const sse = new EventSource('/transfer-engine/sse.php?topics=status,transfer,pricing');

sse.addEventListener('status', (event) => {
  const data = JSON.parse(event.data);
  updateDashboard(data);
});

sse.addEventListener('transfer', (event) => {
  const data = JSON.parse(event.data);
  showNotification(`Transfer completed: ${data.items_count} items`);
});
```

### Available Events
- `system` - Connection status, heartbeat
- `status` - System-wide status updates
- `transfer` - Transfer events
- `pricing` - Pricing events
- `heartbeat` - Keepalive (every 15s)
- `error` - Error notifications

## Rate Limiting

All endpoints enforce per-IP rate limits with burst capacity.

### Response Headers
```
X-RateLimit-Limit: 90
X-RateLimit-Burst: 20
X-RateLimit-Remaining: 85
X-RateLimit-Reset: 1696412400
```

### 429 Too Many Requests
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMITED",
    "message": "Too many GET requests, slow down"
  }
}
```

Header: `Retry-After: 42` (seconds)

### Tuning Limits
See `.env.example` for all configuration options.

Example:
```bash
PRICING_GET_RL_PER_MIN=200  # Increase pricing API limit
PRICING_GET_RL_BURST=50
```

## Error Handling

### Standard Error Format
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid product_id parameter",
    "details": { "field": "product_id", "expected": "integer" }
  },
  "meta": {
    "correlation_id": "abc123",
    "method": "GET",
    "endpoint": "transfer.php",
    "path": "/api/transfer.php",
    "ts": 1696412345,
    "duration_ms": 5
  }
}
```

### Common Error Codes
- `VALIDATION_ERROR` - Invalid input parameters
- `NOT_FOUND` - Resource not found
- `UNAUTHORIZED` - Missing or invalid token
- `FORBIDDEN` - Insufficient permissions
- `RATE_LIMITED` - Too many requests
- `INTERNAL_ERROR` - Server error (check logs)

## Development Tips

### Enable Debug Mode
```bash
APP_ENV=development
APP_DEBUG=true
```

### Disable Rate Limits
```bash
GET_RL_PER_MIN=0
POST_RL_PER_MIN=0
```

### Use Correlation IDs
Always include correlation ID for request tracing:
```javascript
fetch('/api/transfer.php?action=status', {
  headers: {
    'X-Correlation-ID': generateUUID()
  }
})
```

### CORS for Local Development
```bash
APP_ENV=development  # Enables permissive CORS
```

Production requires explicit allowlist:
```bash
CORS_ALLOWLIST=https://staff.vapeshed.co.nz,https://app.example.com
```

## Testing

### Run All Smoke Tests
```bash
./run_smoke_test.sh
```

Or manually:
```bash
SMOKE_BASE_URL=https://staff.vapeshed.co.nz/transfer-engine \
  php bin/http_smoke.php
```

### Test Specific Endpoint
```bash
curl -i https://staff.vapeshed.co.nz/transfer-engine/api/transfer.php?action=status
```

### Load Testing
Use Apache Bench or similar:
```bash
ab -n 1000 -c 10 \
  https://staff.vapeshed.co.nz/transfer-engine/api/transfer.php?action=status
```

Monitor rate limit headers to ensure correct enforcement.

## Production Checklist

Before deploying to production:

- [ ] Database migrations applied
- [ ] `.env` configured with production values
- [ ] `APP_ENV=production` set
- [ ] `APP_DEBUG=false` set
- [ ] API token set (if using)
- [ ] CORS allowlist configured
- [ ] Rate limits reviewed and appropriate
- [ ] Health check returns 200
- [ ] Smoke tests pass
- [ ] Logs directory writable
- [ ] Storage/tmp directory writable
- [ ] PHP-FPM restarted
- [ ] Monitoring/alerting configured

## Getting Help

### Documentation
- `docs/PROJECT_SPECIFICATION.md` - Complete API reference
- `docs/runbooks/RATE_LIMIT_MANAGEMENT.md` - Rate limit tuning
- `CONFIG_VALIDATION_REPORT.md` - Configuration inventory

### Support
- Engineering Team: <pearce.stephens@ecigdis.co.nz>
- Documentation: `docs/` directory
- Issues: Check application logs in `storage/logs/`

## Next Steps

1. **Explore the dashboard**: Visit `/transfer-engine/unified_dashboard.php`
2. **Read the spec**: See `docs/PROJECT_SPECIFICATION.md`
3. **Try real-time updates**: Test SSE connection
4. **Build an integration**: Use your preferred HTTP client
5. **Monitor performance**: Check diagnostics endpoint

Happy coding! 🚀
