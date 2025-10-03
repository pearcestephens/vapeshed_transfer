-- Phase M19 extension - Action audit log for tracking executed/simulated proposals
CREATE TABLE IF NOT EXISTS action_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(64) NOT NULL,
  action_type VARCHAR(32) NOT NULL COMMENT 'pricing, transfer, etc',
  effect VARCHAR(32) NOT NULL COMMENT 'applied, simulated, rejected',
  metadata JSON DEFAULT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_proposal (proposal_id),
  KEY idx_sku_type (sku, action_type, applied_at),
  KEY idx_effect (effect, applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
