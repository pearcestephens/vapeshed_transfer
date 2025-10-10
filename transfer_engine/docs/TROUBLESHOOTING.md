# Troubleshooting Guide
**Vape Shed Transfer Engine - Complete Troubleshooting Reference**

Version: 1.0.0  
Last Updated: October 9, 2025  
For: Support Staff, Administrators, Developers

---

## Table of Contents

1. [Quick Diagnostic Steps](#quick-diagnostic-steps)
2. [Application Errors](#application-errors)
3. [Database Issues](#database-issues)
4. [Performance Problems](#performance-problems)
5. [Integration Failures](#integration-failures)
6. [Authentication Issues](#authentication-issues)
7. [Transfer Workflow Problems](#transfer-workflow-problems)
8. [API Errors](#api-errors)
9. [Email & Notification Issues](#email--notification-issues)
10. [Mobile App Problems](#mobile-app-problems)
11. [Cache & Session Issues](#cache--session-issues)
12. [Log Analysis](#log-analysis)
13. [Emergency Procedures](#emergency-procedures)

---

## Quick Diagnostic Steps

### First Response Checklist

When a problem is reported, follow these steps:

**1. Identify the Scope** (30 seconds)
```
□ Is it affecting one user or everyone?
□ Is it affecting one store or all stores?
□ Is it affecting one feature or the entire application?
□ When did the problem start?
```

**2. Check System Status** (1 minute)
```bash
# Application health
curl https://transfer.vapeshed.co.nz/api/health

# Server health
top
df -h
free -h

# Services status
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mariadb
systemctl status redis-server
```

**3. Check Recent Changes** (1 minute)
```
□ Was there a recent deployment?
□ Were there configuration changes?
□ Were there database migrations?
□ Were there system updates?
```

**4. Review Error Logs** (2 minutes)
```bash
# Application errors (last 50 lines)
tail -50 /var/www/transfer-engine/storage/logs/error.log

# PHP-FPM errors
tail -50 /var/log/php-fpm/transfer-engine-error.log

# Nginx errors
tail -50 /var/log/nginx/transfer-engine-error.log

# Database errors
tail -50 /var/log/mysql/error.log
```

**5. User Impact Assessment** (1 minute)
```
Severity:
□ P1 - Critical: System down, data loss, security breach
□ P2 - High: Major feature unavailable, significant performance degradation
□ P3 - Medium: Minor feature issue, workaround available
□ P4 - Low: Cosmetic issue, enhancement request
```

### Common Quick Fixes

**Problem: Application slow or unresponsive**
```bash
# Clear cache
php bin/cache.php clear
redis-cli FLUSHALL

# Restart PHP-FPM
systemctl restart php8.2-fpm

# Clear OPcache
systemctl reload php8.2-fpm
```

**Problem: Can't login**
```bash
# Check session storage
redis-cli PING

# Clear sessions
redis-cli -n 1 FLUSHDB

# Check database
mysql -u transfer_user -p -e "USE transfer_engine; SELECT COUNT(*) FROM users;"
```

**Problem: Database connection errors**
```bash
# Check MySQL status
systemctl status mariadb

# Restart MySQL
systemctl restart mariadb

# Check connections
mysql -u root -p -e "SHOW PROCESSLIST;"
```

---

## Application Errors

### 500 Internal Server Error

**Symptoms:**
- White screen or generic error page
- "500 Internal Server Error" message
- API returns 500 status code

**Diagnosis:**
```bash
# Check application error log
tail -100 /var/www/transfer-engine/storage/logs/error.log

# Check PHP-FPM error log
tail -100 /var/log/php-fpm/transfer-engine-error.log

# Check Nginx error log
tail -100 /var/log/nginx/transfer-engine-error.log
```

**Common Causes & Solutions:**

**1. PHP Syntax Error**
```
Error: "syntax error, unexpected..."
Solution:
  - Review recent code changes
  - Check for missing semicolons, brackets
  - Run: php -l /path/to/file.php
  - Rollback problematic code
```

**2. Class Not Found**
```
Error: "Class 'ClassName' not found"
Solution:
  - Run: composer dump-autoload
  - Clear OPcache: systemctl reload php8.2-fpm
  - Check class namespace and use statements
```

**3. Memory Exhausted**
```
Error: "Allowed memory size of X bytes exhausted"
Solution:
  - Increase memory_limit in php.ini (currently 256M)
  - Identify memory leak: php bin/profile-memory.php
  - Optimize queries reducing data load
```

**4. Permission Denied**
```
Error: "Permission denied" or "Failed to open stream"
Solution:
  - Check file ownership: ls -la /var/www/transfer-engine
  - Fix permissions: chown -R deploy:www-data /var/www/transfer-engine
  - Storage writable: chmod -R 775 storage/
```

### 404 Not Found Errors

**Symptoms:**
- Page or resource not found
- API endpoint returns 404
- CSS/JS files not loading

**Diagnosis:**
```bash
# Check Nginx access log
tail -100 /var/log/nginx/transfer-engine-access.log | grep " 404 "

# Check if file exists
ls -la /var/www/transfer-engine/public/requested-file.js

# Check Nginx configuration
nginx -t
```

**Solutions:**

**1. Route Not Defined**
```
Issue: API endpoint returns 404
Solution:
  - Check routes/api.php for endpoint definition
  - Verify controller method exists
  - Clear route cache: php bin/cache.php clear --routes
```

**2. Asset Not Found**
```
Issue: CSS/JS file returns 404
Solution:
  - Run asset build: npm run build
  - Check public/assets/ directory
  - Verify asset path in HTML: /assets/css/file.css (absolute path)
```

**3. Rewrite Rules Not Working**
```
Issue: Clean URLs not working (index.php required in URL)
Solution:
  - Check Nginx config has: try_files $uri $uri/ /index.php?$query_string;
  - Reload Nginx: systemctl reload nginx
```

### 403 Forbidden Errors

**Symptoms:**
- "403 Forbidden" message
- User denied access to resource
- API returns 403 status code

**Diagnosis:**
```bash
# Check permissions
ls -la /var/www/transfer-engine/public/

# Check Nginx error log
tail -100 /var/log/nginx/transfer-engine-error.log | grep " 403 "

# Check user permissions in database
mysql -u transfer_user -p -e "USE transfer_engine; SELECT * FROM users WHERE username='username';"
```

**Solutions:**

**1. File Permissions**
```
Issue: Nginx can't read files
Solution:
  - Files: chmod 644
  - Directories: chmod 755
  - Owner: chown deploy:www-data
```

**2. Insufficient User Permissions**
```
Issue: User doesn't have required permission
Solution:
  - Check user role: Admin → Users → [username]
  - Assign required permissions
  - Or add permission to role: Admin → Roles → [role] → Permissions
```

**3. CSRF Token Mismatch**
```
Issue: POST request rejected with 403
Solution:
  - Ensure form includes CSRF token: <input name="_csrf" value="<?= csrf_token() ?>">
  - Check session is active
  - Clear sessions: redis-cli -n 1 FLUSHDB
```

### Blank Page (No Error)

**Symptoms:**
- White screen with no content
- No error message displayed
- Page source shows minimal HTML

**Diagnosis:**
```bash
# Enable display_errors temporarily
nano /etc/php/8.2/fpm/php.ini
# Set: display_errors = On

# Restart PHP-FPM
systemctl restart php8.2-fpm

# Reload page in browser
# Check for error output

# IMPORTANT: Disable after diagnosis
# Set: display_errors = Off
systemctl restart php8.2-fpm
```

**Common Causes:**

**1. Fatal Error with display_errors Off**
```
Solution: Check error log for fatal errors
```

**2. Output Buffer Full**
```
Solution: Increase output_buffering in php.ini
```

**3. Infinite Loop or Timeout**
```
Solution: 
  - Check max_execution_time (default 60s)
  - Identify long-running code
  - Add: set_time_limit(120); for specific operations
```

---

## Database Issues

### Connection Errors

**Error Messages:**
- "SQLSTATE[HY000] [2002] Connection refused"
- "SQLSTATE[HY000] [1045] Access denied"
- "SQLSTATE[HY000] [2006] MySQL server has gone away"

**Diagnosis:**
```bash
# Check MySQL status
systemctl status mariadb

# Check connection
mysql -u transfer_user -p

# Check error log
tail -100 /var/log/mysql/error.log

# Check connections
mysql -u root -p -e "SHOW PROCESSLIST;"
```

**Solutions:**

**1. MySQL Not Running**
```bash
# Start MySQL
systemctl start mariadb

# Check status
systemctl status mariadb

# Enable autostart
systemctl enable mariadb
```

**2. Invalid Credentials**
```bash
# Verify .env credentials
cat /var/www/transfer-engine/.env | grep DB_

# Reset password
mysql -u root -p
ALTER USER 'transfer_user'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;

# Update .env
nano /var/www/transfer-engine/.env
# Update DB_PASSWORD

# Restart PHP-FPM
systemctl restart php8.2-fpm
```

**3. Connection Timeout**
```bash
# Check MySQL max_connections
mysql -u root -p -e "SHOW VARIABLES LIKE 'max_connections';"

# Increase if needed
nano /etc/mysql/mariadb.conf.d/99-custom.cnf
# Add: max_connections = 200

# Restart MySQL
systemctl restart mariadb
```

**4. Server Has Gone Away**
```
Cause: Query timeout or packet too large
Solution:
  - Increase wait_timeout: SET GLOBAL wait_timeout = 600;
  - Increase max_allowed_packet: SET GLOBAL max_allowed_packet = 64*1024*1024;
  - Add to my.cnf for persistence
```

### Slow Queries

**Symptoms:**
- Application slow to load
- Database CPU usage high
- User reports timeouts

**Diagnosis:**
```bash
# Check slow query log
tail -100 /var/log/mysql/slow-query.log

# Check running queries
mysql -u root -p -e "SHOW FULL PROCESSLIST;"

# Check table locks
mysql -u root -p -e "SHOW OPEN TABLES WHERE In_use > 0;"
```

**Solutions:**

**1. Missing Index**
```sql
-- Identify slow query from log
-- Example: SELECT * FROM transfers WHERE status = 'pending';

-- Add index
CREATE INDEX idx_transfers_status ON transfers(status);

-- Verify improvement
EXPLAIN SELECT * FROM transfers WHERE status = 'pending';
```

**2. Large Result Set**
```
Issue: Query returns thousands of rows
Solution:
  - Add LIMIT clause
  - Implement pagination
  - Use cursor-based pagination for large datasets
```

**3. Inefficient JOIN**
```sql
-- Before (slow)
SELECT * FROM transfers t 
LEFT JOIN transfer_items ti ON t.transfer_id = ti.transfer_id;

-- After (optimized)
SELECT t.transfer_id, t.reference, ti.product_id, ti.quantity
FROM transfers t 
INNER JOIN transfer_items ti ON t.transfer_id = ti.transfer_id
WHERE t.status = 'approved'
LIMIT 100;
```

**4. Lock Wait Timeout**
```
Error: "Lock wait timeout exceeded"
Solution:
  - Identify blocking query: SHOW ENGINE INNODB STATUS\G
  - Kill blocking process: KILL [process_id];
  - Optimize transaction scope (commit sooner)
  - Use READ COMMITTED isolation level
```

### Data Integrity Issues

**Symptoms:**
- Orphaned records
- Missing foreign key relationships
- Inconsistent data

**Diagnosis:**
```sql
-- Find orphaned transfer items
SELECT ti.* FROM transfer_items ti
LEFT JOIN transfers t ON ti.transfer_id = t.transfer_id
WHERE t.transfer_id IS NULL;

-- Find transfers with no items
SELECT t.* FROM transfers t
LEFT JOIN transfer_items ti ON t.transfer_id = ti.transfer_id
WHERE ti.item_id IS NULL;

-- Check foreign key constraints
SELECT * FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
AND TABLE_SCHEMA = 'transfer_engine';
```

**Solutions:**

**1. Clean Up Orphaned Records**
```sql
-- Backup first
mysqldump -u backup_user -p transfer_engine > backup_before_cleanup.sql

-- Delete orphaned transfer items
DELETE ti FROM transfer_items ti
LEFT JOIN transfers t ON ti.transfer_id = t.transfer_id
WHERE t.transfer_id IS NULL;

-- Verify
SELECT COUNT(*) FROM transfer_items ti
LEFT JOIN transfers t ON ti.transfer_id = t.transfer_id
WHERE t.transfer_id IS NULL;
-- Should return 0
```

**2. Re-create Foreign Keys**
```sql
-- Drop existing constraint (if corrupt)
ALTER TABLE transfer_items DROP FOREIGN KEY fk_transfer_items_transfer;

-- Re-create
ALTER TABLE transfer_items
ADD CONSTRAINT fk_transfer_items_transfer
FOREIGN KEY (transfer_id) REFERENCES transfers(transfer_id)
ON DELETE CASCADE ON UPDATE CASCADE;
```

**3. Run Integrity Check**
```bash
php bin/integrity-check.php

# Expected output:
# ✓ Transfers: All records valid
# ✓ Transfer Items: No orphans
# ✓ Users: All references valid
# ✓ Foreign Keys: All intact
```

---

## Performance Problems

### Slow Page Load

**Symptoms:**
- Pages take > 3 seconds to load
- User reports application feels sluggish
- High server CPU or memory usage

**Diagnosis:**
```bash
# Check server load
top
htop

# Check slow queries
tail -100 /var/log/mysql/slow-query.log

# Profile page load
curl -w "@curl-format.txt" -o /dev/null -s https://transfer.vapeshed.co.nz/

# curl-format.txt:
time_namelookup:  %{time_namelookup}\n
time_connect:  %{time_connect}\n
time_appconnect:  %{time_appconnect}\n
time_pretransfer:  %{time_pretransfer}\n
time_redirect:  %{time_redirect}\n
time_starttransfer:  %{time_starttransfer}\n
time_total:  %{time_total}\n
```

**Solutions:**

**1. Enable OPcache**
```bash
# Check OPcache status
php -i | grep opcache.enable

# If disabled, enable
nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
# Set: opcache.enable=1

systemctl restart php8.2-fpm
```

**2. Increase Cache TTL**
```bash
# Edit cache configuration
nano /var/www/transfer-engine/config/cache.php

# Increase TTL values:
'products' => 600,  // 10 minutes (was 300)
'stores' => 1800,   // 30 minutes (was 900)
```

**3. Optimize Database Queries**
```
- Add indexes on frequently queried columns
- Use EXPLAIN to analyze query plans
- Implement query caching
- Reduce SELECT * queries
```

**4. Enable Redis Caching**
```bash
# Verify Redis is running
redis-cli PING

# Check cache hit rate
redis-cli INFO stats | grep keyspace

# Clear and warm cache
php bin/cache.php clear
php bin/cache.php warm
```

### High CPU Usage

**Diagnosis:**
```bash
# Identify top processes
top -o %CPU

# Check PHP-FPM processes
ps aux | grep php-fpm | wc -l

# Check MySQL processes
mysql -u root -p -e "SHOW PROCESSLIST;"
```

**Solutions:**

**1. PHP-FPM Pool Exhausted**
```bash
# Check current pool settings
grep -E 'pm\.max_children|pm\.start_servers' /etc/php/8.2/fpm/pool.d/transfer-engine.conf

# Increase if needed
nano /etc/php/8.2/fpm/pool.d/transfer-engine.conf
pm.max_children = 100  # Increase from 50
pm.start_servers = 10   # Increase from 5

systemctl restart php8.2-fpm
```

**2. Infinite Loop in Code**
```
Diagnosis:
  - Identify long-running PHP processes: ps aux | grep php-fpm
  - Check execution time in logs
  - Enable profiling: xdebug.profiler_enable = 1

Solution:
  - Find and fix infinite loop
  - Add max_execution_time checks
  - Implement timeouts
```

**3. Heavy Database Queries**
```sql
-- Find long-running queries
SELECT * FROM information_schema.PROCESSLIST 
WHERE TIME > 10 AND COMMAND != 'Sleep';

-- Kill long-running query
KILL [process_id];

-- Optimize query or add index
```

### Memory Issues

**Symptoms:**
- PHP memory exhausted errors
- Server runs out of RAM
- Swap usage high

**Diagnosis:**
```bash
# Check memory usage
free -h

# Check PHP memory limit
php -i | grep memory_limit

# Check OOM killer logs
dmesg | grep -i "out of memory"

# Check largest PHP processes
ps aux | grep php-fpm | awk '{print $2,$4,$6,$11}' | sort -k3 -rn | head -10
```

**Solutions:**

**1. Increase PHP Memory Limit**
```bash
nano /etc/php/8.2/fpm/pool.d/transfer-engine.conf

# Increase memory_limit
php_value[memory_limit] = 512M  # Was 256M

systemctl restart php8.2-fpm
```

**2. Memory Leak in Code**
```
Diagnosis:
  - Profile memory usage: php bin/profile-memory.php
  - Check for unbounded arrays
  - Check for circular references

Solution:
  - Free large variables: unset($large_array);
  - Use generators for large datasets
  - Implement chunked processing
```

**3. Increase Server RAM**
```
If application legitimately needs more memory:
  - Upgrade server plan
  - Add swap space (temporary)
  - Implement horizontal scaling
```

---

## Integration Failures

### Vend API Errors

**Error: 401 Unauthorized**
```
Cause: Invalid or expired API token
Solution:
  1. Log in to Vend admin panel
  2. Navigate to Setup → API Access
  3. Regenerate token
  4. Update .env file: VEND_TOKEN=new_token_here
  5. Restart PHP-FPM: systemctl restart php8.2-fpm
  6. Test: php bin/test-vend.php
```

**Error: 429 Too Many Requests**
```
Cause: Rate limit exceeded
Solution:
  1. Check current sync interval: cat .env | grep VEND_SYNC_INTERVAL
  2. Increase interval: VEND_SYNC_INTERVAL=600 (10 minutes)
  3. Implement request queuing
  4. Contact Vend support for higher rate limits
```

**Error: Timeout**
```
Cause: Slow response from Vend API
Solution:
  1. Check network connectivity: ping api.vend.com
  2. Increase timeout in config/vend.php:
     'timeout' => 60, // Increase from 30
  3. Implement retry logic with exponential backoff
  4. Check Vend API status: https://status.vendhq.com/
```

**Error: Product Not Found**
```
Cause: Product doesn't exist in Vend or sync outdated
Solution:
  1. Force full sync: php bin/sync-vend.php --full
  2. Verify product exists in Vend
  3. Check sync logs: tail -100 storage/logs/vend-sync.log
  4. Manual sync specific product: php bin/sync-vend.php --product=SKU123
```

### Email Delivery Issues

**Emails Not Sending**
```
Diagnosis:
  - Check SendGrid API key: php bin/test-email.php
  - Check error log: tail -100 storage/logs/error.log | grep -i email
  - Check SendGrid dashboard: https://app.sendgrid.com/

Solution:
  1. Verify API key is valid
  2. Check SendGrid account status (not suspended)
  3. Verify DNS records (SPF, DKIM, DMARC)
  4. Test with simple email: php bin/send-test-email.php --to=test@example.com
```

**Emails Going to Spam**
```
Cause: Poor sender reputation or missing authentication
Solution:
  1. Verify SPF record:
     nslookup -type=TXT vapeshed.co.nz
     Should include: v=spf1 include:sendgrid.net ~all
     
  2. Verify DKIM records:
     Check CNAME records for s1._domainkey and s2._domainkey
     
  3. Set up DMARC:
     Add TXT record: _dmarc.vapeshed.co.nz
     Value: v=DMARC1; p=quarantine; rua=mailto:dmarc@vapeshed.co.nz
     
  4. Warm up IP address (for dedicated IP)
  5. Reduce spam score: Check content, avoid spam keywords
```

**Email Template Not Rendering**
```
Diagnosis:
  - Check template file exists: ls -la resources/views/emails/
  - Check template syntax: php bin/validate-templates.php
  - Test template rendering: php bin/render-template.php --template=transfer_created

Solution:
  1. Verify template path in config
  2. Check for PHP syntax errors in template
  3. Clear template cache: php bin/cache.php clear --templates
  4. Test with basic template first
```

### Webhook Failures

**Webhook Not Triggering**
```
Diagnosis:
  - Check webhook configuration: Admin → Integrations → Webhooks
  - Check event log: Admin → Integrations → Webhooks → Logs
  - Verify endpoint URL is accessible: curl -X POST https://example.com/webhook

Solution:
  1. Verify webhook is enabled
  2. Check event subscription (correct event type)
  3. Test endpoint manually: php bin/test-webhook.php --url=https://example.com/webhook
  4. Check firewall rules (webhook target must be reachable)
```

**Webhook Receiving 4xx/5xx Errors**
```
Diagnosis:
  - Check webhook logs for response codes
  - Test endpoint independently: curl -X POST -H "Content-Type: application/json" -d '{"test":"data"}' https://example.com/webhook

Solution:
  1. Fix receiving endpoint (target server issue)
  2. Verify payload format matches expected
  3. Check authentication (if required)
  4. Implement retry logic for temporary failures
```

---

## Authentication Issues

### Can't Login

**Symptoms:**
- "Invalid username or password" error
- Login button unresponsive
- Redirect loop after login

**Diagnosis:**
```bash
# Check user exists
mysql -u transfer_user -p -e "USE transfer_engine; SELECT username, email FROM users WHERE username='johndoe';"

# Check session storage
redis-cli -n 1 PING

# Check login attempts (rate limiting)
redis-cli GET "login_attempts:johndoe"
```

**Solutions:**

**1. Incorrect Password**
```
Solution:
  - Reset password via "Forgot Password" link
  - Or admin reset: Admin → Users → [user] → Reset Password
  - Or command line: php bin/user.php reset-password --username=johndoe
```

**2. Account Locked**
```
Check:
  mysql -u transfer_user -p -e "USE transfer_engine; SELECT locked, locked_until FROM users WHERE username='johndoe';"

Solution:
  - Wait until lock expires (default 30 minutes)
  - Or admin unlock: Admin → Users → [user] → Unlock Account
  - Or command line: php bin/user.php unlock --username=johndoe
```

**3. Session Storage Unavailable**
```
Check:
  redis-cli PING

Solution:
  - Start Redis: systemctl start redis-server
  - Or temporarily switch to file sessions:
    nano .env
    SESSION_DRIVER=file  # Change from redis
    systemctl restart php8.2-fpm
```

**4. CSRF Token Mismatch**
```
Cause: Session expired or cache issues
Solution:
  - Clear browser cache
  - Clear server sessions: redis-cli -n 1 FLUSHDB
  - Check CSRF token generation: php bin/test-csrf.php
```

### Session Timeout

**Symptoms:**
- User logged out unexpectedly
- "Session expired" message
- Losing work due to timeout

**Diagnosis:**
```bash
# Check session configuration
cat .env | grep SESSION_LIFETIME

# Check Redis session data
redis-cli -n 1 KEYS "session:*" | head -10
redis-cli -n 1 TTL "session:abcd1234efgh5678"
```

**Solutions:**

**1. Increase Session Lifetime**
```bash
nano .env

# Increase from 120 minutes to 240 minutes (4 hours)
SESSION_LIFETIME=240

systemctl restart php8.2-fpm
```

**2. Implement "Remember Me"**
```
Feature: Allow users to stay logged in longer
Implementation:
  - Add "Remember Me" checkbox to login form
  - Extend session to 30 days when checked
  - Use secure, httpOnly cookies
```

**3. Idle Timeout vs Absolute Timeout**
```
Current: Absolute timeout (fixed duration)
Improvement: Implement idle timeout (extends on activity)

Configuration:
  SESSION_LIFETIME=240          # 4 hour absolute max
  SESSION_IDLE_TIMEOUT=30       # 30 minute idle timeout
```

### Two-Factor Authentication Problems

**Lost 2FA Device**
```
User can't access authenticator app

Solution:
  1. Verify user identity (phone call, ID check)
  2. Admin → Users → [user] → Disable 2FA
  3. User logs in (password only)
  4. User re-enables 2FA with new device
  5. Generate new backup codes
```

**2FA Codes Not Working**
```
Cause: Time drift between server and device

Solution:
  1. Check server time: timedatectl
  2. Sync time: systemctl restart systemd-timesyncd
  3. Check device time is correct
  4. Allow ±30 second time window (configurable)
```

**Backup Codes Exhausted**
```
User used all 10 backup codes

Solution:
  1. Verify user identity
  2. Admin → Users → [user] → Regenerate Backup Codes
  3. Email new codes to user (encrypted)
  4. User stores codes securely
```

---

## Transfer Workflow Problems

### Transfer Won't Create

**Symptoms:**
- "Create Transfer" button doesn't work
- Form validation errors
- Transfer saves but doesn't appear

**Diagnosis:**
```bash
# Check application logs
tail -100 storage/logs/error.log | grep -i transfer

# Check database
mysql -u transfer_user -p -e "USE transfer_engine; SELECT * FROM transfers ORDER BY created_at DESC LIMIT 5;"

# Check browser console (F12) for JavaScript errors
```

**Solutions:**

**1. Validation Errors**
```
Common validation issues:
  - Source and destination store are the same
  - No items added
  - Invalid quantities (zero or negative)
  - Store not found
  - Product not found

Solution:
  - Display clear validation messages
  - Check form data: console.log(formData) before submit
  - Verify required fields are filled
```

**2. Permission Denied**
```
User doesn't have transfers.create permission

Solution:
  - Check user role: Admin → Users → [user]
  - Assign appropriate role (Store Manager or Administrator)
  - Or add permission: Admin → Roles → [role] → Add transfers.create
```

**3. Database Constraint Violation**
```
Error: "Duplicate entry" or "Foreign key constraint fails"

Solution:
  - Check for duplicate reference number
  - Verify store IDs exist: SELECT * FROM stores WHERE store_id IN (1, 5);
  - Verify product IDs exist: SELECT * FROM products WHERE product_id IN (101, 102);
```

### Transfer Stuck in Status

**Symptoms:**
- Transfer stuck in "Pending" status
- Can't approve or progress transfer
- Status not updating

**Diagnosis:**
```bash
# Check transfer status
mysql -u transfer_user -p -e "USE transfer_engine; SELECT transfer_id, reference, status, updated_at FROM transfers WHERE transfer_id=12345;"

# Check status transition rules
cat app/Models/Transfer.php | grep -A 20 "allowed_transitions"

# Check logs
tail -100 storage/logs/error.log | grep "transfer_id=12345"
```

**Solutions:**

**1. Invalid Status Transition**
```
Example: Trying to move from "Packed" to "Pending" (not allowed)

Solution:
  - Follow proper workflow: Created → Pending → Approved → Picking → Packed → In Transit → Receiving → Completed
  - Or allow manual status override (admin only): php bin/transfer.php set-status --id=12345 --status=approved --force
```

**2. Approval Threshold Not Met**
```
Transfer requires manager approval but none assigned

Solution:
  - Assign approver: Admin → Transfers → [transfer] → Assign Approver
  - Or reduce approval threshold: Admin → Configuration → Transfer Settings
  - Or auto-approve: Enable auto-approval for stores below threshold
```

**3. Vend Sync Failed**
```
Transfer approved but consignment not created in Vend

Solution:
  - Check Vend sync status: Admin → Integrations → Vend → Sync Logs
  - Retry sync: php bin/sync-transfer-to-vend.php --transfer=12345
  - Check Vend API status: https://status.vendhq.com/
  - Manual consignment creation in Vend (last resort)
```

### Items Not Scanning

**Symptoms:**
- Barcode scan doesn't register
- Wrong product selected
- "Product not found" error

**Diagnosis:**
```bash
# Check product barcode in database
mysql -u transfer_user -p -e "USE transfer_engine; SELECT product_id, name, sku, barcode FROM products WHERE barcode='1234567890123';"

# Check scanner settings
# - Scanner type (USB vs Bluetooth)
# - Scan mode (keyboard emulation vs direct input)
# - Scan suffix (typically Enter key)

# Check browser console for input events
```

**Solutions:**

**1. Barcode Not in Database**
```
Solution:
  - Sync products from Vend: php bin/sync-vend.php --products
  - Or add manually: Admin → Products → [product] → Edit → Add barcode
  - Verify barcode format matches (EAN-13, UPC, Code 128, etc.)
```

**2. Scanner Configuration**
```
Scanner not sending data correctly

Solution:
  - Test scanner in text editor (should type barcode)
  - Configure scanner suffix: Usually Enter key
  - Check USB connection or Bluetooth pairing
  - Try different scanner if hardware issue
```

**3. Product Inactive**
```
Product exists but marked as inactive

Solution:
  - Reactivate product: Admin → Products → [product] → Set Active
  - Or allow inactive products in transfers (config option)
```

---

## API Errors

### 401 Unauthorized

**Cause:**
- No session cookie sent
- Session expired
- Invalid CSRF token

**Solution:**
```javascript
// Ensure session cookie is sent with request
fetch('/api/transfers', {
  credentials: 'same-origin',  // Include cookies
  headers: {
    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
  }
});
```

### 429 Rate Limit Exceeded

**Response:**
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Try again in 32 seconds."
  },
  "meta": {
    "rate_limit": {
      "limit": 100,
      "remaining": 0,
      "reset": 1696857600
    }
  }
}
```

**Solution:**
```javascript
// Implement exponential backoff
async function apiCall(url, options, retries = 3) {
  try {
    const response = await fetch(url, options);
    if (response.status === 429) {
      if (retries > 0) {
        const retryAfter = response.headers.get('Retry-After') || 60;
        await sleep(retryAfter * 1000);
        return apiCall(url, options, retries - 1);
      }
    }
    return response;
  } catch (error) {
    console.error('API call failed:', error);
    throw error;
  }
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}
```

### 422 Validation Error

**Response:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "from_store_id": ["The from store id field is required."],
      "items": ["The items field must contain at least 1 item."]
    }
  }
}
```

**Solution:**
```javascript
// Display validation errors to user
function displayErrors(errors) {
  Object.keys(errors).forEach(field => {
    const fieldElement = document.querySelector(`[name="${field}"]`);
    const errorMessage = errors[field].join(', ');
    
    // Show error message near field
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = errorMessage;
    fieldElement.parentNode.appendChild(errorDiv);
    
    // Highlight field
    fieldElement.classList.add('error');
  });
}
```

### 500 Internal Server Error

**Diagnosis:**
```bash
# Check API error log
tail -100 storage/logs/api.log

# Check general error log
tail -100 storage/logs/error.log

# Reproduce with curl for detailed error
curl -X POST https://transfer.vapeshed.co.nz/api/transfers \
  -H "Content-Type: application/json" \
  -H "Cookie: session=xyz" \
  -d '{"from_store_id":1,"to_store_id":5}' \
  -v
```

**Common Causes:**
- Database connection error
- Unhandled exception in controller
- Memory exhausted
- Timeout

**Solution:**
- Review error logs
- Fix underlying issue
- Implement proper error handling
- Return user-friendly error message

---

## Email & Notification Issues

### Notifications Not Received

**Diagnosis:**
```bash
# Check notification settings
mysql -u transfer_user -p -e "USE transfer_engine; SELECT user_id, notification_email, notification_sms, notification_in_app FROM user_settings WHERE user_id=123;"

# Check email queue
mysql -u transfer_user -p -e "USE transfer_engine; SELECT * FROM email_queue WHERE user_id=123 ORDER BY created_at DESC LIMIT 10;"

# Check sent emails
tail -100 storage/logs/email.log | grep "user_id=123"
```

**Solutions:**

**1. Notifications Disabled**
```
User turned off notifications

Solution:
  - User → Settings → Notifications
  - Enable desired channels (Email, SMS, In-App)
  - Save settings
```

**2. Email Queued but Not Sent**
```
Check queue worker:
  systemctl status transfer-engine-worker

Solution:
  - Start worker: systemctl start transfer-engine-worker
  - Process queue manually: php bin/queue.php work
  - Check worker logs: tail -100 storage/logs/worker.log
```

**3. Email Bounced**
```
Email address invalid or mailbox full

Solution:
  - Check SendGrid bounce list
  - Verify user email: Admin → Users → [user] → Update Email
  - Resend notification: Admin → Notifications → Resend
```

### SMS Not Sending

**Diagnosis:**
```bash
# Check Twilio configuration
cat .env | grep TWILIO

# Test Twilio
php bin/test-sms.php --to=+64XXXXXXXXX

# Check SMS logs
tail -100 storage/logs/sms.log
```

**Solutions:**

**1. Twilio Configuration Error**
```
Solution:
  - Verify Account SID and Auth Token
  - Check Twilio console: https://console.twilio.com/
  - Test credentials: php bin/test-twilio.php
```

**2. Insufficient Twilio Credit**
```
Solution:
  - Add funds to Twilio account
  - Enable auto-recharge
```

**3. Invalid Phone Number**
```
Solution:
  - Verify phone number format: +64XXXXXXXXX (E.164)
  - Update user phone: Admin → Users → [user] → Phone Number
  - Validate on save: Implement phone number validation
```

---

## Mobile App Problems

### Barcode Scanner Not Working

**Symptoms:**
- Camera doesn't activate
- Scan doesn't register
- Wrong barcode detected

**Solutions:**

**1. Camera Permission Denied**
```
User denied camera access

Solution (iOS):
  - Settings → Safari → Camera → Ask
  - Or Settings → Privacy → Camera → Allow Safari

Solution (Android):
  - Settings → Apps → Chrome → Permissions → Camera → Allow
```

**2. Poor Lighting**
```
Barcode not readable in dim light

Solution:
  - Use device flashlight
  - Improve lighting conditions
  - Clean camera lens
  - Try manual entry as fallback
```

**3. Unsupported Barcode Format**
```
Scanner doesn't recognize barcode type

Solution:
  - Verify barcode format (should be EAN-13, UPC-A, Code 128)
  - Update scanner library to support more formats
  - Use manual SKU entry
```

### Offline Mode Issues

**Symptoms:**
- Changes not syncing when online
- Data loss after connectivity restored
- Conflict errors

**Solutions:**

**1. Sync Conflict**
```
Local changes conflict with server

Solution:
  - Show conflict resolution UI
  - Allow user to choose: Keep Local / Use Server / Merge
  - Log conflicts: storage/logs/sync-conflicts.log
```

**2. Sync Failed**
```
Error syncing offline changes

Solution:
  - Retry sync: Pull to refresh
  - Check internet connection
  - Clear offline cache: Settings → Clear Offline Data
  - Manual export: Settings → Export Offline Data
```

**3. Exceeded Offline Storage**
```
Browser storage quota exceeded

Solution:
  - Clear old offline data automatically
  - Reduce offline data retention (default 7 days)
  - Prompt user to sync and clear: "Offline storage full. Sync now?"
```

---

## Cache & Session Issues

### Cache Inconsistency

**Symptoms:**
- Stale data displayed
- Configuration changes not taking effect
- User sees old product information

**Diagnosis:**
```bash
# Check cache status
redis-cli INFO stats | grep keyspace

# List cache keys
redis-cli KEYS "cache:*" | head -20

# Check specific cache entry
redis-cli GET "cache:products:12345"

# Check TTL
redis-cli TTL "cache:products:12345"
```

**Solutions:**

**1. Clear All Cache**
```bash
# Via command line
php bin/cache.php clear

# Or Redis directly
redis-cli FLUSHALL

# Or specific cache type
redis-cli KEYS "cache:products:*" | xargs redis-cli DEL
```

**2. Reduce Cache TTL**
```
For frequently changing data, reduce cache lifetime

Configuration:
  config/cache.php
  'products' => 300,  // 5 minutes instead of 10
```

**3. Cache Stampede**
```
Multiple processes regenerating same cache simultaneously

Solution:
  - Implement cache locking
  - Use probabilistic early expiration
  - Warm cache proactively
```

### Session Issues

**Lost Session Data**
```
User actions not persisting

Diagnosis:
  - Check Redis session storage: redis-cli -n 1 KEYS "session:*"
  - Check session configuration: cat .env | grep SESSION

Solution:
  - Verify Redis is running: systemctl status redis-server
  - Check PHP session handler: php -i | grep session.save_handler
  - Increase session TTL if expiring too quickly
```

**Session Fixation Attack Prevention**
```
Regenerate session ID after login

Implementation:
  session_regenerate_id(true);  // After successful authentication
```

---

## Log Analysis

### Application Logs

**Location:**
```
/var/www/transfer-engine/storage/logs/
  - app.log           # General application logs
  - error.log         # Error logs
  - security.log      # Security events
  - vend-sync.log     # Vend integration logs
  - api.log           # API request logs
```

**Analyzing Errors:**
```bash
# Count errors by type
cat storage/logs/error.log | grep -o "Error: [^[]*" | sort | uniq -c | sort -rn

# Find errors in last hour
find storage/logs -name "*.log" -mmin -60 -exec grep -i error {} +

# Search for specific error
grep -r "SQLSTATE\[HY000\]" storage/logs/

# Show context around error
grep -B 5 -A 10 "Fatal error" storage/logs/error.log
```

### System Logs

**Nginx Access Log:**
```bash
# Top 10 slowest requests
awk '$NF > 1 {print $NF, $7}' /var/log/nginx/transfer-engine-access.log | sort -rn | head -10

# Top 10 most requested URLs
awk '{print $7}' /var/log/nginx/transfer-engine-access.log | sort | uniq -c | sort -rn | head -10

# Error status codes
awk '$9 >= 400 {print $9}' /var/log/nginx/transfer-engine-access.log | sort | uniq -c
```

**PHP-FPM Log:**
```bash
# Recent errors
tail -100 /var/log/php-fpm/transfer-engine-error.log

# Count error types
grep -o "PHP [^:]*" /var/log/php-fpm/transfer-engine-error.log | sort | uniq -c | sort -rn
```

**MySQL Log:**
```bash
# Slow queries
tail -100 /var/log/mysql/slow-query.log

# Connection errors
grep -i "connection" /var/log/mysql/error.log
```

### Log Aggregation

**Centralized Logging (Future Enhancement):**
```
Consider implementing:
  - ELK Stack (Elasticsearch, Logstash, Kibana)
  - Graylog
  - Splunk
  - Papertrail
```

---

## Emergency Procedures

### System Down

**Immediate Actions (5 minutes):**
```
1. Verify scope:
   - Can you access server? ssh deploy@server
   - Is Nginx running? systemctl status nginx
   - Is PHP-FPM running? systemctl status php8.2-fpm
   - Is MySQL running? systemctl status mariadb

2. Check logs:
   - tail -100 /var/log/nginx/error.log
   - tail -100 /var/log/php-fpm/error.log
   - tail -100 /var/log/mysql/error.log

3. Attempt service restart:
   - systemctl restart nginx
   - systemctl restart php8.2-fpm
   - systemctl restart mariadb

4. If still down:
   - Enable maintenance page
   - Escalate to senior admin
   - Notify stakeholders
```

### Data Breach

**Immediate Actions (15 minutes):**
```
1. Isolate affected systems:
   - Disconnect from network (if possible)
   - Block suspicious IP addresses
   - Disable affected user accounts

2. Preserve evidence:
   - Don't delete logs
   - Copy logs to secure location
   - Take system snapshots

3. Assess impact:
   - What data was accessed?
   - What data was modified?
   - What data was exfiltrated?

4. Notify:
   - Security team
   - Management
   - Legal team
   - Potentially affected users (as required by law)

5. Begin incident response:
   - Follow incident response plan
   - Document all actions
   - Engage forensics if needed
```

### Database Corruption

**Immediate Actions (30 minutes):**
```
1. Stop writes:
   - Enable maintenance mode
   - Stop PHP-FPM (no new transactions)

2. Assess damage:
   - mysqlcheck -u root -p transfer_engine
   - Identify corrupt tables

3. Attempt repair:
   - mysqlcheck --repair transfer_engine

4. If repair fails:
   - Restore from latest backup
   - Apply transaction logs since backup (if available)
   - Verify data integrity

5. Resume operation:
   - Test functionality
   - Disable maintenance mode
   - Monitor closely
```

---

**Document Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Maintained By:** Ecigdis Limited Support Team  
**Review Cycle:** Quarterly

**Emergency Support:**  
📧 Email: support@vapeshed.co.nz  
📞 Phone: 0800-VAPESHED  
🆘 After Hours: +64 21 XXX XXXX  
💬 Slack: #transfer-engine-support

**Escalation Path:**
1. L1 Support → L2 Support (1 hour)
2. L2 Support → System Admin (2 hours)
3. System Admin → CTO (4 hours)
4. CTO → CEO (Critical incidents only)
