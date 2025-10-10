-- Migration: Transfer Idempotency
-- Adds idempotency_key to transfer_orders and supporting index for duplicate scans
-- Date: 2025-10-10

-- FORWARD
ALTER TABLE transfer_orders
    ADD COLUMN idempotency_key CHAR(64) NULL AFTER requested_by,
    ADD UNIQUE KEY ux_transfer_idem (idempotency_key);

-- Optional helper index for duplicate scans by destination and time window
CREATE INDEX ix_transfer_dest_created_status ON transfer_orders (dest_store, created_at, status);

-- ROLLBACK
-- ALTER TABLE transfer_orders DROP INDEX ux_transfer_idem, DROP COLUMN idempotency_key;
-- DROP INDEX ix_transfer_dest_created_status ON transfer_orders;