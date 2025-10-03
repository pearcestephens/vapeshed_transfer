-- Dynamic matching threshold configuration
CREATE TABLE IF NOT EXISTS product_match_thresholds (
  scope VARCHAR(64) NOT NULL DEFAULT 'global', -- e.g. global / liquid / hardware
  primary_threshold DECIMAL(5,4) NOT NULL DEFAULT 0.7800,
  secondary_threshold DECIMAL(5,4) NOT NULL DEFAULT 0.7200,
  vision_threshold DECIMAL(5,4) NOT NULL DEFAULT 0.7000,
  min_token_base DECIMAL(5,4) NOT NULL DEFAULT 0.4500,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO product_match_thresholds(scope,primary_threshold,secondary_threshold,vision_threshold,min_token_base) VALUES
 ('global',0.7800,0.7200,0.7000,0.4500);
