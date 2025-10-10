# Security Guide
**Vape Shed Transfer Engine - Complete Security & Compliance Reference**

Version: 1.0.0  
Last Updated: October 9, 2025  
For: Security Team, System Administrators, Compliance Officers

---

## Table of Contents

1. [Security Overview](#security-overview)
2. [Authentication & Authorization](#authentication--authorization)
3. [Data Protection](#data-protection)
4. [Network Security](#network-security)
5. [Application Security](#application-security)
6. [Infrastructure Security](#infrastructure-security)
7. [Security Monitoring](#security-monitoring)
8. [Incident Response](#incident-response)
9. [Compliance](#compliance)
10. [Security Auditing](#security-auditing)
11. [Penetration Testing](#penetration-testing)
12. [Security Best Practices](#security-best-practices)

---

## Security Overview

### Security Objectives

**CIA Triad:**
```
Confidentiality: Protect sensitive data from unauthorized access
Integrity: Ensure data accuracy and prevent unauthorized modification
Availability: Ensure system availability to authorized users
```

**Additional Principles:**
```
Non-repudiation: Audit trail of all actions
Accountability: User actions are traceable
Least Privilege: Minimal necessary permissions
Defense in Depth: Multiple layers of security
```

### Security Architecture

**Multi-Layer Security:**
```
┌─────────────────────────────────────────────────────────┐
│ Layer 7: User Education & Policies                      │
├─────────────────────────────────────────────────────────┤
│ Layer 6: Application Security (Input validation, CSRF)  │
├─────────────────────────────────────────────────────────┤
│ Layer 5: Authentication & Authorization (Sessions, MFA) │
├─────────────────────────────────────────────────────────┤
│ Layer 4: Data Protection (Encryption, Hashing)          │
├─────────────────────────────────────────────────────────┤
│ Layer 3: Network Security (Firewall, IDS/IPS)           │
├─────────────────────────────────────────────────────────┤
│ Layer 2: Infrastructure Security (OS hardening, Patches)│
├─────────────────────────────────────────────────────────┤
│ Layer 1: Physical Security (Data center access)         │
└─────────────────────────────────────────────────────────┘
```

### Threat Model

**Identified Threats:**

**External Threats:**
- Brute force attacks (login, API)
- SQL injection
- Cross-Site Scripting (XSS)
- Cross-Site Request Forgery (CSRF)
- DDoS attacks
- Man-in-the-Middle (MITM)
- Data breaches

**Internal Threats:**
- Insider access abuse
- Accidental data exposure
- Social engineering
- Privilege escalation
- Data exfiltration

**Mitigation Strategy:**
Each threat has corresponding controls documented in this guide.

---

## Authentication & Authorization

### Password Security

**Password Policy:**
```yaml
Minimum Length: 12 characters
Complexity Requirements:
  - Uppercase letter (A-Z)
  - Lowercase letter (a-z)
  - Number (0-9)
  - Special character (!@#$%^&*()_+-=[]{}|;:,.<>?)
  
Expiration: 90 days
History: Cannot reuse last 5 passwords
Lockout: 5 failed attempts = 30 minute lockout
```

**Implementation:**
```php
// app/Services/PasswordService.php

class PasswordService
{
    private const MIN_LENGTH = 12;
    private const HISTORY_COUNT = 5;
    private const EXPIRATION_DAYS = 90;
    
    public function validate(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . self::MIN_LENGTH . ' characters';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return $errors;
    }
    
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64 MB
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }
    
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }
    
    public function isInHistory(int $userId, string $password): bool
    {
        $history = DB::table('password_history')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(self::HISTORY_COUNT)
            ->pluck('password_hash');
        
        foreach ($history as $hash) {
            if (password_verify($password, $hash)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function isExpired(int $userId): bool
    {
        $lastChange = DB::table('users')
            ->where('user_id', $userId)
            ->value('password_changed_at');
        
        if (!$lastChange) {
            return true;
        }
        
        $expirationDate = date('Y-m-d H:i:s', strtotime($lastChange . ' +' . self::EXPIRATION_DAYS . ' days'));
        
        return $expirationDate < date('Y-m-d H:i:s');
    }
}
```

### Multi-Factor Authentication

**Supported Methods:**

**1. TOTP (Time-based One-Time Password)**
```php
// Using OTPHP library
use OTPHP\TOTP;

class TOTPService
{
    public function generateSecret(): string
    {
        $totp = TOTP::create();
        return $totp->getSecret();
    }
    
    public function getQRCode(string $secret, string $userEmail): string
    {
        $totp = TOTP::create($secret);
        $totp->setLabel($userEmail);
        $totp->setIssuer('Vape Shed Transfer Engine');
        
        return $totp->getProvisioningUri();
    }
    
    public function verify(string $secret, string $code): bool
    {
        $totp = TOTP::create($secret);
        return $totp->verify($code, null, 1); // Allow ±30 seconds
    }
}
```

**2. Backup Codes**
```php
class BackupCodeService
{
    public function generate(int $userId): array
    {
        $codes = [];
        
        for ($i = 0; $i < 10; $i++) {
            $code = $this->generateCode();
            $codes[] = $code;
            
            DB::table('backup_codes')->insert([
                'user_id' => $userId,
                'code_hash' => password_hash($code, PASSWORD_ARGON2ID),
                'used' => false,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        
        return $codes;
    }
    
    private function generateCode(): string
    {
        return strtoupper(bin2hex(random_bytes(5))); // 10 characters
    }
    
    public function verify(int $userId, string $code): bool
    {
        $backupCodes = DB::table('backup_codes')
            ->where('user_id', $userId)
            ->where('used', false)
            ->get();
        
        foreach ($backupCodes as $backupCode) {
            if (password_verify($code, $backupCode->code_hash)) {
                DB::table('backup_codes')
                    ->where('id', $backupCode->id)
                    ->update(['used' => true, 'used_at' => date('Y-m-d H:i:s')]);
                
                return true;
            }
        }
        
        return false;
    }
}
```

**3. SMS (via Twilio)**
```php
class SMSMFAService
{
    private $twilio;
    
    public function __construct()
    {
        $this->twilio = new \Twilio\Rest\Client(
            env('TWILIO_ACCOUNT_SID'),
            env('TWILIO_AUTH_TOKEN')
        );
    }
    
    public function send(string $phoneNumber): string
    {
        $code = $this->generateCode();
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes
        
        // Store code
        DB::table('sms_codes')->insert([
            'phone_number' => $phoneNumber,
            'code_hash' => password_hash($code, PASSWORD_ARGON2ID),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        
        // Send SMS
        $this->twilio->messages->create($phoneNumber, [
            'from' => env('TWILIO_FROM_NUMBER'),
            'body' => "Your verification code is: $code\n\nValid for 5 minutes."
        ]);
        
        return $code;
    }
    
    private function generateCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    public function verify(string $phoneNumber, string $code): bool
    {
        $record = DB::table('sms_codes')
            ->where('phone_number', $phoneNumber)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$record) {
            return false;
        }
        
        $valid = password_verify($code, $record->code_hash);
        
        if ($valid) {
            // Invalidate code after use
            DB::table('sms_codes')->where('id', $record->id)->delete();
        }
        
        return $valid;
    }
}
```

### Session Management

**Secure Session Configuration:**
```php
// config/session.php

return [
    'driver' => 'redis',
    'lifetime' => 120, // 2 hours
    'expire_on_close' => false,
    'encrypt' => true,
    'cookie' => 'transfer_session',
    'secure' => true,      // HTTPS only
    'http_only' => true,   // Not accessible via JavaScript
    'same_site' => 'strict', // CSRF protection
];
```

**Session Security Implementation:**
```php
class SessionSecurityMiddleware
{
    public function handle($request, $next)
    {
        // Regenerate session ID after login
        if ($request->session()->has('just_logged_in')) {
            $request->session()->regenerate();
            $request->session()->forget('just_logged_in');
        }
        
        // Check session fingerprint
        $fingerprint = $this->generateFingerprint($request);
        if ($request->session()->has('fingerprint')) {
            if ($request->session()->get('fingerprint') !== $fingerprint) {
                // Possible session hijacking
                $request->session()->invalidate();
                return redirect()->route('login')->with('error', 'Session validation failed');
            }
        } else {
            $request->session()->put('fingerprint', $fingerprint);
        }
        
        // Update activity timestamp
        $request->session()->put('last_activity', time());
        
        return $next($request);
    }
    
    private function generateFingerprint($request): string
    {
        return hash('sha256', implode('|', [
            $request->ip(),
            $request->header('User-Agent'),
            $request->header('Accept-Language'),
        ]));
    }
}
```

### Authorization

**Role-Based Access Control (RBAC):**

```php
// app/Services/AuthorizationService.php

class AuthorizationService
{
    private $user;
    
    public function __construct($user)
    {
        $this->user = $user;
    }
    
    public function can(string $permission): bool
    {
        // Super admin can do everything
        if ($this->user->role === 'super_admin') {
            return true;
        }
        
        // Check user's role permissions
        $permissions = DB::table('role_permissions')
            ->join('roles', 'role_permissions.role_id', '=', 'roles.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.permission_id')
            ->where('roles.role_id', $this->user->role_id)
            ->pluck('permissions.permission_name')
            ->toArray();
        
        // Check exact match
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // Check wildcard match (e.g., transfers.* matches transfers.create)
        foreach ($permissions as $allowed) {
            if (fnmatch($allowed, $permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function canAccessStore(int $storeId): bool
    {
        // Super admin and administrators can access all stores
        if (in_array($this->user->role, ['super_admin', 'administrator'])) {
            return true;
        }
        
        // Check if user is assigned to store
        return DB::table('user_stores')
            ->where('user_id', $this->user->user_id)
            ->where('store_id', $storeId)
            ->exists();
    }
    
    public function canModifyTransfer(int $transferId): bool
    {
        $transfer = DB::table('transfers')->where('transfer_id', $transferId)->first();
        
        if (!$transfer) {
            return false;
        }
        
        // Check if user can access both stores
        return $this->canAccessStore($transfer->from_store_id) 
            && $this->canAccessStore($transfer->to_store_id);
    }
}
```

**Usage:**
```php
// In controller
if (!$this->auth->can('transfers.create')) {
    return $this->forbidden('Insufficient permissions');
}

// In view
<?php if (can('transfers.approve')): ?>
    <button class="btn-approve">Approve</button>
<?php endif; ?>
```

---

## Data Protection

### Encryption

**Encryption at Rest:**

```php
// app/Services/EncryptionService.php

class EncryptionService
{
    private string $key;
    private string $cipher = 'aes-256-gcm';
    
    public function __construct()
    {
        $this->key = base64_decode(env('APP_KEY'));
        
        if (strlen($this->key) !== 32) {
            throw new \Exception('Invalid encryption key length');
        }
    }
    
    public function encrypt(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }
        
        // Combine IV + encrypted data + tag
        return base64_encode($iv . $encrypted . $tag);
    }
    
    public function decrypt(string $encrypted): string
    {
        $data = base64_decode($encrypted);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, -16);
        $ciphertext = substr($data, $ivLength, -16);
        
        $decrypted = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }
        
        return $decrypted;
    }
}
```

**Sensitive Data Encryption:**
```php
// Encrypt sensitive fields in database
class User extends Model
{
    protected $encrypted = ['phone_number', 'ssn', 'bank_account'];
    
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encrypted) && $value !== null) {
            $value = app(EncryptionService::class)->encrypt($value);
        }
        
        return parent::setAttribute($key, $value);
    }
    
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        
        if (in_array($key, $this->encrypted) && $value !== null) {
            $value = app(EncryptionService::class)->decrypt($value);
        }
        
        return $value;
    }
}
```

**Encryption in Transit:**

```nginx
# Force HTTPS
server {
    listen 80;
    server_name transfer.vapeshed.co.nz;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name transfer.vapeshed.co.nz;
    
    # TLS Configuration
    ssl_certificate /etc/letsencrypt/live/transfer.vapeshed.co.nz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/transfer.vapeshed.co.nz/privkey.pem;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    
    # ... rest of config
}
```

### Data Sanitization

**Input Validation:**
```php
class TransferValidator
{
    public function validate(array $data): array
    {
        $errors = [];
        
        // Validate from_store_id
        if (!isset($data['from_store_id']) || !is_numeric($data['from_store_id'])) {
            $errors['from_store_id'] = 'Invalid from store ID';
        }
        
        // Validate to_store_id
        if (!isset($data['to_store_id']) || !is_numeric($data['to_store_id'])) {
            $errors['to_store_id'] = 'Invalid to store ID';
        }
        
        // Validate items
        if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
            $errors['items'] = 'Items array is required and must contain at least one item';
        } else {
            foreach ($data['items'] as $index => $item) {
                if (!isset($item['product_id']) || !is_numeric($item['product_id'])) {
                    $errors["items.$index.product_id"] = 'Invalid product ID';
                }
                
                if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                    $errors["items.$index.quantity"] = 'Quantity must be a positive number';
                }
            }
        }
        
        return $errors;
    }
}
```

**Output Encoding:**
```php
// HTML context
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// JavaScript context
echo json_encode($userInput, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// URL context
echo urlencode($userInput);

// SQL context (use prepared statements)
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute([$username]);
```

### Data Masking

**PII Protection:**
```php
class DataMaskingService
{
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        if (strlen($username) <= 3) {
            $masked = substr($username, 0, 1) . '***';
        } else {
            $masked = substr($username, 0, 2) . '***' . substr($username, -1);
        }
        
        return $masked . '@' . $domain;
    }
    
    public function maskPhone(string $phone): string
    {
        // +64 21 XXX XXXX → +64 21 *** **XX
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        $length = strlen($cleaned);
        
        if ($length < 8) {
            return '***';
        }
        
        return substr($cleaned, 0, $length - 6) . '***' . substr($cleaned, -2);
    }
    
    public function maskCreditCard(string $card): string
    {
        // 1234 5678 9012 3456 → **** **** **** 3456
        $cleaned = preg_replace('/[^0-9]/', '', $card);
        return str_repeat('*', strlen($cleaned) - 4) . substr($cleaned, -4);
    }
}
```

### Secure Data Deletion

**Soft Delete vs Hard Delete:**
```php
class SecureDeleteService
{
    public function softDelete(string $table, int $id): void
    {
        DB::table($table)
            ->where('id', $id)
            ->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => Auth::user()->user_id,
            ]);
    }
    
    public function hardDelete(string $table, int $id): void
    {
        // Log deletion for audit
        Log::info("Hard delete", [
            'table' => $table,
            'id' => $id,
            'user_id' => Auth::user()->user_id,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        
        // Perform deletion
        DB::table($table)->where('id', $id)->delete();
    }
    
    public function secureWipe(string $table, int $id): void
    {
        // Overwrite sensitive data before deletion
        DB::table($table)
            ->where('id', $id)
            ->update([
                'phone_number' => 'DELETED',
                'email' => 'deleted@deleted.com',
                'ssn' => '000-00-0000',
                'bank_account' => '0000000000',
            ]);
        
        // Then hard delete
        $this->hardDelete($table, $id);
    }
}
```

---

## Network Security

### Firewall Configuration

**UFW (Uncomplicated Firewall):**
```bash
# Default policies
ufw default deny incoming
ufw default allow outgoing

# SSH (custom port)
ufw allow 2222/tcp comment 'SSH'

# HTTP/HTTPS
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'

# Limit SSH brute force
ufw limit 2222/tcp

# Allow from specific IPs only (admin panel)
ufw allow from 203.0.113.0/24 to any port 443 comment 'Office network'

# Enable firewall
ufw enable

# Check status
ufw status verbose
```

**iptables (Advanced):**
```bash
# Flush existing rules
iptables -F
iptables -X

# Default policies
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT

# Allow loopback
iptables -A INPUT -i lo -j ACCEPT

# Allow established connections
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

# Allow SSH (rate limited)
iptables -A INPUT -p tcp --dport 2222 -m state --state NEW -m recent --set
iptables -A INPUT -p tcp --dport 2222 -m state --state NEW -m recent --update --seconds 60 --hitcount 4 -j DROP
iptables -A INPUT -p tcp --dport 2222 -j ACCEPT

# Allow HTTP/HTTPS
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT

# Drop invalid packets
iptables -A INPUT -m state --state INVALID -j DROP

# Log dropped packets
iptables -A INPUT -j LOG --log-prefix "iptables-dropped: "

# Save rules
iptables-save > /etc/iptables/rules.v4
```

### Fail2Ban Configuration

**Installation:**
```bash
apt install fail2ban -y
```

**Configuration** (`/etc/fail2ban/jail.local`):
```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
destemail = security@vapeshed.co.nz
sendername = Fail2Ban

[sshd]
enabled = true
port = 2222
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log
maxretry = 5

[nginx-noscript]
enabled = true
filter = nginx-noscript
logpath = /var/log/nginx/access.log
maxretry = 6

[nginx-badbots]
enabled = true
filter = nginx-badbots
logpath = /var/log/nginx/access.log
maxretry = 2

[nginx-noproxy]
enabled = true
filter = nginx-noproxy
logpath = /var/log/nginx/access.log
maxretry = 2

[php-url-fopen]
enabled = true
filter = php-url-fopen
logpath = /var/log/nginx/access.log
maxretry = 2

[transfer-engine-login]
enabled = true
filter = transfer-engine-login
logpath = /var/www/transfer-engine/storage/logs/security.log
maxretry = 5
bantime = 1800
```

**Custom Filter** (`/etc/fail2ban/filter.d/transfer-engine-login.conf`):
```ini
[Definition]
failregex = ^.*Login attempt failed.*username=<HOST>.*$
            ^.*Suspicious activity detected.*ip=<HOST>.*$
ignoreregex =
```

**Start Fail2Ban:**
```bash
systemctl enable fail2ban
systemctl start fail2ban

# Check status
fail2ban-client status
fail2ban-client status transfer-engine-login
```

### DDoS Protection

**Nginx Rate Limiting:**
```nginx
# Define rate limit zones
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=general:10m rate=20r/s;

# Connection limiting
limit_conn_zone $binary_remote_addr zone=addr:10m;

server {
    # ... existing config
    
    # Apply limits
    location /login {
        limit_req zone=login burst=2 nodelay;
        limit_conn addr 5;
        # ... rest of config
    }
    
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        limit_conn addr 10;
        # ... rest of config
    }
    
    location / {
        limit_req zone=general burst=50 nodelay;
        limit_conn addr 20;
        # ... rest of config
    }
}
```

**Cloudflare DDoS Protection:**
```
Enable via Cloudflare Dashboard:
1. Security → DDoS → On
2. Firewall Rules:
   - Challenge on threat score > 10
   - Block on threat score > 50
   - Rate limiting: 100 req/10s per IP
3. Under Attack Mode (emergency): Enable temporarily
```

---

## Application Security

### CSRF Protection

**Token Generation:**
```php
class CSRFProtection
{
    private string $tokenName = '_csrf_token';
    
    public function generateToken(): string
    {
        if (!session()->has($this->tokenName)) {
            $token = bin2hex(random_bytes(32));
            session()->put($this->tokenName, $token);
        }
        
        return session()->get($this->tokenName);
    }
    
    public function validateToken(string $token): bool
    {
        $sessionToken = session()->get($this->tokenName);
        
        if (!$sessionToken || !$token) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
}
```

**Usage in Forms:**
```php
<form method="POST" action="/transfers">
    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
    <!-- ... form fields -->
</form>
```

**Middleware Validation:**
```php
class VerifyCSRFToken
{
    public function handle($request, $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $token = $request->input('_csrf_token') ?? $request->header('X-CSRF-Token');
            
            if (!app(CSRFProtection::class)->validateToken($token)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CSRF_TOKEN_MISMATCH',
                        'message' => 'CSRF token validation failed',
                    ],
                ], 403);
            }
        }
        
        return $next($request);
    }
}
```

### XSS Prevention

**Content Security Policy:**
```php
// Set CSP header
header("Content-Security-Policy: " . implode('; ', [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' https://fonts.gstatic.com",
    "img-src 'self' data: https:",
    "connect-src 'self' https://api.vend.com",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
]));
```

**Input Sanitization:**
```php
class XSSProtection
{
    public function sanitize(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Decode entities
        $input = html_entity_decode($input, ENT_QUOTES, 'UTF-8');
        
        // Remove script tags
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
        
        // Remove event handlers
        $input = preg_replace('/on\w+\s*=\s*["\'].*?["\']/i', '', $input);
        
        // Encode special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    public function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->sanitize($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            }
        }
        
        return $data;
    }
}
```

### SQL Injection Prevention

**Always Use Prepared Statements:**
```php
// ✅ GOOD: Prepared statement
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND active = ?');
$stmt->execute([$username, 1]);

// ❌ BAD: String concatenation
$sql = "SELECT * FROM users WHERE username = '$username' AND active = 1";
$result = $pdo->query($sql);
```

**Query Builder (Safe):**
```php
// Using query builder (automatically uses prepared statements)
$users = DB::table('users')
    ->where('username', $username)
    ->where('active', 1)
    ->get();

// With raw SQL (still use bindings)
$users = DB::select('SELECT * FROM users WHERE username = ? AND active = ?', [$username, 1]);
```

### File Upload Security

**Secure Upload Handler:**
```php
class SecureFileUpload
{
    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'text/csv',
    ];
    
    private int $maxFileSize = 20971520; // 20 MB
    
    public function upload(array $file): array
    {
        // Validate file exists
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'error' => 'File too large'];
        }
        
        // Check MIME type (not just extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        // Generate secure filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        
        // Upload to secure directory (outside webroot or with .htaccess protection)
        $uploadDir = '/var/www/transfer-engine/storage/uploads/';
        $destination = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Upload failed'];
        }
        
        // Set permissions
        chmod($destination, 0644);
        
        return ['success' => true, 'filename' => $filename];
    }
}
```

**Protect Upload Directory:**
```apache
# .htaccess in upload directory
<Files *>
    Order Deny,Allow
    Deny from all
</Files>

<FilesMatch "\.(jpg|jpeg|png|gif|pdf)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Disable PHP execution
php_flag engine off
```

---

## Infrastructure Security

### OS Hardening

**Ubuntu Server Hardening:**

```bash
# 1. Keep system updated
apt update && apt upgrade -y
apt dist-upgrade -y

# 2. Install security tools
apt install -y ufw fail2ban rkhunter clamav aide

# 3. Disable unnecessary services
systemctl disable bluetooth.service
systemctl disable avahi-daemon.service

# 4. Configure automatic security updates
apt install unattended-upgrades -y
dpkg-reconfigure --priority=low unattended-upgrades

# 5. Harden SSH
sed -i 's/#PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/#Port 22/Port 2222/' /etc/ssh/sshd_config
systemctl restart sshd

# 6. Configure system limits
cat >> /etc/security/limits.conf << EOF
* soft nofile 65535
* hard nofile 65535
* soft nproc 65535
* hard nproc 65535
EOF

# 7. Kernel hardening (sysctl)
cat >> /etc/sysctl.conf << EOF
# IP forwarding
net.ipv4.ip_forward = 0

# SYN flood protection
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.tcp_synack_retries = 2
net.ipv4.tcp_syn_retries = 5

# Ignore ICMP redirects
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0

# Ignore source routed packets
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0

# Log martians
net.ipv4.conf.all.log_martians = 1

# Ignore ICMP ping requests
net.ipv4.icmp_echo_ignore_all = 1
EOF

sysctl -p
```

### Docker Security (if using containers)

**Secure Dockerfile:**
```dockerfile
FROM php:8.2-fpm-alpine

# Run as non-root user
RUN addgroup -g 1000 appuser && \
    adduser -D -u 1000 -G appuser appuser

# Install dependencies
RUN apk add --no-cache \
    nginx \
    mysql-client \
    && docker-php-ext-install pdo_mysql opcache

# Copy application
COPY --chown=appuser:appuser . /var/www

# Set working directory
WORKDIR /var/www

# Switch to non-root user
USER appuser

# Expose port
EXPOSE 9000

CMD ["php-fpm"]
```

**Docker Compose Security:**
```yaml
version: '3.8'

services:
  app:
    image: transfer-engine:latest
    read_only: true
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
    tmpfs:
      - /tmp:noexec,nosuid,nodev
    volumes:
      - ./storage:/var/www/storage:rw
    environment:
      - APP_ENV=production
    networks:
      - internal
    
  db:
    image: mariadb:10.11
    read_only: true
    security_opt:
      - no-new-privileges:true
    environment:
      - MYSQL_ROOT_PASSWORD_FILE=/run/secrets/db_root_password
    secrets:
      - db_root_password
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - internal

networks:
  internal:
    driver: bridge
    internal: true

secrets:
  db_root_password:
    file: ./secrets/db_root_password.txt

volumes:
  db_data:
```

### Secrets Management

**Environment Variables (Development):**
```bash
# .env (never commit to git)
DB_PASSWORD=secure_password_here
VEND_TOKEN=secret_token_here
```

**Vault (Production):**
```bash
# Using HashiCorp Vault
vault kv put secret/transfer-engine/production \
  db_password="secure_password" \
  vend_token="secret_token"

# Retrieve in application
$dbPassword = shell_exec('vault kv get -field=db_password secret/transfer-engine/production');
```

**AWS Secrets Manager (Cloud):**
```php
use Aws\SecretsManager\SecretsManagerClient;

$client = new SecretsManagerClient([
    'version' => 'latest',
    'region' => 'ap-southeast-2',
]);

$result = $client->getSecretValue([
    'SecretId' => 'transfer-engine/production',
]);

$secrets = json_decode($result['SecretString'], true);
$dbPassword = $secrets['db_password'];
```

---

## Security Monitoring

### Intrusion Detection

**OSSEC Installation:**
```bash
# Install OSSEC
wget https://github.com/ossec/ossec-hids/archive/3.7.0.tar.gz
tar xf 3.7.0.tar.gz
cd ossec-hids-3.7.0
./install.sh

# Configuration
cat >> /var/ossec/etc/ossec.conf << EOF
<ossec_config>
  <syscheck>
    <directories check_all="yes">/var/www/transfer-engine</directories>
    <directories check_all="yes">/etc</directories>
  </syscheck>
  
  <localfile>
    <log_format>syslog</log_format>
    <location>/var/log/nginx/access.log</location>
  </localfile>
  
  <localfile>
    <log_format>syslog</log_format>
    <location>/var/www/transfer-engine/storage/logs/security.log</location>
  </localfile>
</ossec_config>
EOF

# Start OSSEC
/var/ossec/bin/ossec-control start
```

### Security Logging

**Comprehensive Security Logger:**
```php
class SecurityLogger
{
    public function logLoginAttempt(string $username, bool $success, string $ip): void
    {
        Log::channel('security')->info('Login attempt', [
            'username' => $username,
            'success' => $success,
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        
        if (!$success) {
            $this->incrementFailedAttempts($username, $ip);
        }
    }
    
    public function logPermissionDenied(string $action, int $userId, string $ip): void
    {
        Log::channel('security')->warning('Permission denied', [
            'action' => $action,
            'user_id' => $userId,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
    
    public function logSuspiciousActivity(string $description, array $context): void
    {
        Log::channel('security')->warning('Suspicious activity', array_merge([
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s'),
        ], $context));
        
        // Send alert
        $this->sendSecurityAlert($description, $context);
    }
    
    private function sendSecurityAlert(string $description, array $context): void
    {
        Mail::to('security@vapeshed.co.nz')->send(new SecurityAlertMail($description, $context));
    }
}
```

### Vulnerability Scanning

**Automated Scanning:**
```bash
# Install Nikto (web vulnerability scanner)
apt install nikto -y

# Scan website
nikto -h https://transfer.vapeshed.co.nz -output scan_results.txt

# Install OWASP ZAP (more comprehensive)
wget https://github.com/zaproxy/zaproxy/releases/download/v2.12.0/ZAP_2_12_0_unix.sh
chmod +x ZAP_2_12_0_unix.sh
./ZAP_2_12_0_unix.sh

# Run baseline scan
zap-baseline.py -t https://transfer.vapeshed.co.nz -r scan_report.html
```

---

## Incident Response

### Incident Response Plan

**1. Preparation:**
- Incident response team identified
- Contact information maintained
- Tools and access ready
- Runbooks documented

**2. Detection & Analysis:**
```
Detection Sources:
- Security logs
- IDS/IPS alerts
- User reports
- Monitoring alerts

Analysis Steps:
1. Identify scope and severity
2. Determine affected systems
3. Assess data exposure
4. Document initial findings
```

**3. Containment:**
```
Short-term Containment:
- Isolate affected systems
- Block malicious IPs
- Disable compromised accounts
- Enable maintenance mode

Long-term Containment:
- Apply security patches
- Reconfigure firewalls
- Update access controls
```

**4. Eradication:**
```
- Remove malware
- Close vulnerabilities
- Strengthen authentication
- Update security rules
```

**5. Recovery:**
```
- Restore from clean backups
- Verify system integrity
- Re-enable services gradually
- Monitor closely
```

**6. Lessons Learned:**
```
- Incident review meeting
- Documentation update
- Process improvement
- Training needs identified
```

### Incident Response Runbook

**Suspected Data Breach:**
```bash
# 1. Isolate affected systems
ufw deny from [malicious_ip]
systemctl stop nginx  # If severe

# 2. Preserve evidence
tar czf /tmp/evidence_$(date +%Y%m%d_%H%M%S).tar.gz \
  /var/log \
  /var/www/transfer-engine/storage/logs

# 3. Review logs
grep -r "SELECT.*UNION" /var/log/nginx/access.log  # SQL injection
grep -r "<script" /var/log/nginx/access.log         # XSS
grep -r "../../" /var/log/nginx/access.log          # Path traversal

# 4. Check database for unauthorized access
mysql -u root -p -e "
SELECT user, host, db, time, info 
FROM information_schema.processlist 
WHERE user NOT IN ('root', 'transfer_user');
"

# 5. Identify compromised accounts
mysql -u root -p transfer_engine -e "
SELECT username, last_login, last_ip 
FROM users 
WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
ORDER BY last_login DESC;
"

# 6. Reset passwords for affected accounts
php bin/user.php reset-all-passwords --force

# 7. Notify stakeholders
php bin/notify.php --template=security_breach --recipients=all

# 8. Begin forensic analysis
# ... detailed investigation
```

---

## Compliance

### GDPR Compliance

**Data Subject Rights:**

**1. Right to Access:**
```php
public function exportUserData(int $userId): array
{
    return [
        'personal_info' => DB::table('users')->where('user_id', $userId)->first(),
        'transfers_created' => DB::table('transfers')->where('created_by', $userId)->get(),
        'audit_log' => DB::table('audit_logs')->where('user_id', $userId)->get(),
        'login_history' => DB::table('login_history')->where('user_id', $userId)->get(),
    ];
}
```

**2. Right to Erasure (Right to be Forgotten):**
```php
public function deleteUserData(int $userId): void
{
    // Log request
    Log::info("GDPR data deletion request", ['user_id' => $userId]);
    
    // Anonymize instead of delete (for record keeping)
    DB::table('users')->where('user_id', $userId)->update([
        'username' => 'deleted_' . $userId,
        'email' => 'deleted_' . $userId . '@deleted.com',
        'phone_number' => null,
        'first_name' => 'Deleted',
        'last_name' => 'User',
        'deleted_at' => date('Y-m-d H:i:s'),
    ]);
    
    // Delete sensitive data
    DB::table('password_history')->where('user_id', $userId)->delete();
    DB::table('backup_codes')->where('user_id', $userId)->delete();
    DB::table('login_history')->where('user_id', $userId)->delete();
}
```

**3. Data Breach Notification:**
```php
public function notifyDataBreach(array $affectedUsers, string $description): void
{
    foreach ($affectedUsers as $user) {
        Mail::to($user->email)->send(new DataBreachNotificationMail([
            'description' => $description,
            'affected_data' => 'Personal information',
            'actions_taken' => 'Password reset, security review',
            'user_actions' => 'Please change your password',
            'contact' => 'privacy@vapeshed.co.nz',
        ]));
    }
    
    // Notify authorities within 72 hours (if required)
    // https://www.privacy.org.nz/
}
```

### PCI DSS (if handling payment cards)

**Key Requirements:**
```
1. Install and maintain a firewall: ✓
2. Do not use vendor-supplied defaults: ✓
3. Protect stored cardholder data: ✓ (encrypt)
4. Encrypt transmission of cardholder data: ✓ (TLS)
5. Use and regularly update anti-virus software: ✓
6. Develop and maintain secure systems: ✓
7. Restrict access by business need-to-know: ✓ (RBAC)
8. Assign a unique ID to each person with computer access: ✓
9. Restrict physical access to cardholder data: N/A (cloud)
10. Track and monitor all access to network resources: ✓
11. Regularly test security systems and processes: ✓
12. Maintain a policy that addresses information security: ✓
```

**Note:** Best practice is to never store payment card data. Use payment gateway (e.g., Stripe, PayPal) for PCI compliance.

---

## Security Auditing

### Regular Security Audits

**Monthly Checklist:**
```
□ Review user access and permissions
□ Check for unused accounts (disable)
□ Review audit logs for suspicious activity
□ Verify backup integrity
□ Check for outdated dependencies (composer audit)
□ Review firewall rules
□ Check SSL certificate expiration
□ Review failed login attempts
□ Verify encryption is working
□ Check for security updates
```

**Quarterly Checklist:**
```
□ Penetration testing
□ Vulnerability scanning
□ Password policy review
□ Incident response plan review
□ Security training for staff
□ Third-party security assessment
□ Compliance review (GDPR, etc.)
□ Disaster recovery test
```

### Automated Security Scanning

**Composer Security Audit:**
```bash
# Check for vulnerable dependencies
composer audit

# Example output:
# Found 2 security advisories affecting 2 packages:
# - symfony/http-kernel: CVE-2023-XXXX (High severity)
# - guzzlehttp/guzzle: CVE-2023-YYYY (Medium severity)

# Update vulnerable packages
composer update symfony/http-kernel guzzlehttp/guzzle
```

---

## Penetration Testing

### External Penetration Test

**Scope:**
- Web application
- API endpoints
- Authentication system
- Input validation
- Authorization checks

**Tools:**
- OWASP ZAP
- Burp Suite
- Metasploit
- SQLMap
- Nikto

**Schedule:** Annually + after major changes

### Internal Security Assessment

**Code Review Focus:**
```
- Authentication logic
- Authorization checks
- Input validation
- Output encoding
- SQL queries (injection prevention)
- File upload handling
- Cryptography usage
- Error handling
- Logging (sensitive data)
```

---

## Security Best Practices

### Developer Security Guidelines

**1. Never Trust User Input**
```php
// ✅ GOOD
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
    throw new InvalidArgumentException('Invalid ID');
}

// ❌ BAD
$id = $_GET['id'];
```

**2. Use Prepared Statements**
```php
// ✅ GOOD
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);

// ❌ BAD
$result = $pdo->query("SELECT * FROM users WHERE id = $id");
```

**3. Encode Output**
```php
// ✅ GOOD
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// ❌ BAD
echo $userInput;
```

**4. Use Strong Cryptography**
```php
// ✅ GOOD
$hash = password_hash($password, PASSWORD_ARGON2ID);

// ❌ BAD
$hash = md5($password);
$hash = sha1($password);
```

**5. Implement Rate Limiting**
```php
// ✅ GOOD
$attempts = Cache::get("login_attempts:$ip", 0);
if ($attempts >= 5) {
    return response()->json(['error' => 'Too many attempts'], 429);
}
```

**6. Log Security Events**
```php
// ✅ GOOD
Log::channel('security')->warning('Failed login', [
    'username' => $username,
    'ip' => $request->ip(),
]);
```

### Security Training

**Topics:**
- OWASP Top 10
- Secure coding practices
- Password management
- Phishing awareness
- Social engineering
- Incident reporting
- Data protection

**Frequency:** Quarterly for all staff

---

**Document Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Maintained By:** Ecigdis Limited Security Team  
**Review Cycle:** Quarterly

**Security Contacts:**  
🔒 Security Team: security@vapeshed.co.nz  
🚨 Emergency: +64 21 XXX XXXX (24/7)  
📧 Privacy Officer: privacy@vapeshed.co.nz  
🔐 Vulnerability Reports: security-reports@vapeshed.co.nz
