-- Brand synonym normalization table
CREATE TABLE IF NOT EXISTS brand_synonyms (
  synonym_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  canonical VARCHAR(160) NOT NULL,
  synonym VARCHAR(160) NOT NULL,
  weight DECIMAL(5,4) NOT NULL DEFAULT 1.0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_synonym (synonym),
  KEY idx_canonical (canonical)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
