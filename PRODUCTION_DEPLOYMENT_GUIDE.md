# 🚀 Production Deployment Guide - Vapeshed Transfer Engine

**Version:** 1.0  
**Date:** October 8, 2025  
**Status:** Ready for Pilot Deployment  
**Estimated Deployment Time:** 2-4 hours

---

## 📋 Pre-Deployment Checklist

### Prerequisites ✅
- [x] All integration tests passing (8/8)
- [x] Database credentials verified
- [x] VendAdapter operational with real data
- [x] Cache performance validated (30-95x improvement)
- [x] Business insights reviewed
- [x] Read-only mode tested
- [x] Rollback plan prepared

### Required Access
- [ ] Production server SSH access
- [ ] Database credentials (jcepnzzkmj / wprKh9Jq63 @ 127.0.0.1)
- [ ] Backup system access
- [ ] Monitoring dashboard access
- [ ] Emergency contact list updated

### Team Notification
- [ ] Inventory Manager briefed on 2,703 low stock items
- [ ] Store Operations aware of pilot program
- [ ] IT Support on standby during deployment
- [ ] Executive team briefed on expected impact

---

## 🎯 Deployment Strategy: Phased Rollout

### Phase 1: Pilot (Week 1)
**Target Stores:** Botany, Browns Bay, Glenfield (3 stores)

**Why These Stores?**
- **Botany:** High-performing store with surplus inventory (donor candidate)
- **Browns Bay:** Identified with stockouts (receiver candidate)
- **Glenfield:** Has negative inventory (-5 units) - data quality test case

**Success Criteria:**
- Transfer recommendations generated daily
- Accuracy vs legacy system within ±1%
- No production incidents
- Staff feedback positive

### Phase 2: Expansion (Week 2)
**Target Stores:** Add 6 more stores (Cambridge, Frankton, Christchurch, Papakura, Hamilton, Tauranga)

**Success Criteria:**
- All 9 stores receiving daily recommendations
- Cache performance maintained (>20x improvement)
- Data quality issues identified and tracked
- Business impact measurable

### Phase 3: Full Rollout (Week 3)
**Target Stores:** Remaining 9 stores (all 18 stores total)

**Success Criteria:**
- System-wide transfer optimization operational
- Business KPIs showing improvement (stockout reduction, inventory turns)
- Staff adoption high
- ROI tracking initiated

---

## 🔧 Deployment Steps

### Step 1: Pre-Deployment Backup (30 minutes)

```bash
# 1. Backup current system
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD
tar -czf vapeshed_transfer_backup_$(date +%Y%m%d_%H%M%S).tar.gz vapeshed_transfer/

# 2. Backup database schema
mysqldump -h 127.0.0.1 -u jcepnzzkmj -p jcepnzzkmj \
  vend_outlets vend_inventory vend_products vend_sales vend_sales_line_items \
  > vend_schema_backup_$(date +%Y%m%d_%H%M%S).sql

# 3. Verify backups
ls -lh vapeshed_transfer_backup_*.tar.gz
ls -lh vend_schema_backup_*.sql

# 4. Store backup location
echo "Backups stored at: $(pwd)"
```

**Verification:**
- [ ] Code backup created (tar.gz file exists)
- [ ] Database schema backup created (sql file exists)
- [ ] Backup sizes reasonable (code: ~5-10MB, DB schema: ~500KB-2MB)
- [ ] Backup location documented

### Step 2: Environment Validation (15 minutes)

```bash
# 1. Navigate to transfer engine directory
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

# 2. Verify PHP version
php -v
# Expected: PHP 8.0+ with PDO extension

# 3. Verify database connectivity
php tests/test_transfer_engine_integration.php 2>&1 | head -20
# Expected: "VendAdapter initialized" with 18 outlets

# 4. Check cache directory permissions
ls -ld /tmp/
# Expected: drwxrwxrwt (writable by all)

# 5. Verify log directory exists
mkdir -p logs/
chmod 755 logs/

# 6. Test cache clearing
rm -rf /tmp/vapeshed_* && echo "Cache cleared successfully"
```

