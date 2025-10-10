# Phase 3 Completion Manifest

Status: In Progress  
Date: 2025-10-10  
PR Title: feat(transfers): idempotent policy, safer confidence, config overrides

## Scope
- Idempotency key with safe upsert
- Safer math for confidence/priority (no NaN/INF)
- Configurable overrides (SKU/Store priority)
- Duplicate window suppression
- Dry-run parity
- Structured logs (low-noise)

## Files Changed / Added

### Core
- src/Services/Idempotency/IdempotencyKey.php (NEW)
- src/Repositories/TransferOrderRepository.php (UPDATED)
- src/Services/TransferPolicyService.php (UPDATED)
- src/Repositories/SystemConfigRepository.php (existing helper)

### DB Migration
- database/migrations/003_transfer_idempotency.sql (NEW)

### Tests
- tests/Transfers/IdempotencyKeyTest.php (NEW)
- tests/Transfers/TransferPolicyServiceIdempotencyTest.php (NEW)
- tests/Transfers/TransferPolicyServiceMathTest.php (NEW)
- tests/Transfers/TransferPolicyServiceDryRunTest.php (NEW)

## Acceptance Checklist
- [x] Idempotency via unique idempotency_key
- [x] Repository returns existing order on duplicate
- [x] Duplicate window suppression (configurable)
- [x] Confidence and priority clamped to safe ranges
- [x] Dry-run returns object with parity
- [x] Structured logs without PII
- [ ] Tests passing
- [ ] Static analysis clean
- [ ] Project status updated

## Migration

Forward:
```sql
ALTER TABLE transfer_orders
    ADD COLUMN idempotency_key CHAR(64) NULL AFTER requested_by,
    ADD UNIQUE KEY ux_transfer_idem (idempotency_key);
CREATE INDEX ix_transfer_dest_created_status ON transfer_orders (dest_store, created_at, status);
```

Rollback:
```sql
ALTER TABLE transfer_orders DROP INDEX ux_transfer_idem, DROP COLUMN idempotency_key;
DROP INDEX ix_transfer_dest_created_status ON transfer_orders;
```

## Logging
- transfer.create { transfer_id, store_id, sku, qty, confidence, priority, idem:first|duplicate, reason }
- transfer.skip { store_id, sku, reason, required_units, on_hand }

## Notes
- SQLite tests emulate schema for idempotency path
- Duplicate window suppression uses a guarded SQL, does not block on error
- Overrides resolution order: SKU > Store > Global
