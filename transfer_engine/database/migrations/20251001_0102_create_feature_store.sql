-- Feature store (immutable daily feature snapshots)
CREATE TABLE IF NOT EXISTS features_sku_store_daily (
  feature_row_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  feature_set_id INT NOT NULL,
  sku_id VARCHAR(64) NOT NULL,
  store_id INT NOT NULL,
  feature_date DATE NOT NULL,
  units_sold INT NULL,
  gross_revenue DECIMAL(12,2) NULL,
  on_hand INT NULL,
  on_order INT NULL,
  competitor_price DECIMAL(10,2) NULL,
  our_price DECIMAL(10,2) NULL,
  promo_flag TINYINT(1) NULL,
  season_week INT NULL,
  weather_code INT NULL,
  elasticity_estimate DECIMAL(10,4) NULL,
  demand_p10 DECIMAL(10,2) NULL,
  demand_p50 DECIMAL(10,2) NULL,
  demand_p90 DECIMAL(10,2) NULL,
  feature_hash CHAR(64) NOT NULL,
  lineage_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_feature_version (feature_set_id, sku_id, store_id, feature_date),
  KEY idx_feature_set (feature_set_id, feature_date),
  KEY idx_sku_date (sku_id, feature_date),
  KEY idx_store_date (store_id, feature_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci PARTITION BY RANGE (YEAR(feature_date)) (
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION pmax VALUES LESS THAN MAXVALUE
);