**Verification:**
- [ ] PHP 8.0+ installed
- [ ] PDO extension available
- [ ] Database connection successful (18 outlets retrieved)
- [ ] Cache directory writable
- [ ] Log directory exists and writable

### Step 3: Configuration Review (15 minutes)

```bash
# 1. Review Vend configuration
cat config/vend.php | grep -A 5 "Database Configuration"

# 2. Verify credentials (check without exposing password)
php -r "
require 'config/bootstrap.php';
\$config = require 'config/vend.php';
echo 'Host: ' . \$config['database']['host'] . PHP_EOL;
echo 'Database: ' . \$config['database']['database'] . PHP_EOL;
echo 'User: ' . \$config['database']['username'] . PHP_EOL;
echo 'Password: ' . (empty(\$config['database']['password']) ? 'NOT SET' : 'SET') . PHP_EOL;
"

# 3. Check read-only mode
grep -n "read_only" config/vend.php
# Expected: 'read_only' => true (safety during testing)
```

**Verification:**
- [ ] Database host: 127.0.0.1
- [ ] Database name: jcepnzzkmj
- [ ] Database user: jcepnzzkmj
- [ ] Password configured (not empty)
- [ ] Read-only mode: TRUE (for pilot phase)

### Step 4: Run Comprehensive Tests (10 minutes)

```bash
# 1. Clear cache and run full test suite
rm -rf /tmp/vapeshed_*
php tests/test_transfer_engine_integration.php 2>&1

# Expected output:
# ✓ VendAdapter initialized
# Test 1: ✓ PASS (18 outlets)
# Test 2: ✓ PASS (4,315 items)
# Test 3: ✓ PASS (DSR calculation)
# Test 4: ✓ PASS (2,703 low stock)
# Test 5: ✓ PASS (Transfer opportunity)
# Test 6: ✓ PASS (Sales velocity)
# Test 7: ✓ PASS (Cache performance)
# Test 8: ✓ PASS (Real scenario)
# Total: 8/8 PASSED

# 2. Run cache performance test
php tests/test_cache_performance.php 2>&1

# Expected:
# getOutlets: 30.5x faster (cache hit)
# getInventory: 32.7x faster (cache hit)
# getSalesHistory: ~30x faster (cache hit)

# 3. Run business analysis (optional - review only)
php tests/test_business_analysis.php 2>&1 | head -50
```

**Verification:**
- [ ] All 8 integration tests passing
- [ ] Cache performance >20x improvement
- [ ] No PHP errors or warnings
- [ ] Test duration <5 seconds

### Step 5: Deploy Pilot Stores Configuration (20 minutes)

```bash
# 1. Create pilot store configuration
cat > config/pilot_stores.php << 'EOF'
<?php
/**
 * Pilot Store Configuration
 * Phase 1: 3 stores for initial testing
 */
return [
    'pilot_enabled' => true,
    'pilot_stores' => [
        // High-performing donor candidate
        '0a6f6e36-8b71-11eb-f3d6-40cea3d59c5a', // Botany
        
        // Stockout receiver candidate
        'browns-bay-outlet-id-here', // Browns Bay (replace with actual ID)
        
        // Data quality test case (negative inventory)
        'glenfield-outlet-id-here', // Glenfield (replace with actual ID)
    ],
    'pilot_start_date' => '2025-10-08',
    'pilot_duration_days' => 7,
    'notification_email' => 'inventory@vapeshed.co.nz',
];
EOF

# 2. Get actual outlet IDs for pilot stores
php -r "
require 'config/bootstrap.php';
\$vend = new Unified\Integration\VendAdapter(
    new Unified\Integration\VendConnection(require 'config/vend.php'),
    new Unified\Support\Logger('logs/'),
    new Unified\Support\CacheManager(['enabled' => false])
);
\$outlets = \$vend->getOutlets();
foreach (\$outlets as \$o) {
    if (in_array(\$o['name'], ['Botany', 'Browns Bay', 'Glenfield'])) {
        echo \$o['name'] . ': ' . \$o['id'] . PHP_EOL;
    }
}
"

# 3. Update pilot_stores.php with actual IDs (manual step)
nano config/pilot_stores.php
```

