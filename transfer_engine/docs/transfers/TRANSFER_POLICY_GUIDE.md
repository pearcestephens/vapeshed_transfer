# Transfer Policy Guide (Phase 3)

Date: 2025-10-10  
Status: In Progress  

## Overview
Phase 3 hardens transfer creation with idempotency, safe math, overrideable config, duplicate suppression, and a dry-run path.

## Key Features
- Deterministic idempotency key per transfer proposal
- DB-enforced uniqueness; safe upsert path
- Confidence and priority calculations clamped to safe ranges
- Per-SKU and per-store override system for safety stock and max move qty
- Duplicate window suppression to reduce spam
- Dry-run mode for previews (no persistence)
- Structured logs with minimal noise and no PII

## Usage

```php
$service = new TransferPolicyService($orders, $config, $logger);

$order = $service->propose([
  'store_id' => 'S1',
  'sku' => 'SKU1',
  'predicted_weekly_demand' => 120.0,
  'current_on_hand' => 8,
  'prediction_confidence' => 0.92,
  'forecast_horizon_days' => 14,
  'lead_time_days' => 4,
], persist: true);
```

Dry-run preview (no DB writes):
```php
$preview = $service->propose($signal, persist: false);
```

## Configuration

Global defaults:
- transfers.safety_stock_days (int, default 7)
- transfers.max_move_qty (int, default 200)
- transfers.auto_create (bool, default false)
- transfers.duplicate_window_hours (int, default 6)
- transfers.default_source_hub (string, default HUB_MAIN)

Overrides (optional):
- transfers.overrides.sku.{SKU}.safety_stock_days
- transfers.overrides.sku.{SKU}.max_move_qty
- transfers.overrides.store.{STORE_ID}.safety_stock_days
- transfers.overrides.store.{STORE_ID}.max_move_qty

Resolution order: SKU override > Store override > Global default

## Idempotency Key

```php
$key = IdempotencyKey::fromSignal($storeId, $sku, $qty, $horizon, $safetyDays, $sourceHub)->value();
```
- 64-char hex (sha256 of canonical string)
- Included on payload as `idempotency_key`
- DB column `idempotency_key` has UNIQUE constraint

## Confidence & Priority
- All inputs clamped to safe ranges
- Confidence = prediction_confidence * horizon_factor * lead_penalty (all clamped)
- Priority thresholds:
  - critical: required_units ≥ 90% of max_move OR on_hand ≤ 2
  - high: confidence ≥ 0.9 OR required_units ≥ 50% of max_move
  - normal: confidence ≥ 0.75
  - low: otherwise

## Duplicate Window Suppression
- Checks for similar open transfer for same (store, sku) within configured hours
- If quantities within ±10%, skip create and log `transfer.skip` with reason `duplicate_window_hit`
- Best-effort (guarded try/catch)

## Structured Logs
- transfer.create: { transfer_id, store_id, sku, qty, priority, confidence, idem }
- transfer.skip: { store_id, sku, reason, required_units, on_hand }

## Testing
- `IdempotencyKeyTest.php` validates deterministic keys
- `TransferPolicyServiceIdempotencyTest.php` verifies duplicate-key path and window suppression
- `TransferPolicyServiceMathTest.php` ensures clamping and monotonicity
- `TransferPolicyServiceDryRunTest.php` checks dry-run parity

## Migration
Apply `database/migrations/003_transfer_idempotency.sql`:
```sql
ALTER TABLE transfer_orders
  ADD COLUMN idempotency_key CHAR(64) NULL,
  ADD UNIQUE KEY ux_transfer_idem (idempotency_key);
CREATE INDEX ix_transfer_dest_created_status ON transfer_orders (dest_store, created_at, status);
```

## Rollback
```sql
ALTER TABLE transfer_orders DROP INDEX ux_transfer_idem, DROP COLUMN idempotency_key;
DROP INDEX ix_transfer_dest_created_status ON transfer_orders;
```

## Notes
- Dry-run returns TransferOrder object without DB-assigned timestamps/IDs
- Keep logging concise; avoid large payloads or PII
