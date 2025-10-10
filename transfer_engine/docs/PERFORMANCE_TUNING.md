# Performance Tuning Guide
**Vape Shed Transfer Engine - Complete Performance Optimization Reference**

Version: 1.0.0  
Last Updated: October 9, 2025  
For: System Administrators, DevOps Engineers, Performance Engineers

---

## Table of Contents

1. [Performance Overview](#performance-overview)
2. [Baseline Metrics](#baseline-metrics)
3. [Application Performance](#application-performance)
4. [Database Optimization](#database-optimization)
5. [Caching Strategies](#caching-strategies)
6. [Web Server Tuning](#web-server-tuning)
7. [PHP Configuration](#php-configuration)
8. [Frontend Optimization](#frontend-optimization)
9. [Network Performance](#network-performance)
10. [Monitoring & Profiling](#monitoring--profiling)
11. [Load Testing](#load-testing)
12. [Scalability Planning](#scalability-planning)

---

## Performance Overview

### Performance Goals

**Target Metrics (95th Percentile):**
```yaml
Page Load Time: < 700ms
API Response Time: < 300ms
Database Query Time: < 50ms
Time to First Byte (TTFB): < 200ms
First Contentful Paint (FCP): < 1.0s
Largest Contentful Paint (LCP): < 2.5s
Cumulative Layout Shift (CLS): < 0.1
Interaction to Next Paint (INP): < 200ms
```

**Throughput Goals:**
```yaml
Concurrent Users: 100+
Requests per Second: 500+
Database Connections: 50 concurrent
Queue Processing: 1000 jobs/hour
```

### Performance Philosophy

**1. Measure First**
- Never optimize without metrics
- Establish baseline before changes
- A/B test optimizations
- Document all changes

**2. Focus on Bottlenecks**
- Profile to find slow points
- Optimize highest-impact areas first
- 80/20 rule: 20% of code = 80% of performance
- Don't over-optimize cold paths

**3. Progressive Enhancement**
- Start with critical path
- Defer non-critical resources
- Lazy load heavy components
- Graceful degradation

**4. Monitor Continuously**
- Real-time performance metrics
- Alerting for degradation
- Regular performance audits
- User experience monitoring

---

## Baseline Metrics

### Establish Baseline

**Before optimization, capture baseline metrics:**

**1. Application Performance**
```bash
# Homepage load time
curl -w "@curl-format.txt" -o /dev/null -s https://transfer.vapeshed.co.nz/

# API response time
curl -w "Time: %{time_total}s\n" -o /dev/null -s https://transfer.vapeshed.co.nz/api/transfers

# Multiple requests for average
for i in {1..10}; do
  curl -w "Request $i: %{time_total}s\n" -o /dev/null -s https://transfer.vapeshed.co.nz/
done | awk '{sum+=$3; count++} END {print "Average:", sum/count, "seconds"}'
```

**2. Database Performance**
```sql
-- Query execution time
SET profiling = 1;
SELECT * FROM transfers WHERE status = 'approved' LIMIT 20;
SHOW PROFILES;

-- Slow query log
SHOW VARIABLES LIKE 'slow_query%';
SHOW VARIABLES LIKE 'long_query_time';
```

**3. Resource Utilization**
```bash
# CPU usage
top -b -n 1 | head -20

# Memory usage
free -h

# Disk I/O
iostat -x 1 5

# Network throughput
iftop -i eth0
```

**4. Web Server Performance**
```bash
# Nginx connection statistics
nginx -V 2>&1 | grep -o with-http_stub_status_module

# If enabled:
curl http://localhost/nginx_status

# Active connections
netstat -an | grep :443 | wc -l
```

### Performance Audit Tools

**Automated Tools:**
```bash
# Lighthouse (Chrome)
lighthouse https://transfer.vapeshed.co.nz --output=html --output-path=./lighthouse-report.html

# WebPageTest
curl "https://www.webpagetest.org/runtest.php?url=https://transfer.vapeshed.co.nz&k=API_KEY"

# GTmetrix
# Visit: https://gtmetrix.com/ and enter URL

# PHP Profiler (Xdebug)
php -d xdebug.profiler_enable=1 bin/profile.php
```

**Manual Checks:**
```
□ Time to First Byte (TTFB)
□ First Contentful Paint (FCP)
□ Largest Contentful Paint (LCP)
□ Time to Interactive (TTI)
□ Total Blocking Time (TBT)
□ Cumulative Layout Shift (CLS)
□ Page Size (Total KB)
□ Number of Requests
□ Image optimization
□ JavaScript bundle size
```

---

## Application Performance

### Code-Level Optimization

**1. Reduce Database Queries (N+1 Problem)**

**Before (Inefficient):**
```php
// Fetches transfers (1 query)
$transfers = Transfer::all();

// Then fetches items for each transfer (N queries)
foreach ($transfers as $transfer) {
    echo $transfer->items->count(); // N additional queries
}
// Total: 1 + N queries
```

**After (Optimized):**
```php
// Eager load items (2 queries total)
$transfers = Transfer::with('items')->get();

foreach ($transfers as $transfer) {
    echo $transfer->items->count(); // No additional query
}
// Total: 2 queries (1 for transfers, 1 for all items)
```

**2. Implement Query Caching**

```php
use App\Core\Cache;

class TransferService
{
    public function getActiveTransfers()
    {
        $cacheKey = 'transfers:active';
        $cacheTTL = 300; // 5 minutes
        
        return Cache::remember($cacheKey, $cacheTTL, function() {
            return Transfer::where('status', 'approved')
                ->with('items', 'fromStore', 'toStore')
                ->get();
        });
    }
}
```

**3. Optimize Loops**

**Before:**
```php
$results = [];
foreach ($transfers as $transfer) {
    $results[] = [
        'id' => $transfer->id,
        'reference' => $transfer->reference,
        'total' => $transfer->calculateTotal(), // Calls DB each time
    ];
}
```

**After:**
```php
// Pre-calculate totals with SQL
$transfers = Transfer::select('transfers.*')
    ->selectRaw('SUM(transfer_items.quantity * transfer_items.unit_price) as total')
    ->leftJoin('transfer_items', 'transfers.transfer_id', '=', 'transfer_items.transfer_id')
    ->groupBy('transfers.transfer_id')
    ->get();

$results = $transfers->map(function($transfer) {
    return [
        'id' => $transfer->id,
        'reference' => $transfer->reference,
        'total' => $transfer->total, // Already calculated
    ];
})->toArray();
```

**4. Use Generators for Large Datasets**

**Before (High Memory):**
```php
function getAllProducts() {
    return Product::all(); // Loads all products into memory
}

foreach (getAllProducts() as $product) {
    processProduct($product);
}
```

**After (Low Memory):**
```php
function getAllProducts() {
    foreach (Product::cursor() as $product) {
        yield $product; // One at a time
    }
}

foreach (getAllProducts() as $product) {
    processProduct($product);
}
```

### Response Time Optimization

**1. Implement Early Flush**

```php
// Send headers early
header('X-Accel-Buffering: no');
flush();

// Output critical HTML
echo $htmlHeader;
flush();

// Continue processing
$data = heavyDatabaseQuery();

// Output remaining HTML
echo renderData($data);
```

**2. Async Processing for Slow Operations**

**Before (Synchronous):**
```php
public function createTransfer($data)
{
    $transfer = Transfer::create($data);
    
    // Slow operations block response
    $this->notifyUsers($transfer);
    $this->syncToVend($transfer);
    $this->generatePDF($transfer);
    
    return $transfer;
}
```

**After (Asynchronous):**
```php
public function createTransfer($data)
{
    $transfer = Transfer::create($data);
    
    // Queue slow operations
    Queue::push(new NotifyUsersJob($transfer));
    Queue::push(new SyncToVendJob($transfer));
    Queue::push(new GeneratePDFJob($transfer));
    
    return $transfer; // Fast response
}
```

**3. Implement Result Pagination**

```php
public function getTransfers(Request $request)
{
    $perPage = $request->input('per_page', 20);
    $page = $request->input('page', 1);
    
    $transfers = Transfer::with('items')
        ->where('status', 'approved')
        ->paginate($perPage);
    
    return response()->json([
        'data' => $transfers->items(),
        'pagination' => [
            'current_page' => $transfers->currentPage(),
            'total_pages' => $transfers->lastPage(),
            'total_items' => $transfers->total(),
            'per_page' => $perPage,
        ],
    ]);
}
```

### Memory Optimization

**1. Monitor Memory Usage**

```php
function logMemoryUsage($label = '')
{
    $memory = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    
    Log::info("Memory [$label]", [
        'current' => round($memory / 1024 / 1024, 2) . ' MB',
        'peak' => round($peak / 1024 / 1024, 2) . ' MB',
    ]);
}

logMemoryUsage('Start');
$data = heavyOperation();
logMemoryUsage('After heavy operation');
```

**2. Free Memory Explicitly**

```php
function processLargeDataset()
{
    $products = Product::all();
    
    foreach ($products as $product) {
        processProduct($product);
    }
    
    // Free memory
    unset($products);
    gc_collect_cycles();
}
```

**3. Use Chunking for Bulk Operations**

```php
// Process in chunks of 100
Product::chunk(100, function($products) {
    foreach ($products as $product) {
        updateProduct($product);
    }
    
    // Memory freed automatically after each chunk
});
```

---

## Database Optimization

### Query Optimization

**1. Use EXPLAIN to Analyze Queries**

```sql
-- Check query execution plan
EXPLAIN SELECT * FROM transfers 
WHERE status = 'approved' 
AND created_at > '2025-01-01';

-- Look for:
-- - type: Should be 'ref' or better (not 'ALL')
-- - rows: Number of rows examined (lower is better)
-- - Extra: Avoid 'Using filesort' or 'Using temporary'
```

**Expected Output:**
```
+----+-------------+-----------+------+---------------+--------+---------+-------+------+-------------+
| id | select_type | table     | type | possible_keys | key    | key_len | ref   | rows | Extra       |
+----+-------------+-----------+------+---------------+--------+---------+-------+------+-------------+
|  1 | SIMPLE      | transfers | ref  | idx_status    | idx_st | 20      | const |  150 | Using where |
+----+-------------+-----------+------+---------------+--------+---------+-------+------+-------------+
```

**2. Create Appropriate Indexes**

**Identify Missing Indexes:**
```sql
-- Find queries without indexes
SELECT * FROM information_schema.PROCESSLIST 
WHERE TIME > 1 AND COMMAND != 'Sleep';

-- Check index usage
SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'transfer_engine'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

**Create Indexes:**
```sql
-- Single column index
CREATE INDEX idx_transfers_status ON transfers(status);

-- Composite index (order matters)
CREATE INDEX idx_transfers_status_created ON transfers(status, created_at);

-- Covering index (includes data columns)
CREATE INDEX idx_transfers_status_ref ON transfers(status, reference, created_at);

-- Unique index
CREATE UNIQUE INDEX idx_transfers_reference ON transfers(reference);

-- Full-text index (for search)
CREATE FULLTEXT INDEX idx_products_search ON products(name, description);
```

**Index Best Practices:**
```
✓ Index columns used in WHERE clauses
✓ Index columns used in JOIN conditions
✓ Index columns used in ORDER BY
✓ Use composite indexes for multi-column queries
✓ Put most selective column first in composite index
✗ Don't over-index (slows down writes)
✗ Don't index low-cardinality columns (e.g., boolean)
✗ Don't index very large text columns
```

**3. Optimize JOIN Queries**

**Before:**
```sql
-- Inefficient: Full table scan on transfers
SELECT t.*, ti.*, p.*
FROM transfers t
LEFT JOIN transfer_items ti ON t.transfer_id = ti.transfer_id
LEFT JOIN products p ON ti.product_id = p.product_id;
```

**After:**
```sql
-- Optimized: Filtered and indexed
SELECT t.transfer_id, t.reference, 
       ti.product_id, ti.quantity,
       p.name, p.sku
FROM transfers t
INNER JOIN transfer_items ti ON t.transfer_id = ti.transfer_id
INNER JOIN products p ON ti.product_id = p.product_id
WHERE t.status = 'approved'
  AND t.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
LIMIT 100;

-- With appropriate indexes:
-- idx_transfers_status_created on transfers(status, created_at)
-- idx_transfer_items_transfer on transfer_items(transfer_id)
-- idx_products_id on products(product_id)
```

**4. Use Query Profiling**

```sql
-- Enable profiling
SET profiling = 1;

-- Run queries
SELECT * FROM transfers WHERE status = 'approved';
SELECT * FROM transfers WHERE status = 'approved' AND created_at > '2025-01-01';

-- Show profiles
SHOW PROFILES;

-- Detailed profile
SHOW PROFILE FOR QUERY 1;

-- Disable profiling
SET profiling = 0;
```

### Database Configuration

**MariaDB Optimization** (`/etc/mysql/mariadb.conf.d/99-tuning.cnf`):

```ini
[mysqld]
# ===== InnoDB Settings =====
# Buffer pool (70-80% of RAM for dedicated DB server)
innodb_buffer_pool_size = 8G
innodb_buffer_pool_instances = 8

# Log files
innodb_log_file_size = 1G
innodb_log_buffer_size = 64M

# Flush behavior (1=safest, 2=faster)
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# I/O settings
innodb_io_capacity = 2000
innodb_io_capacity_max = 4000
innodb_read_io_threads = 4
innodb_write_io_threads = 4

# ===== Query Cache =====
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 4M

# ===== Connections =====
max_connections = 200
max_allowed_packet = 64M
connect_timeout = 10
wait_timeout = 600

# ===== Temporary Tables =====
tmp_table_size = 128M
max_heap_table_size = 128M

# ===== Thread Settings =====
thread_cache_size = 50
table_open_cache = 4000
table_definition_cache = 2000

# ===== Slow Query Log =====
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 1
log_queries_not_using_indexes = 1

# ===== Binary Log (for replication/point-in-time recovery) =====
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 7
max_binlog_size = 100M

# ===== Character Set =====
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci
```

**Apply Configuration:**
```bash
systemctl restart mariadb

# Verify settings
mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
```

### Table Maintenance

**Regular Maintenance Tasks:**

```bash
# Optimize tables (weekly)
mysqlcheck -u root -p --optimize transfer_engine

# Analyze tables (daily)
mysqlcheck -u root -p --analyze transfer_engine

# Check tables (monthly)
mysqlcheck -u root -p --check transfer_engine

# Repair tables (if needed)
mysqlcheck -u root -p --repair transfer_engine
```

**Automated Maintenance:**
```bash
# Create maintenance script
nano /usr/local/bin/mysql-maintenance.sh

#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
LOG="/var/log/mysql/maintenance_$DATE.log"

echo "Starting MySQL maintenance at $DATE" > $LOG

# Analyze tables
mysqlcheck -u root -pPASSWORD --analyze transfer_engine >> $LOG 2>&1

# Optimize tables (only needed for MyISAM, safe for InnoDB)
mysqlcheck -u root -pPASSWORD --optimize transfer_engine >> $LOG 2>&1

echo "Maintenance completed at $(date +%Y%m%d_%H%M%S)" >> $LOG

chmod +x /usr/local/bin/mysql-maintenance.sh

# Add to cron (weekly, Sunday 3am)
crontab -e
0 3 * * 0 /usr/local/bin/mysql-maintenance.sh
```

### Connection Pooling

**PHP-FPM Persistent Connections:**

```php
// Database connection with persistent connection
$options = [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = new PDO($dsn, $username, $password, $options);
```

**Monitor Connections:**
```sql
-- Current connections
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';

-- Connection history
SHOW STATUS LIKE 'Connections';
SHOW STATUS LIKE 'Aborted_connects';

-- Process list
SHOW FULL PROCESSLIST;
```

---

## Caching Strategies

### Multi-Layer Caching

**Cache Hierarchy:**
```
1. Browser Cache (HTTP headers)
   └─ 2. CDN Cache (Cloudflare)
      └─ 3. Nginx FastCGI Cache
         └─ 4. OPcache (PHP bytecode)
            └─ 5. Application Cache (Redis)
               └─ 6. Query Cache (MySQL)
                  └─ 7. Database
```

### Redis Configuration

**Optimal Redis Settings** (`/etc/redis/redis.conf`):

```conf
# Memory
maxmemory 1gb
maxmemory-policy allkeys-lru

# Persistence (for sessions)
save 900 1
save 300 10
save 60 10000

# AOF (Append Only File) for durability
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec

# Performance
tcp-keepalive 60
timeout 300
databases 16

# Slow log
slowlog-log-slower-than 10000
slowlog-max-len 128

# Security
requirepass YOUR_REDIS_PASSWORD_HERE
bind 127.0.0.1
protected-mode yes
```

**Apply Configuration:**
```bash
systemctl restart redis-server

# Verify
redis-cli PING
redis-cli CONFIG GET maxmemory
```

### Application Caching

**1. Cache Frequently Accessed Data**

```php
class ProductService
{
    public function getProduct($id)
    {
        $cacheKey = "product:$id";
        $cacheTTL = 600; // 10 minutes
        
        return Cache::remember($cacheKey, $cacheTTL, function() use ($id) {
            return Product::with('category', 'brand')->find($id);
        });
    }
    
    public function updateProduct($id, $data)
    {
        $product = Product::find($id);
        $product->update($data);
        
        // Invalidate cache
        Cache::forget("product:$id");
        
        return $product;
    }
}
```

**2. Cache Configuration**

```php
// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'redis'),
    
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
    ],
    
    'ttls' => [
        'products' => 600,      // 10 minutes
        'stores' => 1800,       // 30 minutes
        'config' => 3600,       // 1 hour
        'users' => 900,         // 15 minutes
        'transfers' => 60,      // 1 minute (frequently updated)
    ],
];
```

**3. Cache Warm-Up**

```bash
# Warm cache on deployment
php bin/cache.php warm

# Warm specific data
php bin/cache.php warm --products
php bin/cache.php warm --stores
php bin/cache.php warm --config
```

**4. Cache Invalidation Strategies**

**Time-Based (TTL):**
```php
Cache::put('key', 'value', 600); // Expires after 10 minutes
```

**Event-Based:**
```php
// When product updated
Event::listen('product.updated', function($product) {
    Cache::forget("product:{$product->id}");
    Cache::tags(['products'])->flush();
});
```

**Manual:**
```php
// Clear specific cache
Cache::forget('products:list');

// Clear tagged cache
Cache::tags(['products'])->flush();

// Clear all cache
Cache::flush();
```

### HTTP Caching

**1. Nginx Caching**

```nginx
# /etc/nginx/conf.d/cache.conf

# Cache path
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=TRANSFER:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

server {
    # ... existing config
    
    # FastCGI cache for PHP
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_cache TRANSFER;
        fastcgi_cache_valid 200 60m;
        fastcgi_cache_valid 404 10m;
        fastcgi_cache_bypass $http_cache_control;
        add_header X-Cache-Status $upstream_cache_status;
        
        # ... rest of config
    }
    
    # Static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

**2. PHP Cache Headers**

```php
// Cache for 10 minutes
header('Cache-Control: public, max-age=600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 600) . ' GMT');

// No cache for dynamic content
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Conditional caching with ETag
$etag = md5_file($file);
header("ETag: \"$etag\"");
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === "\"$etag\"") {
    header('HTTP/1.1 304 Not Modified');
    exit;
}
```

---

## Web Server Tuning

### Nginx Optimization

**Optimal Nginx Configuration** (`/etc/nginx/nginx.conf`):

```nginx
user www-data;
worker_processes auto;
worker_rlimit_nofile 65535;
pid /run/nginx.pid;

events {
    worker_connections 4096;
    use epoll;
    multi_accept on;
}

http {
    ##
    # Basic Settings
    ##
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    keepalive_requests 100;
    types_hash_max_size 2048;
    server_tokens off;
    
    ##
    # Buffer Settings
    ##
    client_body_buffer_size 128k;
    client_max_body_size 20m;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 16k;
    output_buffers 1 32k;
    postpone_output 1460;
    
    ##
    # Timeout Settings
    ##
    client_body_timeout 12;
    client_header_timeout 12;
    send_timeout 10;
    reset_timedout_connection on;
    
    ##
    # Gzip Compression
    ##
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss 
               application/rss+xml font/truetype font/opentype 
               application/vnd.ms-fontobject image/svg+xml;
    gzip_min_length 256;
    gzip_disable "msie6";
    
    ##
    # File Cache
    ##
    open_file_cache max=200000 inactive=20s;
    open_file_cache_valid 30s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;
    
    ##
    # Rate Limiting
    ##
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
    limit_conn_zone $binary_remote_addr zone=addr:10m;
    
    ##
    # SSL Settings
    ##
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_session_cache shared:SSL:50m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;
    ssl_stapling on;
    ssl_stapling_verify on;
    
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

**Apply & Verify:**
```bash
nginx -t
systemctl reload nginx

# Monitor performance
watch -n 1 'curl -o /dev/null -s -w "Time: %{time_total}s\n" https://transfer.vapeshed.co.nz/'
```

### HTTP/2 & HTTP/3

**Enable HTTP/2:**
```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    
    # ... rest of config
}
```

**HTTP/3 (QUIC) Support:**
```nginx
# Requires Nginx 1.25+ with QUIC support
server {
    listen 443 ssl http2;
    listen 443 quic reuseport;
    
    http3 on;
    http3_hq on;
    
    add_header Alt-Svc 'h3=":443"; ma=86400';
    
    # ... rest of config
}
```

---

## PHP Configuration

### PHP-FPM Optimization

**Pool Configuration** (`/etc/php/8.2/fpm/pool.d/transfer-engine.conf`):

```ini
[transfer-engine]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm-transfer.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

# Process Manager
pm = dynamic
pm.max_children = 100         ; Maximum children
pm.start_servers = 20         ; Start with 20 processes
pm.min_spare_servers = 10     ; Minimum idle processes
pm.max_spare_servers = 30     ; Maximum idle processes
pm.max_requests = 500         ; Recycle after 500 requests

# Process Priority
process_priority = -10

# Logging
pm.status_path = /status
ping.path = /ping
ping.response = pong
access.log = /var/log/php-fpm/transfer-engine-access.log
slowlog = /var/log/php-fpm/transfer-engine-slow.log
request_slowlog_timeout = 5s

# Security
php_admin_value[open_basedir] = /var/www/transfer-engine:/tmp
php_admin_value[upload_tmp_dir] = /var/www/transfer-engine/storage/uploads/tmp
php_admin_value[session.save_path] = /var/www/transfer-engine/storage/sessions

# Resource Limits
php_value[memory_limit] = 256M
php_value[max_execution_time] = 60
php_value[max_input_time] = 60
php_value[upload_max_filesize] = 20M
php_value[post_max_size] = 20M

# Error Handling
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php-fpm/transfer-engine-error.log
php_admin_flag[display_errors] = off
```

**Calculate Optimal pm.max_children:**
```
Formula:
pm.max_children = (Total RAM - RAM for other services) / Average PHP process size

Example:
- Server RAM: 8 GB
- Reserved for OS, MySQL, Redis: 3 GB
- Available for PHP: 5 GB (5000 MB)
- Average PHP process size: 50 MB

pm.max_children = 5000 MB / 50 MB = 100
```

**Monitor PHP-FPM:**
```bash
# Check pool status
curl http://localhost/status?full
curl http://localhost/status?json

# Check process sizes
ps aux | grep php-fpm | awk '{print $6}' | awk '{sum+=$1; count++} END {print "Average:", sum/count/1024, "MB"}'

# Check slow requests
tail -f /var/log/php-fpm/transfer-engine-slow.log
```

### OPcache Tuning

**Optimal OPcache Settings** (`/etc/php/8.2/fpm/conf.d/10-opcache.ini`):

```ini
[opcache]
; Enable OPcache
opcache.enable=1
opcache.enable_cli=0

; Memory
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000

; Performance
opcache.validate_timestamps=0        ; Disable in production for max performance
opcache.revalidate_freq=0            ; Never check for file changes
opcache.fast_shutdown=1
opcache.enable_file_override=1

; Optimization
opcache.optimization_level=0x7FFEBFFF
opcache.max_wasted_percentage=5
opcache.use_cwd=1

; Error handling
opcache.log_verbosity_level=1
opcache.error_log=/var/log/php-opcache-error.log
```

**For Development (different settings):**
```ini
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

**OPcache Statistics:**
```php
// Create opcache-status.php (protect with authentication)
<?php
$status = opcache_get_status();
$config = opcache_get_configuration();

echo json_encode([
    'memory_usage' => $status['memory_usage'],
    'statistics' => $status['opcache_statistics'],
    'scripts' => count($status['scripts']),
    'hit_rate' => round($status['opcache_statistics']['opcache_hit_rate'], 2) . '%',
], JSON_PRETTY_PRINT);
```

**Clear OPcache:**
```bash
# Restart PHP-FPM (clears OPcache)
systemctl reload php8.2-fpm

# Or via CLI
php -r "opcache_reset();"

# Or via web script (create opcache-reset.php)
curl https://transfer.vapeshed.co.nz/opcache-reset.php?secret=YOUR_SECRET
```

---

## Frontend Optimization

### Asset Optimization

**1. Minify CSS/JS**

```bash
# Install minification tools
npm install -D cssnano postcss-cli terser

# Minify CSS
npx postcss public/assets/css/style.css --use cssnano -o public/assets/css/style.min.css

# Minify JavaScript
npx terser public/assets/js/app.js -o public/assets/js/app.min.js --compress --mangle

# Or use build script (package.json)
{
  "scripts": {
    "build": "npm run build:css && npm run build:js",
    "build:css": "postcss resources/css/app.css --use cssnano -o public/assets/css/app.min.css",
    "build:js": "terser resources/js/app.js -o public/assets/js/app.min.js --compress --mangle"
  }
}
```

**2. Combine Assets**

```html
<!-- Before: Multiple requests -->
<link rel="stylesheet" href="/assets/css/bootstrap.css">
<link rel="stylesheet" href="/assets/css/custom.css">
<link rel="stylesheet" href="/assets/css/dashboard.css">

<!-- After: Single request -->
<link rel="stylesheet" href="/assets/css/app.min.css">
```

**Build Script:**
```bash
# Combine CSS
cat resources/css/bootstrap.css resources/css/custom.css resources/css/dashboard.css | \
  npx postcss --use cssnano > public/assets/css/app.min.css

# Combine JS
cat resources/js/utils.js resources/js/dashboard.js resources/js/app.js | \
  npx terser --compress --mangle > public/assets/js/app.min.js
```

**3. Image Optimization**

```bash
# Install image optimization tools
apt install optipng jpegoptim webp -y

# Optimize PNG
find public/assets/images -name "*.png" -exec optipng -o5 {} \;

# Optimize JPEG
find public/assets/images -name "*.jpg" -exec jpegoptim --strip-all --max=85 {} \;

# Convert to WebP
find public/assets/images -name "*.jpg" -exec sh -c 'cwebp -q 80 "$1" -o "${1%.jpg}.webp"' _ {} \;
find public/assets/images -name "*.png" -exec sh -c 'cwebp -q 80 "$1" -o "${1%.png}.webp"' _ {} \;
```

**4. Lazy Loading**

```html
<!-- Images -->
<img data-src="/assets/images/large-image.jpg" class="lazy" alt="Product">

<script>
// Lazy load images
document.addEventListener("DOMContentLoaded", function() {
  const lazyImages = document.querySelectorAll('.lazy');
  
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        img.src = img.dataset.src;
        img.classList.remove('lazy');
        observer.unobserve(img);
      }
    });
  });
  
  lazyImages.forEach(img => imageObserver.observe(img));
});
</script>
```

### JavaScript Optimization

**1. Defer Non-Critical JavaScript**

```html
<!-- Critical: Loads immediately -->
<script src="/assets/js/critical.js"></script>

<!-- Non-critical: Deferred -->
<script src="/assets/js/analytics.js" defer></script>
<script src="/assets/js/chat-widget.js" defer></script>

<!-- Async for independent scripts -->
<script src="/assets/js/ads.js" async></script>
```

**2. Code Splitting**

```javascript
// Load modules on demand
document.getElementById('showChartBtn').addEventListener('click', async () => {
  const { Chart } = await import('./chart.js');
  new Chart(data);
});
```

**3. Reduce Bundle Size**

```bash
# Analyze bundle size
npx webpack-bundle-analyzer

# Remove unused code (tree shaking)
# Use ES6 imports (not require)
import { specificFunction } from 'library'; // Only includes specificFunction

# Instead of:
const library = require('library'); // Includes entire library
```

### Critical Rendering Path

**1. Inline Critical CSS**

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        /* Inline critical CSS (above-the-fold styles) */
        body { font-family: sans-serif; margin: 0; }
        .header { background: #333; color: white; padding: 20px; }
        /* ... */
    </style>
    
    <!-- Load full stylesheet asynchronously -->
    <link rel="preload" href="/assets/css/style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="/assets/css/style.css"></noscript>
</head>
```

**2. Resource Hints**

```html
<!-- DNS Prefetch -->
<link rel="dns-prefetch" href="//api.vend.com">
<link rel="dns-prefetch" href="//fonts.googleapis.com">

<!-- Preconnect -->
<link rel="preconnect" href="https://api.vend.com" crossorigin>

<!-- Prefetch (next page resources) -->
<link rel="prefetch" href="/dashboard">
<link rel="prefetch" href="/assets/js/dashboard.js">

<!-- Preload (current page critical resources) -->
<link rel="preload" href="/assets/fonts/main.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/css/critical.css" as="style">
```

---

## Network Performance

### CDN Implementation

**Cloudflare CDN Setup:**

1. **Sign up for Cloudflare**
2. **Add vapeshed.co.nz domain**
3. **Update nameservers**
4. **Configure caching rules:**

```
Page Rules:
1. *transfer.vapeshed.co.nz/assets/*
   - Cache Level: Cache Everything
   - Edge Cache TTL: 1 month
   - Browser Cache TTL: 1 year

2. *transfer.vapeshed.co.nz/api/*
   - Cache Level: Bypass
   
3. *transfer.vapeshed.co.nz/*
   - Cache Level: Standard
   - Edge Cache TTL: 2 hours
   - Browser Cache TTL: 30 minutes
```

**Cache-Control Headers for CDN:**
```php
// Static assets (1 year)
header('Cache-Control: public, max-age=31536000, immutable');

// Dynamic content (short cache)
header('Cache-Control: public, max-age=300, s-maxage=600'); // 5min browser, 10min CDN

// No cache
header('Cache-Control: private, no-store, no-cache, must-revalidate');
```

### Connection Optimization

**1. HTTP Keep-Alive**

```nginx
# Nginx
keepalive_timeout 65;
keepalive_requests 100;
```

**2. TCP Fast Open**

```bash
# Enable TCP Fast Open (Linux)
echo 3 > /proc/sys/net/ipv4/tcp_fastopen

# Make permanent
echo "net.ipv4.tcp_fastopen = 3" >> /etc/sysctl.conf
sysctl -p
```

**3. Reduce DNS Lookups**

```
- Minimize external resources
- Use single CDN domain
- Implement DNS prefetching
```

---

## Monitoring & Profiling

### Application Profiling

**1. Xdebug Profiling**

```ini
; /etc/php/8.2/fpm/conf.d/20-xdebug.ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=profile
xdebug.output_dir=/var/www/transfer-engine/storage/profiling
xdebug.profiler_enable_trigger=1
xdebug.profiler_output_name=cachegrind.out.%p
```

**Trigger Profiling:**
```bash
curl "https://transfer.vapeshed.co.nz/?XDEBUG_PROFILE=1"

# Analyze with kcachegrind
kcachegrind storage/profiling/cachegrind.out.*
```

**2. Blackfire.io**

```bash
# Install Blackfire agent
curl -A "Blackfire Installer" -L https://installer.blackfire.io/installer.sh | bash

# Install PHP extension
blackfire agent:config --server-id=SERVER_ID --server-token=SERVER_TOKEN

# Profile
blackfire curl https://transfer.vapeshed.co.nz/
```

### Real User Monitoring (RUM)

**Implement Performance API:**

```javascript
// Capture real user metrics
window.addEventListener('load', function() {
  // Navigation Timing
  const perfData = performance.getEntriesByType('navigation')[0];
  const metrics = {
    dns: perfData.domainLookupEnd - perfData.domainLookupStart,
    tcp: perfData.connectEnd - perfData.connectStart,
    ttfb: perfData.responseStart - perfData.requestStart,
    download: perfData.responseEnd - perfData.responseStart,
    dom: perfData.domComplete - perfData.domLoading,
    load: perfData.loadEventEnd - perfData.loadEventStart,
  };
  
  // Send to analytics
  fetch('/api/metrics/performance', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(metrics),
  });
});

// Core Web Vitals
new PerformanceObserver((list) => {
  for (const entry of list.getEntries()) {
    if (entry.entryType === 'largest-contentful-paint') {
      console.log('LCP:', entry.startTime);
    }
  }
}).observe({entryTypes: ['largest-contentful-paint']});

// First Input Delay (FID)
new PerformanceObserver((list) => {
  for (const entry of list.getEntries()) {
    console.log('FID:', entry.processingStart - entry.startTime);
  }
}).observe({entryTypes: ['first-input']});

// Cumulative Layout Shift (CLS)
let cls = 0;
new PerformanceObserver((list) => {
  for (const entry of list.getEntries()) {
    if (!entry.hadRecentInput) {
      cls += entry.value;
    }
  }
  console.log('CLS:', cls);
}).observe({entryTypes: ['layout-shift']});
```

### Performance Dashboards

**Grafana + Prometheus Setup:**

```bash
# Install Prometheus
wget https://github.com/prometheus/prometheus/releases/download/v2.40.0/prometheus-2.40.0.linux-amd64.tar.gz
tar xvfz prometheus-*.tar.gz
cd prometheus-*

# Configure (prometheus.yml)
scrape_configs:
  - job_name: 'transfer-engine'
    static_configs:
      - targets: ['localhost:9090']

# Install Grafana
apt install -y software-properties-common
add-apt-repository "deb https://packages.grafana.com/oss/deb stable main"
apt update && apt install grafana

# Start services
systemctl start prometheus
systemctl start grafana-server

# Access Grafana: http://localhost:3000 (admin/admin)
```

---

## Load Testing

### Apache Bench (ab)

```bash
# Basic test: 1000 requests, 10 concurrent
ab -n 1000 -c 10 https://transfer.vapeshed.co.nz/

# With authentication
ab -n 1000 -c 10 -C "session=xyz123" https://transfer.vapeshed.co.nz/dashboard

# POST request
ab -n 100 -c 10 -p post_data.json -T application/json https://transfer.vapeshed.co.nz/api/transfers
```

### wrk (Modern Alternative)

```bash
# Install wrk
apt install wrk -y

# Basic test: 12 threads, 100 connections, 30 seconds
wrk -t12 -c100 -d30s https://transfer.vapeshed.co.nz/

# With Lua script (custom logic)
wrk -t12 -c100 -d30s -s post.lua https://transfer.vapeshed.co.nz/api/transfers

# post.lua:
wrk.method = "POST"
wrk.body   = '{"from_store_id":1,"to_store_id":5}'
wrk.headers["Content-Type"] = "application/json"
```

### Locust (Python-based)

```python
# locustfile.py
from locust import HttpUser, task, between

class TransferEngineUser(HttpUser):
    wait_time = between(1, 3)
    
    @task(3)
    def view_dashboard(self):
        self.client.get("/dashboard")
    
    @task(2)
    def view_transfers(self):
        self.client.get("/transfers")
    
    @task(1)
    def create_transfer(self):
        self.client.post("/api/transfers", json={
            "from_store_id": 1,
            "to_store_id": 5,
            "items": [{"product_id": 101, "quantity": 10}]
        })

# Run test
locust -f locustfile.py --host=https://transfer.vapeshed.co.nz --users 100 --spawn-rate 10
```

---

## Scalability Planning

### Vertical Scaling (Scale Up)

**When to scale up:**
- CPU usage consistently > 70%
- Memory usage > 80%
- Disk I/O saturated
- Still single server

**Upgrade Path:**
```
Current: 4 CPU, 8GB RAM, 100GB SSD
  ↓
Tier 1: 8 CPU, 16GB RAM, 250GB NVMe
  ↓
Tier 2: 16 CPU, 32GB RAM, 500GB NVMe
```

### Horizontal Scaling (Scale Out)

**Multi-Server Architecture:**

```
                  ┌─────────────────┐
                  │  Load Balancer  │
                  │    (Nginx)      │
                  └────────┬────────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
      ┌────▼───┐      ┌────▼───┐     ┌────▼───┐
      │  Web1  │      │  Web2  │     │  Web3  │
      │(App+FPM)│      │(App+FPM)│     │(App+FPM)│
      └────┬───┘      └────┬───┘     └────┬───┘
           │               │               │
           └───────────────┼───────────────┘
                           │
                  ┌────────▼────────┐
                  │   Shared Layer  │
                  │  Redis | MySQL  │
                  └─────────────────┘
```

**Load Balancer Configuration:**

```nginx
# /etc/nginx/conf.d/upstream.conf
upstream transfer_app {
    least_conn;
    server web1.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
    server web2.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
    server web3.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
    
    keepalive 32;
}

server {
    listen 443 ssl http2;
    server_name transfer.vapeshed.co.nz;
    
    location / {
        proxy_pass http://transfer_app;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Database Scaling

**Read Replicas:**
```
Primary (Write) ← Replication ← Replica 1 (Read)
                 ← Replication ← Replica 2 (Read)
```

**Configuration:**
```php
// config/database.php
'mysql' => [
    'write' => [
        'host' => env('DB_HOST_WRITE', 'mysql-primary.internal'),
    ],
    'read' => [
        ['host' => env('DB_HOST_READ_1', 'mysql-replica1.internal')],
        ['host' => env('DB_HOST_READ_2', 'mysql-replica2.internal')],
    ],
    'sticky' => true, // Reads from write host for current session after write
],
```

---

**Document Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Maintained By:** Ecigdis Limited Performance Team  
**Review Cycle:** Quarterly

**Performance Support:**  
📧 Email: performance@vapeshed.co.nz  
📞 Phone: 0800-VAPESHED ext. 4  
📊 Dashboard: https://grafana.vapeshed.co.nz
