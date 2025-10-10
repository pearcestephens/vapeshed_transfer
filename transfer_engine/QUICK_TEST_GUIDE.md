# 🎯 QUICK TEST GUIDE

## Current Status: 26/26 Tests Passing (100%) ✅

---

## Quick Commands

### Basic + Security Tests (No Database Required)
```bash
cd transfer_engine
bash bin/run_critical_tests.sh
```
**Result**: 26/26 passing (10 basic + 16 security)  
**Duration**: ~5 seconds

---

### Full Advanced Test Suite (Database Required)
```bash
cd transfer_engine
bash bin/run_advanced_tests.sh
```
**Runs**: 5 test suites (Basic, Security, Integration, Performance, Chaos)  
**Total**: 56 tests  
**Duration**: 2-5 minutes  
**Requires**: Database configured in phpunit.xml

---

## Individual Test Suites

### Integration Tests
```bash
vendor/bin/phpunit --testsuite=Integration --verbose
```
**Tests**: 11 database integration tests  
**What it validates**: Real database operations, allocation algorithms, connection pooling

### Performance Tests
```bash
vendor/bin/phpunit --testsuite=Performance --verbose
```
**Tests**: 8 load and performance benchmarks  
**What it validates**: Response times, memory usage, throughput, consistency

### Chaos Tests
```bash
vendor/bin/phpunit --testsuite=Chaos --verbose
```
**Tests**: 11 failure scenarios  
**What it validates**: Resilience, error handling, recovery

---

## Before Running Advanced Tests

### 1. Configure Database (phpunit.xml)
```xml
<php>
    <env name="DB_HOST" value="localhost"/>
    <env name="DB_NAME" value="test_transfer_engine"/>
    <env name="DB_USER" value="test_user"/>
    <env name="DB_PASSWORD" value="test_password"/>
</php>
```

### 2. Make Scripts Executable
```bash
chmod +x bin/run_advanced_tests.sh
chmod +x bin/run_critical_tests.sh
```

### 3. Verify Database Connection
```bash
php bin/test_database_connection.php
```

---

## Test Results Location

```
storage/logs/tests/
├── advanced_test_report_YYYYMMDD_HHMMSS.txt    ← Detailed output
├── junit.xml                                    ← CI/CD integration
└── testdox.html                                 ← Human-readable report
```

---

## Expected Performance

### Response Time Targets
- Single request: < 1s
- 10 requests: < 10s
- Throughput: > 5 req/sec

### Memory Targets
- Peak memory: < 128MB
- Growth: < 50% over iterations

### Stability Targets
- Success rate: ≥ 96%
- Auto-recovery: 100%

---

## What Each Suite Tests

### ✅ Basic (10 tests)
- Service instantiation
- Method existence
- Test mode
- Kill switch
- Configuration
- Class availability
- Constants
- Storage paths
- Invalid config handling

### ✅ Security (16 tests)
- CSRF protection
- SQL injection
- XSS attacks
- Path traversal
- Command injection
- Rate limiting
- Authentication
- Session fixation
- Password hashing
- Timing attacks
- Secure headers
- Array sanitization

### 🆕 Integration (11 tests)
- Basic transfer execution
- Product list handling
- Allocation fairness (statistical)
- Zero stock handling
- Connection pool efficiency
- Concurrent execution
- Config validation
- Dry run verification
- Min lines threshold
- Logger integration

### 🆕 Performance (8 tests)
- Single request baseline
- Sequential requests
- Memory leak detection
- Connection pool under load
- Rapid execution
- Large result sets
- Response time consistency

### 🆕 Chaos (11 tests)
- Missing warehouse
- Zero products
- Negative stock
- Concurrent safety
- Kill switch activation
- Invalid configs
- Large product lists
- Repeated execution
- Connection recovery
- Resource cleanup

---

## Quick Troubleshooting

### "Database connection failed"
→ Configure database in `phpunit.xml`

### "Undefined constant WAREHOUSE_ID"
→ Check `config/bootstrap.php` has all constants defined

### "Class not found"
→ Run: `composer dump-autoload`

### "Permission denied"
→ Run: `chmod +x bin/*.sh`

---

## Success Indicators

### 🎉 All Tests Passing
```
Success Rate: 100%
🏆 PRODUCTION READY - ALL QUALITY GATES PASSED
```

### ⚠️ Partial Success
```
Success Rate: 80-99%
Most tests passed - review failures before deployment
```

### ❌ Multiple Failures
```
Success Rate: < 80%
Too many failures - significant issues detected
```

---

## Next Steps After 100% Pass

1. ✅ Document baseline performance metrics
2. ✅ Integrate into CI/CD pipeline
3. ✅ Set up automated test execution
4. ✅ Create performance regression alerts
5. ✅ Deploy to staging environment
6. ✅ Monitor production metrics

---

**Pro Tip**: Run `bash bin/run_advanced_tests.sh` before every deployment to catch issues early!
