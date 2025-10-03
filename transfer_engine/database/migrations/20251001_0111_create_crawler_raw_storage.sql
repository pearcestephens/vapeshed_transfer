-- Raw HTML storage (compressed) for high-fidelity diffs & ML labeling
CREATE TABLE IF NOT EXISTS crawler_raw_storage (
  storage_id CHAR(36) PRIMARY KEY,
  url TEXT NOT NULL,
  content_hash CHAR(64) NOT NULL,
  compression ENUM('none','gzip') NOT NULL DEFAULT 'gzip',
  byte_size INT NOT NULL,
  body_long MEDIUMBLOB NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hash (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
