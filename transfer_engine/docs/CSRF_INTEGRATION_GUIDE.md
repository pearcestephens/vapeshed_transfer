# CSRF Protection Integration Guide

## Overview

This guide shows how to use the CSRF protection system across the Vapeshed Transfer Engine.

**Status**: ✅ Complete as of P0.3
**Components**:
- Server: `CsrfMiddleware` (validates tokens)
- Client: `csrf-fetch.js` (injects tokens)
- Bootstrap: `config/bootstrap.php` (generates tokens)
- Layout: `resources/views/layout/header.php` (meta tag)

---

## Server-Side Protection

### Middleware Configuration

CSRF middleware is enabled globally in `app/Http/Kernel.php`:

```php
$csrfConfig = $this->security['csrf'] ?? ['required' => false, 'token_key' => '_csrf'];

$this->middleware = [
    new CorrelationIdMiddleware(),
    new RateLimitMiddleware($rateConfig),
    new AuthenticationMiddleware(),
    new CsrfMiddleware(
        (bool)($csrfConfig['required'] ?? false),
        (string)($csrfConfig['token_key'] ?? '_csrf')
    ),
];
```

### Enabling CSRF Protection

In `config/security.php`:

```php
return [
    'csrf' => [
        'required' => true,  // Set to true for production
        'token_key' => 'csrf_token',
    ],
];
```

### Protected Methods

The middleware automatically protects:
- `POST`
- `PUT`
- `PATCH`
- `DELETE`

GET, HEAD, OPTIONS requests are **never** blocked.

### Token Validation

The middleware checks for tokens in this order:
1. `X-CSRF-Token` HTTP header (recommended)
2. `csrf_token` in POST body
3. `csrf_token` in query string (not recommended)

---

## Client-Side Integration

### Step 1: Include the CSRF Module

In your view template:

```html
<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>My Page</title>
</head>
<body>
    <!-- Your content -->
    
    <script type="module">
        import { csrfFetch, csrfPost, initCsrfForms } from '/js/csrf-fetch.js';
        
        // Initialize form auto-injection
        initCsrfForms();
        
        // Your code here
    </script>
</body>
</html>
```

### Step 2: Use CSRF-Protected Fetch

#### Basic Usage

```javascript
import { csrfFetch } from '/js/csrf-fetch.js';

// Simple POST request
const response = await csrfFetch('/api/endpoint', {
    method: 'POST',
    body: JSON.stringify({ key: 'value' }),
    headers: { 'Content-Type': 'application/json' }
});

const data = await response.json();
```

#### Helper Functions

```javascript
import { csrfPost, csrfPut, csrfPatch, csrfDelete } from '/js/csrf-fetch.js';

// POST shorthand
const result = await csrfPost('/api/users', { name: 'John' });

// PUT shorthand
const updated = await csrfPut('/api/users/123', { name: 'Jane' });

// PATCH shorthand
const patched = await csrfPatch('/api/users/123', { active: true });

// DELETE shorthand
const deleted = await csrfDelete('/api/users/123');
```

### Step 3: Form Auto-Injection

For traditional HTML forms:

```javascript
import { initCsrfForms } from '/js/csrf-fetch.js';

// Call once on page load
initCsrfForms();
```

This automatically injects hidden CSRF fields into all forms with `method="POST"`.

```html
<form method="POST" action="/submit">
    <input type="text" name="username">
    <!-- Hidden CSRF field auto-injected here -->
    <button type="submit">Submit</button>
</form>
```

---

## Real-World Examples

### Example 1: Webhook Lab Controller

```javascript
// public/js/webhook-lab.js
import { csrfPost } from '/js/csrf-fetch.js';

async function sendWebhook(url, payload) {
    try {
        const result = await csrfPost('/api/webhook-lab/send', {
            url: url,
            payload: payload,
            method: 'POST'
        });
        
        if (result.success) {
            console.log('Webhook sent:', result.data);
            return result.data;
        } else {
            throw new Error(result.error?.message || 'Send failed');
        }
    } catch (err) {
        console.error('Webhook error:', err);
        throw err;
    }
}
```

### Example 2: Vend Tester Controller

```javascript
// public/js/vend-tester.js
import { csrfPost, csrfFetch } from '/js/csrf-fetch.js';

class VendTester {
    async testAuth(domainPrefix, token) {
        const result = await csrfPost('/api/vend-tester/auth', {
            domain_prefix: domainPrefix,
            token: token
        });
        
        return result;
    }
    
    async makeRequest(endpoint, method = 'GET', params = {}) {
        const result = await csrfFetch('/api/vend-tester/request', {
            method: 'POST',
            body: JSON.stringify({
                endpoint: endpoint,
                method: method,
                params: params
            }),
            headers: { 'Content-Type': 'application/json' }
        });
        
        return await result.json();
    }
}
```

### Example 3: Queue Job Tester

```javascript
// public/js/queue-tester.js
import { csrfPost, csrfDelete } from '/js/csrf-fetch.js';

async function dispatchJob(jobType, payload) {
    return await csrfPost('/api/queue-tester/dispatch', {
        job_type: jobType,
        payload: payload
    });
}

async function cancelJob(jobId) {
    return await csrfDelete(`/api/queue-tester/cancel?job_id=${jobId}`);
}

async function runStressTest(jobCount = 100) {
    return await csrfPost('/api/queue-tester/stress', {
        job_count: jobCount
    });
}
```

### Example 4: Dashboard Metrics

```javascript
// public/js/dashboard-index.js
import { csrfPost } from '/js/csrf-fetch.js';

async function updateDashboardMetrics() {
    try {
        const metrics = await csrfPost('/api/dashboard/metrics', {
            include: ['transfers', 'queue', 'performance']
        });
        
        renderMetrics(metrics.data);
    } catch (err) {
        console.error('Failed to fetch metrics:', err);
    }
}

setInterval(updateDashboardMetrics, 10000); // Update every 10s
```