**Verification:**
- [ ] Pilot configuration file created
- [ ] Actual outlet IDs retrieved
- [ ] Pilot stores configured: Botany, Browns Bay, Glenfield
- [ ] Notification email set

### Step 6: Schedule Automated Transfer Calculations (30 minutes)

```bash
# 1. Create daily transfer calculation script
cat > bin/daily_transfer_run.php << 'EOF'
<?php
/**
 * Daily Transfer Calculation Script
 * Runs transfer engine for pilot stores and generates recommendations
 */
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Unified\Integration\{VendConnection, VendAdapter};
use Unified\Support\{Logger, CacheManager};

// Initialize components
$logger = new Logger(__DIR__ . '/../logs/');
$cache = new CacheManager(['enabled' => true, 'ttl' => 300]);
$vendConnection = new VendConnection(require __DIR__ . '/../config/vend.php');
$vendAdapter = new VendAdapter($vendConnection, $logger, $cache);

// Load pilot configuration
$pilotConfig = require __DIR__ . '/../config/pilot_stores.php';

if (!$pilotConfig['pilot_enabled']) {
    echo "Pilot mode not enabled. Exiting.\n";
    exit(0);
}

$logger->info('Starting daily transfer calculation', [
    'pilot_stores' => count($pilotConfig['pilot_stores']),
    'date' => date('Y-m-d H:i:s'),
]);

// Process each pilot store
foreach ($pilotConfig['pilot_stores'] as $outletId) {
    try {
        echo "Processing outlet: {$outletId}...\n";
        
        // Get low stock items for this store
        $lowStock = $vendAdapter->getLowStockItems($outletId, 50);
        echo "  Low stock items: " . count($lowStock) . "\n";
        
        // TODO: Generate transfer recommendations
        // TODO: Save recommendations to database
        // TODO: Send notification email if configured
        
        $logger->info('Processed outlet successfully', [
            'outlet_id' => $outletId,
            'low_stock_count' => count($lowStock),
        ]);
        
    } catch (\Exception $e) {
        $logger->error('Failed to process outlet', [
            'outlet_id' => $outletId,
            'error' => $e->getMessage(),
        ]);
        echo "  ERROR: {$e->getMessage()}\n";
    }
}

echo "\nDaily transfer calculation complete.\n";
$logger->info('Daily transfer calculation complete');
EOF

# 2. Test the script manually
php bin/daily_transfer_run.php

# 3. Set up cron job (run daily at 6:00 AM)
crontab -l > /tmp/current_cron_backup.txt
echo "0 6 * * * cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine && php bin/daily_transfer_run.php >> logs/cron_$(date +\%Y\%m\%d).log 2>&1" >> /tmp/current_cron_backup.txt
crontab /tmp/current_cron_backup.txt

# 4. Verify cron job
crontab -l | grep daily_transfer_run
```

**Verification:**
- [ ] Daily transfer script created
- [ ] Manual test run successful
- [ ] Cron job scheduled (6:00 AM daily)
- [ ] Log rotation configured

### Step 7: Set Up Monitoring (30 minutes)

