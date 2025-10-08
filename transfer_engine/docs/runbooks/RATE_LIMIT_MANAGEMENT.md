# Operational Runbook: Rate Limit Management

**Last Updated:** 2025-10-07  
**Maintainer:** Engineering Team  
**Audience:** Operations, SRE, DevOps

## Overview

The Transfer Engine API endpoints implement configurable per-IP rate limiting at both global and per-endpoint-group levels. This runbook covers monitoring, tuning, and troubleshooting rate limit behavior.

## Architecture

### Rate Limit Hierarchy
1. **Global Defaults**: Apply to all endpoints unless overridden
2. **Group-Specific**: Override global for specific endpoint groups (pricing, transfer, etc.)
3. **Environment Variables**: Override code defaults without deployment

### Enforcement Mechanism
- Per-IP token bucket algorithm
- Separate buckets for GET and POST methods
- State stored in ephemeral files: `storage/tmp/{get|post}_{ip}.bucket`
- 60-second sliding window with burst capacity

## Configuration

### Global Rate Limits
```bash
GET_RL_PER_MIN=120      # Base GET requests per minute
GET_RL_BURST=30         # Additional burst headroom
POST_RL_PER_MIN=0       # Base POST requests (0 = unlimited)
POST_RL_BURST=0         # POST burst headroom
```

### Group-Specific Rate Limits
Each endpoint group can be tuned independently:

```bash
# Example: Pricing API
PRICING_GET_RL_PER_MIN=90
PRICING_GET_RL_BURST=20
PRICING_POST_RL_PER_MIN=30
PRICING_POST_RL_BURST=10
```

**Available Groups:**
- `pricing` - Pricing intelligence API
- `transfer` - Transfer engine API
- `history` - Historical proposals (read-only)
- `traces` - Guardrail traces (read-only)
- `stats` - Dashboard statistics
- `modules` - Module status
- `activity` - Activity feed
- `smoke` - Smoke test summary
- `unified` - Unified status
- `session` - Session/CSRF API
- `diagnostics` - Diagnostics API

### Default Rate Limits by Group

| Group       | GET/min | GET Burst | POST/min | POST Burst | Notes                    |
|-------------|---------|-----------|----------|------------|--------------------------|
| pricing     | 90      | 20        | 30       | 10         | Write-capable            |
| transfer    | 120     | 40        | 40       | 15         | Write-capable            |
| history     | 80      | 20        | 0        | 0          | Read-only                |
| traces      | 60      | 15        | 0        | 0          | Read-only                |
| stats       | 45      | 15        | 0        | 0          | Dashboard polling        |
| modules     | 45      | 15        | 0        | 0          | Dashboard polling        |
| activity    | 60      | 20        | 0        | 0          | Feed updates             |
| smoke       | 15      | 5         | 0        | 0          | Low-frequency monitoring |
| unified     | 30      | 10        | 0        | 0          | Health checks            |
| session     | 150     | 30        | 0        | 0          | High-frequency OK        |
| diagnostics | 20      | 5         | 0        | 0          | Admin only               |

## Monitoring

### Check Current Configuration
```bash
# Via diagnostics API (requires DIAGNOSTICS_ENABLED=true and token)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://staff.vapeshed.co.nz/transfer-engine/api/diagnostics.php
```

Response includes:
- Current rate limit configuration for all groups
- Your IP address
- Current bucket usage (if any)
- Environment and security settings

### Response Headers
All rate-limited endpoints expose observability headers:

**GET Requests:**
```
X-RateLimit-Limit: 90
X-RateLimit-Burst: 20
X-RateLimit-Remaining: 85
X-RateLimit-Reset: 1696412400
```

**POST Requests:**
```
X-RateLimit-POST-Limit: 30
X-RateLimit-POST-Burst: 10
X-RateLimit-POST-Remaining: 25
X-RateLimit-POST-Reset: 1696412400
```

