-- 20251003_0005_create_config_audit.sql (M11)
-- Forward: create config_audit to track config changes.
-- Rollback: DROP TABLE config_audit;
CREATE TABLE IF NOT EXISTS config_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(191) NOT NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  actor VARCHAR(64) NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'system',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_key_created (config_key, created_at),
  KEY idx_actor_created (actor, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
