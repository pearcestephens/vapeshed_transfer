-- Outbox table (pending domain events for reliable delivery)
CREATE TABLE IF NOT EXISTS outbox_events (
  event_id CHAR(36) PRIMARY KEY,
  aggregate_type VARCHAR(64) NOT NULL,
  aggregate_id VARCHAR(64) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  run_id CHAR(40) NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL,
  payload_json JSON NULL,
  status ENUM('pending','dispatched','failed') NOT NULL DEFAULT 'pending',
  attempt INT NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dispatched_at DATETIME NULL,
  UNIQUE KEY uq_outbox_idem (idempotency_key),
  KEY idx_outbox_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inbox table (idempotent consumption of external events)
CREATE TABLE IF NOT EXISTS inbox_events (
  inbox_id CHAR(36) PRIMARY KEY,
  source_system VARCHAR(64) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  external_event_id VARCHAR(128) NOT NULL,
  idempotency_key VARCHAR(140) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  status ENUM('received','processed','failed') NOT NULL DEFAULT 'received',
  last_error TEXT NULL,
  payload_json JSON NULL,
  UNIQUE KEY uq_inbox_idem (idempotency_key),
  KEY idx_inbox_status (status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