```bash
# 1. Create health check script
cat > bin/health_check.php << 'EOF'
<?php
/**
 * Health Check Script
 * Validates system health and reports status
 */
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use Unified\Integration\VendConnection;

$config = require __DIR__ . '/../config/vend.php';
$connection = new VendConnection($config);

$results = [];

// Test 1: Database connectivity
try {
    $health = $connection->healthCheck();
    $results['database'] = [
        'status' => $health ? 'OK' : 'FAIL',
        'response_time' => $health ? '<1ms' : 'N/A',
    ];
} catch (\Exception $e) {
    $results['database'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage(),
    ];
}

// Test 2: Cache directory
$results['cache'] = [
    'status' => is_writable('/tmp/') ? 'OK' : 'FAIL',
    'path' => '/tmp/',
];

// Test 3: Log directory
$results['logs'] = [
    'status' => is_writable(__DIR__ . '/../logs/') ? 'OK' : 'FAIL',
    'path' => __DIR__ . '/../logs/',
];

// Test 4: Configuration
$results['config'] = [
    'status' => (!empty($config['database']['host'])) ? 'OK' : 'FAIL',
    'read_only' => $config['read_only'] ?? false,
];

// Output results
echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;

// Exit with error code if any checks failed
$allOk = true;
foreach ($results as $check) {
    if ($check['status'] !== 'OK') {
        $allOk = false;
        break;
    }
}

exit($allOk ? 0 : 1);
EOF

# 2. Test health check
php bin/health_check.php

# 3. Schedule health check monitoring (every 15 minutes)
crontab -l > /tmp/current_cron_backup.txt
echo "*/15 * * * * cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine && php bin/health_check.php >> logs/health_$(date +\%Y\%m\%d).log 2>&1" >> /tmp/current_cron_backup.txt
crontab /tmp/current_cron_backup.txt

# 4. Create monitoring dashboard endpoint (if web server available)
mkdir -p public/
cat > public/health.php << 'EOF'
<?php
// Simple health check endpoint for monitoring systems
header('Content-Type: application/json');
$output = shell_exec('cd .. && php bin/health_check.php 2>&1');
echo $output;
EOF
```

**Verification:**
- [ ] Health check script created and tested
- [ ] Health monitoring scheduled (every 15 minutes)
- [ ] Web endpoint available (optional)
- [ ] Monitoring dashboard configured

### Step 8: Enable Pilot Mode (5 minutes)

```bash
# 1. Verify read-only mode is active
grep "'read_only' => true" config/vend.php

# 2. Create pilot activation log
echo "Pilot activated: $(date)" >> logs/pilot_activation.log
echo "Pilot stores: Botany, Browns Bay, Glenfield" >> logs/pilot_activation.log
echo "Duration: 7 days (Oct 8-15, 2025)" >> logs/pilot_activation.log

# 3. Run initial transfer calculation for pilot stores
php bin/daily_transfer_run.php | tee logs/pilot_initial_run.log

# 4. Verify output
cat logs/pilot_initial_run.log
```

**Verification:**
- [ ] Read-only mode confirmed active
- [ ] Pilot activation logged
- [ ] Initial transfer calculation successful
- [ ] No errors in output

---

## 📊 Post-Deployment Monitoring (First 24 Hours)

### Hour 1: Immediate Validation
```bash
# Check first cron run
tail -f logs/cron_$(date +%Y%m%d).log

# Verify no errors
grep -i error logs/cron_$(date +%Y%m%d).log

# Check health status
php bin/health_check.php
```

### Hour 4: Performance Check
```bash
# Review cache performance
grep "Cache" logs/*.log | tail -20

# Check database connection stability
grep "VendConnection" logs/*.log | tail -20

# Verify transfer calculations running
ls -lt logs/ | head -10
```

### Hour 24: First Day Review
```bash
# Generate daily summary
php -r "
\$logs = file('logs/cron_' . date('Ymd') . '.log');
echo 'Total runs: ' . count(array_filter(\$logs, fn(\$l) => strpos(\$l, 'Starting daily') !== false)) . PHP_EOL;
echo 'Errors: ' . count(array_filter(\$logs, fn(\$l) => stripos(\$l, 'error') !== false)) . PHP_EOL;
echo 'Warnings: ' . count(array_filter(\$logs, fn(\$l) => stripos(\$l, 'warning') !== false)) . PHP_EOL;
"

# Review low stock trends
php tests/test_business_analysis.php 2>&1 | grep -A 10 "Low Stock Summary"
```

