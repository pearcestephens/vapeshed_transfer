# GuardrailChain Quick Reference

**Version**: 2.0 | **Status**: Production Ready | **Sprint**: 2, Phase 2

---

## 🚀 Quick Start

```php
use Unified\Guardrail\GuardrailChain;
use Unified\Guardrail\CostFloorGuardrail;

$chain = new GuardrailChain($logger);
$chain->register(new CostFloorGuardrail());

$result = $chain->evaluate([
    'cost' => 50.0,
    'candidate_price' => 75.0,
]);

if ($result['final_status'] === 'PASS') {
    // Apply pricing
}
```

---

## 📊 Return Value

```php
[
    'results' => Result[],      // Array of Result objects
    'final_status' => string,   // 'PASS' | 'WARN' | 'BLOCK'
    'blocked_by' => ?string,    // null or 'GR_CODE'
    'total_duration_ms' => float, // Total execution time
    'score_hint' => float,      // 0..1 (confidence)
]
```

---

## 🎯 Result Object

```php
class Result
{
    public readonly string $code;        // 'GR_COST_FLOOR'
    public readonly string $status;      // 'PASS' | 'WARN' | 'BLOCK'
    public readonly string $severity;    // 'INFO' | 'WARN' | 'BLOCK'
    public readonly ?string $reason;     // 'below_cost_floor'
    public readonly string $message;     // Human message
    public readonly array $meta;         // Structured data
    public readonly float $duration_ms;  // Execution time
    
    // Methods
    public function isPassing(): bool;
    public function isWarning(): bool;
    public function isBlocking(): bool;
    public function severityWeight(): int; // 10, 50, or 100
    public function toArray(): array;
}
```

---

## 🚦 Severity Levels

| Severity | Weight | Status Mapping |
|----------|--------|----------------|
| INFO     | 10     | PASS           |
| WARN     | 50     | WARN           |
| BLOCK    | 100    | BLOCK          |

```php
use Unified\Guardrail\Severity;

Severity::INFO;   // 'INFO'
Severity::WARN;   // 'WARN'
Severity::BLOCK;  // 'BLOCK'

Severity::fromStatus('PASS');  // 'INFO'
Severity::weight('BLOCK');     // 100
```

---

## 📈 Score Hints

| Status | Score Range | Meaning |
|--------|-------------|---------|
| BLOCK  | 0.0         | Complete failure |
| WARN   | 0.3 - 0.5   | Partial pass (warnings) |
| PASS   | 0.8 - 1.0   | Full pass (slight duration penalty) |

```php
$confidence = $result['score_hint'];

if ($confidence < 0.3) {
    // Low confidence - escalate
} elseif ($confidence < 0.7) {
    // Medium confidence - apply with warnings
} else {
    // High confidence - auto-apply
}
```

---

## ⏱️ Timing

```php
foreach ($result['results'] as $r) {
    echo "{$r->code}: {$r->duration_ms}ms\n";
}

echo "Total: {$result['total_duration_ms']}ms\n";

// Find slow rails
$slow = array_filter(
    $result['results'],
    fn($r) => $r->duration_ms > 5.0
);
```

---

## 📝 Tracing

```php
use Unified\Persistence\GuardrailTraceRepository;

$traceRepo = new GuardrailTraceRepository($logger);

$traceRepo->insertBatch(
    proposalId: 123,
    runId: 'run_' . uniqid(),
    results: $result['results']
);
```

**Database Schema**:
```sql
CREATE TABLE guardrail_traces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT NOT NULL,
    run_id VARCHAR(64) NOT NULL,
    sequence INT NOT NULL,
    code VARCHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL,
    severity VARCHAR(16) NOT NULL,
    reason VARCHAR(128),
    message TEXT,
    meta JSON,
    duration_ms DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔍 Determinism

Rails execute in **alphabetical order by code**, regardless of registration order:

```php
// Any registration order:
$chain->register(new DeltaCapGuardrail());   // GR_DELTA_CAP
$chain->register(new CostFloorGuardrail());  // GR_COST_FLOOR

// Always executes in this order:
// 1. GR_COST_FLOOR
// 2. GR_DELTA_CAP
```

---

## 🛡️ Validation

```php
// ✅ Valid
new Result(
    code: 'GR_TEST',
    status: 'PASS',
    severity: 'INFO',
    reason: 'test_reason',
    message: 'Test',
    meta: ['key' => 'value'],
    duration_ms: 1.0
);

// ❌ Invalid
new Result(status: 'INVALID', ...);           // Throws
new Result(severity: 'CRITICAL', ...);        // Throws
new Result(duration_ms: -1.0, ...);           // Throws
new Result(meta: ['file' => fopen(...)], ...); // Throws (no resources)
new Result(meta: ['cb' => fn() => 1], ...);    // Throws (no closures)
```

---

## 🔄 Backward Compatibility

### Old Code Still Works

```php
// This still works (100% compatible):
$result = $chain->evaluate($ctx);

if ($result['final_status'] === 'PASS') {
    // Apply pricing
}
```

### New Features (Opt-In)

```php
// Use Result objects:
foreach ($result['results'] as $r) {
    echo "{$r->code}: {$r->status} ({$r->severity})\n";
}

// Use score hint:
$confidence = $result['score_hint'];
```

---

## 🧪 Testing

### Run Tests

```bash
# All guardrail tests
vendor/bin/phpunit tests/Guardrail/

# Specific suite
vendor/bin/phpunit tests/Guardrail/GuardrailChainTest.php

# With coverage
vendor/bin/phpunit --coverage-html coverage/ tests/Guardrail/
```

### Static Analysis

```bash
# Run PHPStan
composer phpstan

# Generate baseline
composer phpstan:baseline
```

---

## 📦 Database Migration

```bash
# Apply migration (required if using GuardrailTraceRepository)
mysql -u user -p database < database/migrations/002_add_guardrail_trace_enhancements.sql

# Verify
mysql -u user -p database -e "DESCRIBE guardrail_traces"
```

---

## 🐛 Common Issues

### Non-Deterministic Results

**Cause**: Missing $code property on guardrail  
**Fix**: Add `protected string $code = 'GR_YOUR_CODE';`

### Result Validation Error

**Cause**: Invalid status/severity or resources in meta  
**Fix**: Use 'PASS'|'WARN'|'BLOCK' only, no resources/closures

### Missing DB Columns

**Cause**: Migration not run  
**Fix**: Run migration script

---

## 📚 Documentation

- **Full Guide**: `docs/GUARDRAIL_CHAIN_GUIDE.md`
- **PR Documentation**: `PR_2_GUARDRAIL_DETERMINISTIC_COMPLETE.md`
- **Phase Manifest**: `PHASE_2_MANIFEST.md`

---

## 🎯 Best Practices

1. ✅ Use Result objects (not arrays)
2. ✅ Check severity for alerting
3. ✅ Log slow rails (> 10ms)
4. ✅ Persist all traces to database
5. ✅ Use score_hint for confidence scoring

---

## 📊 Performance

- **Sorting**: +0.1ms (one-time)
- **Per Rail**: +0.05ms (Result creation) + 0.02ms (timing)
- **Total**: < 1ms for 5-rail chain
- **Memory**: +8KB per evaluation

---

## 🔐 Security

- ✅ Immutable Result objects (readonly properties)
- ✅ No resources/closures in meta (validation)
- ✅ Strict types enforced
- ✅ Type-safe construction
- ✅ No serialization exploits

---

**Quick Ref v2.0** | Phase 2 Complete ✅
