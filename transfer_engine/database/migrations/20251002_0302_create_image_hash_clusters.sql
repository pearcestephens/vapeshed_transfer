-- Optional clustering of perceptual hashes for duplicate intelligence
CREATE TABLE IF NOT EXISTS image_hash_clusters (
  cluster_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  p_hash CHAR(16) NOT NULL,
  representative_hash CHAR(16) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rep (representative_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Optional clustering of perceptual hashes for duplicate intelligence
CREATE TABLE IF NOT EXISTS image_hash_clusters (
  cluster_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  p_hash CHAR(16) NOT NULL,
  representative_hash CHAR(16) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rep (representative_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
