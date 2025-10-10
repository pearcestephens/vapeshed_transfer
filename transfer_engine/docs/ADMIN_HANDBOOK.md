# Administrator Handbook
**Vape Shed Transfer Engine - Complete Admin Guide**

Version: 1.0.0  
Last Updated: October 9, 2025  
For: System Administrators, IT Staff, Operations Managers

---

## Table of Contents

1. [Administrator Overview](#administrator-overview)
2. [System Architecture](#system-architecture)
3. [User Management](#user-management)
4. [Configuration Management](#configuration-management)
5. [Security Administration](#security-administration)
6. [Database Management](#database-management)
7. [Integration Management](#integration-management)
8. [Monitoring & Alerts](#monitoring--alerts)
9. [Backup & Recovery](#backup--recovery)
10. [Performance Optimization](#performance-optimization)
11. [Troubleshooting Guide](#troubleshooting-guide)
12. [Maintenance Procedures](#maintenance-procedures)

---

## Administrator Overview

### Admin Responsibilities

As a system administrator, you are responsible for:

**System Health:**
- Monitor uptime and performance
- Respond to system alerts
- Manage system resources
- Ensure data integrity

**User Management:**
- Create/modify user accounts
- Assign roles and permissions
- Reset passwords
- Audit user activity

**Configuration:**
- System settings management
- Integration configuration
- Feature flag management
- Performance tuning

**Security:**
- Access control
- Audit log review
- Security updates
- Incident response

**Data Management:**
- Backup verification
- Data cleanup
- Archive management
- Migration support

### Admin Access Levels

| Level | Description | Permissions |
|-------|-------------|-------------|
| **Super Admin** | Full system access | All operations, configuration, user management |
| **System Admin** | System configuration | Configuration, monitoring, basic user management |
| **Security Admin** | Security focus | Audit logs, access control, security settings |
| **Support Admin** | User support | View-only access, password resets, basic troubleshooting |

### Admin Dashboard

Access the admin dashboard at: `https://transfer.vapeshed.co.nz/admin`

**Key Sections:**
- 📊 System Overview
- 👥 User Management
- ⚙️ Configuration
- 🔒 Security
- 📈 Performance Metrics
- 🗄️ Database Tools
- 🔗 Integrations
- 📋 Audit Logs

---

## System Architecture

### Components Overview

```
┌─────────────────────────────────────────────────────────┐
│                     Load Balancer                        │
│                    (Cloudways CDN)                       │
└─────────────────┬───────────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────────┐
│                   Web Server Layer                       │
│                  (Nginx + PHP-FPM)                       │
└──────┬──────────────────────────────────────────┬───────┘
       │                                           │
┌──────▼────────────────┐              ┌──────────▼────────┐
│  Application Layer     │              │  Queue Workers    │
│  (PHP 8.2 + MVC)      │              │  (Background Jobs)│
└──────┬────────────────┘              └──────────┬────────┘
       │                                           │
┌──────▼───────────────────────────────────────────▼───────┐
│                    Database Layer                         │
│                  (MariaDB 10.5+)                         │
└──────┬───────────────────────────────────────────────────┘
       │
┌──────▼───────────────────────────────────────────────────┐
│                 External Integrations                     │
│            Vend API │ Email │ Webhooks                   │
└──────────────────────────────────────────────────────────┘
```

### Technology Stack

**Backend:**
- PHP 8.2+ (strict types, OOP)
- MariaDB 10.5+ (InnoDB engine)
- Redis (caching, sessions)
- Nginx 1.18+ (web server)
- PHP-FPM (process manager)

**Frontend:**
- HTML5, CSS3 (custom properties)
- JavaScript ES6+ (modular)
- Bootstrap 5.3 (UI framework)
- Server-Sent Events (real-time updates)

**Infrastructure:**
- Cloudways managed hosting
- Ubuntu 20.04 LTS
- Let's Encrypt SSL
- Cloudflare CDN

**Integrations:**
- Vend POS API (v2.0)
- SendGrid (email)
- Twilio (SMS)
- Custom webhooks

### File Structure

```
transfer_engine/
├── app/
│   ├── Controllers/          # Request handlers
│   ├── Models/              # Data models
│   ├── Services/            # Business logic
│   ├── Middleware/          # Request/response filters
│   └── Core/                # Core components
├── config/                  # Configuration files
├── database/
│   ├── migrations/          # Schema migrations
│   └── seeds/               # Test data
├── public/                  # Web-accessible files
│   ├── assets/             # CSS, JS, images
│   └── index.php           # Entry point
├── resources/
│   └── views/              # Templates
├── routes/                  # Route definitions
├── storage/
│   ├── logs/               # Application logs
│   ├── backups/            # Database backups
│   └── uploads/            # User uploads
├── tests/                   # Test suites
└── vendor/                  # Dependencies
```

### Database Schema

**Core Tables:**

**transfers**
- Primary transfer records
- Foreign keys to stores, users
- Indexes on status, dates, store IDs

**transfer_items**
- Individual line items
- Foreign key to transfers, products
- Indexes on product_id, transfer_id

**stores**
- Store master data
- Vend outlet integration
- Indexes on vend_outlet_id

**products**
- Product catalog
- Synced from Vend
- Indexes on SKU, vend_product_id

**users**
- User accounts
- Role-based permissions
- Indexes on username, email

**audit_logs**
- Security and change tracking
- Indexes on user_id, action, timestamp

**Configuration Tables:**

**system_config**
- Key-value configuration
- Cached aggressively

**presets**
- Saved configurations
- User preferences

### System Requirements

**Minimum:**
- PHP 8.2+
- MariaDB 10.5+
- 2GB RAM
- 20GB SSD storage
- 1 CPU core

**Recommended:**
- PHP 8.3+
- MariaDB 10.11+
- 8GB RAM
- 100GB NVMe storage
- 4 CPU cores
- Redis cache

**Network:**
- Stable internet (10 Mbps+)
- Low latency to Vend API (<200ms)
- HTTPS required (TLS 1.2+)

---

## User Management

### Creating Users

#### Via Admin Panel

1. Navigate to Admin → Users → New User
2. Fill required fields:
   ```
   Username: johndoe
   Email: john@vapeshed.co.nz
   Full Name: John Doe
   Role: Store Manager
   Assigned Stores: [1, 5, 8]
   ```
3. Set initial password (or email auto-generated)
4. Configure permissions
5. Click "Create User"

#### Via Command Line

```bash
php bin/user.php create \
  --username=johndoe \
  --email=john@vapeshed.co.nz \
  --role=store_manager \
  --stores=1,5,8 \
  --send-welcome-email
```

### User Roles

#### Built-In Roles

**Administrator**
```php
Permissions:
- users.create, users.update, users.delete
- transfers.* (all operations)
- config.update
- system.access
- reports.all
```

**Store Manager**
```php
Permissions:
- transfers.create, transfers.approve
- transfers.view (assigned stores only)
- inventory.view
- reports.store
```

**Warehouse Staff**
```php
Permissions:
- transfers.pick, transfers.receive
- transfers.view (assigned stores only)
- inventory.view
```

**Viewer**
```php
Permissions:
- transfers.view
- inventory.view
- reports.view
```

#### Custom Roles

Create custom roles for specific needs:

1. Admin → Roles → New Role
2. Role Name: "Regional Manager"
3. Base Role: Store Manager
4. Add permissions:
   - reports.regional
   - transfers.bulk_approve
5. Save role

### Permission System

#### Permission Structure

Format: `resource.action`

**Examples:**
- `transfers.create`
- `transfers.view`
- `transfers.approve`
- `users.update`
- `config.read`

**Wildcards:**
- `transfers.*` - All transfer operations
- `*.view` - View any resource
- `*.*` - All permissions (admin only)

#### Checking Permissions

**In Controllers:**
```php
if (!$this->user->can('transfers.approve')) {
    return $this->forbidden('Insufficient permissions');
}
```

**In Views:**
```php
<?php if (can('transfers.create')): ?>
    <button>New Transfer</button>
<?php endif; ?>
```

### Password Policies

#### Current Policy

```
Minimum length: 12 characters
Required:
  - Uppercase letter (A-Z)
  - Lowercase letter (a-z)
  - Number (0-9)
  - Special character (!@#$%^&*)
  
Expiration: 90 days
History: Cannot reuse last 5 passwords
Lockout: 5 failed attempts = 30 min lockout
```

#### Updating Password Policy

1. Admin → Security → Password Policy
2. Modify requirements
3. Set enforcement date
4. Notify users

### Multi-Factor Authentication

#### Enabling MFA

**For Individual User:**
```
1. Admin → Users → Select User
2. Security tab
3. Toggle "Require MFA"
4. Save
5. User will be prompted on next login
```

**For All Users (Organization-wide):**
```
1. Admin → Security → MFA Settings
2. Toggle "Require MFA for all users"
3. Set grace period (e.g., 7 days)
4. Email notification sent automatically
```

#### MFA Methods Supported

- **TOTP (Time-based One-Time Password)**
  - Google Authenticator
  - Microsoft Authenticator
  - Authy

- **SMS (if configured)**
  - Requires Twilio setup
  - User must have verified phone number

- **Backup Codes**
  - 10 single-use codes
  - Generated during MFA setup
  - Can be regenerated

### User Activity Monitoring

#### View User Activity

```
Admin → Users → Select User → Activity Log
```

**Shows:**
- Login history (IP, timestamp, device)
- Actions performed
- Data accessed
- Failed login attempts

#### Activity Reports

Generate comprehensive reports:

```
Admin → Reports → User Activity
Filters:
  - Date range
  - User/Role
  - Action type
  - Severity
  
Export: PDF, CSV, JSON
```

### Bulk User Operations

#### Import Users (CSV)

**Template:**
```csv
username,email,full_name,role,stores,send_welcome
johndoe,john@vapeshed.co.nz,John Doe,store_manager,"1,5,8",true
janedoe,jane@vapeshed.co.nz,Jane Doe,warehouse_staff,"3",true
```

**Import Process:**
1. Admin → Users → Import
2. Download template
3. Fill with user data
4. Upload CSV
5. Review validation results
6. Confirm import

#### Bulk Update

Update multiple users at once:

```
1. Admin → Users
2. Select users (checkboxes)
3. Actions dropdown:
   - Enable/Disable
   - Change role
   - Reset password
   - Require MFA
4. Confirm action
```

---

## Configuration Management

### System Configuration

#### Core Settings

Access: Admin → Configuration → System

**General Settings:**
```yaml
Site Name: "Vape Shed Transfer Engine"
Environment: production
Debug Mode: false
Timezone: Pacific/Auckland
Locale: en_NZ
Currency: NZD
```

**Email Settings:**
```yaml
Provider: SendGrid
From Address: noreply@vapeshed.co.nz
From Name: Vape Shed Transfers
Reply To: support@vapeshed.co.nz
```

**Session Settings:**
```yaml
Lifetime: 120 minutes
Idle Timeout: 30 minutes
Secure Cookies: true
SameSite: Strict
```

#### Transfer Settings

**Default Values:**
```yaml
Auto Approval Threshold: $500
Default Priority: normal
Require Notes: false
Max Items Per Transfer: 100
```

**Validation Rules:**
```yaml
Min Transfer Value: $10
Max Transfer Value: $10,000
Allow Negative Stock: false
Require Manager Approval: true (if > threshold)
```

**Workflow Settings:**
```yaml
Auto-sync with Vend: true
Sync Interval: 5 minutes
Create Consignments: true
Update Stock Levels: true
Send Notifications: true
```

### Feature Flags

Enable/disable features without code changes:

```
Admin → Configuration → Features
```

**Available Flags:**

| Flag | Description | Default |
|------|-------------|---------|
| `bulk_operations` | Bulk transfer creation | true |
| `ai_recommendations` | AI-powered suggestions | true |
| `mobile_scanning` | Mobile barcode scanning | true |
| `webhooks` | Outgoing webhooks | true |
| `advanced_analytics` | Predictive analytics | true |
| `auto_rebalancing` | Automated stock rebalancing | false |

**Usage:**
```php
if (feature_enabled('ai_recommendations')) {
    // Show recommendations
}
```

### Environment Variables

Critical settings stored in `.env` file:

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://transfer.vapeshed.co.nz

# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=transfer_engine
DB_USERNAME=transfer_user
DB_PASSWORD=secure_password_here

# Vend API
VEND_DOMAIN=vapeshed
VEND_TOKEN=your_vend_api_token_here

# Email
SENDGRID_API_KEY=your_sendgrid_key_here

# Cache
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Security
SESSION_SECRET=random_64_char_string_here
CSRF_SECRET=random_64_char_string_here
```

**⚠️ Security Note:**
- Never commit `.env` to version control
- Use `.env.example` as template
- Rotate secrets quarterly
- Use different secrets per environment

### Configuration Backup

#### Automated Backups

```
Admin → Configuration → Backups
```

**Schedule:**
- Daily: 2:00 AM NZT
- Before any config change
- Manual on demand

**Backup Contents:**
- All system_config entries
- Feature flags
- Email templates
- Webhook configurations
- User roles (structure only)

#### Restore Configuration

```
1. Admin → Configuration → Backups
2. Select backup by date
3. Review changes (diff view)
4. Click "Restore"
5. Confirm action
6. System restarts (if required)
```

### Configuration Export/Import

#### Export Current Config

```
Admin → Configuration → Export
Format: JSON, YAML, or PHP
```

**Use Cases:**
- Clone configuration to staging
- Document current settings
- Version control configuration
- Disaster recovery

#### Import Configuration

```
Admin → Configuration → Import
Upload file: config_export.json
Options:
  [x] Merge with existing
  [ ] Replace all
  [x] Validate before import
  [ ] Backup current config first
```

---

## Security Administration

### Access Control

#### IP Whitelist

Restrict admin panel access by IP:

```
Admin → Security → IP Whitelist
```

**Configuration:**
```
Allow from:
  - 203.0.113.0/24 (Office network)
  - 198.51.100.50 (VPN gateway)
  - 192.0.2.10 (Admin home)

Block all others: Yes
```

**Emergency Access:**
- Contact hosting provider
- Access via server console
- Update IP whitelist from command line

#### Rate Limiting

Protect against brute force attacks:

```
Admin → Security → Rate Limits
```

**Current Limits:**
```yaml
Login Attempts:
  Limit: 5 attempts per 15 minutes
  Lockout: 30 minutes
  
API Requests:
  Limit: 100 requests per minute
  Burst: 20 requests per second
  
Password Resets:
  Limit: 3 requests per hour
```

### Audit Logging

#### Audit Events

All security-relevant actions logged:

**User Actions:**
- Login/logout (success and failures)
- Password changes
- Permission changes
- Profile updates

**Data Actions:**
- Transfer creation/modification
- Configuration changes
- User management
- Bulk operations

**System Actions:**
- System restarts
- Database migrations
- Integration changes
- Backup/restore

#### Viewing Audit Logs

```
Admin → Security → Audit Logs
```

**Filters:**
- Date range
- User
- Action type
- IP address
- Severity level

**Search:**
```
user:admin action:config.update date:2025-10-09
```

**Export:**
- JSON (for analysis)
- CSV (for spreadsheets)
- SIEM format (for security tools)

#### Log Retention

```
Default: 90 days
Security events: 365 days
Failed logins: 30 days
```

**Archive:**
- Compressed monthly archives
- Stored in `/storage/archives/audit/`
- Encrypted at rest

### Security Headers

#### Current Configuration

```
Admin → Security → Headers
```

**Headers Applied:**
```http
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy: 
  default-src 'self';
  script-src 'self' 'unsafe-inline';
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: https:;
  font-src 'self';
  connect-src 'self' https://api.vend.com;
```

### Encryption

#### Data at Rest

**Database:**
- MariaDB InnoDB encryption
- AES-256 encryption
- Key rotation: quarterly

**File Storage:**
- Sensitive files encrypted
- Per-file encryption keys
- Keys stored in environment

**Backups:**
- Encrypted before upload
- AES-256-GCM
- Unique key per backup

#### Data in Transit

**Requirements:**
- TLS 1.2 minimum
- TLS 1.3 preferred
- Strong ciphers only
- HSTS enforced

**Certificate:**
- Let's Encrypt (auto-renewed)
- 90-day validity
- Automatic renewal 30 days before expiry

### Vulnerability Management

#### Security Scanning

**Automated Scans:**
- Daily dependency scan (Composer)
- Weekly code scan (PHP_CodeSniffer)
- Monthly penetration test (automated)

**Tools:**
```bash
# Dependency vulnerability check
composer audit

# Static analysis
vendor/bin/phpstan analyse

# Security scan
vendor/bin/psalm --security-analysis
```

#### Update Management

**Update Schedule:**
- Security patches: Within 24 hours
- Minor updates: Monthly
- Major updates: Quarterly (after testing)

**Process:**
1. Review changelog
2. Test in staging
3. Schedule maintenance window
4. Deploy to production
5. Monitor for issues
6. Rollback if needed

---

## Database Management

### Database Administration

#### Connection Details

```
Host: localhost
Port: 3306
Database: transfer_engine
User: transfer_user
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Engine: InnoDB
```

#### Performance Metrics

```
Admin → Database → Metrics
```

**Key Metrics:**
- Query execution time (avg, p95, p99)
- Slow query count
- Connection pool usage
- Cache hit ratio
- Table sizes
- Index efficiency

### Schema Migrations

#### Running Migrations

**Via Admin Panel:**
```
Admin → Database → Migrations
- View pending migrations
- Review SQL changes
- Run migrations
- Rollback if needed
```

**Via Command Line:**
```bash
# Check migration status
php bin/migrate.php status

# Run pending migrations
php bin/migrate.php up

# Rollback last migration
php bin/migrate.php down

# Rollback to specific version
php bin/migrate.php rollback --to=20251009_120000
```

#### Creating Migrations

```bash
php bin/migrate.php create add_priority_to_transfers

# Generated file: database/migrations/20251009_150000_add_priority_to_transfers.php
```

**Migration Template:**
```php
<?php

return [
    'up' => "
        ALTER TABLE transfers 
        ADD COLUMN priority ENUM('low','normal','high','urgent') 
        DEFAULT 'normal' AFTER status;
        
        CREATE INDEX idx_transfers_priority ON transfers(priority);
    ",
    
    'down' => "
        ALTER TABLE transfers DROP COLUMN priority;
    "
];
```

### Database Optimization

#### Index Management

**Review Indexes:**
```
Admin → Database → Indexes
```

**Shows:**
- Existing indexes
- Index usage statistics
- Duplicate indexes
- Missing index recommendations

**Add Index:**
```sql
CREATE INDEX idx_transfers_status_created 
ON transfers(status, created_at);
```

#### Query Optimization

**Slow Query Log:**
```
Admin → Database → Slow Queries
```

**Analysis:**
- Query text
- Execution time
- Rows examined
- Rows returned
- EXPLAIN plan

**Optimization Tips:**
1. Add appropriate indexes
2. Avoid SELECT *
3. Use LIMIT for large result sets
4. Optimize JOIN conditions
5. Cache frequently accessed data

#### Table Maintenance

**Automated Tasks:**
```bash
# Optimize tables (weekly)
mysqlcheck -o transfer_engine

# Analyze tables (daily)
mysqlcheck -a transfer_engine

# Repair tables (if needed)
mysqlcheck -r transfer_engine
```

**Manual Optimization:**
```
Admin → Database → Maintenance
Tasks:
  - OPTIMIZE TABLE transfers
  - ANALYZE TABLE transfer_items
  - CHECK TABLE products
```

### Data Archival

#### Archive Strategy

**What to Archive:**
- Completed transfers > 1 year old
- Audit logs > 90 days old
- System logs > 30 days old

**Archive Location:**
- Database: `transfer_engine_archive`
- Files: `/storage/archives/`
- Compressed: gzip

#### Running Archive

**Automated:**
```
Cron: 0 2 1 * * (1st of month, 2:00 AM)
Script: bin/archive.php
```

**Manual:**
```
Admin → Database → Archive
Select: Transfers older than 1 year
Action: Archive and compress
Verify: Yes
Delete originals: After verification
```

#### Restoring Archived Data

```
Admin → Database → Archive → Restore
Select archive: 2024-Q3-transfers.sql.gz
Options:
  [ ] Restore to main database
  [x] Restore to temporary table
  [x] Read-only access
```

---

## Integration Management

### Vend Integration

#### Configuration

```
Admin → Integrations → Vend
```

**Settings:**
```yaml
Domain: vapeshed.vend.com
API Version: 2.0
Authentication: Bearer Token
Token: ••••••••••••••••••••
Sync Interval: 5 minutes
Timeout: 30 seconds
Retry Attempts: 3
```

#### Sync Management

**Manual Sync:**
```
Admin → Integrations → Vend → Sync Now
Options:
  [x] Products
  [x] Outlets (Stores)
  [ ] Customers
  [x] Consignments
```

**Sync Status:**
```
Last Sync: Oct 9, 2025 15:30 NZT
Status: Success
Duration: 2.5 seconds
Records Synced: 1,247 products, 17 outlets
Errors: 0
```

**Sync Logs:**
```
Admin → Integrations → Vend → Logs
Filter: Last 24 hours
```

#### Troubleshooting Vend

**Common Issues:**

1. **Authentication Failed**
```
Error: 401 Unauthorized
Solution: 
  - Regenerate API token in Vend
  - Update token in configuration
  - Test connection
```

2. **Rate Limit Exceeded**
```
Error: 429 Too Many Requests
Solution:
  - Increase sync interval
  - Implement request queuing
  - Contact Vend support for higher limits
```

3. **Sync Timeout**
```
Error: Connection timeout after 30s
Solution:
  - Increase timeout setting
  - Reduce batch size
  - Check network connectivity
```

### Email Integration

#### SendGrid Configuration

```
Admin → Integrations → Email
```

**Settings:**
```yaml
Provider: SendGrid
API Key: ••••••••••••••••••••
From Email: noreply@vapeshed.co.nz
From Name: Vape Shed Transfers
Reply To: support@vapeshed.co.nz
```

**Templates:**
- Welcome email
- Password reset
- Transfer notifications
- Approval requests
- Completion confirmations

#### Email Testing

```
Admin → Integrations → Email → Send Test
To: admin@vapeshed.co.nz
Template: Transfer Created
Variables:
  transfer_id: 12345
  from_store: Auckland
  to_store: Wellington
```

**Deliverability:**
- SPF record: Configured
- DKIM: Enabled
- DMARC: Monitoring

### Webhook Management

#### Outgoing Webhooks

```
Admin → Integrations → Webhooks
```

**Configuration:**
```yaml
Name: "Stock Transfer Notification"
URL: https://example.com/webhooks/transfer
Events:
  - transfer.created
  - transfer.completed
  - transfer.cancelled
Method: POST
Headers:
  Authorization: Bearer xyz123
  X-Webhook-Source: vapeshed-transfer
Timeout: 10 seconds
Retry: 3 times (exponential backoff)
```

#### Webhook Logs

```
Admin → Integrations → Webhooks → Logs
```

**Shows:**
- Request timestamp
- Event type
- Response status
- Response time
- Retry attempts
- Error messages

#### Testing Webhooks

```
Admin → Integrations → Webhooks → Test
```

**Test Payload:**
```json
{
  "event": "transfer.created",
  "timestamp": "2025-10-09T15:30:00Z",
  "data": {
    "transfer_id": 12345
  }
}
```

**Response:**
```
Status: 200 OK
Time: 125ms
Body: {"received": true}
```

---

## Monitoring & Alerts

### System Monitoring

#### Dashboard Metrics

```
Admin → Monitoring → Dashboard
```

**Real-Time Metrics:**
- Requests per second
- Average response time
- Error rate
- Active users
- Queue depth
- Database connections

**Historical Trends:**
- Last 1 hour
- Last 24 hours
- Last 7 days
- Last 30 days

#### Performance Monitoring

**Application Performance:**
```
Admin → Monitoring → Performance
```

**Metrics:**
- Page load times (p50, p95, p99)
- API response times
- Database query times
- External API latency
- Memory usage
- CPU usage

**Alerting Thresholds:**
```yaml
Response Time:
  Warning: > 500ms
  Critical: > 2000ms
  
Error Rate:
  Warning: > 1%
  Critical: > 5%
  
Queue Depth:
  Warning: > 100
  Critical: > 500
```

### Alert Configuration

#### Alert Types

```
Admin → Monitoring → Alerts
```

**System Alerts:**
- High CPU usage (>80%)
- High memory usage (>90%)
- Low disk space (<10%)
- Database connection errors

**Application Alerts:**
- High error rate
- Slow responses
- Failed transfers
- Sync failures

**Security Alerts:**
- Multiple failed logins
- Unauthorized access attempts
- Configuration changes
- Unusual activity patterns

#### Alert Channels

**Email:**
```yaml
Recipients:
  - admin@vapeshed.co.nz
  - it-team@vapeshed.co.nz
Priority: Critical & Warning
Throttle: Max 1 per 5 minutes per alert type
```

**SMS:**
```yaml
Numbers:
  - +64 21 XXX XXXX (Admin)
Priority: Critical only
Time: 24/7
```

**Slack:**
```yaml
Webhook: https://hooks.slack.com/services/XXX
Channel: #alerts
Priority: All
Format: Rich (with graphs)
```

### Log Management

#### Log Levels

```
DEBUG: Detailed information for debugging
INFO: General informational messages
WARNING: Warning messages
ERROR: Error messages
CRITICAL: Critical issues requiring immediate attention
```

#### Log Locations

```
Application: /storage/logs/app.log
Error: /storage/logs/error.log
Security: /storage/logs/security.log
Access: /var/log/nginx/access.log
Database: /var/log/mysql/error.log
```

#### Log Rotation

```
Daily rotation
Compress after 1 day
Delete after 30 days
```

**Configuration:** `/etc/logrotate.d/transfer-engine`

---

## Backup & Recovery

### Backup Strategy

#### Automated Backups

**Database:**
```
Frequency: Every 6 hours
Retention: 
  - Hourly: 48 hours
  - Daily: 30 days
  - Weekly: 90 days
  - Monthly: 1 year
Location: 
  - Primary: /storage/backups/
  - Off-site: S3 bucket
Encryption: AES-256
```

**Files:**
```
Frequency: Daily (2:00 AM)
Retention: 30 days
Includes:
  - Uploads
  - Configuration
  - Custom code
Excludes:
  - Logs
  - Cache
  - Temp files
```

#### Manual Backup

**Via Admin Panel:**
```
Admin → Backups → Create Backup
Options:
  [x] Database
  [x] Files
  [ ] Full system (database + files)
Compression: gzip
Encryption: Yes
```

**Via Command Line:**
```bash
# Database only
php bin/backup.php --database

# Files only
php bin/backup.php --files

# Full backup
php bin/backup.php --full

# With custom name
php bin/backup.php --full --name=pre-upgrade-backup
```

### Backup Verification

#### Automated Verification

```
Schedule: Weekly (Sunday 3:00 AM)
Process:
  1. Select random backup
  2. Restore to test environment
  3. Run integrity checks
  4. Verify data consistency
  5. Email report
```

#### Manual Verification

```
Admin → Backups → Verify
Select backup: backup_20251009_020000.sql.gz
Verification:
  [x] File integrity (checksum)
  [x] Decompression test
  [x] SQL syntax check
  [x] Restore to test database
```

### Recovery Procedures

#### Database Recovery

**Steps:**
1. Stop application (maintenance mode)
2. Export current database (safety backup)
3. Drop existing database
4. Create new database
5. Restore from backup
6. Run migrations (if needed)
7. Verify data integrity
8. Exit maintenance mode

**Command:**
```bash
# Enter maintenance mode
php bin/maintenance.php on

# Restore database
php bin/restore.php --database backup_20251009.sql.gz

# Verify
php bin/verify.php --database

# Exit maintenance mode
php bin/maintenance.php off
```

#### Full System Recovery

**Disaster Scenario:**
- Complete server failure
- Data corruption
- Security breach requiring rebuild

**Recovery Steps:**
1. Provision new server
2. Install system requirements
3. Clone application repository
4. Restore .env file (from secure storage)
5. Restore database backup
6. Restore file backups
7. Update DNS (if needed)
8. Verify all integrations
9. Test functionality
10. Go live

**Estimated Time:**
- Database recovery: 30 minutes
- Full recovery: 2-4 hours

---

## Performance Optimization

### Caching Strategy

#### Cache Layers

**Application Cache (Redis):**
```
Config data: 1 hour
User permissions: 15 minutes
Product catalog: 5 minutes
Store data: 30 minutes
```

**Database Query Cache:**
```
SELECT queries: 1 minute
Aggregate queries: 5 minutes
Report queries: 15 minutes
```

**HTTP Cache:**
```
Static assets: 1 year
API responses: 1 minute
HTML pages: 5 minutes
```

#### Cache Management

```
Admin → Performance → Cache
```

**Actions:**
- Clear all caches
- Clear specific cache (config, products, etc.)
- Warm cache (preload common queries)
- View cache statistics

### Database Optimization

#### Query Optimization

**Identify Slow Queries:**
```
Admin → Performance → Slow Queries
Threshold: > 100ms
```

**Common Optimizations:**
1. Add indexes on foreign keys
2. Use covering indexes
3. Avoid N+1 queries
4. Use EXPLAIN to analyze
5. Cache frequently accessed data

**Example:**
```sql
-- Before (slow)
SELECT * FROM transfers WHERE status = 'pending';

-- After (optimized)
SELECT transfer_id, reference, created_at 
FROM transfers 
WHERE status = 'pending' 
LIMIT 20;
```

#### Connection Pooling

```
Admin → Database → Connections
```

**Settings:**
```yaml
Pool Size: 10 connections
Min Idle: 2
Max Lifetime: 3600 seconds
Idle Timeout: 300 seconds
```

### Code Optimization

#### OPcache Configuration

```
Admin → Performance → OPcache
```

**Settings:**
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0 (production)
opcache.revalidate_freq=0
```

**Stats:**
- Hit rate: 99.8%
- Memory usage: 156MB / 256MB
- Cached files: 847
- Cache misses: 23

#### Asset Optimization

**CSS/JS:**
- Minification: Yes
- Combination: Yes
- Versioning: Hash-based
- Compression: gzip, brotli

**Images:**
- Format: WebP (with fallback)
- Lazy loading: Yes
- CDN: Cloudflare
- Compression: Aggressive

---

## Troubleshooting Guide

### Common Issues

#### Application Won't Start

**Symptoms:**
- 500 Internal Server Error
- White screen
- "Application Error"

**Diagnosis:**
```bash
# Check application logs
tail -f storage/logs/error.log

# Check PHP-FPM
sudo systemctl status php8.2-fpm

# Check Nginx
sudo systemctl status nginx
```

**Solutions:**
1. Check file permissions
2. Verify .env configuration
3. Clear cache
4. Check database connection
5. Review error logs

#### Database Connection Errors

**Error:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Diagnosis:**
```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -u transfer_user -p transfer_engine
```

**Solutions:**
1. Verify database is running
2. Check credentials in .env
3. Verify network connectivity
4. Check firewall rules
5. Increase connection timeout

#### High CPU Usage

**Diagnosis:**
```bash
# Check processes
top -o %CPU

# Check slow queries
mysql -e "SHOW PROCESSLIST;"

# Check application profiler
Admin → Performance → Profiler
```

**Solutions:**
1. Optimize slow queries
2. Enable query cache
3. Increase cache TTL
4. Scale horizontally
5. Optimize code

### Debug Mode

#### Enabling Debug Mode

**⚠️ WARNING:** Only enable in development/staging

```
.env file:
APP_DEBUG=true
APP_LOG_LEVEL=debug
```

**Effects:**
- Detailed error messages
- Stack traces displayed
- Query logging enabled
- Extensive logging

#### Debugging Tools

**Query Logging:**
```php
DB::enableQueryLog();
// ... execute queries
$queries = DB::getQueryLog();
var_dump($queries);
```

**Performance Profiling:**
```php
$profiler = new Profiler();
$profiler->start('operation_name');
// ... code to profile
$profiler->stop('operation_name');
echo $profiler->report();
```

**Memory Usage:**
```php
echo "Memory: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";
```

---

## Maintenance Procedures

### Scheduled Maintenance

#### Maintenance Window

**Standard Window:**
- Time: Sunday 2:00-4:00 AM NZT
- Frequency: Monthly
- Duration: 1-2 hours
- Notification: 7 days advance

**Emergency Maintenance:**
- As needed for critical issues
- Notification: ASAP (minimum 1 hour)

#### Maintenance Mode

**Enable:**
```bash
php bin/maintenance.php on --message="Scheduled maintenance"
```

**Disable:**
```bash
php bin/maintenance.php off
```

**Custom Page:**
```html
Display message to users
Estimated completion time
Contact information
Status updates
```

### Update Procedures

#### Application Updates

**Process:**
1. Review changelog
2. Backup database and files
3. Enable maintenance mode
4. Pull latest code
5. Run `composer install`
6. Run migrations
7. Clear cache
8. Test functionality
9. Disable maintenance mode
10. Monitor for issues

**Rollback Plan:**
```bash
# If issues detected within 1 hour
git checkout previous_version
composer install
php bin/migrate.php rollback
php bin/cache.php clear
```

#### Dependency Updates

**Check for updates:**
```bash
composer outdated
```

**Update all:**
```bash
composer update
```

**Update specific:**
```bash
composer update vendor/package
```

**Security updates:**
```bash
composer audit
composer update --with-dependencies
```

### Health Checks

#### Daily Checks

```
□ System uptime
□ Error log review
□ Backup completion
□ Integration status
□ Pending alerts
```

#### Weekly Checks

```
□ Performance metrics review
□ Database optimization
□ Security audit log review
□ User activity patterns
□ Capacity planning
```

#### Monthly Checks

```
□ Full system audit
□ Backup restore test
□ Security update review
□ Dependency updates
□ Performance optimization
□ Documentation updates
```

---

## Emergency Procedures

### Critical Incident Response

**Severity Levels:**

**P1 - Critical:**
- System down
- Data breach
- Data loss

**Response Time:** 15 minutes  
**Escalation:** Immediate

**P2 - High:**
- Major feature unavailable
- Performance degradation
- Integration failure

**Response Time:** 1 hour  
**Escalation:** 2 hours

**P3 - Medium:**
- Minor feature issue
- Non-critical bug

**Response Time:** 4 hours  
**Escalation:** 24 hours

### Contact Information

**Emergency Contacts:**
```
System Administrator: +64 21 XXX XXXX
Database Administrator: +64 21 XXX XXXX
Security Team: security@vapeshed.co.nz
Hosting Support: Cloudways 24/7 chat
```

**Escalation Path:**
```
1. System Admin
2. IT Manager
3. CTO
4. CEO (for critical incidents)
```

---

**Document Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Maintained By:** Ecigdis Limited IT Team  
**Review Cycle:** Quarterly

**Need Support?**  
📧 Email: it-support@vapeshed.co.nz  
📞 Phone: 0800-VAPESHED ext. 2  
🆘 Emergency: +64 21 XXX XXXX (24/7)
