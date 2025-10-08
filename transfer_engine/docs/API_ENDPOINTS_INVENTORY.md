# API Endpoints Inventory

## Status: PRODUCTION READY ✅
**Last Updated:** 2025-10-07  
**Total Endpoints:** 13

---

## Endpoint Catalog

### 1. **Session Endpoint** 
**Path:** `/public/api/session.php`  
**Method:** GET  
**Rate Limit:** 150 req/min + 30 burst  
**Purpose:** Get CSRF token and session info

**Response:**
```json
{
  "success": true,
  "data": {
    "csrf_token": "...",
    "correlation_id": "...",
    "session_id": "...",
    "session_lifetime": 1440,
    "session_started": 1696636800,
    "ts": 1696636800
  },
  "meta": {
    "correlation_id": "...",
    "method": "GET",
    "endpoint": "session.php",
    "path": "/api/session.php",
    "ts": 1696636800,
    "duration_ms": 15
  }
}
```

---

### 2. **Health Check Endpoint**
**Path:** `/public/api/health.php`  
**Method:** GET  
**Rate Limit:** 120 req/min + 30 burst  
**Purpose:** System health monitoring

**Query Parameters:**
- `detailed=1` - Include detailed metrics (optional)

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy|degraded|unhealthy",
    "timestamp": 1696636800,
    "version": "2.0.0",
    "environment": "production",
    "checks": {
      "database": { "status": "healthy", "message": "..." },
      "configuration": { "status": "healthy", "message": "..." },
      "storage": { "status": "healthy", "directories": {...} },
      "memory": { "status": "healthy", "usage_mb": 45.2, "usage_percent": 35.5 }
    }
  }
}
```

**HTTP Status Codes:**
- `200` - Healthy or degraded
- `503` - Unhealthy

---

### 3. **Metrics Endpoint**
**Path:** `/public/api/metrics.php`  
**Method:** GET  
**Rate Limit:** 60 req/min + 20 burst  
**Cache TTL:** 30 seconds  
**Purpose:** Real-time system and application metrics

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": 1696636800,
    "system": {
      "memory": { "usage_mb": 45.2, "peak_mb": 52.1 },
      "load": { "1min": 0.5, "5min": 0.4, "15min": 0.3 },
      "disk": { "used_percent": 65.5 }
    },
    "database": {
      "size_mb": 1250.5,
      "table_count": 45,
      "connections": { "current": 5, "running": 2 },
      "queries": { "total": 125000, "slow": 3 }
    },
    "application": {
      "transfers_24h": { "total": 150, "completed": 145, "failed": 5 },
      "price_changes_24h": 320,
      "insights_24h": { "total": 25, "critical": 2, "warning": 10 }
    },
    "queue": {
      "jobs_24h": { "total": 450, "pending": 10, "completed": 435 }
    }
  }
}
```

---

### 4. **Diagnostics Endpoint**
**Path:** `/public/api/diagnostics.php`  
**Method:** GET  
**Rate Limit:** 20 req/min + 5 burst  
**Purpose:** Rate limit diagnostics

---

### 5. **Stats Endpoint**
**Path:** `/public/api/stats.php`  
**Method:** GET  
**Rate Limit:** 45 req/min + 15 burst  
**Purpose:** Transfer and pricing statistics

---

### 6. **Transfer Endpoint**
**Path:** `/public/api/transfer.php`  
**Method:** GET, POST  
**Rate Limit:** 120 req/min (GET) + 40 burst, 40 req/min (POST) + 15 burst  
**Purpose:** Transfer operations

---

### 7. **Pricing Endpoint**
**Path:** `/public/api/pricing.php`  
**Method:** GET, POST  
**Rate Limit:** 90 req/min (GET) + 20 burst, 30 req/min (POST) + 10 burst  
**Purpose:** Pricing analysis and updates

---

### 8. **History Endpoint**
**Path:** `/public/api/history.php`  
**Method:** GET  
**Rate Limit:** 80 req/min + 20 burst  
**Purpose:** Transfer execution history

---

