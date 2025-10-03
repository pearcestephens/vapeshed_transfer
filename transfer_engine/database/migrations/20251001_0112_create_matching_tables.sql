-- Candidate matching tables
CREATE TABLE IF NOT EXISTS product_candidate_matches (
  match_id CHAR(36) PRIMARY KEY,
  candidate_id CHAR(36) NOT NULL,
  sku_id VARCHAR(64) NOT NULL,
  confidence DECIMAL(6,4) NOT NULL,
  method ENUM('fuzzy','embedding','rules','manual') NOT NULL,
  status ENUM('proposed','accepted','rejected') NOT NULL DEFAULT 'proposed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_candidate_sku (candidate_id, sku_id),
  KEY idx_sku (sku_id),
  CONSTRAINT fk_pcm_candidate FOREIGN KEY (candidate_id) REFERENCES product_candidates(candidate_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_candidate_match_events (
  event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id CHAR(36) NOT NULL,
  event_type ENUM('confidence_update','status_change','note') NOT NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_match (match_id),
  CONSTRAINT fk_pcm_events FOREIGN KEY (match_id) REFERENCES product_candidate_matches(match_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
