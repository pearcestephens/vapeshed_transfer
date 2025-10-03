-- Images associated with product candidates (for vision-based matching & dedupe)
CREATE TABLE IF NOT EXISTS product_candidate_images (
  image_id CHAR(36) PRIMARY KEY,
  candidate_id CHAR(36) NOT NULL,
  image_url TEXT NOT NULL,
  url_hash CHAR(64) NOT NULL,
  content_hash CHAR(64) NULL, -- optional hash of downloaded binary (sha256)
  width INT NULL,
  height INT NULL,
  bytes INT NULL,
  format VARCHAR(16) NULL,
  role ENUM('primary','variant','gallery','unknown') NOT NULL DEFAULT 'unknown',
  fetched_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_candidate_urlhash (candidate_id, url_hash),
  KEY idx_candidate (candidate_id),
  CONSTRAINT fk_pci_candidate FOREIGN KEY (candidate_id) REFERENCES product_candidates(candidate_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
