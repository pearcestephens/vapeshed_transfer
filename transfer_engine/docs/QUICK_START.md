# Quick Start Guide - Transfer Engine Enhancement Package

## Instant Enable (Copy-Paste Ready)

### Option 1: Enable Everything (Development/Testing)

Add to `src/Support/Config.php` in the `prime()` method or your config layer:

```php
// === FOOTER UI FEATURES ===
'neuro.unified.ui.smoke_summary_enabled' => true,
'neuro.unified.ui.sse_health_poll_enabled' => true,
'neuro.unified.ui.footer_proposals_enabled' => true,
'neuro.unified.ui.show_diagnostics' => true,

// === NEW READ-ONLY APIs ===
'neuro.unified.ui.history_api_enabled' => true,
'neuro.unified.ui.traces_api_enabled' => true,
'neuro.unified.ui.unified_status_enabled' => true,

// === ENVIRONMENT ===
'neuro.unified.environment' => 'development', // Change to 'production' when ready

// === OPTIONAL: API TOKENS (Production Recommended) ===
// 'neuro.unified.ui.smoke_summary_token' => 'your-secret-token-here',
// 'neuro.unified.ui.api_token' => 'your-shared-api-token-here',
```

### Option 2: Production-Safe (Minimal Footprint)

```php
// Only enable critical operational features
'neuro.unified.ui.smoke_summary_enabled' => true,
'neuro.unified.ui.unified_status_enabled' => true,
'neuro.unified.environment' => 'production',

// Production security tokens (REQUIRED for production)
'neuro.unified.ui.smoke_summary_token' => getenv('SMOKE_API_TOKEN'),
'neuro.unified.ui.api_token' => getenv('UNIFIED_API_TOKEN'),
```

---

## Test Each Feature (30 seconds)

```bash
# 1. Test Smoke Summary API
curl https://staff.vapeshed.co.nz/transfer-engine/api/smoke_summary.php

# 2. Test History API
curl "https://staff.vapeshed.co.nz/transfer-engine/api/history.php?type=pricing&limit=5"

# 3. Test Traces API (use real proposal_id from history)
curl "https://staff.vapeshed.co.nz/transfer-engine/api/traces.php?proposal_id=1"

# 4. Test Unified Status
curl https://staff.vapeshed.co.nz/transfer-engine/api/unified_status.php

# 5. Test SSE Health
curl https://staff.vapeshed.co.nz/transfer-engine/health_sse.php

# 6. With token (if configured)
curl "https://staff.vapeshed.co.nz/transfer-engine/api/history.php?token=YOUR_TOKEN"
```

---

## What You'll See in the UI

Once enabled, the footer will show:

1. **Smoke Status Badge** 🟢
   - Color: GREEN/RED/YELLOW/SKIPPED
   - Updates every 60 seconds
   - Click "View" to see JSON details

2. **SSE Connection Status** 🔵
   - Color changes based on capacity:
     - Green = Connected
     - Yellow = Busy (near capacity)
     - Red = Busy (over capacity)
   - Updates every 90 seconds

3. **Proposals Today** 📊
   - T: <count> — Transfer proposals today
   - P: <count> — Pricing proposals today
   - Updates every 120 seconds

4. **System Diagnostics** (if enabled) 🔧
   - Correlation ID
   - CSRF token preview
   - SSE caps and cadence config

---

## Generate Secure Tokens (Production)

