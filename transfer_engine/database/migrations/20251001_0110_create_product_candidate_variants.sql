-- Variants for product candidates (separate table to avoid bloating primary row)
CREATE TABLE IF NOT EXISTS product_candidate_variants (
  variant_id CHAR(36) PRIMARY KEY,
  candidate_id CHAR(36) NOT NULL,
  sku VARCHAR(160) NULL,
  title VARCHAR(255) NULL,
  price DECIMAL(10,2) NULL,
  compare_at_price DECIMAL(10,2) NULL,
  available TINYINT(1) NULL,
  options_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_candidate_sku (candidate_id, sku),
  KEY idx_candidate (candidate_id),
  CONSTRAINT fk_pcv_candidate FOREIGN KEY (candidate_id) REFERENCES product_candidates(candidate_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coil ohm readings (many-to-one with candidate)
CREATE TABLE IF NOT EXISTS product_candidate_coil_ohms (
  coil_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id CHAR(36) NOT NULL,
  ohm_value DECIMAL(5,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_candidate_ohm (candidate_id, ohm_value),
  KEY idx_candidate (candidate_id),
  CONSTRAINT fk_pcco_candidate FOREIGN KEY (candidate_id) REFERENCES product_candidates(candidate_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
