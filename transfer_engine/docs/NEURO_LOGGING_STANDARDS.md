# Neuro Logging Standards

## Overview

All logging operations in the Vapeshed Transfer Engine must include standardized "neuro" context for consistent observability, traceability, and system-wide correlation.

**Version**: 1.0.0  
**Date**: 2025-10-07  
**Status**: MANDATORY for all logging operations

---

## Core Principles

### 1. Unified Namespace
Every log entry MUST include a `neuro` section with standardized fields:

```json
{
  "neuro": {
    "namespace": "unified",
    "system": "vapeshed_transfer",
    "component": "api|database|monitor|cli|cron|security",
    "environment": "production|staging|development",
    "version": "2.0.0"
  }
}
```

### 2. Correlation IDs
All logs MUST include correlation ID for request tracing:

```json
{
  "correlation_id": "67042af8e1b0a",
  "neuro": { ... }
}
```

### 3. Structured Context
Use nested structures for related data:

```json
{
  "neuro": { ... },
  "context": {
    "operation": "transfer_create",
    "outlet_id": 123,
    "product_count": 45
  }
}
```

---

## Standard Components

### Component Identifiers

| Component | Usage |
|-----------|-------|
| `api` | HTTP API endpoints |
| `database` | Database queries and operations |
| `monitor` | System health and monitoring |
| `cli` | Command-line scripts |
| `cron` | Scheduled tasks |
| `security` | Security events (auth, rate limits, violations) |
| `error_handler` | PHP errors and exceptions |
| `cache` | Cache operations |
| `config` | Configuration management |

### Subsystem Qualifiers

Additional context within components:

```json
{
  "neuro": {
    "component": "database",
    "subsystem": "pdo",
    "operation": "query"
  }
}
```

---

## Logger Class Usage

### Basic Logging with Neuro Context

The `Logger` class automatically includes neuro context:

```php
use Unified\Support\Logger;

$logger = new Logger('api');
$logger->info('Transfer created', [
    'outlet_id' => 123,
    'product_count' => 45,
    'total_value' => 1250.50
]);
```

**Output:**
```json
{
  "timestamp": "2025-10-07T14:32:18+00:00",
  "level": "INFO",
  "channel": "api",
  "message": "Transfer created",
  "correlation_id": "67042af8e1b0a",
  "neuro": {
    "namespace": "unified",
    "system": "vapeshed_transfer",
    "environment": "production",
    "version": "2.0.0"
  },
  "context": {
    "outlet_id": 123,
    "product_count": 45,
    "total_value": 1250.50
  },
  "server": {
    "hostname": "prod-server-01",
    "pid": 12345
  },
  "memory_mb": 45.67
}
```

### Exception Logging

```php
try {
    // risky operation
} catch (\Exception $e) {
    $logger->exception($e, 'ERROR', [
        'operation' => 'transfer_validation',
        'outlet_id' => 123
    ]);
}
```

---

## NeuroContext Helper

The `NeuroContext` class provides standardized context builders:

### API Context

```php
use Unified\Support\NeuroContext;

$context = NeuroContext::api('session', [
    'user_id' => 456,
    'action' => 'login'
]);

$logger->info('User logged in', $context);
```

### Database Context

```php
$context = NeuroContext::database('query', [
    'table' => 'transfers',
    'operation' => 'SELECT',
    'duration_ms' => 125
]);

$logger->debug('Query executed', $context);
```

### Monitoring Context

```php
$context = NeuroContext::monitoring('health_check', [
    'status' => 'healthy',
    'checks_passed' => 5,
    'checks_failed' => 0
]);

$logger->info('Health check completed', $context);
```

### Security Context

```php
$context = NeuroContext::security('rate_limit_exceeded', [
    'endpoint' => '/api/transfers',
    'ip' => '203.45.67.89',
    'limit': 120
]);

$logger->warn('Rate limit exceeded', $context);
```

### CLI Context

```php
$context = NeuroContext::cli('transfer_report', [
    'outlet_id' => 123,
    'date_range' => '2025-10-01 to 2025-10-07'
]);

$logger->info('Report generated', $context);
```

### Cron Context

