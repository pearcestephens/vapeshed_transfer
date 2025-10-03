-- Crawler content cache & change detection
CREATE TABLE IF NOT EXISTS crawler_page_cache (
  cache_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  host VARCHAR(255) NOT NULL,
  url TEXT NOT NULL,
  url_hash CHAR(64) NOT NULL,
  etag VARCHAR(255) NULL,
  last_modified_header VARCHAR(255) NULL,
  content_hash CHAR(64) NOT NULL,
  content_simhash BIGINT NULL,
  content_length INT NULL,
  status_code INT NULL,
  fetched_at DATETIME NOT NULL,
  next_check_at DATETIME NULL,
  unchanged_streak INT NOT NULL DEFAULT 0,
  change_flag TINYINT(1) NOT NULL DEFAULT 0,
  raw_storage_ref VARCHAR(255) NULL,
  meta_json JSON NULL,
  UNIQUE KEY uq_cache_url (url_hash),
  KEY idx_host (host),
  KEY idx_next_check (next_check_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crawler_page_diffs (
  diff_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  url_hash CHAR(64) NOT NULL,
  previous_content_hash CHAR(64) NOT NULL,
  new_content_hash CHAR(64) NOT NULL,
  diff_ratio DECIMAL(6,4) NULL,
  block_change_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_url_hash (url_hash),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