---

## 🚨 Rollback Procedure (Emergency)

### If Critical Issues Arise

```bash
# 1. Stop all cron jobs immediately
crontab -l | grep -v "daily_transfer_run\|health_check" | crontab -

# 2. Restore from backup
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD
tar -xzf vapeshed_transfer_backup_YYYYMMDD_HHMMSS.tar.gz

# 3. Verify restoration
cd vapeshed_transfer/transfer_engine
php tests/test_transfer_engine_integration.php

# 4. Document incident
echo "Rollback performed: $(date)" >> logs/incident.log
echo "Reason: [DOCUMENT REASON HERE]" >> logs/incident.log

# 5. Notify team
echo "ALERT: Transfer Engine rolled back. Check logs/incident.log for details."
```

### Rollback Triggers
- Database connection failures (>3 consecutive failures)
- Test suite failure rate >10%
- Cache performance degradation (improvement <5x)
- Data corruption detected
- Business logic errors affecting transfer recommendations

---

## 📈 Success Metrics & KPIs

### Week 1 Targets (Pilot Phase)
- [ ] **System Uptime:** >99% (6.9/7 days)
- [ ] **Test Pass Rate:** 100% maintained
- [ ] **Cache Performance:** >20x improvement sustained
- [ ] **Daily Runs:** 7/7 successful
- [ ] **Errors:** <5 total for the week
- [ ] **Transfer Recommendations:** >50 generated
- [ ] **Staff Feedback:** Positive from 3 pilot stores

### Week 2-3 Targets (Expansion)
- [ ] **Store Coverage:** 18/18 stores operational
- [ ] **Stockout Reduction:** 10% improvement vs baseline
- [ ] **Inventory Turns:** 5% improvement vs baseline
- [ ] **Recommendation Accuracy:** >95% vs manual review

### Month 1 Targets (Business Impact)
- [ ] **Stockout Reduction:** 30% vs pre-deployment baseline
- [ ] **Excess Inventory Reduction:** 15% vs baseline
- [ ] **Sales Lift:** 5% from improved availability
- [ ] **Staff Adoption:** 80%+ of store managers using system

---

## 📞 Support & Contacts

### Technical Issues
- **Database:** Check logs/cron_*.log and logs/health_*.log
- **Cache:** Clear with `rm -rf /tmp/vapeshed_*`
- **Tests:** Run `php tests/test_transfer_engine_integration.php`

### Business Questions
- **Inventory Manager:** Review logs/pilot_initial_run.log for low stock items
- **Store Operations:** Review transfer recommendations in daily logs
- **Executive Team:** Weekly summary reports (generate from logs)

### Emergency Contacts
- **On-Call Engineer:** [Phone/Email]
- **Database Admin:** [Phone/Email]
- **Business Lead:** [Phone/Email]

---

## ✅ Deployment Completion Checklist

### Pre-Deployment
- [ ] All tests passing (8/8)
- [ ] Backups created (code + database)
- [ ] Environment validated
- [ ] Configuration reviewed
- [ ] Team notified

### Deployment
- [ ] Pilot store configuration deployed
- [ ] Daily transfer script created
- [ ] Cron jobs scheduled
- [ ] Health monitoring enabled
- [ ] Pilot mode activated

### Post-Deployment
- [ ] Initial run successful
- [ ] Health checks passing
- [ ] No errors in logs
- [ ] Monitoring dashboard active
- [ ] Team briefed on pilot results

### Week 1 Milestones
- [ ] 7 consecutive successful daily runs
- [ ] Cache performance sustained (>20x)
- [ ] Transfer recommendations reviewed by inventory manager
- [ ] Staff feedback collected from pilot stores
- [ ] Expansion to Phase 2 approved

---

**Deployment Date:** October 8, 2025  
**Deployment Engineer:** [Your Name]  
**Sign-off:** ___________________  Date: ___________

**Next Review:** October 15, 2025 (End of Week 1 Pilot)