```php
$context = NeuroContext::cron('automatic_transfers', [
    'run_mode' => 'full',
    'transfers_created' => 23
]);

$logger->info('Cron job completed', $context);
```

---

## Performance Metrics

Add performance data to any context:

```php
$context = NeuroContext::api('metrics');
$context = NeuroContext::withPerformance($context);

$logger->info('Metrics collected', $context);
```

**Output includes:**
```json
{
  "performance": {
    "memory_mb": 45.67,
    "peak_memory_mb": 52.34,
    "load_avg": {
      "1min": 1.25,
      "5min": 1.45,
      "15min": 1.67
    },
    "duration_ms": 234
  }
}
```

---

## API Request Logging

The `Api::logRequest()` method automatically includes neuro context:

```php
use Unified\Support\Api;

Api::logRequest('transfer_status', [
    'transfer_id' => 789,
    'status' => 'completed'
]);
```

**Output:**
```json
{
  "timestamp": "2025-10-07T14:32:18+00:00",
  "correlation_id": "67042af8e1b0a",
  "neuro": {
    "namespace": "unified",
    "system": "vapeshed_transfer",
    "environment": "production",
    "version": "2.0.0",
    "component": "api"
  },
  "endpoint": "transfer_status",
  "method": "GET",
  "ip": "203.45.67.89",
  "user_agent": "Mozilla/5.0...",
  "request_uri": "/api/transfers/789/status",
  "context": {
    "transfer_id": 789,
    "status": "completed"
  }
}
```

---

## Error Handler Integration

The global error handler automatically includes neuro context:

```php
use Unified\Support\ErrorHandler;

ErrorHandler::register(new Logger('errors'), true);
```

**PHP Error Output:**
```json
{
  "timestamp": "2025-10-07T14:32:18+00:00",
  "level": "ERROR",
  "channel": "errors",
  "message": "Undefined variable: foo",
  "correlation_id": "67042af8e1b0a",
  "neuro": {
    "namespace": "unified",
    "system": "vapeshed_transfer",
    "component": "error_handler",
    "error_category": "php_error",
    "environment": "production",
    "version": "2.0.0"
  },
  "context": {
    "type": "NOTICE",
    "errno": 8,
    "file": "/path/to/file.php",
    "line": 42,
    "trace": [...]
  }
}
```

**Exception Output:**
```json
{
  "timestamp": "2025-10-07T14:32:18+00:00",
  "level": "CRITICAL",
  "channel": "errors",
  "message": "Exception: Database connection failed",
  "correlation_id": "67042af8e1b0a",
  "neuro": {
    "namespace": "unified",
    "system": "vapeshed_transfer",
    "component": "error_handler",
    "error_category": "exception",
    "environment": "production",
    "version": "2.0.0"
  },
  "context": {
    "exception": {
      "class": "PDOException",
      "message": "SQLSTATE[HY000] [2002] Connection refused",
      "code": 2002,
      "file": "/path/to/file.php",
      "line": 123,
      "trace": "..."
    }
  }
}
```

---

## Database Profiler Integration

The `DatabaseProfiler` automatically includes neuro context for slow queries:

```php
use Unified\Support\DatabaseProfiler;

DatabaseProfiler::enable(1.0); // 1 second threshold
```

**Slow Query Log Output:**
```json
{
  "timestamp": "2025-10-07T14:32:18+00:00",
  "level": "WARN",
  "channel": "slow_queries",
  "message": "Slow query detected",
  "correlation_id": "67042af8e1b0a",
  "neuro": {
    "namespace": "unified",
    "system": "vapeshed_transfer",
    "component": "database",
    "profiler": "query_performance",
    "environment": "production",
    "version": "2.0.0"
  },
  "context": {
    "sql": "SELECT * FROM transfers WHERE...",
    "duration": 1.2345,
    "row_count": 5432,
    "memory_delta_mb": 12.34,
    "caller": "TransferRepository.php:45",
    "threshold": 1.0
  }
}
```

---

## Monitor Integration

The `Monitor` class includes neuro context in alerts:

```php
use Unified\Support\Monitor;

$monitor = Monitor::fromConfig();
$health = $monitor->checkHealth();
```

