# Deployment Guide
**Vape Shed Transfer Engine - Complete Deployment Documentation**

Version: 1.0.0  
Last Updated: October 9, 2025  
For: DevOps Engineers, System Administrators, IT Staff

---

## Table of Contents

1. [Pre-Deployment Planning](#pre-deployment-planning)
2. [Server Requirements](#server-requirements)
3. [Initial Server Setup](#initial-server-setup)
4. [Application Installation](#application-installation)
5. [Database Setup](#database-setup)
6. [Web Server Configuration](#web-server-configuration)
7. [SSL/TLS Configuration](#ssltls-configuration)
8. [Environment Configuration](#environment-configuration)
9. [Integration Setup](#integration-setup)
10. [Performance Tuning](#performance-tuning)
11. [Security Hardening](#security-hardening)
12. [Deployment Verification](#deployment-verification)
13. [Post-Deployment Tasks](#post-deployment-tasks)
14. [Update & Upgrade Procedures](#update--upgrade-procedures)
15. [Rollback Procedures](#rollback-procedures)
16. [Multi-Server Deployment](#multi-server-deployment)
17. [Disaster Recovery](#disaster-recovery)

---

## Pre-Deployment Planning

### Deployment Checklist

#### Pre-Deployment (T-7 days)

```
□ Server provisioning approved
□ Budget approved
□ Backup strategy defined
□ DNS records prepared
□ SSL certificates obtained
□ API keys and credentials secured
□ Database schema reviewed
□ Migration scripts tested
□ Rollback plan documented
□ Monitoring tools configured
□ Stakeholders notified
```

#### Deployment Day (T-0)

```
□ Backup existing system (if applicable)
□ Maintenance mode enabled
□ Code deployed
□ Database migrated
□ Configuration updated
□ Cache cleared
□ Services restarted
□ Smoke tests passed
□ Monitoring active
□ Maintenance mode disabled
□ Users notified
```

#### Post-Deployment (T+1 week)

```
□ Performance monitoring
□ Error tracking
□ User feedback collection
□ Documentation updated
□ Team debriefing
□ Lessons learned documented
```

### Environment Strategy

**Recommended Environments:**

```
Development (dev.transfer.vapeshed.co.nz)
  - Developer machines
  - Feature testing
  - Rapid iteration
  
Staging (staging.transfer.vapeshed.co.nz)
  - Pre-production testing
  - QA validation
  - Client demos
  - Mirror of production
  
Production (transfer.vapeshed.co.nz)
  - Live system
  - Real data
  - High availability
  - Full monitoring
```

### Deployment Timeline

**Typical Deployment:**
```
Planning:        2-4 weeks
Setup:           1-2 days
Testing:         3-5 days
Go-Live:         2-4 hours
Monitoring:      1 week intensive
```

---

## Server Requirements

### Minimum Requirements

**Production Server:**
```yaml
CPU: 4 cores (2.4 GHz+)
RAM: 8 GB
Storage: 100 GB SSD
OS: Ubuntu 20.04 LTS or 22.04 LTS
Network: 100 Mbps+ (low latency)
```

**Development Server:**
```yaml
CPU: 2 cores
RAM: 4 GB
Storage: 50 GB SSD
OS: Ubuntu 20.04 LTS or 22.04 LTS
Network: 10 Mbps+
```

### Recommended Requirements

**Production (High Availability):**
```yaml
CPU: 8 cores (3.0 GHz+)
RAM: 16 GB
Storage: 250 GB NVMe SSD
OS: Ubuntu 22.04 LTS
Network: 1 Gbps (redundant)
Backup: Separate backup server/service
```

### Software Stack

**Core Components:**
```
OS: Ubuntu 22.04 LTS
Web Server: Nginx 1.18+
PHP: 8.2+ with FPM
Database: MariaDB 10.11+
Cache: Redis 6.2+
Process Manager: Supervisor
```

**PHP Extensions Required:**
```
php8.2-cli
php8.2-fpm
php8.2-mysql
php8.2-mbstring
php8.2-xml
php8.2-curl
php8.2-zip
php8.2-gd
php8.2-intl
php8.2-bcmath
php8.2-redis
```

**Additional Tools:**
```
Git (version control)
Composer (dependency management)
Node.js 18+ & npm (asset building)
Certbot (SSL certificates)
```

---

## Initial Server Setup

### Server Provisioning

#### Ubuntu 22.04 LTS Setup

**1. Initial Login**
```bash
ssh root@your-server-ip
```

**2. Update System**
```bash
apt update && apt upgrade -y
```

**3. Set Hostname**
```bash
hostnamectl set-hostname transfer-prod
echo "127.0.0.1 transfer-prod" >> /etc/hosts
```

**4. Configure Timezone**
```bash
timedatectl set-timezone Pacific/Auckland
```

**5. Create Deployment User**
```bash
adduser deploy
usermod -aG sudo deploy
```

**6. Configure SSH Key Authentication**
```bash
# On local machine
ssh-keygen -t ed25519 -C "deploy@transfer-engine"

# Copy to server
ssh-copy-id deploy@your-server-ip
```

**7. Harden SSH**
```bash
nano /etc/ssh/sshd_config

# Update settings:
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
Port 2222  # Optional: Change default port

systemctl restart sshd
```

### Firewall Configuration

```bash
# Install UFW
apt install ufw -y

# Default policies
ufw default deny incoming
ufw default allow outgoing

# Allow SSH (custom port if changed)
ufw allow 2222/tcp

# Allow HTTP/HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Enable firewall
ufw enable

# Check status
ufw status verbose
```

### Install Core Software

**1. Nginx**
```bash
apt install nginx -y
systemctl enable nginx
systemctl start nginx
```

**2. PHP 8.2**
```bash
# Add PHP repository
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP and extensions
apt install php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
            php8.2-xml php8.2-curl php8.2-zip php8.2-gd \
            php8.2-intl php8.2-bcmath php8.2-redis -y

# Enable PHP-FPM
systemctl enable php8.2-fpm
systemctl start php8.2-fpm
```

**3. MariaDB**
```bash
apt install mariadb-server -y
systemctl enable mariadb
systemctl start mariadb

# Secure installation
mysql_secure_installation
```

**4. Redis**
```bash
apt install redis-server -y
systemctl enable redis-server
systemctl start redis-server

# Configure Redis
nano /etc/redis/redis.conf
# Set: maxmemory 256mb
# Set: maxmemory-policy allkeys-lru

systemctl restart redis-server
```

**5. Composer**
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"

# Verify
composer --version
```

**6. Node.js & npm**
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install nodejs -y

# Verify
node --version
npm --version
```

**7. Git**
```bash
apt install git -y
git config --global user.name "Deploy Bot"
git config --global user.email "deploy@vapeshed.co.nz"
```

**8. Supervisor**
```bash
apt install supervisor -y
systemctl enable supervisor
systemctl start supervisor
```

---

## Application Installation

### Clone Repository

```bash
# Create application directory
mkdir -p /var/www
cd /var/www

# Clone repository
git clone https://github.com/vapeshed/transfer-engine.git transfer-engine
cd transfer-engine

# Checkout specific version (production)
git checkout tags/v1.0.0
```

### Set Permissions

```bash
# Set owner
chown -R deploy:www-data /var/www/transfer-engine

# Set directory permissions
find /var/www/transfer-engine -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/transfer-engine -type f -exec chmod 644 {} \;

# Storage directories need write access
chmod -R 775 /var/www/transfer-engine/storage
chmod -R 775 /var/www/transfer-engine/public/uploads
```

### Install Dependencies

**1. PHP Dependencies**
```bash
cd /var/www/transfer-engine
composer install --no-dev --optimize-autoloader
```

**2. Frontend Assets**
```bash
npm install
npm run build
```

### Directory Structure Verification

```bash
ls -la /var/www/transfer-engine

Expected structure:
/var/www/transfer-engine/
├── app/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
│   ├── logs/
│   ├── backups/
│   └── uploads/
├── tests/
├── vendor/
├── .env.example
├── composer.json
└── README.md
```

---

## Database Setup

### Create Database

```bash
mysql -u root -p

# In MySQL prompt:
CREATE DATABASE transfer_engine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user
CREATE USER 'transfer_user'@'localhost' IDENTIFIED BY 'SECURE_PASSWORD_HERE';

# Grant privileges
GRANT ALL PRIVILEGES ON transfer_engine.* TO 'transfer_user'@'localhost';
FLUSH PRIVILEGES;

# Verify
SHOW DATABASES;
SELECT User, Host FROM mysql.user;

EXIT;
```

### Import Schema

**Method 1: Using Migrations (Recommended)**
```bash
cd /var/www/transfer-engine
php bin/migrate.php up
```

**Method 2: Direct Import**
```bash
mysql -u transfer_user -p transfer_engine < database/schema.sql
```

### Seed Initial Data

```bash
# Seed essential data
php bin/seed.php --essential

# Or full seed (for staging/dev)
php bin/seed.php --full
```

**Essential Data:**
- System configuration
- Admin user account
- Default roles and permissions
- System presets

### Database Optimization

```bash
mysql -u root -p transfer_engine

# In MySQL prompt:

# Optimize tables
OPTIMIZE TABLE transfers, transfer_items, products, stores;

# Analyze tables
ANALYZE TABLE transfers, transfer_items, products, stores;

# Check table status
SHOW TABLE STATUS;

EXIT;
```

### Create Database Backup User

```bash
mysql -u root -p

CREATE USER 'backup_user'@'localhost' IDENTIFIED BY 'BACKUP_PASSWORD_HERE';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON transfer_engine.* TO 'backup_user'@'localhost';
FLUSH PRIVILEGES;

EXIT;
```

---

## Web Server Configuration

### Nginx Configuration

**1. Create Server Block**
```bash
nano /etc/nginx/sites-available/transfer-engine
```

**2. Configuration File**
```nginx
# /etc/nginx/sites-available/transfer-engine

server {
    listen 80;
    listen [::]:80;
    server_name transfer.vapeshed.co.nz;
    
    # Redirect to HTTPS (after SSL setup)
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name transfer.vapeshed.co.nz;
    
    root /var/www/transfer-engine/public;
    index index.php index.html;
    
    # SSL Configuration (update paths after certbot)
    ssl_certificate /etc/letsencrypt/live/transfer.vapeshed.co.nz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/transfer.vapeshed.co.nz/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Client body size
    client_max_body_size 20M;
    
    # Logging
    access_log /var/log/nginx/transfer-engine-access.log;
    error_log /var/log/nginx/transfer-engine-error.log;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;
    gzip_min_length 256;
    
    # Root location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Performance
        fastcgi_buffer_size 32k;
        fastcgi_buffers 8 16k;
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 180;
        fastcgi_read_timeout 180;
    }
    
    # Static assets caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /\.git {
        deny all;
    }
    
    location ~ /\.env {
        deny all;
    }
}
```

**3. Enable Site**
```bash
ln -s /etc/nginx/sites-available/transfer-engine /etc/nginx/sites-enabled/

# Test configuration
nginx -t

# Reload Nginx
systemctl reload nginx
```

### PHP-FPM Configuration

**1. PHP-FPM Pool Configuration**
```bash
nano /etc/php/8.2/fpm/pool.d/transfer-engine.conf
```

**2. Pool Settings**
```ini
[transfer-engine]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm-transfer.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 500

; Logging
php_admin_value[error_log] = /var/log/php-fpm/transfer-engine-error.log
php_admin_flag[log_errors] = on

; Security
php_admin_value[open_basedir] = /var/www/transfer-engine:/tmp
php_admin_value[upload_tmp_dir] = /var/www/transfer-engine/storage/uploads/tmp
php_admin_value[session.save_path] = /var/www/transfer-engine/storage/sessions

; Memory & Execution
php_value[memory_limit] = 256M
php_value[max_execution_time] = 60
php_value[upload_max_filesize] = 20M
php_value[post_max_size] = 20M
```

**3. Update Nginx to Use New Pool**
```nginx
# In /etc/nginx/sites-available/transfer-engine
# Update PHP location block:
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.2-fpm-transfer.sock;
    # ... rest of config
}
```

**4. Restart Services**
```bash
systemctl restart php8.2-fpm
systemctl reload nginx
```

### PHP Configuration

**1. Edit php.ini**
```bash
nano /etc/php/8.2/fpm/php.ini
```

**2. Key Settings**
```ini
; Error Handling (Production)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Resource Limits
memory_limit = 256M
max_execution_time = 60
max_input_time = 60

; File Uploads
upload_max_filesize = 20M
post_max_size = 20M
max_file_uploads = 20

; Sessions
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?database=1"
session.gc_maxlifetime = 7200
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict

; OPcache (Performance)
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.fast_shutdown = 1

; Security
expose_php = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen
```

**3. Restart PHP-FPM**
```bash
systemctl restart php8.2-fpm
```

---

## SSL/TLS Configuration

### Install Certbot

```bash
apt install certbot python3-certbot-nginx -y
```

### Obtain SSL Certificate

**Method 1: Automatic (Recommended)**
```bash
certbot --nginx -d transfer.vapeshed.co.nz
```

**Follow prompts:**
```
Email: it@vapeshed.co.nz
Terms: Agree
Share email: No
Redirect HTTP to HTTPS: Yes
```

**Method 2: Manual**
```bash
certbot certonly --webroot -w /var/www/transfer-engine/public \
  -d transfer.vapeshed.co.nz
```

Then manually configure Nginx (already done in config above).

### Auto-Renewal

**Test Renewal:**
```bash
certbot renew --dry-run
```

**Cron Job (automatic):**
```bash
crontab -e

# Add line:
0 3 * * * certbot renew --quiet --post-hook "systemctl reload nginx"
```

**Renewal Hook Script:**
```bash
nano /etc/letsencrypt/renewal-hooks/post/reload-nginx.sh

#!/bin/bash
systemctl reload nginx

chmod +x /etc/letsencrypt/renewal-hooks/post/reload-nginx.sh
```

### SSL Testing

**Test Configuration:**
```bash
# Test Nginx config
nginx -t

# Test SSL handshake
openssl s_client -connect transfer.vapeshed.co.nz:443 -servername transfer.vapeshed.co.nz
```

**Online Testing:**
- SSL Labs Test: https://www.ssllabs.com/ssltest/
- Target Grade: A or A+

---

## Environment Configuration

### Create .env File

```bash
cd /var/www/transfer-engine
cp .env.example .env
nano .env
```

### Environment Variables

**Complete .env Configuration:**
```bash
# Application
APP_NAME="Vape Shed Transfer Engine"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://transfer.vapeshed.co.nz
APP_TIMEZONE=Pacific/Auckland

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=transfer_engine
DB_USERNAME=transfer_user
DB_PASSWORD=SECURE_DATABASE_PASSWORD_HERE

# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DATABASE=0

# Sessions
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# Queue
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database

# Vend Integration
VEND_DOMAIN=vapeshed
VEND_TOKEN=YOUR_VEND_API_TOKEN_HERE
VEND_API_VERSION=2.0
VEND_SYNC_INTERVAL=300

# Email (SendGrid)
MAIL_DRIVER=sendgrid
SENDGRID_API_KEY=YOUR_SENDGRID_API_KEY_HERE
MAIL_FROM_ADDRESS=noreply@vapeshed.co.nz
MAIL_FROM_NAME="Vape Shed Transfers"

# SMS (Twilio - Optional)
TWILIO_ACCOUNT_SID=YOUR_TWILIO_SID_HERE
TWILIO_AUTH_TOKEN=YOUR_TWILIO_TOKEN_HERE
TWILIO_FROM_NUMBER=+64XXXXXXXXX

# Security
APP_KEY=base64:GENERATE_32_RANDOM_BYTES_BASE64_ENCODED
CSRF_TOKEN_SECRET=GENERATE_64_RANDOM_CHARACTERS_HERE
SESSION_SECRET=GENERATE_64_RANDOM_CHARACTERS_HERE

# Rate Limiting
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=60

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAYS=30

# Monitoring
SENTRY_DSN=YOUR_SENTRY_DSN_HERE
SENTRY_ENVIRONMENT=production

# Feature Flags
FEATURE_AI_RECOMMENDATIONS=true
FEATURE_BULK_OPERATIONS=true
FEATURE_MOBILE_SCANNING=true
FEATURE_WEBHOOKS=true
FEATURE_ADVANCED_ANALYTICS=true

# Performance
OPCACHE_ENABLE=true
QUERY_CACHE_ENABLE=true
ASSET_VERSION=1.0.0
```

### Generate Secure Keys

**Generate APP_KEY:**
```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

**Generate Secrets:**
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Secure .env File

```bash
chmod 600 /var/www/transfer-engine/.env
chown deploy:www-data /var/www/transfer-engine/.env

# Verify
ls -la /var/www/transfer-engine/.env
# Should show: -rw------- 1 deploy www-data
```

### Environment Validation

```bash
php bin/validate-config.php

# Expected output:
✓ Database connection: OK
✓ Redis connection: OK
✓ Vend API: OK
✓ SendGrid API: OK
✓ File permissions: OK
✓ Required extensions: OK
✓ Security settings: OK
```

---

## Integration Setup

### Vend API Integration

**1. Obtain API Token**
- Log in to Vend admin panel
- Navigate to Setup → API Access
- Generate new token with permissions:
  - Read products
  - Read outlets
  - Write consignments
  - Read inventory

**2. Configure in .env**
```bash
VEND_DOMAIN=vapeshed
VEND_TOKEN=your_actual_token_here
VEND_API_VERSION=2.0
```

**3. Test Connection**
```bash
php bin/test-vend.php

# Expected output:
✓ Vend API connection: OK
✓ Token valid: Yes
✓ Outlets found: 17
✓ Products synced: 1,247
✓ Last sync: 2025-10-09 15:30:00
```

**4. Initial Sync**
```bash
php bin/sync-vend.php --full

# This will:
# - Sync all products
# - Sync all outlets (stores)
# - Create initial stock levels
# Duration: 5-10 minutes
```

### SendGrid Email Integration

**1. Create SendGrid Account**
- Sign up at sendgrid.com
- Verify domain: vapeshed.co.nz
- Create API key with Mail Send permissions

**2. Configure DNS Records**
```
# Add these DNS records for vapeshed.co.nz:

TXT record: 
  Name: @
  Value: v=spf1 include:sendgrid.net ~all

CNAME records:
  s1._domainkey → s1.domainkey.u1234567.wl123.sendgrid.net
  s2._domainkey → s2.domainkey.u1234567.wl123.sendgrid.net
  
  em1234.vapeshed.co.nz → u1234567.wl123.sendgrid.net
```

**3. Configure in .env**
```bash
MAIL_DRIVER=sendgrid
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxx
MAIL_FROM_ADDRESS=noreply@vapeshed.co.nz
MAIL_FROM_NAME="Vape Shed Transfers"
```

**4. Test Email**
```bash
php bin/test-email.php --to=admin@vapeshed.co.nz

# Expected output:
✓ SendGrid API connection: OK
✓ Test email sent: OK
✓ Message ID: <message-id>
Check inbox: admin@vapeshed.co.nz
```

### Twilio SMS Integration (Optional)

**1. Create Twilio Account**
- Sign up at twilio.com
- Purchase NZ phone number (+64)
- Get Account SID and Auth Token

**2. Configure in .env**
```bash
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_FROM_NUMBER=+64XXXXXXXXX
```

**3. Test SMS**
```bash
php bin/test-sms.php --to=+64XXXXXXXXX

# Expected output:
✓ Twilio API connection: OK
✓ Test SMS sent: OK
✓ SID: SMxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## Performance Tuning

### OPcache Optimization

**Check Current Status:**
```bash
php -r "print_r(opcache_get_status());"
```

**Optimal Configuration:**
```ini
# /etc/php/8.2/fpm/conf.d/10-opcache.ini

opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.fast_shutdown=1
opcache.enable_cli=0
```

**Restart PHP-FPM:**
```bash
systemctl restart php8.2-fpm
```

### Redis Configuration

```bash
nano /etc/redis/redis.conf

# Key settings:
maxmemory 512mb
maxmemory-policy allkeys-lru
timeout 300
tcp-keepalive 60
```

**Restart Redis:**
```bash
systemctl restart redis-server
```

### MariaDB Tuning

```bash
nano /etc/mysql/mariadb.conf.d/99-custom.cnf

[mysqld]
# InnoDB Settings
innodb_buffer_pool_size = 4G
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query Cache
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Connections
max_connections = 200
max_allowed_packet = 64M

# Temp Tables
tmp_table_size = 64M
max_heap_table_size = 64M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 1
```

**Restart MariaDB:**
```bash
systemctl restart mariadb
```

### Nginx Tuning

```bash
nano /etc/nginx/nginx.conf

user www-data;
worker_processes auto;
worker_rlimit_nofile 65535;

events {
    worker_connections 4096;
    use epoll;
    multi_accept on;
}

http {
    # Basic Settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 20M;
    
    # Buffer Settings
    client_body_buffer_size 128k;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 16k;
    output_buffers 1 32k;
    postpone_output 1460;
    
    # Timeouts
    client_body_timeout 12;
    client_header_timeout 12;
    send_timeout 10;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss 
               application/rss+xml font/truetype font/opentype 
               application/vnd.ms-fontobject image/svg+xml;
    gzip_min_length 256;
    
    # File Cache
    open_file_cache max=200000 inactive=20s;
    open_file_cache_valid 30s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;
    
    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
    
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

**Reload Nginx:**
```bash
nginx -t && systemctl reload nginx
```

---

## Security Hardening

### System Security

**1. Install Fail2Ban**
```bash
apt install fail2ban -y

# Configure
nano /etc/fail2ban/jail.local

[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = 2222
logpath = /var/log/auth.log

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log

systemctl enable fail2ban
systemctl start fail2ban
```

**2. Install ClamAV (Antivirus)**
```bash
apt install clamav clamav-daemon -y
freshclam
systemctl enable clamav-daemon
systemctl start clamav-daemon
```

**3. Automatic Security Updates**
```bash
apt install unattended-upgrades -y
dpkg-reconfigure --priority=low unattended-upgrades

# Enable automatic updates: Yes
```

### Application Security

**1. File Permissions**
```bash
# Set ownership
chown -R deploy:www-data /var/www/transfer-engine

# Directories: 755
find /var/www/transfer-engine -type d -exec chmod 755 {} \;

# Files: 644
find /var/www/transfer-engine -type f -exec chmod 644 {} \;

# Storage: 775 (writable)
chmod -R 775 /var/www/transfer-engine/storage
chmod -R 775 /var/www/transfer-engine/public/uploads

# .env: 600 (owner read/write only)
chmod 600 /var/www/transfer-engine/.env
```

**2. Disable Directory Listing**
```nginx
# In Nginx config
autoindex off;
```

**3. Hide PHP Version**
```ini
# /etc/php/8.2/fpm/php.ini
expose_php = Off
```

**4. Secure Headers** (Already in Nginx config)
- X-Frame-Options
- X-Content-Type-Options
- X-XSS-Protection
- Strict-Transport-Security
- Referrer-Policy

---

## Deployment Verification

### Smoke Tests

**1. Application Health Check**
```bash
curl https://transfer.vapeshed.co.nz/api/health

# Expected: {"status":"ok","timestamp":"2025-10-09T15:30:00Z"}
```

**2. Database Connection**
```bash
php bin/test-database.php

# Expected output:
✓ Database connection: OK
✓ Tables exist: OK
✓ Sample query: OK
```

**3. Vend Integration**
```bash
php bin/test-vend.php

# Expected output:
✓ Vend API: OK
✓ Products: 1,247
✓ Outlets: 17
```

**4. Cache System**
```bash
php bin/test-cache.php

# Expected output:
✓ Redis connection: OK
✓ Cache write: OK
✓ Cache read: OK
✓ Cache delete: OK
```

**5. Email System**
```bash
php bin/test-email.php --to=test@example.com

# Expected output:
✓ SendGrid API: OK
✓ Email sent: OK
```

### Load Testing

**Using Apache Bench:**
```bash
ab -n 1000 -c 10 https://transfer.vapeshed.co.nz/

# Results should show:
# - Requests per second: > 100
# - Mean time per request: < 100ms
# - Failed requests: 0
```

**Using wrk:**
```bash
wrk -t12 -c100 -d30s https://transfer.vapeshed.co.nz/

# Results should show:
# - Throughput: > 500 req/sec
# - Latency avg: < 200ms
# - Timeouts: 0
```

### Security Scan

```bash
# SSL Labs
# Visit: https://www.ssllabs.com/ssltest/analyze.html?d=transfer.vapeshed.co.nz
# Expected: A or A+ rating

# Security headers
curl -I https://transfer.vapeshed.co.nz/

# Expected headers:
# Strict-Transport-Security
# X-Frame-Options
# X-Content-Type-Options
# X-XSS-Protection
```

---

## Post-Deployment Tasks

### Monitoring Setup

**1. Application Monitoring**
```bash
# Setup Sentry (error tracking)
# Already configured via SENTRY_DSN in .env

# Verify
curl -X POST https://sentry.io/api/0/projects/your-org/your-project/events/ \
  -H "Authorization: Bearer YOUR_SENTRY_TOKEN"
```

**2. Server Monitoring**
```bash
# Install monitoring agent (example: New Relic, Datadog)
# Or use built-in monitoring dashboard
```

**3. Uptime Monitoring**
- Configure Pingdom, UptimeRobot, or similar
- Monitor: transfer.vapeshed.co.nz
- Check interval: 1 minute
- Alert contacts: it@vapeshed.co.nz

### Backup Configuration

**1. Automated Database Backup**
```bash
nano /usr/local/bin/backup-transfer-db.sh

#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/transfer-engine"
mkdir -p $BACKUP_DIR

mysqldump -u backup_user -p'BACKUP_PASSWORD' transfer_engine \
  | gzip > $BACKUP_DIR/transfer_engine_$DATE.sql.gz

# Delete backups older than 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

chmod +x /usr/local/bin/backup-transfer-db.sh

# Add to cron
crontab -e
0 2 * * * /usr/local/bin/backup-transfer-db.sh
```

**2. File Backup**
```bash
# Use rsync or cloud storage
# Example: rsync to backup server
rsync -avz --delete /var/www/transfer-engine/storage/ \
  backup-server:/backups/transfer-engine/storage/
```

### Documentation

**1. Document Server Details**
```
Server IP: XXX.XXX.XXX.XXX
Hostname: transfer-prod
SSH Port: 2222
SSH User: deploy
Deployment Path: /var/www/transfer-engine
Database: transfer_engine
Database User: transfer_user
```

**2. Document Credentials**
Store securely in password manager (Bitwarden):
- Server SSH key
- Database passwords
- API keys (Vend, SendGrid, Twilio)
- SSL certificates

**3. Document Procedures**
- Deployment process
- Rollback process
- Backup & restore
- Emergency contacts

---

## Update & Upgrade Procedures

### Application Updates

**Minor Updates (e.g., 1.0.0 → 1.0.1):**
```bash
cd /var/www/transfer-engine

# 1. Enable maintenance mode
php bin/maintenance.php on

# 2. Backup database
mysqldump -u backup_user -p transfer_engine > /tmp/pre-update-backup.sql

# 3. Pull latest code
git fetch origin
git checkout tags/v1.0.1

# 4. Update dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 5. Run migrations (if any)
php bin/migrate.php up

# 6. Clear cache
php bin/cache.php clear
redis-cli FLUSHALL

# 7. Reload PHP-FPM
systemctl reload php8.2-fpm

# 8. Test
curl https://transfer.vapeshed.co.nz/api/health

# 9. Disable maintenance mode
php bin/maintenance.php off
```

**Major Updates (e.g., 1.0.0 → 2.0.0):**
Same process, but:
- Test thoroughly in staging first
- Schedule longer maintenance window
- Review breaking changes
- Update configuration if needed
- Notify users 7 days in advance

### System Updates

**Regular Updates:**
```bash
apt update
apt upgrade -y
apt autoremove -y
apt autoclean
```

**Security Updates (immediate):**
```bash
unattended-upgrades -d
```

**PHP Updates:**
```bash
# When upgrading PHP version (e.g., 8.2 → 8.3)
apt install php8.3-fpm php8.3-cli php8.3-mysql # ... all extensions

# Update Nginx config to use new socket
# Update systemd services
# Test thoroughly
# Remove old PHP version
apt purge php8.2-*
```

---

## Rollback Procedures

### Application Rollback

**Quick Rollback (< 1 hour after deployment):**
```bash
cd /var/www/transfer-engine

# 1. Enable maintenance mode
php bin/maintenance.php on

# 2. Checkout previous version
git checkout tags/v1.0.0

# 3. Restore dependencies
composer install --no-dev --optimize-autoloader

# 4. Rollback database (if migrations ran)
php bin/migrate.php rollback

# 5. Clear cache
php bin/cache.php clear

# 6. Reload services
systemctl reload php8.2-fpm

# 7. Test
curl https://transfer.vapeshed.co.nz/api/health

# 8. Disable maintenance mode
php bin/maintenance.php off
```

**Full Rollback (with database restore):**
```bash
# 1. Enable maintenance mode
php bin/maintenance.php on

# 2. Restore database backup
mysql -u root -p transfer_engine < /var/backups/transfer-engine/pre-deployment-backup.sql

# 3. Rollback application code (as above)

# 4. Verify data integrity
php bin/verify-data.php

# 5. Test thoroughly

# 6. Disable maintenance mode
php bin/maintenance.php off
```

### Database Rollback

**Rollback Single Migration:**
```bash
php bin/migrate.php rollback --steps=1
```

**Rollback to Specific Version:**
```bash
php bin/migrate.php rollback --to=20251001_120000
```

**Full Database Restore:**
```bash
mysql -u root -p

DROP DATABASE transfer_engine;
CREATE DATABASE transfer_engine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

mysql -u root -p transfer_engine < /var/backups/backup_file.sql
```

---

## Multi-Server Deployment

### Load Balancer Setup

**(Coming in v2.0 - High Availability)**

**Architecture:**
```
┌─────────────────────┐
│   Load Balancer     │
│   (Nginx/HAProxy)   │
└──────┬──────────────┘
       │
   ┌───┴────┐
   │        │
┌──▼──┐  ┌──▼──┐
│ Web1 │  │ Web2 │
└──┬───┘  └──┬───┘
   │        │
   └────┬───┘
        │
   ┌────▼─────┐
   │ Database │
   │ (Primary)│
   └──────────┘
```

---

## Disaster Recovery

### Backup Strategy

**3-2-1 Rule:**
- 3 copies of data
- 2 different media types
- 1 offsite copy

**Backup Locations:**
1. Local: /var/backups/transfer-engine/
2. Cloud: S3 bucket (encrypted)
3. Offsite: Different datacenter

### Recovery Time Objectives

**RTO (Recovery Time Objective):**
- Database failure: 30 minutes
- Application failure: 15 minutes
- Complete server failure: 4 hours

**RPO (Recovery Point Objective):**
- Maximum data loss: 6 hours
- Database backups: Every 6 hours
- File backups: Daily

### Disaster Scenarios

**Scenario 1: Database Corruption**
```
1. Identify corruption
2. Stop application
3. Restore latest backup
4. Verify data integrity
5. Resume operation
Time: 30 minutes
```

**Scenario 2: Server Failure**
```
1. Provision new server
2. Restore from backups
3. Update DNS
4. Verify functionality
Time: 4 hours
```

**Scenario 3: Data Center Outage**
```
1. Activate DR site
2. Update DNS
3. Verify replication
4. Monitor systems
Time: 2 hours (if DR configured)
```

---

**Document Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Maintained By:** Ecigdis Limited DevOps Team  
**Review Cycle:** Quarterly

**Need Deployment Support?**  
📧 Email: devops@vapeshed.co.nz  
📞 Phone: 0800-VAPESHED ext. 3  
🆘 Emergency: +64 21 XXX XXXX (24/7)