```bash
# Generate smoke API token
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Generate shared API token
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Add to `.env` or equivalent:
```bash
SMOKE_API_TOKEN=your_generated_64_char_hex
UNIFIED_API_TOKEN=your_generated_64_char_hex
```

Then reference in config:
```php
'neuro.unified.ui.smoke_summary_token' => getenv('SMOKE_API_TOKEN'),
'neuro.unified.ui.api_token' => getenv('UNIFIED_API_TOKEN'),
```

---

## Disable Everything Instantly

```php
// Set all flags to false
'neuro.unified.ui.smoke_summary_enabled' => false,
'neuro.unified.ui.sse_health_poll_enabled' => false,
'neuro.unified.ui.footer_proposals_enabled' => false,
'neuro.unified.ui.show_diagnostics' => false,
'neuro.unified.ui.history_api_enabled' => false,
'neuro.unified.ui.traces_api_enabled' => false,
'neuro.unified.ui.unified_status_enabled' => false,
```

---

## Troubleshooting

### Footer features not showing?
1. Check config flags are set to `true`
2. Clear browser cache
3. Check browser console for JavaScript errors
4. Verify API endpoints return 200 OK

### API returns 403 Forbidden?
- Feature flag is disabled, enable it in config

### API returns 401 Unauthorized?
- Token is configured but not provided or invalid
- Include `?token=YOUR_TOKEN` or header `X-API-TOKEN: YOUR_TOKEN`

### Footer badges show "—" or "0"?
- APIs may be disabled or returning empty data
- Check that proposals exist in `proposal_log` table
- Verify database connection is working

### SSE badge stuck on "Connecting..."?
- SSE endpoint may not be accessible
- Check firewall/proxy settings
- Verify `public/sse.php` is accessible

---

## Performance Impact

All features are lightweight:

| Feature | Polling Interval | Payload Size | Impact |
|---------|-----------------|--------------|--------|
| Smoke Badge | 60s | ~2KB | Negligible |
| SSE Health | 90s | ~1KB | Negligible |
| Proposals Today | 120s | ~1KB | Negligible |
| History API | On-demand | ~5-50KB | Low |
| Traces API | On-demand | ~1-10KB | Low |
| Unified Status | On-demand | ~3KB | Low |

**Total continuous load**: ~4KB every 60-120 seconds per connected client.

---

## Integration Examples

### JavaScript - Fetch History

```javascript
async function getRecentPricing() {
    const response = await fetch('/api/history.php?type=pricing&limit=10', {
        headers: { 'X-API-TOKEN': 'your-token-here' }
    });
    const data = await response.json();
    if (data.success) {
        console.log('Recent pricing proposals:', data.data.items);
    }
}
```

### Monitoring Dashboard Integration

```javascript
// Poll unified status every 30 seconds
setInterval(async () => {
    const res = await fetch('/api/unified_status.php');
    const status = await res.json();
    
    if (status.success) {
        updateDashboard({
            transfersToday: status.data.transfer.today,
            pricingToday: status.data.pricing.today,
            dbStatus: status.data.database.status,
            sseLoad: status.data.sse.global + '/' + status.data.sse.max_global
        });
    }
}, 30000);
```

### Python - Health Check Script

```python
import requests

def check_system_health():
    response = requests.get(
        'https://staff.vapeshed.co.nz/transfer-engine/api/unified_status.php',
        headers={'X-API-TOKEN': 'your-token-here'}
    )
    data = response.json()
    
    if data['success']:
        print(f"Transfers today: {data['data']['transfer']['today']}")
        print(f"Pricing today: {data['data']['pricing']['today']}")
        print(f"Database: {data['data']['database']['status']}")
        print(f"SSE status: {data['data']['sse']['status']}")
    
    return data['success']
```

---

## Next Steps After Enabling

1. **Verify Footer UI**: Load any page, check footer for new indicators
2. **Test API Access**: Run curl commands above to verify responses
3. **Generate Tokens**: Create secure tokens for production deployment
4. **Monitor Performance**: Watch for any impact on page load times
5. **Review Logs**: Check for any errors in application logs
6. **Document**: Update internal docs with your specific token storage method

---

## Support & Documentation

- **BUILD_JOURNAL.md** — Complete change log with entries #005-#011
- **SESSION_SUMMARY.md** — Comprehensive overview of all changes
- **POLICY_VALIDATION_REPORT.md** — Policy/guardrail validation details
- **DOMAIN_VALIDATION_REPORT.md** — Transfer/pricing engine validation
- **PROJECT_SPECIFICATION.md** — Updated with all new APIs and flags

---

## Rollback Procedure

If you need to completely remove these features:

1. Set all config flags to `false`
2. Restart PHP-FPM/Apache if needed
3. Clear browser caches
4. Optional: Delete new API files if desired:
   - `public/api/history.php`
   - `public/api/traces.php`
   - `public/api/unified_status.php`
5. Optional: Revert `public/views/partials/footer.php` to pre-enhancement version

All changes are non-destructive and can be safely disabled without data loss.

---

**Last Updated**: 2025-10-04  
**Version**: 1.0.0  
**Status**: Production Ready ✅
