# ⚡ Quick Reference Guide - Vapeshed Transfer Engine

**Version:** 1.0 | **Date:** October 8, 2025 | **Status:** Production Ready

---

## 🚀 Quick Start Commands

### Run Full Test Suite
```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine
rm -rf /tmp/vapeshed_* && php tests/test_transfer_engine_integration.php
```
**Expected:** 8/8 tests passing, ~3 seconds duration

### Check System Health
```bash
php bin/health_check.php
```
**Expected:** All checks return "OK"

### Clear Cache
```bash
rm -rf /tmp/vapeshed_* && echo "Cache cleared"
```

### View Recent Logs
```bash
tail -50 logs/cron_$(date +%Y%m%d).log
```

### Run Manual Transfer Calculation
```bash
php bin/daily_transfer_run.php
```

---

## 📊 Key Metrics at a Glance

| Metric | Target | Current Status |
|--------|--------|----------------|
| **Test Pass Rate** | 100% | ✅ 8/8 passing |
| **Cache Performance** | >20x | ✅ 30-95x improvement |
| **Database Response** | <50ms | ✅ 0.48ms (health check) |
| **Store Coverage** | 18 stores | ✅ 18/18 accessible |
| **Inventory Items** | 4,000+ | ✅ 4,315+ per store |
| **System Uptime** | >99% | ✅ Stable |

---

## 🔍 Common Operations

### Get Outlet IDs
```bash
php -r "
require 'config/bootstrap.php';
\$vend = new Unified\Integration\VendAdapter(
    new Unified\Integration\VendConnection(require 'config/vend.php'),
    new Unified\Support\Logger('logs/'),
    new Unified\Support\CacheManager(['enabled' => false])
);
foreach (\$vend->getOutlets() as \$o) {
    echo \$o['name'] . ': ' . \$o['id'] . PHP_EOL;
}
"
```

### Check Low Stock Items for Specific Store
```bash
php -r "
require 'config/bootstrap.php';
\$vend = new Unified\Integration\VendAdapter(
    new Unified\Integration\VendConnection(require 'config/vend.php'),
    new Unified\Support\Logger('logs/'),
    new Unified\Support\CacheManager(['enabled' => true])
);
\$items = \$vend->getLowStockItems('0a6f6e36-8b71-11eb-f3d6-40cea3d59c5a', 10); // Botany
foreach (\$items as \$i) {
    echo \$i['product_name'] . ' - Stock: ' . \$i['inventory_level'] . PHP_EOL;
}
"
```

### Test Cache Performance
```bash
php tests/test_cache_performance.php
```

### View Business Insights
```bash
php tests/test_business_analysis.php | head -100
```

---

## 🚨 Troubleshooting Guide

### Issue: Tests Failing

**Symptom:** Test suite shows failures  
**Check:**
```bash
# 1. Database connectivity
php -r "require 'config/bootstrap.php'; \$c = new Unified\Integration\VendConnection(require 'config/vend.php'); var_dump(\$c->healthCheck());"

# 2. Check error logs
grep -i error logs/*.log | tail -20

# 3. Clear cache and retry
rm -rf /tmp/vapeshed_* && php tests/test_transfer_engine_integration.php
```

**Solution:** 
- If database error: Check credentials in `config/vend.php`
- If cache error: Verify `/tmp/` is writable
- If code error: Check recent changes, consider rollback

---

### Issue: Cache Not Working

**Symptom:** Cache performance <5x improvement  
**Check:**
```bash
# 1. Verify cache directory
ls -ld /tmp/
# Expected: drwxrwxrwt (writable)

# 2. Check cache files
ls -lh /tmp/vapeshed_*

# 3. Test cache explicitly
php tests/test_cache_performance.php
```

**Solution:**
```bash
# Clear cache and restart
rm -rf /tmp/vapeshed_*
# Verify write permissions
touch /tmp/test_write && rm /tmp/test_write && echo "Writable" || echo "Not writable"
```

---

### Issue: Cron Job Not Running

**Symptom:** No recent logs in `logs/cron_*.log`  
**Check:**
```bash
# 1. Verify cron job exists
crontab -l | grep daily_transfer_run

# 2. Check cron service
systemctl status cron

# 3. Test manual run
php bin/daily_transfer_run.php
```

**Solution:**
```bash
# Re-add cron job
crontab -e
# Add: 0 6 * * * cd /path/to/transfer_engine && php bin/daily_transfer_run.php >> logs/cron_$(date +\%Y\%m\%d).log 2>&1
```

---

### Issue: Database Connection Failed

**Symptom:** "SQLSTATE[HY000]" or "Connection refused" errors  
**Check:**
```bash
# 1. Test MySQL connectivity
mysql -h 127.0.0.1 -u jcepnzzkmj -p jcepnzzkmj -e "SELECT 1;"

# 2. Verify credentials in config
grep -A 5 "'database'" config/vend.php

# 3. Check MySQL service
systemctl status mysql
```

**Solution:**
- Verify database credentials: `jcepnzzkmj / wprKh9Jq63 @ 127.0.0.1`
- Check MySQL service is running
- Verify user has SELECT permissions
- Check firewall rules (if remote database)

---

### Issue: Negative Inventory Detected

**Symptom:** Business report shows items with negative stock  
**Check:**
```bash
# Get list of negative inventory items
php tests/test_business_analysis.php 2>&1 | grep -A 20 "CRITICAL: Negative"
```

**Solution:**
1. **Immediate:** Document affected stores and products
2. **Short-term:** Investigate POS sync issues
3. **Long-term:** 
   - Audit inventory counting procedures
   - Review sale transaction processing
   - Implement inventory reconciliation process
   - Add alerts for negative inventory trends

---

## 📈 Performance Benchmarks