### 429 Rate Limit Exceeded
When rate limited:
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMITED",
    "message": "Too many GET requests, slow down"
  }
}
```

HTTP Status: `429 Too Many Requests`  
Header: `Retry-After: 42` (seconds until window resets)

## Tuning Guidelines

### Dashboard Polling
For real-time dashboard updates:
- Stats/modules: 30-60/min typical (1-2 second intervals)
- Burst: 15-20 to handle reconnects
- Monitor: Ensure `Remaining` stays positive during peak

### Automated Clients
For batch operations or automation:
- Respect `Retry-After` header on 429
- Implement exponential backoff
- Use correlation IDs for tracing
- Consider dedicated API token with higher limits

### High-Traffic Scenarios
If legitimate traffic hits limits:

1. **Identify the group**: Check which endpoint is constrained
2. **Review usage pattern**: Is it legitimate or abuse?
3. **Increase limits**: Set environment variable for that group
4. **Restart PHP-FPM**: Limits are cached at bootstrap
5. **Monitor**: Verify new limits resolve the issue

Example adjustment:
```bash
# Increase transfer API limits
TRANSFER_GET_RL_PER_MIN=200
TRANSFER_GET_RL_BURST=60
```

### Security Incidents
If under attack:
1. **Identify source IPs**: Check logs for patterns
2. **Reduce limits**: Temporarily lower limits for affected groups
3. **Enable CSRF**: Set `CSRF_REQUIRED=true` for POST protection
4. **IP blocking**: Use firewall/WAF for persistent attackers
5. **Token enforcement**: Set `API_TOKEN` to require authentication

## Troubleshooting

### Symptom: Legitimate users getting 429
**Cause:** Limits too low or polling too aggressive

**Resolution:**
1. Check diagnostics endpoint for current config
2. Review `X-RateLimit-Remaining` in response headers
3. Increase limits for affected group
4. Optimize client polling intervals

### Symptom: Rate limits not applying
**Cause:** Configuration not loaded or PHP-FPM cache

**Resolution:**
1. Verify `.env` file exists and is readable
2. Check `Config::prime()` is called in bootstrap
3. Restart PHP-FPM: `sudo service php8.2-fpm restart`
4. Verify via diagnostics endpoint

### Symptom: Bucket state persists after restart
**Cause:** Bucket files in `storage/tmp/` not cleared

**Resolution:**
```bash
# Clear all rate limit buckets
rm -f storage/tmp/get_*.bucket storage/tmp/post_*.bucket
```

**Note:** Buckets auto-expire after 60 seconds; manual cleanup rarely needed.

### Symptom: Different users share same bucket
**Cause:** Proxy or load balancer masking real IP

**Resolution:**
1. Configure `X-Forwarded-For` trust in web server
2. Update `$_SERVER['REMOTE_ADDR']` to use forwarded IP
3. Ensure unique IPs are visible to application

## Best Practices

### Development
- Use `.env.example` as template
- Set generous limits: `GET_RL_PER_MIN=999`, `GET_RL_BURST=999`
- Or disable: Set to `0` to skip enforcement

### Staging
- Mirror production config structure
- Use slightly higher limits for load testing
- Enable diagnostics API for visibility

### Production
- Conservative defaults (current values are production-ready)
- Enable CSRF for all write operations
- Disable diagnostics API unless actively troubleshooting
- Monitor `429` rates in application logs

## Configuration Validation

### Lint Check
```bash
php bin/unified_config_lint.php
```

Validates all required configuration keys are present.

### Smoke Test
```bash
SMOKE_BASE_URL=https://staff.vapeshed.co.nz/transfer-engine \
  php bin/http_smoke.php
```

Tests all API endpoints including rate limit headers and meta fields.

## Related Documentation
- `CONFIG_VALIDATION_REPORT.md` - Complete configuration inventory
- `docs/PROJECT_SPECIFICATION.md` - API contracts and security model
- `.env.example` - Environment variable reference

## Support Contacts
- **Operations Team**: ops@ecigdis.co.nz
- **Engineering Lead**: Pearce Stephens <pearce.stephens@ecigdis.co.nz>
- **On-Call**: Check PagerDuty rotation