### 9. **Traces Endpoint**
**Path:** `/public/api/traces.php`  
**Method:** GET  
**Rate Limit:** 60 req/min + 15 burst  
**Purpose:** Execution trace logs

---

### 10. **Unified Status Endpoint**
**Path:** `/public/api/unified_status.php`  
**Method:** GET  
**Rate Limit:** 30 req/min + 10 burst  
**Purpose:** Unified system status

---

### 11. **Modules Endpoint**
**Path:** `/public/api/modules.php`  
**Method:** GET  
**Rate Limit:** 45 req/min + 15 burst  
**Purpose:** Module configuration and status

---

### 12. **Activity Endpoint**
**Path:** `/public/api/activity.php`  
**Method:** GET  
**Rate Limit:** 60 req/min + 20 burst  
**Purpose:** System activity log

---

### 13. **Smoke Summary Endpoint**
**Path:** `/public/api/smoke_summary.php`  
**Method:** GET  
**Rate Limit:** 15 req/min + 5 burst  
**Purpose:** Smoke test results

---

## Standard Response Envelope

All endpoints follow standardized response format:

```json
{
  "success": true|false,
  "data": { ... },
  "error": { "code": "...", "message": "..." },
  "meta": {
    "correlation_id": "unique-request-id",
    "method": "GET|POST",
    "endpoint": "filename.php",
    "path": "/api/path",
    "ts": 1696636800,
    "duration_ms": 25
  }
}
```

---

## Security Features

### Applied to ALL Endpoints:
- ✅ JSON content type headers
- ✅ Security headers (CSP, X-Frame-Options, etc.)
- ✅ CORS with environment-aware allowlist
- ✅ OPTIONS preflight handling
- ✅ Per-IP rate limiting (group-specific)
- ✅ Correlation ID tracking
- ✅ Request duration tracking
- ✅ Standardized error responses

### Optional Security (configurable):
- Bearer token authentication
- CSRF token validation
- Internal network restrictions
- Request logging

---

## Rate Limit Headers

All endpoints return rate limit headers:

```
X-RateLimit-Limit: 120
X-RateLimit-Burst: 30
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1696636860
```

POST endpoints also include:
```
X-RateLimit-POST-Limit: 40
X-RateLimit-POST-Burst: 15
X-RateLimit-POST-Remaining: 32
X-RateLimit-POST-Reset: 1696636860
```

**429 Response on Rate Limit:**
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMITED",
    "message": "Too many GET requests, slow down"
  }
}
```

---

## Configuration

Rate limits configurable via environment variables:

```bash
# Global defaults
GET_RL_PER_MIN=120
GET_RL_BURST=30
POST_RL_PER_MIN=0
POST_RL_BURST=0

# Per-endpoint overrides
HEALTH_GET_RL_PER_MIN=120
METRICS_GET_RL_PER_MIN=60
SESSION_GET_RL_PER_MIN=150
# ... etc
```

---

## Monitoring Integration

### Recommended Monitoring Queries:

1. **Health Check:** `GET /api/health.php?detailed=1`
2. **Metrics Dashboard:** `GET /api/metrics.php` (auto-cached 30s)
3. **Rate Limit Status:** `GET /api/diagnostics.php`

### Alerting Thresholds:

- Health status = `unhealthy` → P1 alert
- Health status = `degraded` → P2 alert
- Memory usage > 90% → Warning
- Disk usage > 90% → Warning
- Slow queries > 100 → Investigation

---

## Development vs Production

**Development Mode:**
- CORS: `*` (all origins allowed)
- Additional debug fields in responses
- Relaxed rate limits (optional)

**Production Mode:**
- CORS: Allowlist only (staff.vapeshed.co.nz)
- Strict security headers
- HSTS enabled (HTTPS only)
- Full rate limiting enforced

---

## Next Steps

1. ✅ All endpoints standardized
2. ✅ Security hardening complete
3. ✅ Rate limiting operational
4. ✅ Health & metrics monitoring ready
5. 🔜 Frontend integration testing
6. 🔜 Load testing and optimization
7. 🔜 Production deployment validation
