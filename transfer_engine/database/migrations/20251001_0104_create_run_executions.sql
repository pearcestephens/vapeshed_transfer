-- Run executions & step idempotency registry
CREATE TABLE IF NOT EXISTS run_executions (
  run_id CHAR(40) NOT NULL,
  step_hash CHAR(64) NOT NULL,
  workflow VARCHAR(80) NOT NULL,
  step_name VARCHAR(120) NOT NULL,
  status ENUM('started','completed','failed','skipped') NOT NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  result_hash CHAR(64) NULL,
  error_text TEXT NULL,
  PRIMARY KEY (run_id, step_hash),
  KEY idx_workflow (workflow, step_name),
  KEY idx_status (status, last_updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;