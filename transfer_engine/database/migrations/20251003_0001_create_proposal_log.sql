-- 20251003_0001_create_proposal_log.sql (M11)
-- Forward: create proposal_log table to store pricing/transfer proposals.
-- Rollback: DROP TABLE proposal_log;
CREATE TABLE IF NOT EXISTS proposal_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_type VARCHAR(32) NOT NULL, -- pricing | transfer
  band ENUM('auto','propose','discard') NOT NULL,
  score DECIMAL(6,4) NOT NULL,
  features JSON NOT NULL,
  blocked_by VARCHAR(64) NULL,
  context_hash CHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_type_created (proposal_type, created_at),
  KEY idx_band_created (band, created_at),
  KEY idx_context (context_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
