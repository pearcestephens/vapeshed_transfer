-- Transfer domain tables for Unity Transfer Engine integration
-- Includes transfer_orders, transfer_lines, store_stock_snapshots, and transfer_order_audit

CREATE TABLE IF NOT EXISTS transfer_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id VARCHAR(64) NOT NULL,
  source_hub VARCHAR(64) NOT NULL,
  dest_store VARCHAR(64) NOT NULL,
  status ENUM('proposed','approved','committed','in_transit','received','cancelled') NOT NULL DEFAULT 'proposed',
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  reason JSON NULL,
  confidence DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  requested_by VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_transfer_orders_transfer_id (transfer_id),
  KEY idx_transfer_orders_dest_store (dest_store),
  KEY idx_transfer_orders_status (status),
  KEY idx_transfer_orders_priority (priority),
  KEY idx_transfer_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transfer_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id VARCHAR(64) NOT NULL,
  sku VARCHAR(64) NOT NULL,
  qty INT NOT NULL,
  uom VARCHAR(16) NOT NULL DEFAULT 'ea',
  rationale JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_transfer_lines_transfer (transfer_id),
  KEY idx_transfer_lines_sku (sku),
  CONSTRAINT fk_transfer_lines_order FOREIGN KEY (transfer_id)
    REFERENCES transfer_orders (transfer_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_stock_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id VARCHAR(64) NOT NULL,
  sku VARCHAR(64) NOT NULL,
  on_hand INT NOT NULL DEFAULT 0,
  reserved INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_store_stock_snapshots (store_id, sku),
  KEY idx_store_stock_snapshots_store (store_id),
  KEY idx_store_stock_snapshots_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transfer_order_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id VARCHAR(64) NOT NULL,
  event_type VARCHAR(48) NOT NULL,
  status_from ENUM('proposed','approved','committed','in_transit','received','cancelled') NULL,
  status_to ENUM('proposed','approved','committed','in_transit','received','cancelled') NULL,
  actor VARCHAR(128) NULL,
  note TEXT NULL,
  payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_transfer_order_audit_transfer (transfer_id),
  KEY idx_transfer_order_audit_event (event_type, created_at),
  CONSTRAINT fk_transfer_audit_order FOREIGN KEY (transfer_id)
    REFERENCES transfer_orders (transfer_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
