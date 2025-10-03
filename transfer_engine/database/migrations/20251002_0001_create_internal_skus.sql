-- Canonical internal SKU catalog (minimal) for matching
CREATE TABLE IF NOT EXISTS internal_skus (
  sku_id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  brand VARCHAR(160) NULL,
  nicotine_mg DECIMAL(6,2) NULL,
  volume_ml DECIMAL(8,2) NULL,
  pack_count INT NULL,
  product_type VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_brand (brand),
  KEY idx_name (name(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
