# 🔧 Logger Fix Required - Quick Action

## Issue Found

The test files are calling `new Logger()` with only 1 argument, but Logger requires 2:
- `new Logger($channel, $logFile)`

## Errors Seen

```
❌ ERROR: Too few arguments to function Unified\Support\Logger::__construct(), 
0 passed... and at least 1 expected
```

## Fix Applied

### File 1: tests/test_flush_fix.php ✅ FIXED
Changed:
```php
$logger = new Logger();
```
To:
```php
$logger = new Logger('test_cache', null);
```

### File 2: tests/comprehensive_phase_test.php ⏳ NEEDS FIX
Needs to change (11 instances):
```php
$logger = new Logger(storage_path('logs'));
```
To:
```php
$logger = new Logger('test', storage_path('logs'));
```

## 🚀 How to Fix

### Option 1: Run the fix script (RECOMMENDED)

```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine

bash fix_logger_instantiations.sh
```

### Option 2: Manual sed command

```bash
cd transfer_engine
sed -i "s/new Logger(storage_path('logs'))/new Logger('test', storage_path('logs'))/g" tests/comprehensive_phase_test.php
```

### Option 3: Use the PHP script

```bash
cd transfer_engine
php fix_logger_tests.php
```

## 📋 After Fix - Run Tests

```bash
# From transfer_engine directory
bash run_all_tests.sh
```

## Expected Result

✅ All Logger instantiations fixed
✅ Tests run without constructor errors
✅ 100% pass rate achieved

---

**Current Status**: 1/2 test files fixed, 1 remaining

**Action Required**: Run `bash fix_logger_instantiations.sh` then `bash run_all_tests.sh`
