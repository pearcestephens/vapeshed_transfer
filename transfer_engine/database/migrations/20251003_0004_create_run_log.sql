-- 20251003_0004_create_run_log.sql (M11)
-- Forward: create run_log capturing per-run metadata.
-- Rollback: DROP TABLE run_log;
CREATE TABLE IF NOT EXISTS run_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id CHAR(36) NOT NULL,
  module VARCHAR(48) NOT NULL,
  status VARCHAR(32) NOT NULL,
  metrics JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_run_module (run_id, module),
  KEY idx_module_created (module, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
