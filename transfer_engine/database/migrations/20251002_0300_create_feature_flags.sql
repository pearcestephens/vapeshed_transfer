-- Runtime feature flags table
CREATE TABLE IF NOT EXISTS feature_flags (
  flag_key VARCHAR(120) PRIMARY KEY,
  flag_value TINYINT(1) NOT NULL DEFAULT 1,
  description VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO feature_flags(flag_key,flag_value,description) VALUES
 ('brand_weighting',1,'Enable brand name weighting in composite score'),
 ('duplicate_suppression',1,'Skip already accepted SKU matches'),
 ('category_analytics',1,'Emit category_annotation events'),
 ('synonym_learning',1,'Learn brand-like tokens from strong matches'),
 ('image_similarity',1,'Use perceptual hash distance scoring'),
 ('vision_bonus',1,'Apply vision/image similarity bonus');
-- Runtime feature flags table
CREATE TABLE IF NOT EXISTS feature_flags (
  flag_key VARCHAR(120) PRIMARY KEY,
  flag_value TINYINT(1) NOT NULL DEFAULT 1,
  description VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO feature_flags(flag_key,flag_value,description) VALUES
 ('brand_weighting',1,'Enable brand name weighting in composite score'),
 ('duplicate_suppression',1,'Skip already accepted SKU matches'),
 ('category_analytics',1,'Emit category_annotation events'),
 ('synonym_learning',1,'Learn brand-like tokens from strong matches'),
 ('image_similarity',1,'Use perceptual hash distance scoring'),
 ('vision_bonus',1,'Apply vision/image similarity bonus');