### Expected Performance
| Operation | Cold Cache | Warm Cache | Improvement |
|-----------|-----------|------------|-------------|
| Get 18 Outlets | ~110ms | ~5ms | **23.6x** |
| Get Inventory (4,315 items) | ~155ms | ~5ms | **30.5x** |
| Get Sales History | ~160ms | ~5ms | **32.7x** |
| Multiple Queries | ~450ms | ~5ms | **94.8x** |

### Acceptable Ranges
- ✅ **Excellent:** >20x cache improvement
- ⚠️ **Warning:** 5-20x cache improvement (investigate)
- 🚨 **Critical:** <5x cache improvement (troubleshoot immediately)

---

## 🔧 Configuration Quick Reference

### Database Configuration
**File:** `config/vend.php`
```php
'database' => [
    'host' => '127.0.0.1',
    'database' => 'jcepnzzkmj',
    'username' => 'jcepnzzkmj',
    'password' => 'wprKh9Jq63',
    'port' => 3306,
],
```

### Cache Configuration
```php
'cache' => [
    'enabled' => true,
    'ttl' => 300, // 5 minutes
    'prefix' => 'vapeshed_',
    'storage' => '/tmp/',
],
```

### Performance Configuration
```php
'performance' => [
    'connection_pool_size' => [2, 10], // min, max
    'query_timeout' => 30,
    'retry_attempts' => 3,
    'health_check_interval' => 60,
],
```

### Read-Only Mode (Safety)
```php
'read_only' => true, // Set to false only for production writes
```

---

## 📞 Emergency Contacts

### Critical Issues (P1)
- **Database Down:** Contact Database Admin immediately
- **System Errors:** Check logs, attempt rollback if needed
- **Data Corruption:** Stop all processes, backup immediately

### Escalation Path
1. **Level 1:** Check logs, clear cache, restart processes
2. **Level 2:** Review recent changes, consider rollback
3. **Level 3:** Contact technical lead, initiate emergency procedure

### Emergency Commands
```bash
# Stop all cron jobs
crontab -l | grep -v "transfer" | crontab -

# Emergency backup
tar -czf emergency_backup_$(date +%s).tar.gz .

# Rollback to last backup
# (See PRODUCTION_DEPLOYMENT_GUIDE.md - Rollback Procedure)
```

---

## 📋 Daily Operations Checklist

### Morning Check (9:00 AM)
- [ ] Review overnight cron logs: `tail -50 logs/cron_$(date +%Y%m%d).log`
- [ ] Verify health checks passing: `php bin/health_check.php`
- [ ] Check for errors: `grep -i error logs/*.log | tail -20`
- [ ] Review low stock items: `php tests/test_business_analysis.php | head -50`

### Weekly Review (Monday 10:00 AM)
- [ ] Run full test suite: `php tests/test_transfer_engine_integration.php`
- [ ] Review cache performance: `php tests/test_cache_performance.php`
- [ ] Analyze transfer opportunities: `php tests/test_business_analysis.php`
- [ ] Document any data quality issues
- [ ] Report business metrics to inventory manager

### Monthly Audit (1st of Month)
- [ ] Full system backup
- [ ] Performance benchmark comparison
- [ ] Business impact analysis (stockout rate, inventory turns, sales lift)
- [ ] Staff feedback collection
- [ ] Technical debt review

---

## 🎯 Business Insights Dashboard

### Current Status (Updated: Oct 8, 2025)
- **Total Stores:** 18 active retail locations
- **Inventory Items:** 4,315+ per store average
- **Low Stock Items:** 2,703 items (62% of inventory)
- **Critical Items:** 5 items with negative inventory
- **Transfer Opportunities:** Multiple identified daily

### Top Transfer Opportunities
1. **SMOK V12 Prince M4 Coil**
   - From: Botany (6 units) → To: Browns Bay (0 units)
   - Recommended: Transfer 3 units
   
2. **Efest Battery Charger**
   - Priority: CRITICAL (out of stock at multiple locations)
   - Recommended: Emergency restock

### Data Quality Alerts 🚨
- **Negative Inventory:** 5 items across 3 stores
- **Impact:** Customer fulfillment issues
- **Action Required:** Immediate inventory audit

---

## 📚 Additional Resources

### Documentation
- **Full Report:** `PHASE_11_COMPLETE_REPORT.md`
- **Deployment Guide:** `PRODUCTION_DEPLOYMENT_GUIDE.md`
- **Architecture:** `docs/ENGINE_ARCHITECTURE.md`

### Test Files
- **Integration Tests:** `tests/test_transfer_engine_integration.php`
- **Cache Performance:** `tests/test_cache_performance.php`
- **Business Analysis:** `tests/test_business_analysis.php`

### Key Source Files
- **VendConnection:** `src/Integration/VendConnection.php` (368 lines)
- **VendAdapter:** `src/Integration/VendAdapter.php` (445 lines)
- **Configuration:** `config/vend.php` (120 lines)

---

## ✅ Quick Health Check

Run this one-liner for instant system status:
```bash
echo "=== SYSTEM HEALTH ===" && \
php bin/health_check.php && \
echo -e "\n=== RECENT ERRORS ===" && \
grep -i error logs/*.log | tail -5 && \
echo -e "\n=== CACHE STATUS ===" && \
ls /tmp/vapeshed_* 2>/dev/null | wc -l && echo "cache files" || echo "No cache files" && \
echo -e "\n=== LAST CRON RUN ===" && \
tail -3 logs/cron_$(date +%Y%m%d).log 2>/dev/null || echo "No cron run today"
```

---

**Last Updated:** October 8, 2025  
**Version:** 1.0  
**Maintained By:** Transfer Engine Team  
**Support:** inventory@vapeshed.co.nz