**Alert Output:**
```json
{
  "timestamp": "2025-10-07T14:32:18+00:00",
  "level": "WARN",
  "channel": "monitor",
  "message": "Memory usage at 92%, threshold 90%",
  "correlation_id": "67042af8e1b0a",
  "neuro": {
    "namespace": "unified",
    "system": "vapeshed_transfer",
    "component": "monitor",
    "alert_type": "threshold",
    "environment": "production",
    "version": "2.0.0"
  },
  "context": {
    "check": "memory",
    "severity": "warning",
    "threshold_exceeded": true
  }
}
```

---

## Log Analysis & Querying

### Find all API errors in last hour

```bash
grep -r '"component":"api"' storage/logs/ | grep '"level":"ERROR"' | grep "$(date -u +'%Y-%m-%dT%H')"
```

### Find all slow queries

```bash
grep -r '"profiler":"query_performance"' storage/logs/
```

### Find all security events

```bash
grep -r '"component":"security"' storage/logs/
```

### Find all logs for correlation ID

```bash
grep -r '"correlation_id":"67042af8e1b0a"' storage/logs/
```

### Find all critical alerts

```bash
grep -r '"level":"CRITICAL"' storage/logs/ | grep '"neuro"'
```

---

## Best Practices

### ✅ DO

1. **Always include neuro context** in custom logging
2. **Use NeuroContext helpers** for standardized context
3. **Include correlation IDs** for request tracing
4. **Structure context data** with nested objects
5. **Log performance metrics** for slow operations
6. **Use appropriate log levels** (DEBUG, INFO, WARN, ERROR, CRITICAL)
7. **Include operation context** (what was being done)
8. **Sanitize sensitive data** before logging (passwords, tokens, PII)

### ❌ DON'T

1. **Don't log without neuro context** - breaks observability
2. **Don't log raw passwords or tokens** - security violation
3. **Don't use generic messages** - "Error occurred" is useless
4. **Don't log excessive detail in production** - performance impact
5. **Don't mix structured and unstructured logging** - parsing nightmare
6. **Don't ignore correlation IDs** - can't trace requests
7. **Don't log PII without redaction** - privacy violation
8. **Don't spam logs with duplicate messages** - noise

---

## Example: Complete Request Lifecycle

```php
use Unified\Support\{Api, Logger, NeuroContext};

// 1. Initialize API response
Api::initJson();

// 2. Log request
Api::logRequest('transfer_create');

// 3. Log business logic
$logger = new Logger('api');
$context = NeuroContext::api('transfer_create', [
    'outlet_from' => 123,
    'outlet_to' => 456,
    'product_count' => 10
]);

$logger->info('Creating transfer', $context);

try {
    // Business logic here
    
    $logger->info('Transfer created successfully', array_merge($context, [
        'transfer_id' => 789
    ]));
    
    Api::ok(['transfer_id' => 789]);
    
} catch (\Exception $e) {
    $logger->exception($e, 'ERROR', $context);
    Api::error('TRANSFER_FAILED', $e->getMessage(), 500);
}
```

**Result**: Complete audit trail with:
- API request logged with neuro context
- Business operation logged with neuro context
- Success/failure logged with neuro context
- All linked by correlation ID
- Performance metrics captured
- Structured for easy querying

---

## Migration Guide

### Updating Existing Code

**Before:**
```php
$logger->info('Transfer created', [
    'outlet_id' => 123
]);
```

**After:**
```php
$context = NeuroContext::api('transfer_create', [
    'outlet_id' => 123
]);
$logger->info('Transfer created', $context);
```

**Or use the automatic context injection (already implemented in Logger class):**
```php
// Logger automatically adds neuro context
$logger->info('Transfer created', [
    'outlet_id' => 123
]);
```

---

## Compliance

- **All new code MUST use neuro logging standards**
- **Existing code SHOULD be migrated incrementally**
- **Pull requests MUST include proper neuro context in logs**
- **Code reviews MUST verify neuro logging compliance**

---

## Support

For questions or issues:
- Review this documentation
- Check `src/Support/NeuroContext.php` for helper methods
- Check `src/Support/Logger.php` for core logging
- Review example logs in `storage/logs/` for patterns

---

**Status**: ✅ PRODUCTION READY  
**Enforcement**: MANDATORY  
**Last Updated**: 2025-10-07
