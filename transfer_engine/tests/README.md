# Testing Guide - Phases 8, 9, 10

## Quick Start

### Option 1: Quick Verification Test (Recommended First)
This verifies all components can load without running full tests:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

php tests/quick_verify.php
```

### Option 2: Full Comprehensive Test Suite
Run all integration tests for Phases 8, 9, and 10:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

php tests/comprehensive_phase_test.php
```

### Option 3: Using Shell Script
```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

chmod +x run_comprehensive_tests.sh
./run_comprehensive_tests.sh
```

---

## Test Files

### 1. `tests/quick_verify.php`
**Purpose**: Quick component loading verification  
**Duration**: < 1 second  
**Tests**:
- ✅ File existence checks
- ✅ Class loading verification
- ✅ Basic instantiation tests
- ✅ No external dependencies required

**Output**: Pass/fail summary with component status

---

### 2. `tests/comprehensive_phase_test.php`
**Purpose**: Full integration testing  
**Duration**: 5-10 seconds  
**Tests**:

#### Phase 8: Integration & Advanced Tools
- CacheManager (set, get, delete, increment, tags)
- Integration helpers (storage_path, config_path, base_path)

#### Phase 9: Monitoring & Alerting
- MetricsCollector (counter, gauge, histogram, timer, query)
- HealthMonitor (register check, run check, get trends)
- PerformanceProfiler (start request, add query, end request, dashboard)
- AlertManager (send alert, get statistics)
- LogAggregator (search logs, get statistics)

#### Phase 10: Analytics & Reporting
- AnalyticsEngine (trend analysis, forecasting, anomaly detection, statistics)
- ReportGenerator (HTML/JSON generation, scheduling)
- DashboardDataProvider (widgets, full dashboard)
- NotificationScheduler (schedule, retrieve, cancel)
- ApiDocumentationGenerator (OpenAPI, Markdown, Postman)

**Output**: Detailed test results with pass/fail for each component

---

## Troubleshooting

### Issue: "Could not open input file"
**Solution**: Make sure you're in the correct directory:
```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine
```

### Issue: "Class not found"
**Solution**: Check autoloader in `config/bootstrap.php` and verify files exist in `src/Support/`

### Issue: Storage directory errors
**Solution**: Ensure storage directory exists and is writable:
```bash
mkdir -p storage/logs storage/cache storage/reports
chmod -R 755 storage
```

### Issue: Redis/Memcached connection errors
**Solution**: CacheManager will fall back to file-based caching. To use Redis:
```php
// In config/bootstrap.php or test file
$cache = new CacheManager($logger, [
    'driver' => 'redis',
    'redis' => [
        'host' => 'localhost',
        'port' => 6379,
    ]
]);
```

---

## Expected Output

### Quick Verify Test
```
╔══════════════════════════════════════════════════════════╗
║   QUICK VERIFICATION TEST - PHASES 8, 9, 10             ║
╚══════════════════════════════════════════════════════════╝

┌─ Loading Components ─────────────────────────────────────┐
  Testing Logger... ✓ Class loaded
  Testing CacheManager... ✓ Class loaded
  Testing MetricsCollector... ✓ Class loaded
  ...
└──────────────────────────────────────────────────────────┘

┌─ Testing Basic Instantiation ───────────────────────────┐
  Creating Logger... ✓
  Creating CacheManager... ✓
  Creating MetricsCollector... ✓
  ...
└──────────────────────────────────────────────────────────┘

╔══════════════════════════════════════════════════════════╗
║                    SUMMARY                               ║
╠══════════════════════════════════════════════════════════╣
║  Total Tests:     17                                     ║
║  Passed:          17                                     ║
║  Failed:          0                                      ║
║  Pass Rate:       100.0%                                 ║
╚══════════════════════════════════════════════════════════╝

✅ All components loaded successfully!
```

### Comprehensive Test
```
╔══════════════════════════════════════════════════════════╗
║   COMPREHENSIVE TEST SUITE - PHASES 8, 9, 10            ║
╚══════════════════════════════════════════════════════════╝

┌─ PHASE 8: Integration & Advanced Tools ─────────────────┐
  Testing CacheManager...
    ✓ CacheManager: Set and get
    ✓ CacheManager: Delete
    ✓ CacheManager: Increment
    ✓ CacheManager: Tags
    ✓ CacheManager: Flush tags
  Testing Integration Helpers...
    ✓ Helper: storage_path exists
    ...
└──────────────────────────────────────────────────────────┘

[... more phases ...]

╔══════════════════════════════════════════════════════════╗
║                    TEST SUMMARY                          ║
╠══════════════════════════════════════════════════════════╣
║  Total Tests:     80                                     ║
║  Passed:          80                                     ║
║  Failed:          0                                      ║
║  Pass Rate:       100.0%                                 ║
╚══════════════════════════════════════════════════════════╝
```

---

## Manual Component Testing

### Test Individual Components

```php
<?php
require_once __DIR__ . '/config/bootstrap.php';

use VapeshedTransfer\Support\Logger;
use VapeshedTransfer\Support\CacheManager;
use VapeshedTransfer\Support\MetricsCollector;

// Initialize
$logger = new Logger(storage_path('logs'));
$cache = new CacheManager($logger);
$metrics = new MetricsCollector($logger, $cache);

// Test metrics
$metrics->counter('test.counter', 1, ['env' => 'test']);
$metrics->gauge('test.gauge', 100, ['env' => 'test']);
$metrics->flush();

echo "✓ Metrics recorded successfully\n";

// Query metrics
$result = $metrics->query('test.counter', time() - 3600, time());
print_r($result);
```

---

## Performance Benchmarks

Expected performance for test suite:
- Quick Verify: < 1 second
- Comprehensive Suite: 5-10 seconds
- Individual component tests: < 100ms each

---

## Next Steps After Testing

1. ✅ Verify all tests pass
2. Review any failed tests and fix issues
3. Check logs in `storage/logs/` for detailed information
4. Validate integration with existing systems
5. Run production smoke tests
6. Deploy to production environment

---

## Support

If tests fail:
1. Check PHP version (requires PHP 8.0+)
2. Verify file permissions on storage directories
3. Review error messages in output
4. Check logs in `storage/logs/`
5. Verify all Phase 8, 9, 10 files are present in `src/Support/`

For additional help, see:
- `docs/PHASE_8_INTEGRATION_COMPLETE.md`
- `docs/PHASE_9_MONITORING_COMPLETE.md`
- `docs/PHASE_10_COMPLETE.md`
- `docs/CUMULATIVE_PROGRESS_TRACKER_FINAL.md`
