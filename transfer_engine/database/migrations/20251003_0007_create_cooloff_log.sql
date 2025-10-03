-- Phase M19 (prelude if executed later) - Cooloff log table for auto-apply governance
CREATE TABLE IF NOT EXISTS cooloff_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(64) NOT NULL,
  action_type VARCHAR(32) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sku_action (sku, action_type, applied_at),
  KEY idx_proposal (proposal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
