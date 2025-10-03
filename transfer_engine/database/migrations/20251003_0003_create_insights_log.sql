-- 20251003_0003_create_insights_log.sql (M11)
-- Forward: create insights_log for pattern/anomaly/recommendation/alert entries.
-- Rollback: DROP TABLE insights_log;
CREATE TABLE IF NOT EXISTS insights_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('pattern','anomaly','recommendation','alert') NOT NULL,
  message VARCHAR(255) NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  meta JSON NULL,
  acknowledged TINYINT(1) NOT NULL DEFAULT 0,
  ack_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_type_created (type, created_at),
  KEY idx_severity_created (severity, created_at),
  KEY idx_ack (acknowledged, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
