-- 20251003_0006_create_drift_metrics.sql (M11 optional)
-- Forward: create drift_metrics for PSI tracking.
-- Rollback: DROP TABLE drift_metrics;
CREATE TABLE IF NOT EXISTS drift_metrics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feature_set VARCHAR(64) NOT NULL,
  psi DECIMAL(8,6) NOT NULL,
  buckets JSON NOT NULL,
  status ENUM('normal','warn','critical') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_feature_created (feature_set, created_at),
  KEY idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
