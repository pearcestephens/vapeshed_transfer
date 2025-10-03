-- Logs calibration vs active threshold deltas (multi-scope support)
CREATE TABLE IF NOT EXISTS product_match_threshold_drift (
  drift_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(64) NOT NULL,
  current_primary DECIMAL(5,4) NOT NULL,
  suggested_primary DECIMAL(5,4) NOT NULL,
  current_secondary DECIMAL(5,4) NOT NULL,
  suggested_secondary DECIMAL(5,4) NOT NULL,
  delta_primary DECIMAL(6,4) NOT NULL,
  delta_secondary DECIMAL(6,4) NOT NULL,
  accepted_sample INT NOT NULL,
  proposed_sample INT NOT NULL,
  window_days INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_scope_created (scope,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Match rejection audit
CREATE TABLE IF NOT EXISTS product_match_rejections (
  rejection_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id CHAR(36) NOT NULL,
  sku_id VARCHAR(64) NOT NULL,
  confidence DECIMAL(6,4) NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_candidate (candidate_id),
  KEY idx_reason (reason_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Synonym promotion audit
CREATE TABLE IF NOT EXISTS brand_synonym_promotion_audit (
  audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(160) NOT NULL,
  canonical VARCHAR(160) NOT NULL,
  occurrences INT NOT NULL,
  sku_spread INT NOT NULL,
  promoted_by VARCHAR(64) DEFAULT 'auto',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