### Example 5: Lightspeed Sync Tester

```javascript
// public/js/lightspeed-tester.js
import { csrfPost } from '/js/csrf-fetch.js';

async function runSync(syncType) {
    const result = await csrfPost('/api/lightspeed-tester/sync', {
        sync_type: syncType
    });
    
    if (result.success) {
        pollSyncStatus(result.data.sync_id);
    }
    
    return result;
}

async function forceFullSync() {
    return await csrfPost('/api/lightspeed-tester/force-sync', {
        full: true,
        force: true
    });
}
```

---

## Debugging CSRF Issues

### Enable Debug Mode

```javascript
import { validateCsrfSetup } from '/js/csrf-fetch.js';

// Run on page load
if (window.location.hostname === 'localhost') {
    validateCsrfSetup();
}
```

**Output**:
```
✅ CSRF token found in meta tag
✅ CSRF token in session: e4f2a8c9b...
✅ Form injection enabled
```

### Common Issues

#### Issue 1: "CSRF token missing"

**Cause**: Meta tag not present or session expired.

**Fix**:
```php
// In layout header
<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
```

#### Issue 2: "CSRF validation failed"

**Cause**: Token mismatch between client and server.

**Fix**: Ensure session is started before rendering views:
```php
// config/bootstrap.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

#### Issue 3: "Fetch not using CSRF wrapper"

**Cause**: Direct `fetch()` call instead of `csrfFetch()`.

**Fix**:
```javascript
// ❌ Wrong
fetch('/api/endpoint', { method: 'POST', body: data });

// ✅ Correct
import { csrfPost } from '/js/csrf-fetch.js';
csrfPost('/api/endpoint', data);
```

---

## Testing CSRF Protection

### Manual Testing

1. **Test without token**:
```bash
curl -X POST https://staff.vapeshed.co.nz/admin/?endpoint=api/test \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```
Expected: `403 Forbidden` or `419 CSRF Token Missing`

2. **Test with valid token**:
```bash
TOKEN="your_session_token"
curl -X POST https://staff.vapeshed.co.nz/admin/?endpoint=api/test \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"test": "data"}'
```
Expected: `200 OK` with valid response

### Automated Testing

```bash
cd transfer_engine
vendor/bin/phpunit tests/Security/CsrfProtectionTest.php
```

**Expected output**:
```
PHPUnit 10.5.x

.............  13 / 13 (100%)

Time: 00:00.123, Memory: 8.00 MB

OK (13 tests, 32 assertions)
```

---

## Migration Checklist

When migrating existing code to CSRF protection:

- [ ] Add `<meta name="csrf-token">` to layout header
- [ ] Replace all `fetch()` calls with `csrfFetch()` for mutating requests
- [ ] Import `csrf-fetch.js` module in JavaScript files
- [ ] Enable CSRF in `config/security.php` (`'required' => true`)
- [ ] Run test suite to verify protection
- [ ] Test all API endpoints manually with/without tokens
- [ ] Add `initCsrfForms()` to pages with traditional forms
- [ ] Update API documentation with CSRF requirements

---

## Performance Notes

### Zero Overhead for GET Requests

The CSRF wrapper adds **zero overhead** for GET requests:

```javascript
// These are identical
fetch('/api/data');
csrfFetch('/api/data');
```

### Token Caching

The CSRF token is read once and cached:

```javascript
// First call: reads from DOM
await csrfPost('/api/endpoint1', data1);

// Subsequent calls: uses cached token
await csrfPost('/api/endpoint2', data2);
await csrfPost('/api/endpoint3', data3);
```

### Header Injection Cost

Token injection adds ~50 bytes to each mutating request:

```
X-CSRF-Token: e4f2a8c9b3d71a0f6e2c4b8a1d9f7e3c0a5b8d1f4e7a2c6b9d3f8e1a4c7b0d5
```

This is negligible compared to typical JSON payloads.

---

## Security Best Practices

1. **Always use HTTPS** - CSRF tokens transmitted over HTTP can be intercepted
2. **Regenerate token after login** - Prevents session fixation attacks
3. **Use short session timeouts** - Reduces token exposure window
4. **Never log CSRF tokens** - Keep tokens out of application logs
5. **Validate token length** - Ensure tokens are 64 hex characters (32 bytes)
6. **Use constant-time comparison** - Prevents timing attacks (already implemented)
7. **Don't expose tokens in URLs** - Use headers or POST body only

---

## Quick Reference

### Import Statements

```javascript
import {
    csrfFetch,        // Generic fetch wrapper
    csrfPost,         // POST helper
    csrfPut,          // PUT helper
    csrfPatch,        // PATCH helper
    csrfDelete,       // DELETE helper
    csrfSubmitForm,   // Form submission helper
    getCsrfToken,     // Get current token
    initCsrfForms,    // Auto-inject into forms
    validateCsrfSetup // Debug helper
} from '/js/csrf-fetch.js';
```

### Server Configuration

```php
// config/security.php
'csrf' => [
    'required' => true,
    'token_key' => 'csrf_token',
],
```

### Meta Tag

```html
<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
```

### Bootstrap Initialization

```php
// config/bootstrap.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

---

## Support

For issues or questions:
- Check logs: `logs/application.log`
- Run debug: `validateCsrfSetup()` in browser console
- Test suite: `vendor/bin/phpunit tests/Security/CsrfProtectionTest.php`
- Review middleware: `app/Http/Middleware/CsrfMiddleware.php`

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-19  
**Phase**: P0.3 - CSRF Enforcement Complete  
**Author**: Vapeshed Transfer Engine Security Team
