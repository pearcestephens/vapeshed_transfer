# GuardrailChain Enhancement Guide

**Version**: 2.0  
**Sprint**: 2, Phase 2  
**Status**: ✅ Production Ready  

---

## Table of Contents

1. [Overview](#overview)
2. [Key Enhancements](#key-enhancements)
3. [Quick Start](#quick-start)
4. [Result Object](#result-object)
5. [Severity Levels](#severity-levels)
6. [Deterministic Execution](#deterministic-execution)
7. [Timing & Performance](#timing--performance)
8. [Score Hints](#score-hints)
9. [Tracing & Persistence](#tracing--persistence)
10. [Migration Guide](#migration-guide)
11. [Best Practices](#best-practices)
12. [Troubleshooting](#troubleshooting)

---

## Overview

The GuardrailChain enhancement introduces **deterministic execution**, **rich Result objects**, **severity classification**, and **comprehensive tracing** to the pricing guardrail system.

### What's New

- 🎯 **Deterministic Ordering**: Rails execute alphabetically by code (same input → same output)
- 📊 **Result Objects**: Immutable value objects with 7 properties (code, status, severity, reason, message, meta, duration_ms)
- 🚦 **Severity Levels**: INFO, WARN, BLOCK classifications for better alerting
- ⏱️ **Timing Collection**: Microsecond precision for each rail and total chain
- 🎲 **Score Hints**: 0..1 float indicating rule satisfaction (used for confidence scoring)
- 📝 **Enhanced Tracing**: Persist rich metadata to database for debugging

### Backward Compatibility

✅ **100% Backward Compatible** - No breaking changes. Existing code continues to work.

---

## Key Enhancements

### 1. Deterministic Execution

**Problem**: Rails were executed in registration order, causing non-deterministic results across systems.

**Solution**: Rails now execute in **alphabetical order by code** using reflection:

```php
// Rails registered in any order:
$chain->register(new DeltaCapGuardrail());      // GR_DELTA_CAP
$chain->register(new CostFloorGuardrail());     // GR_COST_FLOOR
$chain->register(new RoiViabilityGuardrail());  // GR_ROI_VIABILITY

// Always execute in this order:
// 1. GR_COST_FLOOR
// 2. GR_DELTA_CAP
// 3. GR_ROI_VIABILITY
```

**Benefits**:
- Predictable pricing decisions
- Consistent test results
- Easier debugging
- No hidden order dependencies

---

### 2. Result Objects

**Before** (Legacy Array):
```php
$result = [
    'code' => 'GR_COST_FLOOR',
    'status' => 'BLOCK',
    'message' => 'Price below cost floor',
    'meta' => ['floor' => 50.0, 'candidate' => 45.0],
];
```

**After** (Result Object):
```php
$result = new Result(
    code: 'GR_COST_FLOOR',
    status: 'BLOCK',
    severity: 'BLOCK',
    reason: 'below_cost_floor',
    message: 'Price below cost floor',
    meta: ['floor' => 50.0, 'candidate' => 45.0],
    duration_ms: 1.23
);
```

**Benefits**:
- Type-safe access (no array typos)
- Immutable (cannot be accidentally modified)
- Rich metadata (severity, reason, timing)
- Serializable (JSON-friendly)
- Validated (no resources/closures in meta)

---

### 3. Severity Levels

Three levels for better alerting and UI display:

| Severity | Weight | Meaning | Use Cases |
|----------|--------|---------|-----------|
| **INFO** | 10 | Informational, rail passed | Success paths, auditing |
| **WARN** | 50 | Warning, proceed with caution | Edge cases, margin warnings |
| **BLOCK** | 100 | Critical failure, stop | Cost violations, regulatory blocks |

**Automatic Mapping**:
```php
PASS → INFO
WARN → WARN
BLOCK → BLOCK
```

---

## Quick Start

### Basic Usage (Unchanged)

```php
use Unified\Guardrail\GuardrailChain;
use Unified\Guardrail\CostFloorGuardrail;
use Unified\Guardrail\DeltaCapGuardrail;

$chain = new GuardrailChain($logger);
$chain->register(new CostFloorGuardrail());
$chain->register(new DeltaCapGuardrail());

$result = $chain->evaluate([
    'cost' => 50.0,
    'current_price' => 80.0,
    'candidate_price' => 75.0,
]);

// Old keys still work:
echo $result['final_status'];  // 'PASS', 'WARN', or 'BLOCK'
echo $result['blocked_by'];    // null or 'GR_COST_FLOOR'

// New keys available:
echo $result['score_hint'];         // 0.0 .. 1.0
echo $result['total_duration_ms'];  // e.g., 2.47
```

---

### Accessing Result Objects

```php
$result = $chain->evaluate($ctx);

foreach ($result['results'] as $r) {
    // Result object properties (readonly):
    echo "Code: {$r->code}\n";
    echo "Status: {$r->status}\n";
    echo "Severity: {$r->severity}\n";
    echo "Reason: {$r->reason}\n";
    echo "Message: {$r->message}\n";
    echo "Duration: {$r->duration_ms}ms\n";
    print_r($r->meta);
    
    // Helper methods:
    if ($r->isPassing()) {
        // Handle passing rail
    }
    
    if ($r->isWarning()) {
        // Show warning UI
    }
    
    if ($r->isBlocking()) {
        // Abort and show error
    }
    
    // Get severity weight for sorting:
    $weight = $r->severityWeight(); // 10, 50, or 100
}
```

---

## Result Object

### Properties

All properties are **readonly** (immutable):

```php
class Result
{
    public readonly string $code;        // e.g., 'GR_COST_FLOOR'
    public readonly string $status;      // 'PASS' | 'WARN' | 'BLOCK'
    public readonly string $severity;    // 'INFO' | 'WARN' | 'BLOCK'
    public readonly ?string $reason;     // 'below_cost_floor'
    public readonly string $message;     // Human-readable message
    public readonly array $meta;         // Structured data
    public readonly float $duration_ms;  // Execution time (≥0)
}
```

---

### Construction

#### Direct Construction

```php
$result = new Result(
    code: 'GR_COST_FLOOR',
    status: 'BLOCK',
    severity: 'BLOCK',
    reason: 'below_cost_floor',
    message: 'Price below cost floor ($45 < $50)',
    meta: ['floor' => 50.0, 'candidate' => 45.0],
    duration_ms: 1.23
);
```

#### From Legacy Array

```php
$legacy = [
    'code' => 'GR_COST_FLOOR',
    'status' => 'BLOCK',
    'message' => 'Price below cost floor',
    'meta' => ['floor' => 50.0],
];

$result = Result::fromLegacy($legacy, duration_ms: 1.5);

// Auto-derives:
// - severity: 'BLOCK' (from status)
// - reason: 'price_below_cost_floor' (from message)
```

---

### Validation

The Result object validates on construction:

```php
// ✅ Valid
new Result(
    code: 'GR_TEST',
    status: 'PASS',
    severity: 'INFO',
    reason: 'test_reason',
    message: 'Test message',
    meta: ['key' => 'value'],
    duration_ms: 1.0
);

// ❌ Invalid Status
new Result(
    code: 'GR_TEST',
    status: 'INVALID',  // Must be PASS, WARN, or BLOCK
    severity: 'INFO',
    // ... throws \InvalidArgumentException
);

// ❌ Invalid Severity
new Result(
    code: 'GR_TEST',
    status: 'PASS',
    severity: 'CRITICAL',  // Must be INFO, WARN, or BLOCK
    // ... throws \InvalidArgumentException
);

// ❌ Negative Duration
new Result(
    code: 'GR_TEST',
    status: 'PASS',
    severity: 'INFO',
    reason: 'test',
    message: 'Test',
    meta: [],
    duration_ms: -1.0  // Must be ≥ 0
    // ... throws \InvalidArgumentException
);

// ❌ Resources in Meta
new Result(
    code: 'GR_TEST',
    status: 'PASS',
    severity: 'INFO',
    reason: 'test',
    message: 'Test',
    meta: ['file' => fopen('test.txt', 'r')],  // Resources not allowed
    duration_ms: 1.0
    // ... throws \InvalidArgumentException
);

// ❌ Closures in Meta
new Result(
    code: 'GR_TEST',
    status: 'PASS',
    severity: 'INFO',
    reason: 'test',
    message: 'Test',
    meta: ['callback' => function() {}],  // Closures not allowed
    duration_ms: 1.0
    // ... throws \InvalidArgumentException
);
```

**Security**: Resources and closures are rejected to prevent serialization exploits.

---

### Methods

#### Status Checks

```php
$result->isPassing();   // status === 'PASS'
$result->isWarning();   // status === 'WARN'
$result->isBlocking();  // status === 'BLOCK'
```

#### Severity Weight

```php
$result->severityWeight();  // 10 (INFO), 50 (WARN), or 100 (BLOCK)
```

#### Serialization

```php
// To array (7 keys)
$array = $result->toArray();
/*
[
    'code' => 'GR_COST_FLOOR',
    'status' => 'BLOCK',
    'severity' => 'BLOCK',
    'reason' => 'below_cost_floor',
    'message' => 'Price below cost floor',
    'meta' => ['floor' => 50.0],
    'duration_ms' => 1.23,
]
*/

// To JSON (implements JsonSerializable)
$json = json_encode($result);
/*
{
    "code": "GR_COST_FLOOR",
    "status": "BLOCK",
    "severity": "BLOCK",
    "reason": "below_cost_floor",
    "message": "Price below cost floor",
    "meta": {"floor": 50.0},
    "duration_ms": 1.23
}
*/
```

---

## Severity Levels

### Constants

```php
use Unified\Guardrail\Severity;

Severity::INFO;   // 'INFO'
Severity::WARN;   // 'WARN'
Severity::BLOCK;  // 'BLOCK'
```

---

### Validation

```php
Severity::isValid('INFO');     // true
Severity::isValid('WARN');     // true
Severity::isValid('BLOCK');    // true
Severity::isValid('UNKNOWN');  // false
```

---

### Mapping from Status

```php
Severity::fromStatus('PASS');     // 'INFO'
Severity::fromStatus('WARN');     // 'WARN'
Severity::fromStatus('BLOCK');    // 'BLOCK'
Severity::fromStatus('UNKNOWN');  // 'INFO' (default)
```

---

### Weights

```php
Severity::weight('INFO');   // 10
Severity::weight('WARN');   // 50
Severity::weight('BLOCK');  // 100
Severity::weight('UNKNOWN'); // 0
```

**Use Case**: Sort results by severity:

```php
usort($results, fn($a, $b) => $b->severityWeight() <=> $a->severityWeight());
// Results sorted: BLOCK (100), WARN (50), INFO (10)
```

---

## Deterministic Execution

### Alphabetical Ordering

Rails execute in **alphabetical order by code**, regardless of registration order:

```php
// Registration Order (arbitrary)
$chain->register(new DonorFloorGuardrail());      // GR_DONOR_FLOOR
$chain->register(new CostFloorGuardrail());       // GR_COST_FLOOR
$chain->register(new ReceiverOvershootGuardrail()); // GR_RECEIVER_OVERSHOOT

// Execution Order (deterministic)
// 1. GR_COST_FLOOR
// 2. GR_DONOR_FLOOR
// 3. GR_RECEIVER_OVERSHOOT
```

**Why This Matters**:
- Predictable test results
- Consistent pricing across environments
- No hidden dependencies on registration order
- Easier debugging (always same order)

---

### Verification

Test determinism with identical inputs:

```php
$ctx = [
    'cost' => 50.0,
    'current_price' => 80.0,
    'candidate_price' => 75.0,
];

// Run 100 times
$orders = [];
for ($i = 0; $i < 100; $i++) {
    $result = $chain->evaluate($ctx);
    $order = array_map(fn($r) => $r->code, $result['results']);
    $orders[] = implode(',', $order);
}

// All orders should be identical
$uniqueOrders = array_unique($orders);
assert(count($uniqueOrders) === 1); // ✅ Deterministic
```

---

## Timing & Performance

### Timing Collection

Every rail execution is timed with **microsecond precision**:

```php
$result = $chain->evaluate($ctx);

foreach ($result['results'] as $r) {
    echo "{$r->code}: {$r->duration_ms}ms\n";
}

echo "Total: {$result['total_duration_ms']}ms\n";
```

**Example Output**:
```
GR_COST_FLOOR: 0.52ms
GR_DELTA_CAP: 0.31ms
GR_ROI_VIABILITY: 1.48ms
Total: 2.31ms
```

---

### Performance Impact

| Metric | Impact |
|--------|--------|
| Sorting Overhead | +0.1ms (one-time per evaluation) |
| Result Object Creation | +0.05ms per rail |
| Timing Collection | +0.02ms per rail (microtime calls) |
| Memory | +8KB per evaluation |

**Overall**: < 1ms for typical 5-rail chain

---

### Slow Rail Detection

Identify slow rails for optimization:

```php
$result = $chain->evaluate($ctx);

$slowRails = array_filter(
    $result['results'],
    fn($r) => $r->duration_ms > 5.0  // > 5ms
);

foreach ($slowRails as $r) {
    $logger->warning('slow_guardrail', [
        'code' => $r->code,
        'duration_ms' => $r->duration_ms,
    ]);
}
```

---

## Score Hints

The chain calculates a **score_hint** (0..1 float) indicating rule satisfaction:

### Score Ranges

| Status | Score Range | Meaning |
|--------|-------------|---------|
| **BLOCK** | 0.0 | Complete failure |
| **WARN** | 0.3 - 0.5 | Partial pass with warnings |
| **PASS** | 0.8 - 1.0 | Full pass (slight duration penalty) |

---

### Calculation Algorithm

```php
private function calculateScoreHint(array $results, string $finalStatus): float
{
    // BLOCK: Always 0.0
    if ($finalStatus === 'BLOCK') {
        return 0.0;
    }
    
    // WARN: Start at 0.5, deduct 0.1 per warning
    if ($finalStatus === 'WARN') {
        $warnCount = count(array_filter($results, fn($r) => $r->isWarning()));
        return max(0.0, 0.5 - ($warnCount * 0.1));
    }
    
    // PASS: Start at 1.0, slight duration penalty
    $totalDuration = array_sum(array_map(fn($r) => $r->duration_ms, $results));
    $durationPenalty = min(0.2, $totalDuration / 10000);
    
    return max(0.0, min(1.0, 1.0 - $durationPenalty));
}
```

---

### Usage

```php
$result = $chain->evaluate($ctx);

$scoreHint = $result['score_hint'];

if ($scoreHint < 0.3) {
    // Low confidence - reject or escalate
} elseif ($scoreHint < 0.7) {
    // Medium confidence - apply with warnings
} else {
    // High confidence - apply automatically
}
```

**Use Case**: Feed score_hint into confidence calculation for transfer proposals.

---

## Tracing & Persistence

### GuardrailTraceRepository

Persist guardrail traces to database for debugging and analytics:

```php
use Unified\Persistence\GuardrailTraceRepository;

$traceRepo = new GuardrailTraceRepository($logger);

$result = $chain->evaluate($ctx);

$traceRepo->insertBatch(
    proposalId: 123,
    runId: 'run_' . uniqid(),
    results: $result['results']  // Result[] or array[]
);
```

---

### Database Schema

```sql
CREATE TABLE guardrail_traces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT NOT NULL,
    run_id VARCHAR(64) NOT NULL,
    sequence INT NOT NULL,
    code VARCHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL,
    severity VARCHAR(16) NOT NULL,      -- NEW
    reason VARCHAR(128),                -- NEW
    message TEXT,
    meta JSON,
    duration_ms DECIMAL(10,2) DEFAULT 0, -- NEW
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proposal_run (proposal_id, run_id),
    INDEX idx_code (code),
    INDEX idx_severity (severity),
    INDEX idx_reason (reason),
    INDEX idx_duration (duration_ms)
);
```

---

### Querying Traces

#### All Traces for Proposal

```php
$traces = Db::query(
    'SELECT * FROM guardrail_traces 
     WHERE proposal_id = ? AND run_id = ? 
     ORDER BY sequence',
    [$proposalId, $runId]
);
```

#### Blocked Proposals

```php
$blocked = Db::query(
    'SELECT DISTINCT proposal_id, code, reason, message
     FROM guardrail_traces 
     WHERE status = "BLOCK" AND created_at > NOW() - INTERVAL 1 DAY
     ORDER BY created_at DESC'
);
```

#### Slowest Rails

```php
$slow = Db::query(
    'SELECT code, AVG(duration_ms) as avg_ms, MAX(duration_ms) as max_ms
     FROM guardrail_traces 
     WHERE created_at > NOW() - INTERVAL 1 HOUR
     GROUP BY code 
     ORDER BY avg_ms DESC 
     LIMIT 10'
);
```

---

## Migration Guide

### For Existing Code

**Good News**: No changes required! The chain is **100% backward compatible**.

#### Old Code Still Works

```php
// This still works exactly as before:
$result = $chain->evaluate($ctx);

if ($result['final_status'] === 'PASS') {
    // Apply pricing
}

if ($result['blocked_by']) {
    echo "Blocked by: {$result['blocked_by']}\n";
}
```

---

### Adopting New Features

#### Opt-In to Result Objects

```php
// Old way (still works):
foreach ($result['results'] as $r) {
    echo $r['code'] . ': ' . $r['status'] . "\n";
}

// New way (recommended):
foreach ($result['results'] as $r) {
    echo "{$r->code}: {$r->status} ({$r->severity}) - {$r->duration_ms}ms\n";
}
```

#### Opt-In to Score Hints

```php
// New feature - use if needed:
$confidence = $result['score_hint'] * 100;  // 0..100%

if ($confidence < 50) {
    echo "Low confidence - manual review required\n";
}
```

---

### Database Migration

If using GuardrailTraceRepository, run the migration:

```bash
mysql -u user -p database < database/migrations/002_add_guardrail_trace_enhancements.sql
```

**What It Does**:
- Adds `severity`, `reason`, `duration_ms` columns
- Adds indexes for common queries
- Backfills severity from existing status values
- Derives reason from existing message text (best-effort)

---

## Best Practices

### 1. Use Result Objects

```php
// ❌ Old way (fragile):
if ($r['status'] === 'BLOCK') {
    echo $r['message'];
}

// ✅ New way (type-safe):
if ($r->isBlocking()) {
    echo $r->message;
}
```

---

### 2. Check Severity for Alerting

```php
foreach ($result['results'] as $r) {
    if ($r->severity === Severity::BLOCK) {
        $alerting->sendCritical($r->message);
    } elseif ($r->severity === Severity::WARN) {
        $alerting->sendWarning($r->message);
    }
}
```

---

### 3. Log Slow Rails

```php
foreach ($result['results'] as $r) {
    if ($r->duration_ms > 10.0) {
        $logger->warning('slow_guardrail', [
            'code' => $r->code,
            'duration_ms' => $r->duration_ms,
            'proposal_id' => $proposalId,
        ]);
    }
}
```

---

### 4. Persist All Traces

```php
// Always persist traces for debugging:
$traceRepo->insertBatch($proposalId, $runId, $result['results']);

// Later, query for analysis:
$traces = Db::query(
    'SELECT * FROM guardrail_traces WHERE proposal_id = ?',
    [$proposalId]
);
```

---

### 5. Use Score Hints for Confidence

```php
$confidence = $result['score_hint'];

if ($confidence < 0.5) {
    // Low confidence - escalate to manual review
    $proposal->status = 'manual_review';
} else {
    // High confidence - auto-apply
    $proposal->status = 'approved';
}
```

---

## Troubleshooting

### Issue: Non-Deterministic Results

**Symptom**: Different results with identical inputs

**Solution**: Ensure all guardrails have unique codes and implement GuardrailInterface correctly.

```php
// ❌ Bad: Missing code property
class MyGuardrail implements GuardrailInterface
{
    public function evaluate(array $ctx, Logger $logger): array
    {
        return ['status' => 'PASS'];
    }
}

// ✅ Good: Has code property
class MyGuardrail implements GuardrailInterface
{
    protected string $code = 'GR_MY_GUARDRAIL';
    
    public function evaluate(array $ctx, Logger $logger): array
    {
        return ['code' => $this->code, 'status' => 'PASS'];
    }
}
```

---

### Issue: Result Validation Errors

**Symptom**: `\InvalidArgumentException` on Result construction

**Solutions**:

```php
// ❌ Invalid status
new Result(status: 'INVALID', ...);

// ✅ Valid status (PASS, WARN, BLOCK)
new Result(status: 'PASS', ...);

// ❌ Resources in meta
new Result(meta: ['file' => fopen('test.txt', 'r')], ...);

// ✅ Serializable meta
new Result(meta: ['filename' => 'test.txt'], ...);

// ❌ Negative duration
new Result(duration_ms: -1.0, ...);

// ✅ Non-negative duration
new Result(duration_ms: 0.0, ...);
```

---

### Issue: Missing Database Columns

**Symptom**: SQL error when inserting traces

**Solution**: Run the database migration:

```bash
mysql -u user -p database < database/migrations/002_add_guardrail_trace_enhancements.sql
```

**Verify**:
```sql
DESCRIBE guardrail_traces;
-- Should show: severity, reason, duration_ms columns
```

---

### Issue: Slow Performance

**Symptom**: Chain execution takes > 50ms

**Diagnosis**:
```php
$result = $chain->evaluate($ctx);

foreach ($result['results'] as $r) {
    if ($r->duration_ms > 10.0) {
        echo "Slow rail: {$r->code} ({$r->duration_ms}ms)\n";
    }
}
```

**Solutions**:
- Optimize slow rails (reduce DB queries, cache data)
- Remove unnecessary rails
- Parallelize independent rails (future enhancement)

---

### Issue: Score Hint Not as Expected

**Symptom**: score_hint doesn't match expectations

**Explanation**:
- BLOCK: Always 0.0
- WARN: 0.5 - (count * 0.1), so 3 warnings → 0.2
- PASS: 1.0 - (duration / 10000), so 2000ms → 0.8

**Verification**:
```php
$result = $chain->evaluate($ctx);

echo "Final Status: {$result['final_status']}\n";
echo "Score Hint: {$result['score_hint']}\n";

$warnCount = count(array_filter(
    $result['results'],
    fn($r) => $r->isWarning()
));
echo "Warnings: {$warnCount}\n";
```

---

## Summary

The GuardrailChain enhancement provides:

✅ **Deterministic execution** (alphabetical by code)  
✅ **Rich Result objects** (7 properties, immutable, validated)  
✅ **Severity classification** (INFO/WARN/BLOCK)  
✅ **Microsecond timing** (per rail + total)  
✅ **Score hints** (0..1 confidence indicator)  
✅ **Enhanced tracing** (persist to database)  
✅ **100% backward compatible** (no breaking changes)  

**Next Steps**:
1. Update guardrail implementations to return severity/reason
2. Start using Result objects in UI/alerts
3. Persist all traces for analytics
4. Integrate score_hint into confidence calculation

---

**Questions?** See [PR #2 Documentation](./PR_2_GUARDRAIL_DETERMINISTIC_COMPLETE.md) or consult the code.
